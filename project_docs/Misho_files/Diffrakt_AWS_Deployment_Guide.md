# Diffrakt — AWS Deployment Guide (Console Edition)

> **Target stack:** EC2 t2.micro (Free Tier) · Docker Compose · MySQL in container · S3 for file storage  
> **Estimated monthly cost:** ~$0–3 (EC2 free for 12 months on Free Tier; S3 ~$0.023/GB)  
> **Approach:** Everything done through the AWS Management Console and EC2 Instance Connect (browser terminal). No local AWS CLI required.

---

## Table of Contents

1. [Architecture overview](#1-architecture-overview)
2. [Changes needed to the Diffrakt codebase](#2-changes-needed-to-the-diffrakt-codebase)
3. [AWS setup — one-time preparation](#3-aws-setup--one-time-preparation)
4. [Launch and configure the EC2 instance](#4-launch-and-configure-the-ec2-instance)
5. [Set up Docker on the server](#5-set-up-docker-on-the-server)
6. [Deploy the application](#6-deploy-the-application)
7. [Initialise the database](#7-initialise-the-database)
8. [Verify everything works](#8-verify-everything-works)
9. [Updating the application after code changes](#9-updating-the-application-after-code-changes)
10. [Cost reference](#10-cost-reference)

---

## 1. Architecture overview

```
Internet
   │  HTTP :80
   ▼
EC2 t2.micro (Ubuntu 24.04)
   ├── app container   (PHP 8.2-FPM + Nginx, your Dockerfile)
   │       │  reads/writes files
   │       ▼
   │    S3 bucket  (originals, thumbs, processed, avatars)
   │
   └── db container    (MySQL 8, same Compose file)
           │
        db_data volume  (persists on EC2 disk)
```

Everything runs on a single VM with Docker Compose — identical to your local setup, just on AWS hardware. S3 replaces the `storage_data` volume so uploaded files survive container restarts and are served directly by URL without going through PHP.

---

## 2. Changes needed to the Diffrakt codebase

This is the only section that touches PHP. Everything else is infrastructure.

### 2.1 Install the AWS SDK

Run this locally before uploading the project to the server:

```bash
composer require aws/aws-sdk-php
```

Commit both `composer.json` and `composer.lock`.

---

### 2.2 Add new environment variables

Add these to your `.env`:

```dotenv
# S3
AWS_REGION=eu-central-1
AWS_ACCESS_KEY_ID=AKIAxxxxxxxxxxxxxxxx
AWS_SECRET_ACCESS_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
S3_BUCKET=diffrakt-storage
S3_URL_BASE=https://diffrakt-storage.s3.eu-central-1.amazonaws.com
```

You will get `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` in section 3.2.

---

### 2.3 Rewrite `StorageService.php`

This is the only application file that needs to change. Replace its current disk-based implementation with the version below. The public interface (`put`, `putFile`, `delete`, `url`, `downloadTemp`) stays stable so no controllers, models, or filters need touching.

```php
<?php

namespace App\Services;

use Aws\S3\S3Client;

class StorageService
{
    private S3Client $client;
    private string   $bucket;
    private string   $urlBase;

    public function __construct()
    {
        $this->client = new S3Client([
            'version'     => 'latest',
            'region'      => $_ENV['AWS_REGION'],
            'credentials' => [
                'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
            ],
        ]);

        $this->bucket  = $_ENV['S3_BUCKET'];
        $this->urlBase = rtrim($_ENV['S3_URL_BASE'], '/');
    }

    /**
     * Store a stream directly in S3.
     *
     * @param string   $key      S3 object key, e.g. "originals/abc123.jpg"
     * @param resource $stream   Open file handle
     * @param string   $mimeType e.g. "image/jpeg"
     */
    public function put(string $key, $stream, string $mimeType = 'application/octet-stream'): void
    {
        $this->client->putObject([
            'Bucket'      => $this->bucket,
            'Key'         => $key,
            'Body'        => $stream,
            'ContentType' => $mimeType,
        ]);
    }

    /**
     * Store a local file path in S3 (convenience wrapper for GD output).
     */
    public function putFile(string $key, string $localPath, string $mimeType = 'image/jpeg'): void
    {
        $this->client->putObject([
            'Bucket'      => $this->bucket,
            'Key'         => $key,
            'SourceFile'  => $localPath,
            'ContentType' => $mimeType,
        ]);
    }

    /**
     * Delete an object from S3.
     */
    public function delete(string $key): void
    {
        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
        ]);
    }

    /**
     * Return the public URL for an S3 object.
     */
    public function url(string $key): string
    {
        return $this->urlBase . '/' . ltrim($key, '/');
    }

    /**
     * Download an S3 object to a local temp file and return the path.
     * Use this when GD needs a local file to process.
     */
    public function downloadTemp(string $key): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'diffrakt_');
        $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
            'SaveAs' => $tmp,
        ]);
        return $tmp;
    }
}
```

#### GD pipeline pattern

GD writes to disk, so the pattern for every image operation is:

1. Write GD output to a **local temp file** as before.
2. Call `$storage->putFile($key, $tmpPath)` to upload it to S3.
3. Call `unlink($tmpPath)` to clean up the temp file.

```php
// Example: saving a processed export
$tmpPath = tempnam(sys_get_temp_dir(), 'diffrakt_processed_');
imagejpeg($gdImage, $tmpPath, 90);

$storage = new StorageService();
$storage->putFile("processed/{$postId}.jpg", $tmpPath);
unlink($tmpPath);

$publicUrl = $storage->url("processed/{$postId}.jpg");
```

Apply the same pattern for `originals/`, `thumbs/`, and `avatars/`.

---

### 2.4 Update `docker-compose.prod.yml`

Remove the `storage_data` volume (files go to S3 now) and set the port to 80:

```yaml
services:
  app:
    build: .
    ports:
      - "80:80"
    env_file: .env
    volumes:
      # storage_data removed — files go to S3
      - ./public:/var/www/diffrakt/public
      - ./src:/var/www/diffrakt/src
    depends_on:
      db:
        condition: service_healthy

  db:
    image: mysql:8
    volumes:
      - db_data:/var/lib/mysql
      - ./database/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql
      - ./database/seeds/filters.sql:/docker-entrypoint-initdb.d/03-seeds.sql
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_NAME}
      MYSQL_USER: ${DB_USER}
      MYSQL_PASSWORD: ${DB_PASS}
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-p$$MYSQL_ROOT_PASSWORD"]
      interval: 5s
      timeout: 5s
      retries: 10

volumes:
  db_data:
```

---

### 2.5 Summary of changed files

| File | What changes |
|---|---|
| `composer.json` / `composer.lock` | `aws/aws-sdk-php` dependency added |
| `src/Services/StorageService.php` | Full rewrite to S3-backed implementation |
| `docker-compose.prod.yml` | `storage_data` volume removed, port changed to 80 |
| `.env` | 5 new AWS/S3 variables added |

No controllers, models, filters, or frontend files need to change.

---

## 3. AWS setup — one-time preparation

### 3.1 Create the S3 bucket

1. Go to **S3** in the AWS Console and click **Create bucket**.
2. Set **Bucket name** to `diffrakt-storage`.
3. Set **AWS Region** to `eu-central-1` (Frankfurt) — or whichever region you prefer, just use it consistently everywhere.
4. Under **Block Public Access settings**, **uncheck** "Block all public access". Confirm the warning checkbox that appears.
5. Leave all other settings as default and click **Create bucket**.

**Attach a public-read policy to the bucket:**

1. Open the bucket, go to the **Permissions** tab.
2. Scroll to **Bucket policy** and click **Edit**.
3. Paste the following policy (replace `diffrakt-storage` if you used a different name):

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "PublicReadGetObject",
      "Effect": "Allow",
      "Principal": "*",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::diffrakt-storage/*"
    }
  ]
}
```

4. Click **Save changes**.

> **Why public-read?** Images are served directly from S3 URLs in `<img src="...">` tags. Making objects publicly readable avoids the complexity of generating a pre-signed URL for every image request.

---

### 3.2 Create an IAM user for the application

1. Go to **IAM** → **Users** → **Create user**.
2. Set **User name** to `diffrakt-app`. Click **Next**.
3. On the permissions page, choose **Attach policies directly**, then click **Create policy**.  
   A new tab opens — use the **JSON** editor and paste:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject"
      ],
      "Resource": "arn:aws:s3:::diffrakt-storage/*"
    }
  ]
}
```

4. Click **Next**, name the policy `diffrakt-s3-policy`, and click **Create policy**.
5. Back in the user creation tab, refresh the policy list, search for `diffrakt-s3-policy`, select it, and click **Next** then **Create user**.

**Generate access keys:**

1. Click on the `diffrakt-app` user you just created.
2. Go to the **Security credentials** tab.
3. Scroll to **Access keys** and click **Create access key**.
4. Choose **Application running outside AWS** as the use case.
5. Click through to the final step — **copy both the Access Key ID and Secret Access Key now**. The secret is shown only once.
6. Put both values into your `.env` file.

---

## 4. Launch and configure the EC2 instance

### 4.1 Create a security group

1. Go to **EC2** → **Security Groups** (under Network & Security) → **Create security group**.
2. Set **Security group name** to `diffrakt-sg`.
3. Under **Inbound rules**, add two rules:

| Type | Protocol | Port | Source |
|---|---|---|---|
| HTTP | TCP | 80 | Anywhere (0.0.0.0/0) |
| SSH | TCP | 22 | Anywhere (0.0.0.0/0) |

> The SSH rule is needed for EC2 Instance Connect (the browser terminal). You can restrict it to your IP for extra security, but for a student project Anywhere is fine.

4. Click **Create security group**.

### 4.2 Create a key pair

Even though you will use Instance Connect in the browser, a key pair is required to launch the instance.

1. Go to **EC2** → **Key Pairs** → **Create key pair**.
2. Name it `diffrakt-key`, choose **RSA**, format **`.pem`**.
3. Click **Create key pair** — the `.pem` file downloads automatically. Keep it somewhere safe.

### 4.3 Launch the instance

1. Go to **EC2** → **Instances** → **Launch instances**.
2. Set **Name** to `diffrakt`.
3. Under **Application and OS Images**, search for `Ubuntu` and select **Ubuntu Server 24.04 LTS (HVM), SSD Volume Type**. Make sure the architecture is **64-bit (x86)**.
4. Under **Instance type**, select **t2.micro** (labelled "Free tier eligible").
5. Under **Key pair**, select `diffrakt-key`.
6. Under **Network settings**, click **Edit** and:
   - Select the existing security group `diffrakt-sg`.
7. Under **Configure storage**, leave the default 8 GiB gp3 volume. This is enough for the OS, Docker, and your project.
8. Click **Launch instance**.

### 4.4 Get the public IP

1. Go to **EC2** → **Instances** and click on your `diffrakt` instance.
2. Copy the **Public IPv4 address** from the instance details panel.

> This IP changes every time you stop and start the instance. To get a stable IP, go to **Elastic IPs** → **Allocate Elastic IP address** → **Allocate**, then **Actions** → **Associate Elastic IP address** and select your instance. Elastic IPs are free while the instance is running.

### 4.5 Open the browser terminal (EC2 Instance Connect)

1. In **EC2** → **Instances**, select your `diffrakt` instance.
2. Click **Connect** at the top.
3. Choose the **EC2 Instance Connect** tab.
4. Leave the username as `ubuntu` and click **Connect**.

A browser terminal opens. All commands from this point forward are run here.

---

## 5. Set up Docker on the server

Run these commands in the EC2 Instance Connect terminal:

```bash
# Update packages
sudo apt-get update && sudo apt-get upgrade -y

# Install Docker
curl -fsSL https://get.docker.com | sudo sh

# Add ubuntu user to docker group (avoids needing sudo for every docker command)
sudo usermod -aG docker ubuntu

# Apply group membership without logging out
newgrp docker

# Install Docker Compose plugin
sudo apt-get install -y docker-compose-plugin

# Verify both are working
docker --version
docker compose version
```

---

## 6. Deploy the application

### 6.1 Upload your project to the server

EC2 Instance Connect does not support file uploads directly. The simplest approach is to push your project to a **GitHub or GitLab repository** (it can be private) and clone it on the server.

**Option A — Git (recommended):**

```bash
# On the server
git clone https://github.com/your-username/diffrakt.git
cd diffrakt
```

If the repository is private, use a personal access token:

```bash
git clone https://your-token@github.com/your-username/diffrakt.git
```

**Option B — Direct upload via the AWS Console (S3 as transfer):**

If you do not want to use Git:

1. Compress your project locally: `tar --exclude='.git' --exclude='vendor' -czf diffrakt.tar.gz .`
2. Upload `diffrakt.tar.gz` to your S3 bucket via the Console (drag and drop into the bucket).
3. On the server, download and extract it:

```bash
sudo apt-get install -y awscli
aws s3 cp s3://diffrakt-storage/diffrakt.tar.gz ~/diffrakt.tar.gz \
  --region eu-central-1
mkdir ~/diffrakt && tar -xzf ~/diffrakt.tar.gz -C ~/diffrakt
cd ~/diffrakt
```

For this to work, the EC2 instance needs permission to read from S3. The easiest way is to temporarily make the tar file public in the S3 Console (tick the file → Actions → Make public), download it with `wget`, then make it private again.

### 6.2 Install Composer dependencies

```bash
# Install PHP CLI and Composer prerequisites
sudo apt-get install -y php8.2-cli php8.2-curl php8.2-xml php8.2-mbstring unzip

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install project dependencies (including aws/aws-sdk-php)
composer install --no-dev --optimize-autoloader
```

### 6.3 Create the production `.env`

```bash
nano .env
```

Fill in all values:

```dotenv
APP_ENV=production
APP_URL=http://<PUBLIC_IP>

DB_HOST=db
DB_PORT=3306
DB_NAME=diffrakt
DB_USER=diffrakt
DB_PASS=choose_a_strong_password
DB_ROOT_PASSWORD=choose_a_strong_root_password

AWS_REGION=eu-central-1
AWS_ACCESS_KEY_ID=AKIAxxxxxxxxxxxxxxxx
AWS_SECRET_ACCESS_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
S3_BUCKET=diffrakt-storage
S3_URL_BASE=https://diffrakt-storage.s3.eu-central-1.amazonaws.com
```

Save with `Ctrl+O`, `Enter`, then `Ctrl+X`.

### 6.4 Build and start the containers

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

The first build takes 2–4 minutes while it downloads the base images and installs extensions. Check that both containers are running:

```bash
docker compose -f docker-compose.prod.yml ps
```

You should see `app` and `db` both with status `Up`.

---

## 7. Initialise the database

The `db` container automatically runs `schema.sql` and `filters.sql` on first boot via `docker-entrypoint-initdb.d/` — the same mechanism as your local setup. Nothing extra to do.

Verify the tables were created:

```bash
docker compose -f docker-compose.prod.yml exec db \
  mysql -u diffrakt -p diffrakt -e "SHOW TABLES;"
```

Enter `DB_PASS` when prompted. You should see all your tables.

---

## 8. Verify everything works

Open a browser and go to `http://<PUBLIC_IP>`.

**If the page does not load**, check the logs in the Instance Connect terminal:

```bash
# App container logs (Nginx + PHP errors)
docker compose -f docker-compose.prod.yml logs app

# Database logs
docker compose -f docker-compose.prod.yml logs db

# Check Nginx config is valid
docker compose -f docker-compose.prod.yml exec app nginx -t

# Check supervisord is managing both processes
docker compose -f docker-compose.prod.yml exec app supervisorctl status
```

**Test S3 integration:**

1. Register a user and upload a photo.
2. Check that the image URL in the browser starts with `https://diffrakt-storage.s3...`.
3. In the AWS Console, open your S3 bucket — you should see the uploaded files under `originals/`, `thumbs/`, or `processed/`.

You can also browse uploaded files visually in the Console: go to **S3** → `diffrakt-storage` → click through the folders.

---

## 9. Updating the application after code changes

### Via Git (if you used Option A above)

In the Instance Connect terminal:

```bash
cd ~/diffrakt
git pull
composer install --no-dev --optimize-autoloader
docker compose -f docker-compose.prod.yml up -d --build
```

### Via S3 upload (if you used Option B above)

1. Compress and upload the new `diffrakt.tar.gz` to S3 as before.
2. In the Instance Connect terminal:

```bash
cd ~
aws s3 cp s3://diffrakt-storage/diffrakt.tar.gz ~/diffrakt.tar.gz --region eu-central-1
tar -xzf ~/diffrakt.tar.gz -C ~/diffrakt
cd ~/diffrakt
composer install --no-dev --optimize-autoloader
docker compose -f docker-compose.prod.yml up -d --build
```

The `--build` flag rebuilds the app image with your new code. The `db` container is unaffected and keeps all its data. Downtime is ~10–30 seconds while the new container starts.

---

## 10. Cost reference

| Service | Free Tier | After Free Tier |
|---|---|---|
| EC2 t2.micro | 750 hours/month free for 12 months | ~$8.50/month |
| S3 storage | 5 GB free for 12 months | $0.023/GB/month |
| S3 requests | 20,000 GET / 2,000 PUT free/month | negligible at small scale |
| Data transfer out | 1 GB/month free | $0.09/GB |
| Elastic IP | Free while instance is running | $0.005/hour if unattached |

**While on Free Tier:** ~$0–1/month.  
**After Free Tier:** ~$10–12/month if running 24/7.

To stop the instance when not in use (preserves all data):

1. Go to **EC2** → **Instances**.
2. Select `diffrakt` → **Instance state** → **Stop instance**.

The `db_data` Docker volume and all files on the EC2 disk persist when stopped. S3 files are always persistent regardless of instance state.
