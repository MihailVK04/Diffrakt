# Diffrakt — XAMPP Local Setup Guide

> **Goal:** Drop the project folder into XAMPP's `htdocs/`, run it, rename the folder freely — zero config changes needed anywhere outside the project, and zero changes needed after a rename.

---

## How It Works (Big Picture)

The app uses a **self-contained, fully dynamic base-path detection** strategy:

- `public/shell.php` replaces `public/index.html` as the SPA shell. It uses PHP to detect the real folder path at runtime and injects it into a `<base>` tag.
- `public/assets/js/app.js` and `api.js` read that `<base>` tag dynamically — they never hardcode a path.
- `.htaccess` rules are written with relative patterns, not folder names.
- `StorageService.php` derives the storage path from its own location on disk using `__DIR__` — no absolute path in `.env` needed.
- The folder can be named anything (`diffrakt/`, `my-app/`, `project123/`) and nothing breaks. After a rename, zero files need changing.

---

## Prerequisites

| Requirement | Version |
|---|---|
| XAMPP | Any recent version |
| PHP | **8.2** (check in XAMPP Control Panel → Apache → config) |
| MySQL | **8.0** |
| Apache module | `mod_rewrite` enabled (on by default in XAMPP) |

To verify your PHP version, visit `http://localhost/dashboard/phpinfo.php` after starting Apache.

---

## Step 1 — Place the Project

Put the project folder inside XAMPP's web root:

```
C:\xampp\htdocs\diffrakt\
```

The folder can be named anything you like. This guide uses `diffrakt/` as the example. The app will be reachable at:

```
http://localhost/diffrakt/
```

---

## Step 2 — Create the `.env` File

Copy `.env.example` to `.env` inside the project root and fill it in:

```env
APP_ENV=local
APP_ORIGIN=http://localhost

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=diffrakt
DB_USER=root
DB_PASS=

SESSION_LIFETIME=7200
SESSION_COOKIE_NAME=diffrakt_sid
```

**Notes:**
- `DB_PASS` is blank by default in XAMPP (root has no password). Fill it in if you set one.
- `STORAGE_PATH` is **not needed** — the storage path is derived automatically from the project's location on disk (see Step 5g). Do not add it to `.env` or `.env.example`.
- `APP_ORIGIN` stays as `http://localhost` regardless of the folder name — it is the domain only, not the subfolder.

---

## Step 3 — Set Up the Database

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin` in your browser.
3. Click **New** in the left sidebar, name the database `diffrakt`, set collation to `utf8mb4_unicode_ci`, and click **Create**.
4. Select the `diffrakt` database, go to the **Import** tab.
5. Import `database/schema.sql` — creates all tables (`users`, `sessions`, `posts`, `filters`, `pipelines`, `pipeline_steps`, `follows`, `rate_limits`).
6. Import `database/seeds/filters.sql` — populates the built-in atomic filters.

---

## Step 4 — Create the Storage Folders

The `storage/` directory lives outside the web root and must have all four subfolders present:

```
diffrakt/
  storage/
    originals/
    thumbs/
    processed/
    avatars/
```

Create any missing subfolders manually. On Windows with XAMPP, Apache runs under your user account so write permissions are usually automatic. If you hit upload errors later, right-click `storage/` → Properties → Security → grant **Full Control** to your user and `SYSTEM`.

---

## Step 5 — File Changes Inside the Project

These are the only modifications needed to the project files. Everything is self-contained.

---

### 5a — Delete `public/index.html`

The static `index.html` is replaced by `public/shell.php` (see 5b). Remove it so `.htaccess` doesn't accidentally serve it as a real file.

---

### 5b — Create `public/shell.php` (replaces `index.html`)

This is the SPA shell. PHP detects the real subfolder path at runtime and injects it into the `<base>` tag. The JS side reads that tag — so renaming the folder requires zero further changes.

```php
<?php
// Dynamically resolve the base path from the real URL.
// dirname(__FILE__) gives us the absolute disk path to public/.
// We need the URL-relative path instead, which SCRIPT_NAME provides.
// Example: if the folder is "diffrakt", SCRIPT_NAME is "/diffrakt/shell.php"
// and $base becomes "/diffrakt/".
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diffrakt</title>
    <base href="<?= htmlspecialchars($base) ?>">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div id="app"></div>
    <script type="module" src="assets/js/app.js"></script>
</body>
</html>
```

---

### 5c — Update `public/.htaccess`

Replace the existing `.htaccess` content with:

```apache
RewriteEngine On

# Let real files (assets, images) pass through untouched
RewriteCond %{REQUEST_FILENAME} !-f

# API requests → PHP front controller
RewriteRule ^api/ index.php [QSA,L]

# Everything else → SPA shell (PHP, so base path is dynamic)
RewriteRule ^(?!api/) shell.php [L]
```

**What changed from the original:** the fallback rule now points to `shell.php` instead of `index.html`. Everything else is identical to the spec.

---

### 5d — Add a root-level `.htaccess` (new file: `diffrakt/.htaccess`)

This sits one level above `public/` and transparently forwards all traffic into `public/`, making `http://localhost/diffrakt/` work as the app root without a virtual host.

```apache
RewriteEngine On

# Forward everything into public/ without changing the URL
RewriteRule ^$ public/shell.php [L]
RewriteRule ^(assets/.*)$ public/$1 [L]
RewriteRule ^api/(.*)$ public/index.php [QSA,L]
RewriteRule ^(.*)$ public/shell.php [L]
```

---

### 5e — Update `public/assets/js/api.js`

Add base-path detection at the top of the file. The `_request` function must prepend `BASE` to every API path:

```js
// Read the base path that shell.php injected at runtime.
// e.g. "/diffrakt" — no trailing slash.
// Falls back to "" if somehow missing (virtual host at root).
const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');

async function _request(method, path, body = null, opts = {}) {
    const init = {
        method,
        headers: {},
        credentials: 'include',   // required — sends session cookie on every call
        signal: opts.signal ?? null,
    };

    if (body !== null) {
        if (body instanceof FormData) {
            init.body = body;     // let the browser set Content-Type with boundary
        } else {
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(body);
        }
    }

    // path always starts with /api/v1/...
    // BASE is e.g. /diffrakt — so the final URL is /diffrakt/api/v1/...
    const res = await fetch(`${BASE}${path}`, init);
    // ... rest of your existing response handling
}
```

> **Important:** only the `BASE` constant and the `fetch()` call line change. All your existing response-handling logic stays untouched.

---

### 5f — Update `public/assets/js/app.js`

Add base-path detection and use it in the router. Three things need updating:

```js
// Same base detection as api.js
const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');

// When reading the current route, strip the base prefix first
function getPath() {
    return window.location.pathname.replace(BASE, '') || '/';
}

// When navigating programmatically, prepend the base
function navigate(path) {
    history.pushState({}, '', BASE + path);
    renderRoute();
}

// popstate (browser back/forward) — use getPath() instead of window.location.pathname
window.addEventListener('popstate', () => renderRoute());

// Intercept <a data-link> clicks
document.addEventListener('click', e => {
    const a = e.target.closest('a[data-link]');
    if (!a) return;
    e.preventDefault();
    navigate(a.getAttribute('href')); // href is the app path e.g. /feed
});

// renderRoute uses getPath() internally
function renderRoute() {
    const path = getPath();
    // ... rest of your existing route matching logic
}
```

> **Important:** `window.app.navigate(path)` must also call the local `navigate()` above so the base is always prepended correctly.

---

### 5g — Update `src/Services/StorageService.php`

Remove any reference to `STORAGE_PATH` from the environment. Instead, derive the path from the file's own location on disk using `__DIR__`. This works regardless of the folder name, XAMPP install location, or operating system — PHP resolves the directory separator correctly everywhere.

```php
class StorageService
{
    // Derives the absolute path to storage/ from this file's location on disk.
    // src/Services/StorageService.php → two levels up → project root → storage/
    // Works on Windows and Linux. No env variable needed.
    private string $storageRoot;

    public function __construct()
    {
        $this->storageRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage';
    }

    public function path(string $subfolder, string $filename): string
    {
        return $this->storageRoot
            . DIRECTORY_SEPARATOR . $subfolder
            . DIRECTORY_SEPARATOR . $filename;
    }
}
```

`dirname(__DIR__, 2)` walks up two directory levels from the file's own directory:

```
src/Services/StorageService.php
        ↑ __DIR__          = .../src/Services
        ↑ dirname(..., 1)  = .../src
        ↑ dirname(..., 2)  = project root
                                    + /storage = .../storage
```

> **Also remove** `STORAGE_PATH` from `.env.example` so no one accidentally adds it to their `.env` thinking it is required.

---

## Step 6 — Verify `mod_rewrite` is Enabled

Open `C:\xampp\apache\conf\httpd.conf` and confirm this line is present and **not commented out**:

```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

Also confirm that `AllowOverride` is set to `All` for the `htdocs` directory. Find the block for your htdocs path and check:

```apache
<Directory "C:/xampp/htdocs">
    AllowOverride All
    ...
</Directory>
```

If you change either of these, restart Apache from the XAMPP Control Panel.

---

## Step 7 — Start and Visit the App

1. Start **Apache** and **MySQL** in XAMPP Control Panel.
2. Visit `http://localhost/diffrakt/` in your browser.
3. You should see the Diffrakt landing page with login/register forms.

---

## Renaming the Folder

Rename `diffrakt/` to anything — `myapp/`, `photo-editor/`, `project/`. Then visit `http://localhost/<new-name>/`.

That's it. **Zero file changes needed.** Every moving part adapts automatically:

- The `<base>` tag is generated by `shell.php` from the real URL at request time.
- `api.js` and `app.js` read the `<base>` tag at runtime — no hardcoded paths.
- `StorageService.php` derives the storage path from `__DIR__` — no env variable involved.
- `.htaccess` rules use relative patterns — no folder name in them.

---

## File Summary — What Changed vs the Original Spec

| File | Status | What changed |
|---|---|---|
| `public/index.html` | **Deleted** | Replaced by `shell.php` |
| `public/shell.php` | **New** | SPA shell with dynamic `<base>` tag |
| `public/.htaccess` | **Modified** | Fallback rule points to `shell.php` instead of `index.html` |
| `diffrakt/.htaccess` | **New** | Forwards traffic into `public/` without a virtual host |
| `public/assets/js/api.js` | **Modified** | Reads `<base>` tag, prepends to all fetch paths |
| `public/assets/js/app.js` | **Modified** | Reads `<base>` tag, uses it in router push/pop/match |
| `src/Services/StorageService.php` | **Modified** | Storage path derived from `__DIR__`, no env variable |
| `.env` / `.env.example` | **Modified** | `APP_ORIGIN=http://localhost`, `STORAGE_PATH` removed |
| Everything else | **Unchanged** | All other PHP source, models, controllers untouched |

---

## Troubleshooting

| Symptom | Likely Cause | Fix |
|---|---|---|
| XAMPP dashboard shows instead of app | Root `.htaccess` not picked up | Check `AllowOverride All` in `httpd.conf` for htdocs |
| API calls return 404 | `mod_rewrite` not enabled | Uncomment `LoadModule rewrite_module` in `httpd.conf` |
| Blank white page | JS module error | Open DevTools (F12) → Console and read the error |
| `/feed` loads but then goes blank on refresh | `shell.php` not catching all routes | Check root `.htaccess` catch-all rule |
| Session not persisting between requests | `credentials: 'include'` missing in fetch | Verify `api.js` has `credentials: 'include'` on every call |
| File uploads fail | `storage/` subfolders missing or `StorageService` path wrong | Create `originals/`, `thumbs/`, `processed/`, `avatars/`; verify `dirname(__DIR__, 2)` in `StorageService` points to project root |
| Database errors on boot | Schema not imported | Re-import `schema.sql` then `seeds/filters.sql` |

---

## Quick Checklist

- [ ] Project folder placed in `C:\xampp\htdocs\`
- [ ] `.env` created from `.env.example` with correct values (`STORAGE_PATH` not included)
- [ ] `diffrakt` database created in phpMyAdmin (utf8mb4_unicode_ci)
- [ ] `database/schema.sql` imported
- [ ] `database/seeds/filters.sql` imported
- [ ] `storage/originals/`, `thumbs/`, `processed/`, `avatars/` folders exist
- [ ] `public/index.html` deleted
- [ ] `public/shell.php` created with dynamic `<base>` tag
- [ ] `public/.htaccess` updated to point to `shell.php`
- [ ] `diffrakt/.htaccess` created (root-level forwarding rules)
- [ ] `api.js` updated with `BASE` constant and prepended fetch paths
- [ ] `app.js` updated with `BASE` constant and `getPath()` / `navigate()` functions
- [ ] `StorageService.php` updated to use `dirname(__DIR__, 2)` instead of `STORAGE_PATH`
- [ ] `STORAGE_PATH` removed from `.env.example`
- [ ] `mod_rewrite` enabled in `httpd.conf`
- [ ] `AllowOverride All` set for htdocs in `httpd.conf`
- [ ] Apache and MySQL started
- [ ] `http://localhost/<folder-name>/` loads the landing page
