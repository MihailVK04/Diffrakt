# Diffrakt — Project Spec Snapshot 005

**Stack:** Vanilla PHP 8.2 · MySQL 8 · HTML5 · CSS3 · Vanilla JS (ES2022)
**Rule:** Zero external dependencies. No Composer packages, no npm, no CDN links.

> **Changed from Snapshot 004:** A new endpoint `GET /users/{username}/posts` is added to the REST API. `profile.js` no longer expects posts to be embedded in the `GET /users/{username}` response — posts are fetched separately with cursor-based pagination using an IntersectionObserver (infinite scroll), matching the pattern used in `feed.js`. `api.js` gains `api.users.getPosts(username, cursor)` to call the new endpoint. All other architecture is unchanged.

**Environments:**
- **Local development** — XAMPP (Apache 2.4 + MySQL 8 + PHP 8.2). Virtual host configured in `httpd-vhosts.conf`. `.htaccess` handles URL rewriting (`FrontController` pattern, all requests routed through `public/index.php`).
- **Production (GitLab CI/CD → Docker)** — Single app container (Nginx + PHP-FPM) + single MySQL container, orchestrated with Docker Compose. No horizontal scaling — one container per service. Sessions are stored in the MySQL `sessions` table so they survive PHP-FPM restarts and container redeploys. Nginx `try_files` directive replaces `.htaccess`. Config lives in `deploy/nginx.conf`.

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
│   ├── index.html                  # SPA shell — single static HTML file served for all non-API requests
│   ├── index.php                   # API front controller — handles /api/v1/* only, always returns JSON
│   ├── .htaccess                   # Apache rewrite rules (local XAMPP only, ignored by Nginx)
│   └── assets/
│       ├── css/
│       │   └── app.css
│       └── js/
│           ├── app.js              # Entry point, wires up router and views
│           ├── api.js              # fetch() wrapper — sends credentials:include on every call
│           ├── filters/
│           │   ├── canvas.js       # Canvas API implementations of every atomic filter
│           │   └── pipeline.js     # Client-side pipeline runner (chains canvas filters)
│           └── views/
│               ├── home.js         # Landing page — shown to unauthenticated visitors
│               ├── editor.js       # Photo editor UI (filter panel, live preview)
│               ├── feed.js         # Feed view with cursor pagination
│               └── profile.js      # User profile view
│
├── src/                            # All PHP application code (never served directly)
│   ├── Bootstrap.php               # Bootstraps env, DB connection, session, and dispatches to router
│   │
│   ├── Core/
│   │   ├── Router.php              # Regex-based route table, dispatches to controllers
│   │   ├── Request.php             # Wraps $_SERVER, $_GET, $_POST, php://input
│   │   ├── Response.php            # Sends JSON responses with correct status codes
│   │   ├── Middleware.php          # Checks $_SESSION['user_id'], runs rate-limit checks
│   │   ├── Session.php             # Configures and starts the DB-backed session handler
│   │   ├── Validator.php           # Rules-based field validator (required, email, min…)
│   │   ├── RateLimiter.php         # Upserts rate_limits table, returns 429 on breach
│   │   └── Database.php            # PDO wrapper, singleton connection
│   │
│   ├── Controllers/
│   │   ├── AuthController.php      # register, login, logout, me
│   │   ├── UserController.php      # profile, posts (paginated), update, follow, unfollow
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
│   ├── schema.sql                  # Full CREATE TABLE definitions (includes sessions and rate_limits tables)
│   ├── seeds/
│   │   └── filters.sql             # Seeds all built-in atomic filter rows
│   └── migrations/                 # Numbered SQL migration files
│
├── deploy/
│   ├── nginx.conf                  # Nginx server block (copied into image at build time)
│   └── php-fpm.conf                # PHP-FPM pool config (copied into image at build time)
│
├── Dockerfile
├── docker-compose.yml
├── docker-compose.prod.yml
├── .gitlab-ci.yml
├── .env.example                    # Template: DB creds, APP_ENV, APP_ORIGIN, STORAGE_PATH,
│                                   # SESSION_LIFETIME, SESSION_COOKIE_NAME
├── .env
└── .gitignore
```

---

### Notes on the two environments

**Apache (XAMPP local)**
The `.htaccess` inside `public/` handles the front controller rewrite:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^api/ index.php [QSA,L]
RewriteRule ^(?!api/) index.html [L]
```
API calls (`/api/v1/*`) are routed to `index.php`. Everything else (all browser navigation) falls back to `index.html`. Static assets (`/assets/*`) are served directly by Apache as real files, so the `!-f` condition excludes them from rewriting.

**Nginx (production)**
`.htaccess` files are silently ignored by Nginx. The equivalent config lives in `deploy/nginx.conf`:
```nginx
server {
    root /var/www/diffrakt/public;

    location /api/ {
        try_files $uri /index.php$is_args$args;
    }

    location / {
        try_files $uri /index.html;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

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

Environment variables (DB credentials, `APP_ENV`, `STORAGE_PATH`, `SESSION_LIFETIME`, `SESSION_COOKIE_NAME`) are never stored in the image. They are defined as protected CI/CD variables in GitLab and injected at runtime.

---

## SPA Shell

### Overview

The SPA shell is a plain static file — `public/index.html`. It contains no page content, just the `#app` mount point and the `app.js` script tag. Every view is rendered into `#app` by the client-side router.

`public/index.php` is a pure API front controller. It never emits HTML. Apache and Nginx are configured so that `/api/*` requests go to `index.php` and all other requests go to `index.html`. The browser loads `index.html` once and never reloads it again; all subsequent navigation is handled client-side by `app.js`.

### index.html

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diffrakt</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div id="app"></div>
    <script type="module" src="/assets/js/app.js"></script>
</body>
</html>
```

`type="module"` on the script tag is required — it enables the ES module `import` chain (`app.js` → `api.js` → view files) and defers execution until the DOM is ready.

### index.php

`index.php` handles `/api/v1/*` requests only. It never emits HTML. Apache/Nginx ensure non-API requests never reach it.

### Client-side route table

| Path | View | Auth required |
|---|---|---|
| `/` | `HomeView` | No |
| `/feed` | `FeedView` | Yes |
| `/editor` | `EditorView` | Yes |
| `/editor/:postId` | `EditorView` | Yes |
| `/profile/:username` | `ProfileView` | No |

Unauthenticated visits to auth-required routes redirect to `/`. `HomeView` calls `app.refreshUser()` after a successful login, then `app.navigate('/feed')`.

### app.js responsibilities

- Imports `api.js` as an ES module (`import api from './api.js'`).
- Compiles route patterns (`:param` segments) into RegExp at runtime.
- On every navigation event: tears down the current view (calls `destroy()` if present), clears `#app`, instantiates the next view class, and calls `await view.render()`.
- Intercepts clicks on `<a data-link>` elements — routes them through `history.pushState` + `renderRoute()` instead of a full page reload.
- Handles `popstate` (browser back/forward).
- Exposes a minimal global `window.app` object for use inside views:

```js
window.app = {
    navigate(path),       // programmatic navigation
    refreshUser(),        // re-fetches /auth/me and updates the cached user
    getCurrentUser(),     // returns the cached user object or null
};
```

### View contract

Every view must be a class that accepts `(container, params)` in its constructor and exposes a `render()` method. `render()` may be async — `app.js` awaits it.

```js
class SomeView {
    constructor(container, params) { ... }
    async render() { ... }   // writes into container
    destroy() { ... }        // optional — clean up listeners, timers, abort controllers
}
```

### home.js responsibilities

`HomeView` is the only view that handles both authenticated and unauthenticated states:

- **Unauthenticated** — renders a landing page with login and register forms.
- **Authenticated** — immediately redirects to `/feed` via `app.navigate('/feed')`. This handles the case where a logged-in user navigates to `/` directly.

On successful login, `HomeView` must:
1. Call `await app.refreshUser()` to bust the cached session in `app.js`.
2. Call `app.navigate('/feed')` to transition into the app.

---

## Session Management

### Overview

Authentication uses PHP's native session mechanism backed by a custom DB session handler. Sessions are stored in the `sessions` MySQL table rather than on the filesystem, so they survive PHP-FPM process restarts and container redeploys without needing a separate session store.

### How it works

1. `Session::start()` is called in `Bootstrap.php` before the router runs. It registers a custom `SessionHandlerInterface` implementation that reads/writes the `sessions` table via PDO.
2. On login, `AuthController::login()` calls `session_regenerate_id(true)` (deletes the old session) then writes `$_SESSION['user_id']` and `$_SESSION['username']`.
3. On every protected request, `Middleware::requireAuth()` checks that `$_SESSION['user_id']` is set and non-empty. If not, returns 401.
4. On logout, `AuthController::logout()` calls `session_destroy()`, which triggers the handler's `destroy()` method and removes the row from the `sessions` table. The cookie is also explicitly expired in the response.

### Session configuration (set before `session_start()`)

| Setting | Value | Reason |
|---------|-------|--------|
| `session.use_strict_mode` | `1` | Rejects unrecognised session IDs |
| `session.cookie_httponly` | `1` | JS cannot read the cookie |
| `session.cookie_samesite` | `Lax` | CSRF mitigation |
| `session.cookie_secure` | `1` in production, `0` in dev | HTTPS only in prod |
| `session.gc_maxlifetime` | `SESSION_LIFETIME` env var (default 7200) | 2-hour idle timeout |
| `session.name` | `SESSION_COOKIE_NAME` env var (default `diffrakt_sid`) | Avoid default `PHPSESSID` |

### Session.php responsibilities

`src/Core/Session.php` implements `SessionHandlerInterface` with these methods mapped to SQL:

| Method | SQL |
|--------|-----|
| `open` | no-op (PDO already connected) |
| `close` | no-op |
| `read($id)` | `SELECT data FROM sessions WHERE id = ? AND expires_at > NOW()` |
| `write($id, $data)` | `INSERT … ON DUPLICATE KEY UPDATE data = ?, expires_at = ?` |
| `destroy($id)` | `DELETE FROM sessions WHERE id = ?` |
| `gc($maxlifetime)` | `DELETE FROM sessions WHERE expires_at < NOW()` |

### JS side (`api.js`)

Every `fetch()` call must include `credentials: 'include'` so the session cookie is sent cross-origin during local development (frontend on one port, PHP on another). In production both are served from the same origin so this is a no-op but harmless.

```js
// api.js — base fetch wrapper
async function _request(method, path, body = null, opts = {}) {
    const init = {
        method,
        headers: {},
        credentials: 'include',
        signal: opts.signal ?? null,
    };
    // ...
}
```

No Authorization header, no token storage in `localStorage` or `sessionStorage`. The cookie is managed entirely by the browser.

---

## Database

### Tables

**users** — `id, username, email, password_hash, avatar_path, bio, created_at`

**sessions** — custom PHP session store.
```
sessions
  id           VARCHAR(128)  PRIMARY KEY   — PHP session ID (cryptographically random)
  user_id      INT           NOT NULL      — FK → users.id
  data         TEXT          NOT NULL      — serialised $_SESSION payload
  expires_at   DATETIME      NOT NULL      — NOW() + SESSION_LIFETIME seconds
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
```
Indexed on `expires_at` so the `gc()` delete is fast.

**posts** — one row per uploaded image. Stores paths to three versions: original, thumbnail (800px), and processed export.

**filters** — built-in (`atomic`) and user-created (`composite`) filters.

**pipelines** — a named, ordered list of steps owned by a user.

**pipeline_steps** — steps inside a pipeline. Each row points to either a `filter_id` OR a `sub_pipeline_id` (never both).

```
pipeline_steps
  id
  pipeline_id       → pipelines.id
  step_order        (1-based integer)
  filter_id         → filters.id        (null if sub-pipeline)
  sub_pipeline_id   → pipelines.id      (null if filter)
  params            JSON
```

**follows** — `follower_id, followee_id, created_at` (composite primary key)

**rate_limits** — per-IP, per-endpoint fixed-window request counter. Upserted atomically on each request; 429 returned if the counter exceeds the threshold within the window.

```
rate_limits
  ip_hash        CHAR(64)      NOT NULL   — SHA-256 hex of client IP (raw IP never stored)
  endpoint       VARCHAR(128)  NOT NULL   — logical name, e.g. 'auth.login'
  requests       INT UNSIGNED  NOT NULL DEFAULT 1
  window_start   DATETIME      NOT NULL   — start of the current fixed window

  PRIMARY KEY (ip_hash, endpoint)
```

The composite primary key on `(ip_hash, endpoint)` makes the upsert O(1) and prevents duplicate rows under concurrent requests. No separate index is needed.

A scheduled `DELETE FROM rate_limits WHERE window_start < NOW() - INTERVAL 1 DAY` (nightly cron or MySQL EVENT) prevents unbounded table growth.

---

## Rate Limiting

### Overview

`RateLimiter::check($endpoint, $maxRequests, $windowSeconds)` is called by `Middleware::rateLimit()` before any controller logic runs. It hashes the client IP, upserts the `rate_limits` row, and calls `Response::tooManyRequests()` + exits if the counter exceeds the threshold.

### Algorithm — fixed window counter

On every request:
1. Hash the client IP with SHA-256 (`CHAR(64)` hex string).
2. Upsert the `(ip_hash, endpoint)` row:
   - **Window expired** → reset `requests` to 1, slide `window_start` to `NOW()`.
   - **Window active** → increment `requests` by 1.
3. Fetch the current `requests` count.
4. If `requests > $maxRequests` → `Response::tooManyRequests()` + `exit`.

The reset and increment are handled in a single atomic `INSERT … ON DUPLICATE KEY UPDATE` query using `IF(TIMESTAMPDIFF(SECOND, window_start, NOW()) >= :window_seconds, …)` so there is no read-then-write race condition.

### IP handling

`X-Forwarded-For` is read when present (leftmost value is the client IP). This assumes Diffrakt sits behind a single reverse proxy (Nginx/Caddy). Without a proxy, remove the `X-Forwarded-For` branch to prevent clients spoofing their IP by sending the header directly.

### Usage in Middleware

```php
// Rate-limit only
$this->middleware->rateLimit('auth.login', maxRequests: 10, windowSeconds: 60);

// Auth + rate-limit in one call
$this->middleware->requireAuthAndRateLimit('posts.export', maxRequests: 20, windowSeconds: 60);
```

---

## REST API

All routes under `/api/v1/`. Auth is enforced by checking `$_SESSION['user_id']` in `Middleware::requireAuth()`.

### Auth

| Method | Endpoint | Auth | Notes |
|--------|----------|------|-------|
| POST | `/auth/register` | No | Creates user, starts session, sets cookie |
| POST | `/auth/login` | No | `password_verify()`, regenerates session ID, sets cookie |
| POST | `/auth/logout` | Yes | `session_destroy()`, expires cookie |
| GET | `/auth/me` | Yes | Returns own user record from `$_SESSION['user_id']` |

### Users

| Method | Endpoint | Auth | Notes |
|--------|----------|------|-------|
| GET | `/users/{username}` | No | Public profile metadata (no posts) |
| GET | `/users/{username}/posts` | No | Cursor-paginated posts for a user: `?cursor={last_post_id}` |
| PATCH | `/users/me` | Yes | Update bio or avatar |
| POST | `/users/{username}/follow` | Yes | Follow |
| DELETE | `/users/{username}/follow` | Yes | Unfollow |

### Posts

| Method | Endpoint | Auth | Notes |
|--------|----------|------|-------|
| POST | `/posts` | Yes | Upload image (`multipart/form-data`) + caption |
| GET | `/posts/{id}` | No | Single post |
| PATCH | `/posts/{id}` | Yes | Update caption or visibility |
| DELETE | `/posts/{id}` | Yes | Deletes DB row and files |
| POST | `/posts/{id}/export` | Yes | Runs pipeline via PHP GD, returns download URL |

### Filters

| Method | Endpoint | Auth | Notes |
|--------|----------|------|-------|
| GET | `/filters` | No | All public + own filters |
| GET | `/filters/{id}` | No | Detail including `params_schema` |
| POST | `/filters` | Yes | Save pipeline as named composite filter |
| DELETE | `/filters/{id}` | Yes | Only composite filters can be deleted |

### Pipelines

| Method | Endpoint | Auth | Notes |
|--------|----------|------|-------|
| GET | `/pipelines/{id}` | No | Pipeline with ordered steps |
| POST | `/pipelines` | Yes | Create new pipeline |
| PUT | `/pipelines/{id}/steps` | Yes | Replace all steps |
| DELETE | `/pipelines/{id}` | Yes | Delete pipeline |
| POST | `/pipelines/{id}/apply` | Yes | Base64 in → Base64 out (server-side preview) |

### Feed

| Method | Endpoint | Auth | Notes |
|--------|----------|------|-------|
| GET | `/feed` | Yes | Posts from followed users, cursor-based: `?cursor={last_post_id}` |

---

## How the Filter Pipeline Works

### Atomic filters
Single-operation transforms (`blur`, `grayscale`, `sepia`, `brightness`, etc.). Each implemented twice — PHP/GD for export, Canvas API/JS for live preview. Accept typed parameters via `params_schema`, e.g. `{"radius": {"type": "int", "min": 1, "max": 20}}`.

### Piping filters together
A pipeline is an ordered list of steps. Output of step N is input of step N+1. A step can reference another pipeline via `sub_pipeline_id`, enabling nested pipelines.

**Example — "vintage" effect:**
```
step 1: sepia       { intensity: 0.7 }
step 2: brightness  { value: -0.05 }
step 3: contrast    { value: 0.15 }
step 4: vignette    { strength: 0.5 }
step 5: noise       { amount: 0.04 }
```

**Example — nesting composites:**
```
step 1: [vintage pipeline]
step 2: edge_detect
step 3: invert
step 4: saturation  { value: 2.5 }
```
Hard depth limit of 5 levels enforced in `PipelineRunner`.

### Cycle detection
Before saving a pipeline that references a sub-pipeline, a DFS runs on the reference graph. If a cycle is detected the request is rejected with 422.

---

## Key Hand-Written Pieces

- **Session handler** — implements `SessionHandlerInterface`, reads/writes the `sessions` MySQL table via PDO. Configured before `session_start()` in `Session::start()`.
- **Router** — reads `REQUEST_URI`, strips query string, matches regex patterns with named segments like `{id}`.
- **Validator** — rules array (`required`, `email`, `min_length`, `max_length`, `integer`, `min`, `max`), returns per-field errors. One error per field; non-required rules skip empty values.
- **Rate limiter** — SHA-256 hashes client IP, upserts `rate_limits` table with a single atomic query, calls `Response::tooManyRequests()` on breach.
- **Thumbnail generation** — `imagecreatefrom*()`, `imagescale()` to 800px long side, `imagejpeg()`.
- **Pixel loops** — saturation, hue rotation, sepia, vignette, noise use `imagecolorat()` / `imagesetpixel()`.

---

## File Storage

Files stored outside the web root, served through PHP via `readfile()`. Direct file URLs never work.

```
storage/
  originals/    raw uploaded files
  thumbs/       800px previews, always JPEG
  processed/    exported filter results
  avatars/      profile pictures
```

Filenames are UUID4 values from `random_bytes(16)`. User-supplied filenames are never used.
