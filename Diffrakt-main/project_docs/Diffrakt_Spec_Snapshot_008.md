# Diffrakt — Project Spec Snapshot 008

**Stack:** Vanilla PHP 8.2 · MySQL 8 · HTML5 · CSS3 · Vanilla JS (ES2022)
**Rule:** Zero external dependencies. No Composer packages, no npm, no CDN links.

> **Changed from Snapshot 007:**
> - CSS palette replaced — "Warm Studio" design system. New CSS custom properties throughout `app.css`. See CSS Design System section below.
> - Feed `--max-width-narrow` bumped from `30rem` to `42rem`. Feed padding and card gap tightened.
> - `.editor__preview-actions` restructured: buttons now live inside a `.editor__preview-actions-row` wrapper div, with `.editor__save-filter-form` as a sibling below it inside `.editor__preview-actions`.
> - `.editor__save-filter-form` now has `display: flex` as its base rule with a `[hidden]` override for `display: none`. Previously had `display: none` as base rule which prevented JS `hidden = false` toggling from working.
> - `_buildShellHTML()` in `editor.js` — fixed unclosed `<div class="editor__preview-actions-row">` tag (was written as an opening tag instead of `</div>`).
> - Post images and photo display work primarily with processed/thumbnail variants. Originals are used only in specific edge cases (e.g. export).
> - `public/shell.php` replaced `public/index.html` — dynamic `<base>` tag makes the app fully portable across folder renames on XAMPP with no host file edits required.

---

## Environments

- **Local development** — XAMPP (Apache 2.4 + MySQL 8 + PHP 8.2). Virtual host configured in `httpd-vhosts.conf`. `.htaccess` handles URL rewriting (front controller pattern, all requests routed through `public/index.php`). A dynamic `<base>` tag in `public/shell.php` makes the app portable — no host file edits needed when the project folder is renamed.
- **Production (GitLab CI/CD → Docker)** — Single app container (Nginx + PHP-FPM) + single MySQL container, orchestrated with Docker Compose. No horizontal scaling. Sessions stored in the MySQL `sessions` table survive PHP-FPM restarts and container redeploys. Nginx `try_files` replaces `.htaccess`. Config lives in `deploy/nginx.conf`.

> **Key difference:** Apache uses `.htaccess` per-directory; Nginx silently ignores it. The rewrite logic that sends all traffic to `public/index.php` must be declared in the Nginx server block instead. The PHP application code is identical in both environments — only the web server config differs.

---

## What It Does

Users upload photos, apply filters, chain those filters into pipelines, and save the result. Two social features exist: a follow system and a feed. Everything else is about editing.

---

## Project File Structure

```
diffrakt/
│
├── public/                         # Web root (only this dir is exposed by Apache/Nginx)
│   ├── shell.php                   # SPA shell — dynamic <base> tag, serves index HTML for all non-API requests
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
│               ├── editor.js       # Photo editor UI (filter panel, live preview, save-as-filter)
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
│   │   ├── PipelineController.php  # CRUD + apply (export to file) + preview (stateless base64) + response-time step flattening
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
│   ├── originals/                  # Raw uploaded files (used for export only)
│   ├── thumbs/                     # 800px JPEG previews — primary display format
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
│   ├── php-fpm.conf                # PHP-FPM pool config (copied into image at build time)
│   └── supervisord.conf            # supervisord config — manages nginx + php-fpm processes inside container
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

## Environment Details

### Apache (XAMPP local)

The `.htaccess` inside `public/` handles the front controller rewrite:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^api/ index.php [QSA,L]
RewriteRule ^(?!api/) shell.php [L]
```

API calls (`/api/v1/*`) are routed to `index.php`. Everything else falls back to `shell.php`. Static assets (`/assets/*`) are served directly as real files — the `!-f` condition excludes them.

`shell.php` emits the SPA shell HTML with a dynamically generated `<base>` tag:

```php
<?php
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= htmlspecialchars($base) ?>">
    <title>Diffrakt</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div id="app"></div>
    <script type="module" src="assets/js/app.js"></script>
</body>
</html>
```

The `<base>` tag makes all relative asset paths and API calls resolve correctly regardless of what folder the project lives under in `htdocs/`. Renaming `diffrakt/` to anything else requires no config changes.

### Nginx (production)

`.htaccess` files are silently ignored by Nginx. The equivalent config lives in `deploy/nginx.conf`:

```nginx
server {
    listen 80;
    root /var/www/diffrakt/public;

    location /api/ {
        try_files $uri /index.php$is_args$args;
    }

    location / {
        try_files $uri /shell.php;
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

### Docker (production)

The `Dockerfile` builds a single image containing both Nginx and PHP-FPM, managed by `supervisord`. Running two processes in one container is a deliberate simplification — there is no load balancing requirement, and it avoids the networking overhead of a separate Nginx container calling PHP-FPM over TCP.

```dockerfile
FROM php:8.2-fpm

RUN apt-get update && apt-get install -y nginx supervisor \
    && docker-php-ext-install pdo pdo_mysql gd

COPY deploy/nginx.conf      /etc/nginx/sites-available/default
COPY deploy/php-fpm.conf    /usr/local/etc/php-fpm.d/www.conf
COPY deploy/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

COPY public/ /var/www/diffrakt/public/
COPY src/    /var/www/diffrakt/src/

RUN chown -R www-data:www-data /var/www/diffrakt

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

`deploy/supervisord.conf` keeps both processes running and restarts either one if it crashes:

```ini
[supervisord]
nodaemon=true

[program:nginx]
command=/usr/sbin/nginx -g "daemon off;"
autostart=true
autorestart=true
stderr_logfile=/var/log/nginx.err.log
stdout_logfile=/var/log/nginx.out.log

[program:php-fpm]
command=/usr/local/sbin/php-fpm --nodaemonize
autostart=true
autorestart=true
stderr_logfile=/var/log/php-fpm.err.log
stdout_logfile=/var/log/php-fpm.out.log
```

`docker-compose.prod.yml` defines two services:

```yaml
services:
  app:
    image: registry.gitlab.com/<group>/diffrakt:latest
    restart: unless-stopped
    ports:
      - "80:80"
    env_file: .env
    volumes:
      - storage_data:/var/www/diffrakt/storage
    depends_on:
      - db

  db:
    image: mysql:8
    restart: unless-stopped
    env_file: .env
    volumes:
      - db_data:/var/lib/mysql

volumes:
  storage_data:   # persists storage/ across redeploys
  db_data:        # persists MySQL data across redeploys
```

**Named volumes** are critical:
- `storage_data` — mounts over `storage/` inside the container. Without it, uploaded files and processed exports are lost every time the container is replaced.
- `db_data` — standard MySQL data persistence. Without it, the database is wiped on every `docker compose up`.

Environment variables (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_ENV`, `APP_ORIGIN`, `STORAGE_PATH`, `SESSION_LIFETIME`, `SESSION_COOKIE_NAME`) are never baked into the image. They are defined as protected CI/CD variables in GitLab and injected at runtime via `env_file: .env` on the production server.

### GitLab CI/CD pipeline

`.gitlab-ci.yml` runs three stages on every push to `main`:

1. **build** — `docker build` the image, tag it with the commit SHA and `latest`, push both tags to the GitLab Container Registry.
2. **test** — spin up the built image alongside a throw-away MySQL 8 container, run the test suite against it, tear everything down.
3. **deploy** — SSH into the production server, run `docker compose -f docker-compose.prod.yml pull`, then `docker compose -f docker-compose.prod.yml up -d`. The `--no-deps` flag can be added to update only the `app` service without restarting `db`.

The deploy stage only runs on the `main` branch. Feature branches trigger build and test only.

### Storage path across environments

`STORAGE_PATH` env var controls where files are written. In local XAMPP it points to an absolute path inside the project. In Docker it points to `/var/www/diffrakt/storage`, which is where the `storage_data` named volume is mounted.

`StorageService.php` reads this env var and never hardcodes paths, so the same code works in both environments.

---

## SPA Shell

### Overview

The SPA shell is `public/shell.php` — a PHP file that emits static HTML with a dynamic `<base>` tag. It contains no page content, just the `#app` mount point and the `app.js` script tag. Every view is rendered into `#app` by the client-side router.

`public/index.php` is a pure API front controller. It never emits HTML. Apache and Nginx are configured so that `/api/*` requests go to `index.php` and all other requests go to `shell.php`. The browser loads the shell once and never reloads; all subsequent navigation is client-side.

### Client-side route table

| Path | View | Auth required |
|---|---|---|
| `/` | `HomeView` | No |
| `/feed` | `FeedView` | Yes |
| `/editor` | `EditorView` | Yes |
| `/editor/:postId` | `EditorView` | Yes |
| `/profile/:username` | `ProfileView` | No |

Unauthenticated visits to auth-required routes redirect to `/`. `HomeView` calls `app.refreshUser()` after login, then `app.navigate('/feed')`.

### app.js responsibilities

- Imports `api.js` as an ES module.
- Compiles route patterns (`:param` segments) into RegExp at runtime.
- On every navigation event: tears down the current view (`destroy()` if present), clears `#app`, instantiates the next view class, calls `await view.render()`.
- Intercepts clicks on `<a data-link>` elements — routes through `history.pushState` + `renderRoute()`.
- Handles `popstate` (browser back/forward).
- Exposes a minimal global `window.app`:

```js
window.app = {
    navigate(path),       // programmatic navigation
    refreshUser(),        // re-fetches /auth/me and updates the cached user
    getCurrentUser(),     // returns the cached user object or null
};
```

### View contract

```js
class SomeView {
    constructor(container, params) { ... }
    async render() { ... }   // writes into container
    destroy() { ... }        // optional — clean up listeners, timers, abort controllers
}
```

---

## CSS Design System

### Design direction — "Warm Studio"

The palette was reworked in snapshot 008. The previous golden yellow / terracotta palette was replaced with a warm off-white base that reads as bright without feeling clinical. Navy and accent pink are preserved — they carry the personality of the app.

### Color tokens

| Token | Value | Role |
|---|---|---|
| `--color-bg` | `#F5EFE6` | Page background — warm linen |
| `--color-surface` | `#FFFBF5` | Cards, panels, preview panel |
| `--color-surface-hover` | `#FFF5E8` | Card hover tint |
| `--color-accent` | `#D94179` | CTAs, active states, focus ring |
| `--color-accent-hover` | `#B8305F` | Accent hover |
| `--color-navy` | `#141259` | Nav bar, editor sidebar, headings |
| `--color-navy-hover` | `#0E0B40` | Navy hover |
| `--color-deep` | `#010626` | Canvas background |
| `--color-text` | `#1A1510` | Body text — warm near-black |
| `--color-text-muted` | `#6B5F52` | Labels, subtitles — warm medium gray |
| `--color-text-on-dark` | `#F5EFE6` | Text on navy backgrounds |
| `--color-text-on-accent` | `#ffffff` | Text on pink buttons |
| `--color-border` | `rgba(26,21,16,0.12)` | Subtle warm dividers |
| `--color-border-strong` | `rgba(26,21,16,0.22)` | Stronger borders |
| `--color-error` | `#C0184A` | Inline errors |
| `--color-error-bg` | `rgba(192,24,74,0.07)` | Error message background |
| `--color-success` | `#1A7A4A` | Success states |
| `--color-focus` | `#D94179` | Focus outline (matches accent) |

Shadows use warm-tinted `rgba(26, 21, 16, …)` base instead of cold blue-gray, giving cards and panels a grounded feel.

### Layout tokens

| Token | Value | Notes |
|---|---|---|
| `--max-width` | `60rem` | Main container |
| `--max-width-narrow` | `42rem` | Feed, auth forms (was `30rem` in 007) |
| `--nav-height` | `3.5rem` | Fixed nav; `body` has matching `padding-top` |

### Key component notes

**Feed** — cards use `--color-surface` background, `--shadow-card` at rest, `--shadow-md` on hover. Gap between cards is `--space-5` (tightened from `--space-8`). Images are `aspect-ratio: 4/3`, `object-fit: cover` with a slow scale zoom on hover.

**Editor sidebar** — navy background unchanged. Filter buttons, step list, and parameter sliders remain on the dark surface. Scrollbar thumb uses `rgba(242, 167, 102, 0.35)`.

**Save as filter form** — lives inside `.editor__preview-actions` as a sibling to `.editor__preview-actions-row`. Hidden on load via `[hidden]` attribute. Base CSS rule is `display: flex` — the `[hidden]` attribute override (`display: none`) controls visibility. JS toggles the `hidden` attribute only; it does not manipulate `display` directly.

```css
.editor__save-filter-form {
    display: flex;
    /* ... other properties */
}
.editor__save-filter-form[hidden] {
    display: none;
}
```

**Buttons** — pill-shaped (`--radius-full`), three variants: primary (accent pink), secondary (navy), ghost (transparent, navy border).

---

## Editor View — HTML Structure

```html
<div class="editor__preview-actions">
    <div class="editor__preview-actions-row">
        <button id="editor-save-btn"        class="btn btn--primary">Publish</button>
        <button id="editor-export-btn"      class="btn btn--secondary">Export</button>
        <button id="editor-save-filter-btn" class="btn btn--ghost">Save as filter</button>
    </div>

    <div id="editor-save-filter-form" class="editor__save-filter-form" hidden>
        <input id="editor-save-filter-input" class="form__input" type="text" placeholder="Filter name" maxlength="80">
        <button id="editor-save-filter-confirm" class="btn btn--primary">Save</button>
        <button id="editor-save-filter-cancel"  class="btn btn--ghost">Cancel</button>
    </div>
</div>
```

The form starts with `hidden` on the element. `_bindSaveFilter()` in `editor.js` toggles `form.hidden` — it does not touch `display`. The CSS `[hidden]` selector ensures `display: none` overrides the base `display: flex` rule when the attribute is present.

---

## Session Management

### Overview

Authentication uses PHP's native session mechanism backed by a custom DB session handler. Sessions are stored in the `sessions` MySQL table rather than on the filesystem, so they survive PHP-FPM process restarts and container redeploys.

### How it works

1. `Session::start()` called in `Bootstrap.php` before the router runs. Registers a custom `SessionHandlerInterface` that reads/writes the `sessions` table via PDO.
2. On login, `AuthController::login()` calls `session_regenerate_id(true)` then writes `$_SESSION['user_id']` and `$_SESSION['username']`.
3. On every protected request, `Middleware::requireAuth()` checks `$_SESSION['user_id']`. If missing, returns 401.
4. On logout, `session_destroy()` triggers the handler's `destroy()` and removes the row. Cookie is also explicitly expired.

### Session configuration

| Setting | Value | Reason |
|---------|-------|--------|
| `session.use_strict_mode` | `1` | Rejects unrecognised session IDs |
| `session.cookie_httponly` | `1` | JS cannot read the cookie |
| `session.cookie_samesite` | `Lax` | CSRF mitigation |
| `session.cookie_secure` | `1` prod / `0` dev | HTTPS only in prod |
| `session.gc_maxlifetime` | `SESSION_LIFETIME` env var (default 7200) | 2-hour idle timeout |
| `session.name` | `SESSION_COOKIE_NAME` env var (default `diffrakt_sid`) | Avoids default `PHPSESSID` |

### Session.php — SQL mapping

| Method | SQL |
|--------|-----|
| `read($id)` | `SELECT data FROM sessions WHERE id = ? AND expires_at > NOW()` |
| `write($id, $data)` | `INSERT … ON DUPLICATE KEY UPDATE data = ?, expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)` |
| `destroy($id)` | `DELETE FROM sessions WHERE id = ?` |
| `gc($maxlifetime)` | `DELETE FROM sessions WHERE expires_at < NOW()` |

`expires_at` is always computed in SQL via `DATE_ADD(NOW(), INTERVAL ? SECOND)` — never in PHP — to avoid timezone mismatch.

---

## Database

### Tables

**users** — `id, username, email, password_hash, avatar_path, bio, created_at`

**sessions** — custom PHP session store.
```
sessions
  id           VARCHAR(128)  PRIMARY KEY   — PHP session ID
  user_id      INT           NULL          — FK → users.id (NULL before login)
  data         TEXT          NOT NULL      — serialised $_SESSION payload
  expires_at   DATETIME      NOT NULL
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
```
`user_id` is `NULL`-able because `Session::write()` fires on every request, including unauthenticated ones before `$_SESSION['user_id']` is set.

**posts** — one row per uploaded image. Stores paths to three versions: original, thumbnail (800px), and processed export. The thumbnail is the primary display format; originals are read only during pipeline export.

**filters** — built-in (`atomic`) and user-created (`composite`) filters.

**pipelines** — named, ordered list of steps owned by a user.

**pipeline_steps**
```
pipeline_steps
  id
  pipeline_id       → pipelines.id
  step_order        (1-based integer)
  filter_id         → filters.id        (null if sub-pipeline)
  sub_pipeline_id   → pipelines.id      (null if filter)
  params            JSON
```

**follows** — `follower_id, followee_id, created_at` (composite PK)

**rate_limits**
```
rate_limits
  ip_hash        CHAR(64)      NOT NULL   — SHA-256 hex of client IP
  endpoint       VARCHAR(128)  NOT NULL
  requests       INT UNSIGNED  NOT NULL DEFAULT 1
  window_start   DATETIME      NOT NULL

  PRIMARY KEY (ip_hash, endpoint)
```

---

## Rate Limiting

`RateLimiter::check($endpoint, $maxRequests, $windowSeconds)` called by `Middleware::rateLimit()` before controller logic. Hashes client IP with SHA-256, upserts the `rate_limits` row atomically, returns 429 if threshold exceeded.

**Algorithm — fixed window counter:** single `INSERT … ON DUPLICATE KEY UPDATE` with an `IF(TIMESTAMPDIFF(SECOND, window_start, NOW()) >= :window_seconds, …)` inline reset. No read-then-write race condition.

`X-Forwarded-For` is only trusted when `APP_ENV=production`. In local dev `REMOTE_ADDR` is always used. This applies in both `RateLimiter` and `Request::ip()`.

```php
// Rate-limit only
$this->middleware->rateLimit('auth.login', maxRequests: 10, windowSeconds: 60);

// Auth + rate-limit
$this->middleware->requireAuthAndRateLimit('posts.export', maxRequests: 20, windowSeconds: 60);
```

---

## REST API

All routes under `/api/v1/`. Auth enforced by `$_SESSION['user_id']` in `Middleware::requireAuth()`.

### Auth

| Method | Endpoint | Auth | Notes |
|--------|----------|------|-------|
| POST | `/auth/register` | No | Creates user, starts session, sets cookie |
| POST | `/auth/login` | No | `password_verify()`, regenerates session ID |
| POST | `/auth/logout` | Yes | `session_destroy()`, expires cookie |
| GET | `/auth/me` | Yes | Returns own user record |

### Users

| Method | Endpoint | Auth | Notes |
|--------|----------|------|-------|
| GET | `/users/{username}` | No | Public profile metadata |
| GET | `/users/{username}/posts` | No | Cursor-paginated: `?cursor={last_post_id}` |
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
| GET | `/pipelines/{id}` | No | Pipeline with steps — sub-pipelines recursively flattened to atomic steps |
| POST | `/pipelines` | Yes | Create new pipeline |
| PUT | `/pipelines/{id}/steps` | Yes | Replace all steps — body must be `{ "steps": [...] }` |
| DELETE | `/pipelines/{id}` | Yes | Delete pipeline |
| POST | `/pipelines/{id}/apply` | Yes | Accepts `post_id`, saves result to `storage/processed/`, returns `processed_path` |
| POST | `/pipelines/{id}/preview` | Yes | Stateless: accepts `image_b64`, returns `image_b64`. Nothing persisted. |

### Feed

| Method | Endpoint | Auth | Notes |
|--------|----------|------|-------|
| GET | `/feed` | Yes | Posts from followed users, cursor-based: `?cursor={last_post_id}` |

---

## How the Filter Pipeline Works

### Atomic filters

Single-operation transforms. Each implemented twice — PHP/GD for server-side export, Canvas API/JS for live client-side preview. Accept typed parameters via `params_schema`.

The 10 built-in atomic filters:

| ID | Name | Params |
|----|------|--------|
| 1 | Gaussian Blur | `intensity` (1–50) |
| 2 | Grayscale | — |
| 3 | Sepia | — |
| 4 | Brightness | `level` (−255–255) |
| 5 | Contrast | `level` (−255–255) |
| 6 | Saturation | `level` (−100–0) |
| 7 | Hue Rotate | `angle` (0–360) |
| 8 | Vignette | — |
| 9 | Digital Noise | `intensity` (1–100) |
| 10 | Edge Detect | — |

### Sub-pipeline flattening

`GET /pipelines/{id}` resolves all `sub_pipeline_id` references recursively and returns a flat list of atomic steps. `filter_id` is always set, `sub_pipeline_id` is always `null` in the response. This allows `pipeline.js` to run live preview entirely client-side with no server round-trips per frame.

Flattening is performed by `PipelineController` (read-time). The hard depth limit of 5 levels is enforced by `PipelineRunner` at write time. Stored `pipeline_steps` rows are unchanged.

### apply vs preview

| | `POST /pipelines/{id}/apply` | `POST /pipelines/{id}/preview` |
|---|---|---|
| Input | `post_id` (int) | `image_b64` (string) |
| Source file | `storage/originals/{uuid}` | Temporary file in `sys_get_temp_dir()` |
| Output | Saved to `storage/processed/` | Returned as `image_b64`, nothing saved |
| Use case | Publish / export button | Editor live preview |

### Cycle detection

Before saving a pipeline that references a sub-pipeline, a DFS runs on the reference graph. Cycle detected → 422 rejected.

---

## File Storage

Files stored outside the web root, served through PHP via `readfile()`. Direct file URLs never work.

```
storage/
  originals/    raw uploaded files (used for pipeline export only)
  thumbs/       800px JPEG previews — primary display format for feed, profile, editor
  processed/    exported pipeline results
  avatars/      profile pictures
```

Filenames are UUID4 values from `random_bytes(16)`. User-supplied filenames are never used.

In Docker, the entire `storage/` directory is mounted as the `storage_data` named volume so files persist across container replacements.

---

## Key Hand-Written Pieces

- **Session handler** — implements `SessionHandlerInterface`, reads/writes `sessions` table via PDO. `expires_at` computed in SQL.
- **Router** — reads `REQUEST_URI`, strips query string, matches regex patterns with named segments. Returns after dispatch to prevent fallthrough.
- **Validator** — rules array (`required`, `email`, `min_length`, `max_length`, `integer`, `min`, `max`). One error per field. Unknown rules throw `\InvalidArgumentException`.
- **Rate limiter** — SHA-256 hashes client IP, upserts `rate_limits` atomically, 429 on breach. `X-Forwarded-For` only trusted in production.
- **Thumbnail generation** — `imagecreatefrom*()`, `imagescale()` to 800px long side, `imagejpeg()`.
- **Pixel loops** — saturation, hue rotation, sepia, vignette, noise use `imagecolorat()` / `imagesetpixel()`.
- **`Request::body()`** — result cached in `$bodyCache` to prevent double-reads of `php://input`.
