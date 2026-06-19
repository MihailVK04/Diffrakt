# Diffrakt — Feed Scope Toggle

Adds a button on the feed page that switches between "Following only" (existing
behaviour) and "Following + Me" (your own published posts merged in alongside
followees', newest-first, same cursor pagination). Implemented as a query-param
toggle on the existing `GET /api/v1/feed` endpoint — no new routes, no
`Bootstrap.php` changes.

---

## 1. Behaviour

- Default scope on page load is **`following`** — identical to current behaviour.
- Toggling to **`all`** shows: your own posts (if `is_published = 1`) **and**
  posts from anyone you follow (also `is_published = 1`). Your own unpublished
  drafts are never shown in either scope.
- Switching scope **resets pagination**: cursor back to `null`, post list
  cleared, infinite-scroll re-armed. It is **not persisted** — reloading
  `/feed` always returns to `following`.
- Switching scope while a request is in flight **aborts** that request, so a
  slow "following" response can't land after the switch and append stale
  posts under the "all" list.

---

## 2. Files changed

| File | What changed |
|---|---|
| `src/Models/Post.php` | `getFeed()` gains a `$scope` param (`'following'` \| `'all'`) |
| `src/Controllers/FeedController.php` | Reads `scope` from query string; also fixes a pre-existing bug (see §4) |
| `public/assets/js/api.js` | `feed.get()` gains `scope` and `opts` params |
| `public/assets/js/views/feed.js` | New toggle button, `_setScope()` reset logic, abort signal wired into the fetch |
| `public/assets/css/app.css` | `.feed__header` layout, `.feed__scope-toggle` styling (reuses `.btn.btn--ghost`) |

---

## 3. API

`GET /api/v1/feed`

| Query param | Values | Default | Notes |
|---|---|---|---|
| `cursor` | int (post id) | none | unchanged — older-than cursor |
| `limit` | int | `10` | unchanged |
| `scope` | `following` \| `all` | `following` | new — anything other than exactly `all` falls back to `following` |

No new endpoint was added; this was a deliberate choice over a separate
`/feed/all` route, to keep one cursor/pagination code path on both frontend
and backend.

### SQL — `Post::getFeed()`

**`scope = 'following'`** (unchanged):
```sql
SELECT p.*, u.username, u.avatar_path
FROM posts p
JOIN users u ON p.user_id = u.id
JOIN follows f ON f.followee_id = p.user_id
WHERE f.follower_id = ? AND p.is_published = 1
  [AND p.id < ?]
ORDER BY p.id DESC
LIMIT ?
```

**`scope = 'all'`** (new):
```sql
SELECT p.*, u.username, u.avatar_path
FROM posts p
JOIN users u ON p.user_id = u.id
LEFT JOIN follows f ON f.followee_id = p.user_id AND f.follower_id = ?
WHERE (p.user_id = ? OR f.follower_id IS NOT NULL) AND p.is_published = 1
  [AND p.id < ?]
ORDER BY p.id DESC
LIMIT ?
```

The `'all'` branch uses a **`LEFT JOIN`**, not `INNER JOIN`, specifically so
the user's own posts still appear even though nobody follows themselves —
an inner join would silently exclude them.

---

## 4. Bug fixed as part of this change

`FeedController::index()` was reading `cursor` and `limit` via
`$request->input()`, which only reads `$_POST` / JSON body — never `$_GET`.
Since `/feed` is a GET-only endpoint, this means **cursor-based pagination on
the feed was already silently broken** before this change (every request
returned page one, regardless of `?cursor=`). This is the same bug class
already documented and fixed in the chat feature (Spec Snapshot 009, §2.1).

Fixed by switching `cursor`, `limit`, and the new `scope` param to
`$request->query()`, which correctly reads `$_GET`.

---

## 5. Frontend notes

- `_setScope()` in `feed.js` is the single reset point: aborts any in-flight
  request, clears `_cursor`/`_hasMore`/the rendered list, re-arms the
  `IntersectionObserver` if a previous "exhausted feed" state had disconnected
  it, updates the button label and `aria-pressed`, then calls `_loadPage()`.
- `_loadPage()`'s `AbortController` signal is now actually passed into
  `api.feed.get()` — previously the controller was created but its signal was
  never attached to the underlying `fetch()`, so aborting did nothing. Fixed
  as part of wiring up scope-switch cancellation, since an un-cancelled
  request was the main risk for stale-post bugs when switching scopes quickly.
- Button reuses the existing `.btn.btn--ghost` style rather than introducing
  a new button variant. `aria-pressed="true"` (scope = `all`) gets a filled
  navy treatment via `--color-navy` / `--color-text-on-dark`, no new colors
  added to the palette.

---

## 6. Known minor issues (not blocking)

- The empty-state message ("Nothing here yet — follow some users…") is
  generic and slightly inaccurate when scope is `all` and the user has no
  posts and follows nobody. Cosmetic; not fixed in this change.
- `.feed__header` uses `justify-content: space-between` with no wrap/media
  query. On very narrow viewports the title + "Show: Following + Me" label
  could get tight. No existing breakpoint convention was available to match
  against, so this was left as-is pending a look at the rest of `app.css`'s
  responsive rules.
- In `_setScope()`, if the `IntersectionObserver` is still active (feed wasn't
  exhausted before switching), `observe()` is called again on the same
  sentinel it's already observing. This is a harmless no-op per spec, not a
  leak — just redundant.

---

## 7. Manual test checklist

- [ ] Default load shows following-only feed, button reads "Show: Following only"
- [ ] Click toggle → list clears, "all" posts load (own + followees', published only)
- [ ] Click toggle again → reverts to following-only, list resets again
- [ ] Scroll to bottom in "following" (exhausts feed, observer disconnects) →
      switch to "all" → infinite scroll still works (observer re-armed)
- [ ] Rapidly toggle scope mid-scroll → no duplicate or stale posts appear
- [ ] Reload page → always resets to "following" (no persistence)
- [ ] Own unpublished/draft posts never appear in either scope
