# Diffrakt — Project Spec

**Stack:** Vanilla PHP 8.2 · MySQL 8 · HTML5 · CSS3 · Vanilla JS (ES2022)
**Rule:** Zero external dependencies. No Composer packages, no npm, no CDN links.

**Environments:**
- **Local development** — XAMPP (Apache 2.4 + MySQL 8 + PHP 8.2). Virtual host configured in `httpd-vhosts.conf`. `.htaccess` handles URL rewriting (`FrontController` pattern, all requests routed through `public/index.php`).
- **Production (GitLab CI/CD → Docker)** — The app is packaged as a Docker image and deployed via GitLab CI/CD. The container runs Nginx + PHP-FPM; MySQL runs in a separate container, both orchestrated with Docker Compose. Nginx `try_files` directive replaces `.htaccess` (Apache rewrites are not read by Nginx). Config lives in `deploy/nginx.conf`. PHP runs as a separate FPM process pool, not as an Apache module. The GitLab Container Registry stores the built image; the CI pipeline builds, tags, pushes, and redeploys on every merge to `main`.

> **Key difference:** Apache uses `.htaccess` per-directory; Nginx does not. The rewrite logic that sends all traffic to `public/index.php` must be declared in the Nginx server block instead. The application PHP code is identical in both environments — only the web server config differs.

---

## What It Does

Users upload photos, apply filters, chain those filters into pipelines, and save the result. Two social features exist: a follow system and a feed. Everything else is about editing.

---

## Project File Structure

```
diffrakt/
│
├── public/                         # Web root (only this dir is exposed by Apache/Nginx)
│   ├── index.php                   # Front controller — all HTTP requests enter here
│   ├── .htaccess                   # Apache rewrite rules (local XAMPP only, ignored by Nginx)
│   └── assets/
│       ├── css/
│       │   └── app.css
│       └── js/
│           ├── app.js              # Entry point, wires up router and views
│           ├── api.js              # fetch() wrapper for all REST calls
│           ├── filters/
│           │   ├── canvas.js       # Canvas API implementations of every atomic filter
│           │   └── pipeline.js     # Client-side pipeline runner (chains canvas filters)
│           └── views/
│               ├── editor.js       # Photo editor UI (filter panel, live preview)
│               ├── feed.js         # Feed view with cursor pagination
│               └── profile.js      # User profile view
│
├── src/                            # All PHP application code (never served directly)
│   ├── Bootstrap.php               # Bootstraps env, DB connection, and dispatches to router
│   │
│   ├── Core/
│   │   ├── Router.php              # Regex-based route table, dispatches to controllers
│   │   ├── Request.php             # Wraps $_SERVER, $_GET, $_POST, php://input
│   │   ├── Response.php            # Sends JSON responses with correct status codes
│   │   ├── Middleware.php          # Runs auth, rate-limit checks before controllers
│   │   ├── Jwt.php                 # Hand-rolled HMAC-SHA256 JWT (sign + verify)
│   │   ├── Validator.php           # Rules-based field validator (required, email, min…)
│   │   ├── RateLimiter.php         # Upserts rate_limits table, returns 429 on breach
│   │   └── Database.php            # PDO wrapper, singleton connection
│   │
│   ├── Controllers/
│   │   ├── AuthController.php      # register, login, logout, me
│   │   ├── UserController.php      # profile, update, follow, unfollow
│   │   ├── PostController.php      # upload, get, update, delete, export
│   │   ├── FilterController.php    # list, get, create composite, delete
│   │   ├── PipelineController.php  # CRUD + apply (server-side preview)
│   │   └── FeedController.php      # cursor-paginated follow feed
│   │
│   ├── Models/
│   │   ├── User.php
│   │   ├── Post.php
│   │   ├── Filter.php
│   │   ├── Pipeline.php
│   │   └── PipelineStep.php
│   │
│   ├── Services/
│   │   ├── ImageService.php        # GD thumbnail generation + readfile() file serving
│   │   ├── PipelineRunner.php      # Resolves step graph, enforces 5-level depth limit
│   │   ├── CycleDetector.php       # DFS cycle check before saving sub-pipeline refs
│   │   └── StorageService.php      # UUID4 filename generation, path resolution
│   │
│   └── Filters/                    # One class per atomic filter (GD implementation)
│       ├── BlurFilter.php
│       ├── GrayscaleFilter.php
│       ├── SepiaFilter.php
│       ├── BrightnessFilter.php
│       ├── ContrastFilter.php
│       ├── SaturationFilter.php
│       ├── HueRotateFilter.php
│       ├── VignetteFilter.php
│       ├── NoiseFilter.php
│       └── EdgeDetectFilter.php
│
├── storage/                        # All user files — stored outside web root
│   ├── originals/                  # Raw uploaded files
│   ├── thumbs/                     # 800px JPEG previews (generated on upload)
│   ├── processed/                  # Exported pipeline results
│   └── avatars/                    # Profile pictures
│
├── database/
│   ├── schema.sql                  # Full CREATE TABLE definitions
│   ├── seeds/
│   │   └── filters.sql             # Seeds all built-in atomic filter rows
│   └── migrations/                 # Numbered SQL migration files (e.g. 001_add_visibility.sql)
│
├── deploy/
│   ├── nginx.conf                  # Nginx server block (copied into image at build time)
│   │                               # Uses try_files $uri /index.php (replaces .htaccess)
│   └── php-fpm.conf                # PHP-FPM pool config (copied into image at build time)
│
├── Dockerfile                      # Single-stage build: php:8.2-fpm base, installs Nginx,
│                                   # copies src/ public/ deploy/; Nginx + PHP-FPM via supervisor
├── docker-compose.yml              # Local Docker stack (optional XAMPP alternative):
│                                   # app service (Dockerfile) + db service (mysql:8)
├── docker-compose.prod.yml         # Production stack: app image from GitLab Container Registry
│                                   # + db (mysql:8), named volumes for storage/ and MySQL data
│
├── .gitlab-ci.yml                  # CI/CD pipeline — three stages:
│                                   #   build:  docker build + push to GitLab Container Registry
│                                   #   test:   run test container against a test DB
│                                   #   deploy: ssh to server, docker compose pull + up -d
├── .env.example                    # Template for all env vars (DB creds, JWT secret, APP_ENV,
│                                   # STORAGE_PATH) — on the server, injected as GitLab CI variables
├── .env                            # Actual env file — never committed (in .gitignore)
└── .gitignore
```

### Notes on the two environments

**Apache (XAMPP local)**
The `.htaccess` inside `public/` handles the front controller rewrite:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]
```
All static assets (`/assets/*`) are served directly by Apache; everything else hits `index.php`.

**Nginx (production)**
`.htaccess` files are silently ignored by Nginx. The equivalent config lives in `deploy/nginx.conf`:
```nginx
server {
    root /var/www/diffrakt/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Block direct access to storage (files are served through PHP)
    location /storage {
        deny all;
    }
}
```

**Docker (production)**
The `Dockerfile` builds a single image containing both Nginx and PHP-FPM, managed by `supervisord`:
```dockerfile
FROM php:8.2-fpm

RUN apt-get update && apt-get install -y nginx supervisor \
    && docker-php-ext-install pdo pdo_mysql gd

COPY deploy/nginx.conf /etc/nginx/sites-available/default
COPY deploy/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY deploy/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

COPY public/ /var/www/diffrakt/public/
COPY src/    /var/www/diffrakt/src/

RUN chown -R www-data:www-data /var/www/diffrakt

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

`docker-compose.prod.yml` defines two services — `app` (the built image pulled from the GitLab Container Registry) and `db` (MySQL 8) — plus a named volume that persists the `storage/` directory across container restarts and redeploys.

The `.gitlab-ci.yml` pipeline runs three stages on every push to `main`:
1. **build** — `docker build` and push the tagged image to the GitLab Container Registry.
2. **test** — spin up the image alongside a throw-away MySQL container and run the test suite.
3. **deploy** — SSH into the production server, `docker compose pull`, then `docker compose up -d`.

Environment variables (DB credentials, JWT secret, `APP_ENV`, `STORAGE_PATH`) are never stored in the image. They are defined as protected CI/CD variables in GitLab and injected at runtime via the compose `env_file` or `environment` keys.

> **`.env` on the server:** the `.env` file itself is not in the repo. On the production server it lives outside the project directory and is bind-mounted into the container, or its values are passed directly as Docker environment variables by the CI deploy job.

---

## Database

### Tables

**users** — account info. `id, username, email, password_hash, avatar_path, bio, created_at`

**posts** — one row per uploaded image. Stores paths to three versions of the file: the original, a thumbnail (800px, generated on upload), and the processed export (filled in later when the user exports).

**filters** — both built-in and user-created filters live here. A `type` column distinguishes them:
- `atomic` — a built-in single operation (blur, grayscale, etc.). These rows are seeded at setup and never deleted.
- `composite` — a filter a user assembled from steps and saved as a named filter. Under the hood it's just an alias for a pipeline.

**pipelines** — a named, ordered list of steps owned by a user.

**pipeline_steps** — the actual steps inside a pipeline. Each row belongs to a pipeline and points to either a `filter_id` OR a `sub_pipeline_id` (never both). This is what makes piping work — a step can reference another pipeline, not just an atomic filter.

```
pipeline_steps
  id
  pipeline_id       → pipelines.id
  step_order        (1-based integer, defines execution order)
  filter_id         → filters.id        (null if sub-pipeline)
  sub_pipeline_id   → pipelines.id      (null if filter)
  params            JSON  e.g. {"brightness": 1.4}
```

**follows** — two foreign keys, composite primary key. `follower_id, followee_id, created_at`

**rate_limits** — used by the hand-rolled rate limiter. Stores a hashed IP, endpoint name, window start timestamp, and a counter. On each request the row is upserted; if the count exceeds the limit a 429 is returned.

---

## REST API

All routes live under `/api/v1/`. PHP reads `$_SERVER['REQUEST_URI']` and `REQUEST_METHOD` and matches against a hand-written route table. Auth is a hand-rolled JWT (HMAC-SHA256 using PHP's built-in `hash_hmac`), sent as a Bearer token.

### Auth

| Method | Endpoint | Notes |
|--------|----------|-------|
| POST | `/auth/register` | Creates user, returns signed JWT |
| POST | `/auth/login` | Validates password with `password_verify()`, returns JWT |
| POST | `/auth/logout` | Adds token to a denylist table |
| GET | `/auth/me` | Returns own user record |

### Users

| Method | Endpoint | Notes |
|--------|----------|-------|
| GET | `/users/{username}` | Public profile |
| PATCH | `/users/me` | Update bio or avatar |
| POST | `/users/{username}/follow` | Follow |
| DELETE | `/users/{username}/follow` | Unfollow |

### Posts

| Method | Endpoint | Notes |
|--------|----------|-------|
| POST | `/posts` | Upload image (`multipart/form-data`) + caption. Server generates thumbnail via GD. |
| GET | `/posts/{id}` | Single post |
| PATCH | `/posts/{id}` | Update caption or visibility |
| DELETE | `/posts/{id}` | Deletes DB row and files from disk |
| POST | `/posts/{id}/export` | Runs the pipeline through PHP GD, saves the result, returns a download URL |

### Filters

| Method | Endpoint | Notes |
|--------|----------|-------|
| GET | `/filters` | All public + own filters |
| GET | `/filters/{id}` | Detail including `params_schema` (what params this filter accepts) |
| POST | `/filters` | Save a pipeline as a named composite filter |
| DELETE | `/filters/{id}` | Only composite filters can be deleted |

### Pipelines

| Method | Endpoint | Notes |
|--------|----------|-------|
| GET | `/pipelines/{id}` | Pipeline with its ordered steps |
| POST | `/pipelines` | Create new pipeline |
| PUT | `/pipelines/{id}/steps` | Replace all steps — send a JSON array in the desired order |
| DELETE | `/pipelines/{id}` | Delete pipeline |
| POST | `/pipelines/{id}/apply` | Send a Base64 image, get back a Base64 result. Used for server-side preview. |

### Feed

| Method | Endpoint | Notes |
|--------|----------|-------|
| GET | `/feed` | Posts from followed users, newest first. Cursor-based pagination: `?cursor={last_post_id}` |

---

## How the Filter Pipeline Works

### Atomic filters
Single-operation transforms like `blur`, `grayscale`, `sepia`, `brightness`. Each one is implemented twice — in PHP using GD (for export) and in JS using the Canvas API (for live preview). They accept typed parameters defined in `params_schema`, e.g. `{"radius": {"type": "int", "min": 1, "max": 20}}`.

### Piping filters together
A pipeline is just an ordered list of steps. The output of step 1 becomes the input of step 2, and so on. Because a step can point to another pipeline (via `sub_pipeline_id`), you can nest pipelines inside pipelines.

**Example — building a "vintage" effect:**
```
step 1: sepia       { intensity: 0.7 }
step 2: brightness  { value: -0.05 }
step 3: contrast    { value: 0.15 }
step 4: vignette    { strength: 0.5 }
step 5: noise       { amount: 0.04 }
```
Save that pipeline as a filter named "vintage". Now "vintage" appears in the filter list like any built-in.

**Example — piping a composite into another composite:**
```
step 1: [vintage pipeline]      ← the whole vintage pipeline runs here
step 2: edge_detect
step 3: invert
step 4: saturation  { value: 2.5 }
```
Save that as "neon vintage". Any depth of nesting is allowed up to a hard limit of 5 levels, enforced in `PipelineRunner` to prevent runaway execution.

### Cycle detection
Before saving a pipeline that references a sub-pipeline, the backend runs a depth-first search on the pipeline reference graph. If following `sub_pipeline_id` links ever leads back to the pipeline being saved, the request is rejected with a 422.

---

## Key Hand-Written Pieces

Since no libraries are allowed, these common things are built from scratch:

- **JWT** — `hash_hmac('sha256', header.payload, secret)` encoded as base64url. Verified with `hash_equals()` to avoid timing attacks.
- **Router** — reads `REQUEST_URI`, strips query string, matches regex patterns with named segments like `{id}`.
- **Validator** — takes a rules array (`required`, `min_length`, `email`, `integer`, `min`, `max`) and returns per-field errors.
- **Rate limiter** — upserts a row in `rate_limits` on each request; returns 429 if `request_count` exceeds the threshold for the current time window.
- **Thumbnail generation** — `imagecreatefrom*()` loads the upload, `imagescale()` resizes to 800px on the long side, `imagejpeg()` writes it.
- **Pixel loops** — filters GD has no built-in for (saturation, hue rotation, sepia, vignette, noise) use `imagecolorat()` / `imagesetpixel()` loops.

---

## File Storage

Files are stored outside the web root and served through PHP with `readfile()`. Direct file URLs never work — everything goes through the API.

```
storage/
  originals/    raw uploaded files
  thumbs/       800px previews, always JPEG
  processed/    exported filter results
  avatars/      profile pictures
```

Filenames are UUID4 values generated from `random_bytes(16)`. User-supplied filenames are never used.
