# Docker — What Just Happened

A practical guide written after containerizing the Diffrakt project from scratch.

---

## The Core Idea

On your machine you have XAMPP — Apache, PHP, and MySQL all installed directly on Windows. This works, but it has a problem: if someone else clones your project, they need to install the exact same versions of the exact same software and configure everything the same way. If they don't, things break in ways that are hard to debug.

Docker solves this by packaging the application *together with everything it needs to run* into a single unit. That unit runs identically on your machine, your teammate's machine, and your production server. The classic "works on my machine" problem goes away.

---

## Three Concepts You Must Understand

### 1. Images

An **image** is a blueprint. It's a read-only snapshot that describes:
- Which operating system to start from
- What software to install
- Which files to copy in
- What command to run on startup

An image is built from a `Dockerfile`. It doesn't run — it just describes what a running thing should look like.

Think of it like a **class** in object-oriented programming. The image is the class definition.

```
Image = blueprint (read-only, does not run)
```

You saw images in Docker Desktop:
- `mysql:8` — an image downloaded from Docker Hub (MySQL's official image)
- `diffrakt-app:latest` — the image you built from your own Dockerfile

### 2. Containers

A **container** is a running instance of an image. When you ran `docker compose up`, Docker took your images and started containers from them.

Continuing the OOP analogy: if an image is a class, a container is an **instance** of that class. You can run multiple containers from the same image simultaneously.

```
Container = running instance of an image
```

Containers are isolated from each other and from your host machine. The PHP container can't directly talk to the MySQL container unless you explicitly connect them (Docker Compose does this automatically).

Containers are **ephemeral** — when you stop and remove a container, anything written inside it is gone. This is why volumes exist.

### 3. Volumes

A **volume** is persistent storage that lives outside the container. It survives container stops, removals, and rebuilds.

```
Volume = persistent storage that outlives containers
```

In your project you have two named volumes:

| Volume | What it stores | Why it matters |
|---|---|---|
| `db_data` | MySQL database files | Without this, your entire database is wiped every time you run `docker compose down` |
| `storage_data` | User uploads, thumbnails, processed exports, avatars | Without this, every photo ever uploaded disappears on container restart |

When you ran `docker compose down`, the containers were destroyed — but both volumes survived intact. Next time you run `docker compose up`, MySQL finds its data already there and doesn't reinitialise, and all uploaded files are still present.

---

## Your Dockerfile Explained Line by Line

```dockerfile
FROM php:8.2-fpm
```
Start from PHP's official image with PHP-FPM pre-installed. This is the base layer — you're not installing PHP from scratch, you're building on top of an existing image.

```dockerfile
RUN apt-get update && apt-get install -y nginx supervisor zlib1g-dev libpng-dev \
    && docker-php-ext-install pdo pdo_mysql gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
```
Install nginx (web server), supervisor (process manager), and the system libraries that PHP's GD extension needs. Then install three PHP extensions: PDO (database abstraction), pdo_mysql (MySQL driver), and GD (image processing). Clean up afterwards to keep the image smaller.

```dockerfile
COPY deploy/nginx.conf       /etc/nginx/sites-available/default
COPY deploy/php-fpm.conf     /usr/local/etc/php-fpm.d/www.conf
COPY deploy/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
```
Copy your three config files from your project into the right locations inside the image. These replace the default configs.

```dockerfile
COPY public/ /var/www/diffrakt/public/
COPY src/    /var/www/diffrakt/src/
```
Copy your application code into the image. In local dev this is overridden by volume mounts (so live edits work), but the code is baked in for production.

```dockerfile
RUN mkdir -p /var/www/diffrakt/storage/originals \
             /var/www/diffrakt/storage/thumbs \
             /var/www/diffrakt/storage/processed \
             /var/www/diffrakt/storage/avatars \
    && chown -R www-data:www-data /var/www/diffrakt
```
Create the storage directories and give ownership to `www-data` (the user PHP-FPM runs as). Without the correct ownership, PHP can't write uploaded files.

```dockerfile
RUN mkdir -p /run/php && chown www-data:www-data /run/php
```
Create the directory where PHP-FPM writes its Unix socket file. Nginx communicates with PHP-FPM through this socket. Without the directory, PHP-FPM crashes on startup with exit code 78.

```dockerfile
EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```
`EXPOSE 80` documents that the container listens on port 80 (it doesn't actually open anything — that's done in docker-compose.yml). `CMD` is the command that runs when the container starts: supervisord, which then starts both nginx and php-fpm and keeps them running.

---

## Why Supervisord?

A Docker container is designed to run **one process**. But your app needs two: nginx and php-fpm. Supervisord is a process manager that runs as the single container process and manages nginx and php-fpm as child processes. If either one crashes, supervisord restarts it automatically.

```
Container starts
  └── supervisord (pid 1)
        ├── nginx
        └── php-fpm
```

---

## Your docker-compose.yml Explained

Docker Compose is a tool for defining and running multi-container applications. Instead of running long `docker run` commands manually, you describe everything in a YAML file.

```yaml
services:
  app:
    build: .
```
Build the image from the `Dockerfile` in the current directory (`.`). This is your PHP + Nginx container.

```yaml
    ports:
      - "8080:80"
```
Map port 8080 on your host machine to port 80 inside the container. Format is always `host:container`. This is why you visit `http://127.0.0.1:8080` — your machine's 8080 is forwarded into the container's 80.

```yaml
    env_file: .env
```
Load all variables from `.env` and inject them into the container as environment variables. Your PHP code reads `DB_HOST`, `DB_PASS`, etc. from here. Secrets never get baked into the image.

```yaml
    volumes:
      - storage_data:/var/www/diffrakt/storage
      - ./public:/var/www/diffrakt/public
      - ./src:/var/www/diffrakt/src
```
Three volume mounts:
- `storage_data` — named volume for persistent file storage
- `./public` and `./src` — bind mounts of your local source code folders into the container. This is what makes live editing work: you save a PHP file on Windows, and the container immediately sees the change. These two lines are removed in production.

```yaml
    depends_on:
      db:
        condition: service_healthy
```
Don't start the `app` container until the `db` container passes its health check. Without this, PHP tries to connect to MySQL before MySQL is ready and crashes.

```yaml
  db:
    image: mysql:8
```
Use MySQL's official image version 8 directly from Docker Hub. No custom Dockerfile needed.

```yaml
    volumes:
      - db_data:/var/lib/mysql
      - ./database/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql
      - ./database/migrations/001_followee_id.sql:/docker-entrypoint-initdb.d/02-001_followee_id.sql
      - ./database/seeds/filters.sql:/docker-entrypoint-initdb.d/03-seeds.sql
```
`/var/lib/mysql` is where MySQL stores its data files — mounted to a named volume for persistence. The `docker-entrypoint-initdb.d/` directory is special: MySQL runs every `.sql` file in it alphabetically on first boot (when the volume is empty). The numbered prefixes `01-`, `02-`, `03-` control the order: create tables first, then run migrations, then seed data.

```yaml
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-p$$MYSQL_ROOT_PASSWORD"]
      interval: 5s
      timeout: 5s
      retries: 10
```
Every 5 seconds, check if MySQL is accepting connections. The `app` container's `depends_on` waits for this to pass before starting.

---

## The Two Compose Files

| File | Used for | Key differences |
|---|---|---|
| `docker-compose.yml` | Local development | Builds image locally, mounts source code for live editing, port 8080 |
| `docker-compose.prod.yml` | Production server | Pulls pre-built image from GitLab registry, no source mounts, port 80 |

---

## Essential Commands

### Starting and stopping

```bash
# Build images and start all containers (use this when you change the Dockerfile)
docker compose up --build

# Start all containers without rebuilding (use this for normal daily startup)
docker compose up

# Start in detached mode (runs in background, terminal is free)
docker compose up -d

# Stop and remove containers (volumes are preserved)
docker compose down

# Stop and remove containers AND delete all volumes (wipes database and uploads)
docker compose down -v
```

> **Warning:** `docker compose down -v` is destructive. Only use it when you want a completely clean slate.

### Checking status

```bash
# Show running containers with their ports and status
docker compose ps

# Stream logs from all containers
docker compose logs

# Stream logs from one container only
docker compose logs app
docker compose logs db

# Follow logs in real time (like tail -f)
docker compose logs -f app
```

### Running commands inside containers

```bash
# Open a bash shell inside the app container
docker compose exec app bash

# Run a single command inside the app container
docker compose exec app php -v

# Test nginx config
docker compose exec app nginx -t

# Test php-fpm config
docker compose exec app php-fpm -t

# Run a MySQL query
docker compose exec db mysql -u diffrakt -psecret diffrakt -e "SHOW TABLES;"
```

### Managing images

```bash
# List all images on your machine
docker images

# Remove a specific image
docker rmi diffrakt-app

# Remove all unused images (frees disk space)
docker image prune
```

### Managing volumes

```bash
# List all volumes
docker volume ls

# Inspect a volume (see where it's stored)
docker volume inspect diffrakt_db_data

# Delete a specific volume (container must be stopped)
docker volume rm diffrakt_db_data
```

---

## What Happens When You Change Your Code

Because `./public` and `./src` are bind-mounted in your local `docker-compose.yml`, you don't need to do anything — just save the file. PHP reads it fresh on the next request.

The only time you need to rebuild is when you change the **Dockerfile itself** or the files in `deploy/` (nginx.conf, php-fpm.conf, supervisord.conf). In that case:

```bash
docker compose up --build
```

---

## The localhost vs 127.0.0.1 Issue You Hit

On Windows, Docker Desktop uses a network bridge that sometimes doesn't resolve `localhost` correctly in the browser. `127.0.0.1` is the explicit IP address for the same thing and always works. This is a known Windows/Docker Desktop quirk — on Linux and Mac, `localhost` works fine.

Bookmark `http://127.0.0.1:8080` for your local development.

---

## Local Dev vs Production — The Key Differences

| | Local (docker-compose.yml) | Production (docker-compose.prod.yml) |
|---|---|---|
| Image source | Built from your local Dockerfile | Pulled from GitLab Container Registry |
| Source code | Bind-mounted (live edits) | Baked into image at build time |
| Port | 8080 | 80 |
| APP_ENV | `local` | `production` |
| Secrets | `.env` file on your machine | Protected CI/CD variables injected at deploy time |
