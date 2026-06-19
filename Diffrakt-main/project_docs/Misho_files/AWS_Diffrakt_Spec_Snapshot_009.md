# AWS_Diffrakt — Spec Snapshot 009

Covers: the Chat feature (real-time messaging via short polling) and the Dockerized deployment setup, including all bugs found and fixed while bringing the project up in containers.

---

## 1. Chat Feature

Added via `003_chat.sql` concept — but the `conversations` and `messages` tables are actually defined directly in `database/schema.sql` (no separate migration file needed).

### Rules

- A conversation can only be opened between two users who **mutually follow each other**. Enforced in `ChatController::createConversation()` and `Conversation::isMutualFollow()`.
- A user cannot open a conversation with themselves.
- `POST /chat/conversations` is idempotent — if a conversation between the two users already exists, it returns the existing one (`200`) instead of creating a duplicate (`201`).

### Database

**`conversations`**
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | |
| `user_a_id` | INT FK → users | Always the smaller of the two user IDs |
| `user_b_id` | INT FK → users | Always the larger |
| `created_at` | DATETIME | |

`CHECK (user_a_id < user_b_id)` + `UNIQUE KEY (user_a_id, user_b_id)` prevent duplicate pairs. All code must normalize the pair via `Conversation::normalisePair()` before querying/inserting.

**`messages`**
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | Used as cursor |
| `conversation_id` | INT FK → conversations | Indexed |
| `sender_id` | INT FK → users | |
| `body` | TEXT | Max 2000 chars enforced at controller level |
| `created_at` | DATETIME | |

### API

All endpoints require authentication (`$_SESSION['user_id']`).

| Method | Endpoint | Rate limit | Description |
|---|---|---|---|
| GET | `/api/v1/chat/conversations` | — | List all conversations for the current user, ordered by most recent message |
| POST | `/api/v1/chat/conversations` | 10 req/min | Open a conversation. Body: `{ "username": "..." }` |
| GET | `/api/v1/chat/conversations/{id}/messages` | — | Fetch messages, see query params below |
| POST | `/api/v1/chat/conversations/{id}/messages` | 30 req/min | Send a message. Body: `{ "body": "..." }` |

**`GET /messages` query params**

| Param | Behaviour |
|---|---|
| _(none)_ | Most recent 30 messages, newest-first, then reversed to chronological |
| `?cursor={id}` | 30 messages older than `{id}` — infinite scroll upward |
| `?after={id}` | All messages newer than `{id}`, oldest-first — used by the 3s poll |

`cursor` and `after` are mutually exclusive; `after` takes priority if both present.

### Files

| File | Location |
|---|---|
| `Conversation.php` | `src/Models/` |
| `Message.php` | `src/Models/` |
| `ChatController.php` | `src/Controllers/` |
| `chat.js` | `public/assets/js/views/` |
| `chat.css` | Appended to `public/assets/css/app.css` |

### Frontend

**Route:** `/chat` and `/chat/:convId` → `ChatView` in `app.js`. Requires auth.

**Polling:** Every 3s, `GET /messages?after={lastMessageId}`. Cleared on `destroy()` and when switching conversations.

**Scroll behaviour:** First load scrolls to bottom. Infinite scroll up via `IntersectionObserver` on a top sentinel, with scroll position restored by diffing `scrollHeight`. Auto-scroll on new messages only if user was within 40px of bottom.

**Sending:** Optimistic append on `201`, `_lastMessageId` updated immediately so the next poll doesn't re-fetch it.

**New conversation flow:** `+` button opens a search panel (`GET /users/search`). Selecting a user calls `POST /chat/conversations`; a `403` (mutual-follow check failed) is shown inline.

### Key invariants

- `user_a_id < user_b_id` always true; `Conversation::normalisePair()` is the single enforcement point.
- `_lastMessageId` starts at `0`; first poll requests `?after=0`, but `_loadHistoryPage()` sets it to the newest loaded message ID before the first poll fires.
- Only one active poll interval per view instance.

---

## 2. Bugs Found & Fixed During Chat Implementation

### 2.1 Polling returned duplicate messages forever (`?after=1` stuck)

**Cause:** `Request::input()` reads from `$this->body()`, which for GET requests is `$_POST ?? []` — always empty. So `ChatController::getMessages()`'s `$request->input('after')` was always `null`, falling into the `cursor`/`getPage` branch every time, which kept returning the latest page regardless of what was already shown.

**Fix:** Use `$request->query('after')` / `$request->query('cursor')` (reads `$_GET`) instead of `input()`.

### 2.2 Last messages duplicated after history load

**Cause:** `_prependMessages()` unshifts messages from the server's ascending-order array one at a time, which reverses their order in `this._messages`. `_loadHistoryPage()` then computed `_lastMessageId` from `this._messages[this._messages.length - 1]` — now the *oldest* message, not the newest. The first poll then re-fetched (and re-appended) messages already shown from history.

**Fix:** In `_loadHistoryPage()`, compute `_lastMessageId` from the original server array: `msgs[msgs.length - 1].id`, not from `this._messages`.

---

## 3. Dockerized Deployment (AWS_Diffrakt)

This variant of the project (`AWS_Diffrakt`) runs via Docker Compose instead of XAMPP, to ensure consistent environments across machines and prep for AWS deployment.

### Core concepts

- **Image** — read-only blueprint built from a `Dockerfile` (e.g. `diffrakt-app:latest`, `mysql:8`).
- **Container** — a running instance of an image. Isolated; the PHP container can't talk to MySQL unless explicitly connected (Compose handles this).
- **Volume** — persistent storage surviving container stop/remove/rebuild.
  - `db_data` → MySQL data files. Without it, `docker compose down` wipes the database.
  - `storage_data` → uploads, thumbnails, processed exports, avatars.

### Dockerfile (`app` image)

- Base: `php:8.2-fpm`.
- Installs `nginx`, `supervisor`, GD build deps, then `pdo`, `pdo_mysql`, `gd` PHP extensions.
- Copies `deploy/nginx.conf`, `deploy/php-fpm.conf`, `deploy/supervisord.conf` into place.
- Copies `public/` and `src/` into the image (overridden by bind mounts in local dev).
- Creates `storage/{originals,thumbs,processed,avatars}` owned by `www-data`.
- Creates `/run/php` (PHP-FPM socket dir) owned by `www-data` — without this, php-fpm exits with code 78.
- `EXPOSE 80`; `CMD` runs `supervisord`, which manages `nginx` + `php-fpm` as child processes (a container runs one process; supervisord is that process and restarts its children if they crash).

### docker-compose.yml (local dev)

```yaml
services:
  app:
    build: .
    ports:
      - "8080:80"
    env_file: .env
    volumes:
      - storage_data:/var/www/diffrakt/storage
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
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-p$$MYSQL_ROOT_PASSWORD"]
      interval: 5s
      timeout: 5s
      retries: 10
```

`docker-entrypoint-initdb.d/` runs every `.sql` file alphabetically, **only when the volume is empty** (first boot). Numbered prefixes control order.

`./public` and `./src` bind mounts mean local edits are picked up immediately — no rebuild needed for code changes. Rebuild (`docker compose up --build`) only required when `Dockerfile` or `deploy/*.conf` change.

### docker-compose.prod.yml differences

| | Local | Production |
|---|---|---|
| Image source | Built from local Dockerfile | Pulled from GitLab Container Registry |
| Source code | Bind-mounted (live edits) | Baked into image at build time |
| Port | 8080 | 80 |
| APP_ENV | `local` | `production` |
| Secrets | `.env` on disk | CI/CD protected variables |

### Windows quirk

`localhost:8080` sometimes doesn't resolve correctly via Docker Desktop's network bridge on Windows. Use `http://127.0.0.1:8080` instead — always works, and is fine on Linux/Mac too.

---

## 4. Schema Drift Bugs Found While Containerizing

Bringing the project up fresh in Docker (`docker compose down -v && up --build`) exposed several mismatches between `schema.sql`, the old `001_followee_id.sql` migration, the seed file, and the actual model/filter code — none of which had surfaced under XAMPP because that environment's DB had accumulated ad-hoc fixes over time.

### 4.1 Feed page 500 — `Unknown column 'p.is_published'`

`Post::getFeed()` queries `p.is_published`, `Post::publish()` sets it, but `posts` in `schema.sql` never had this column.

It turned out `001_followee_id.sql` *did* contain `ALTER TABLE posts ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0;` — but that migration's **first** statement was:

```sql
ALTER TABLE follows RENAME COLUMN followed_id TO followee_id;
```

`schema.sql` already defines `follows.followee_id` directly (i.e. `schema.sql` already reflects this migration's *other* changes — `follows.followee_id`, `filters.is_public`, `filters.pipeline_id`, `fk_filters_pipeline` were all already present). So on a fresh DB this `RENAME COLUMN` fails (`followed_id` doesn't exist), aborting the rest of the script — including the `is_published` addition.

**Fix:**
- Added `is_published BOOLEAN NOT NULL DEFAULT 0` directly to the `posts` table in `schema.sql`.
- Removed the `001_followee_id.sql` mount from `docker-entrypoint-initdb.d/` (and the file itself, since `schema.sql` already supersedes everything else it did).

### 4.2 Saturation filter `params_schema` was wrong in the seed (and in the migration)

`001_followee_id.sql` also contained:

```sql
UPDATE filters SET params_schema = '{"level": {"type": "float", "min": 0, "max": 3}}' WHERE name = 'saturation';
```

— a data fix, not a schema change, for the `Saturation` row in `database/seeds/filters.sql`. The seed's original value was:

```json
{"value": {"type": "float", "min": 0.0, "max": 3.0, "default": 1.0}}
```

Both versions were wrong: `SaturationFilter.php` and `canvas.js` (the live-preview implementation) both read `params.level`, clamp it to **-100..0**, with **default -50** (0 = no change, -100 = fully grayscale).

**Fix:** seed's `Saturation` row corrected directly to:

```sql
(6, 'Saturation', 'atomic', NULL, 1, '{"level": {"type": "float", "min": -100, "max": 0, "default": -50}}'),
```

This makes the seed self-sufficient and correct without needing any post-seed `UPDATE`.

### 4.3 `shell.php` in `public/` — false alarm

A stray `public/shell.php`, byte-identical to `public/index.php` (SPA shell template), was found alongside `index.php` and showed up in nginx access logs returning `200`. Initially flagged as a possible webshell/compromise, but confirmed via content inspection to be a harmless duplicate (likely copy-pasted during local Docker debugging). Removed; no security incident — `storage_data` volume and `src/` were both clean of any injected files.

---

## 5. Resulting docker-compose.yml `db` volumes (final)

```yaml
  db:
    image: mysql:8
    volumes:
      - db_data:/var/lib/mysql
      - ./database/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql
      - ./database/seeds/filters.sql:/docker-entrypoint-initdb.d/03-seeds.sql
```

(`001_followee_id.sql` mount removed entirely — `schema.sql` and the corrected seed are now the complete, self-sufficient source of truth for a fresh database.)

---

## 6. Outstanding / Notes

- `PostController::upload` accepts a `visibility` param (per `api.js`) but `Post::create()` doesn't persist it — `posts` has no `visibility` column. Not currently causing errors (feed filters on `is_published` only), but worth reviewing if visibility-based filtering is intended.
- Debug `display_errors`/`error_reporting` overrides added to `/usr/local/etc/php/conf.d/zz-debug.ini` during troubleshooting — remove or gate behind `APP_ENV=local` before any prod-like deployment.
- Password used during curl-based auth testing was pasted in plaintext in chat — recommend rotating it.
