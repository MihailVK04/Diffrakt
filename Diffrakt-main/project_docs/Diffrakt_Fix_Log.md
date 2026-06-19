# Diffrakt — Core Debug Fix Log

Pre-run audit of `src/Core/` and `src/Bootstrap.php`.
Fixes applied before first run, ranked by severity.

---

## CRITICAL

### 1. `Router.php` — controller method called as literal `action`
**File:** `src/Core/Router.php`
**Problem:** `$controller->action($this->request)` called a method literally named
`action` on every controller instead of the actual registered method name.
Every single API route would fatal with `Call to undefined method`.
**Fix:** Changed to `$controller->$action($this->request)`.

---

### 2. `Router.php` — `requireAuth()` called statically on an instance class
**File:** `src/Core/Router.php`
**Problem:** `Middleware::requireAuth($this->request)` — static call on a non-static
method. Fatals on every protected route with
`Non-static method cannot be called statically`.
**Fix:** `Middleware` is now instantiated inside `Router`'s constructor and called
as `$this->middleware->requireAuth()`.

---

### 3. `Bootstrap.php` — `Database::getInstance()` return value discarded
**File:** `src/Bootstrap.php`
**Problem:** `Database::getInstance()` was called without capturing the return value,
leaving `$pdo` undefined. The next line `new RateLimiter($pdo)` would fatal
with `Undefined variable $pdo`.
**Fix:** `$pdo = Database::getInstance()`.

---

### 4. `Bootstrap.php` — missing semicolon on `RateLimiter` instantiation
**File:** `src/Bootstrap.php`
**Problem:** `$rateLimiter = new RateLimiter($pdo)` was missing a terminating `;`.
PHP parse error — nothing runs at all.
**Fix:** Semicolon added.

---

## HIGH

### 5. `Router.php` — `Middleware` property initialised to `null` with typed declaration
**File:** `src/Core/Router.php`
**Problem:** `private Middleware $middleware = null` — typed properties cannot have
`null` as a default unless declared nullable (`?Middleware`). Fatal on class load.
**Fix:** Removed the `= null` default. Constructor assigns it immediately so no
default is needed.

### 6. `Bootstrap.php` — duplicate route registration
**File:** `src/Bootstrap.php`
**Problem:** `GET /api/v1/users/{username}/posts` was registered twice — once pointing
to `UserController::posts` and once to `UserController::getPosts`. Router is FIFO
so `getPosts` would never be reached.
**Fix:** Removed the duplicate `getPosts` line. `posts` is the correct method name
per the spec.

### 7. `Bootstrap.php` — trailing slash on filters create route
**File:** `src/Bootstrap.php`
**Problem:** `POST /api/v1/filters/` registered with a trailing slash. `Request::uri()`
strips trailing slashes, so the incoming URI becomes `/api/v1/filters` and never
matches the registered pattern.
**Fix:** Removed trailing slash → `POST /api/v1/filters`.

### 8. `Bootstrap.php` — missing `use` for `RateLimiter`, stale `use` for `Middleware`
**File:** `src/Bootstrap.php`
**Problem:** `RateLimiter` was used without a `use` statement (fatal autoload miss).
`Middleware` was imported but no longer referenced directly in Bootstrap.
**Fix:** Added `use Diffrakt\Core\RateLimiter`. Removed `use Diffrakt\Core\Middleware`.

---

## MEDIUM

### 9. `Response.php` — bare constant typo in `detectMime()`
**File:** `src/Core/Response.php`
**Problem:** `mime !== 'application/octet-stream'` — missing `$`. PHP treats `mime`
as a bare constant, emits a warning, and the fallback `match` on file extension
never runs correctly. Files could be served with wrong MIME types.
**Fix:** Changed to `$mime !== 'application/octet-stream'`.

### 10. `Router.php` — `patternToRegex` unset group index check
**File:** `src/Core/Router.php`
**Problem:** `if ($m[1] !== '')` — when the second alternation branch matches,
`$m[1]` is unset (not an empty string). Could produce undefined index notices
or enter the wrong branch in strict PHP configs.
**Fix:** Changed to `if (!empty($m[1]))`.

### 11. `RateLimiter.php` — duplicate PDO named parameter
**File:** `src/Core/RateLimiter.php`
**Problem:** The upsert query used `:window_reset` and `:window_slide` for the same
value. Some PDO/MySQL driver combinations silently bind only the first occurrence,
making the second `IF()` always evaluate against `0` instead of `$windowSeconds`.
Window never slides → rate limit never resets.
**Fix:** Renamed to `:window_a` and `:window_b`, both bound to `$windowSeconds`.

### 12. `RateLimiter.php` — dead `create()` factory method
**File:** `src/Core/RateLimiter.php`
**Problem:** `create()` called `Database::getInstance()->getPdo()` but
`Database::getInstance()` returns a `PDO` directly — there is no `getPdo()` method.
Fatals if ever called. Never called anywhere — `Bootstrap.php` passes `$pdo`
directly.
**Fix:** Deleted the `create()` method entirely.

### 13. `RateLimiter.php` — `X-Forwarded-For` trusted in all environments
**File:** `src/Core/RateLimiter.php`
**Problem:** On local XAMPP there is no reverse proxy. Any client can send
`X-Forwarded-For: 1.2.3.4` and the rate limiter hashes the spoofed IP,
bypassing limits entirely.
**Fix:** `X-Forwarded-For` is now only trusted when `APP_ENV=production`.

---

## LOW

### 14. `Request.php` — `body()` returned `null` on malformed JSON
**File:** `src/Core/Request.php`
**Problem:** `json_decode()` failure returned `null` from `body()`. Any controller
accessing `$request->body()['field']` would fatal with
`Cannot access offset on null`.
**Fix:** Returns `[]` instead of `null` on bad or empty JSON.

### 15. `Middleware.php` — `userId()` and `username()` duplicated `Request`
**File:** `src/Core/Middleware.php`
**Problem:** `Middleware` exposed `userId()` and `username()` which are read-only
session accessors — the same responsibility as `Request`. Two sources of truth
for the same value.
**Fix:** Removed both methods from `Middleware`. Added `username()` to `Request`
alongside the existing `userId()`. Controllers use `$request->userId()` and
`$request->username()` exclusively.

### 16. `Router.php` — stale `@param bool $auth` docblock mentions JWT
**File:** `src/Core/Router.php`
**Problem:** Comment says "Whether JWT auth is required" — Diffrakt uses sessions,
not JWT. Misleading for anyone reading the code.
**Fix:** Updated to "Whether session auth is required".

---

## Summary table

| # | File | Severity | Type |
|---|------|----------|------|
| 1 | Router.php | Critical | Wrong method call |
| 2 | Router.php | Critical | Static call on instance method |
| 3 | Bootstrap.php | Critical | Undefined variable |
| 4 | Bootstrap.php | Critical | Parse error |
| 5 | Router.php | High | Typed property null default |
| 6 | Bootstrap.php | High | Duplicate route |
| 7 | Bootstrap.php | High | Trailing slash mismatch |
| 8 | Bootstrap.php | High | Missing/stale use statements |
| 9 | Response.php | Medium | Bare constant typo |
| 10 | Router.php | Medium | Undefined index in regex callback |
| 11 | RateLimiter.php | Medium | Duplicate PDO parameter |
| 12 | RateLimiter.php | Medium | Dead factory method |
| 13 | RateLimiter.php | Medium | IP spoofing in dev |
| 14 | Request.php | Low | Null body on bad JSON |
| 15 | Middleware.php | Low | Duplicate session accessors |
| 16 | Router.php | Low | Stale docblock |

