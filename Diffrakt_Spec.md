# Diffrakt — Project Spec

**Stack:** Vanilla PHP 8.2 · MySQL 8 · HTML5 · CSS3 · Vanilla JS (ES2022)
**Rule:** Zero external dependencies. No Composer packages, no npm, no CDN links.

---

## What It Does

Users upload photos, apply filters, chain those filters into pipelines, and save the result. Two social features exist: a follow system and a feed. Everything else is about editing.

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
