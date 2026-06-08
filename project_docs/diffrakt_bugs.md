# Diffrakt — Outstanding Bugs

## PostController.php

### Bug 1 — `upload()` response missing `thumb_url`
`editor.js` expects `this._post.thumb_url` after upload but the response returns `thumb_path` (a server filesystem path). The browser cannot fetch a filesystem path — the image load will silently fail.
- **Fix:** Return a public-facing URL as `thumb_url` in the response, using whatever URL pattern the app uses to serve files through `readfile()`.

### Bug 2 — `export()` is a stub returning 200
`editor.js` calls `api.posts.export(postId, pipelineId)` and reads `result.download_url`. The stub returns a 200 with no `download_url`, causing a silent failure in the export flow.
- **Fix:** Implement the export logic, or at minimum return 501 so the frontend gets a catchable error.

### Bug 3 — `update()` is a stub
`PATCH /posts/{id}` is wired in the route table but returns a placeholder 200.
- **Fix:** Implement or return 501.

---

## FilterController.php

### Bug 1 — `create()` ignores `pipeline_id`
`api.js` sends `{ name, pipeline_id }` but the controller only passes `name` and `owner_id` to `Filter::createComposite()`. The pipeline reference is silently dropped, creating a composite filter with no steps.
- **Fix:** Validate `pipeline_id` is present and pass it to `createComposite()`.

---

## FeedController.php

### Bug 1 — Response key mismatch
`feed.js` reads `data.posts` but the controller returns `feed`. Feed will always be empty on the frontend.
- **Fix:** Change `'feed' => $posts` to `'posts' => $posts`.

### Bug 2 — `next_cursor` always set when posts exist
When the last page is returned, `next_cursor` is set to the last post ID instead of `null`, causing one unnecessary extra request.
- **Fix:** In `Post::getFeed()`, fetch `limit + 1` rows. If count equals `limit + 1`, there are more pages — return first `limit` rows with cursor. Otherwise return `null` for `next_cursor`.

---

## CycleDetector.php

### Bug 1 — `visited` reset on every DFS root causes missed cycles across branches

`$visited` is reset to `[]` for every root node in the outer loop. This means a node that was already fully explored in a previous DFS run gets re-explored in the next one. The cycle detection itself still works correctly because `$recursionStack` catches true cycles. However the depth check is wrong — starting a fresh DFS from every node with `$depth = 1` means a 3-level chain (A→B→C→D) will never be seen as depth 4 from A's perspective if B is also used as a root and its sub-chain is only depth 3 from B. Pipelines can exceed MAX_DEPTH without being caught if the entry point isn't the root of the DFS.

- **Fix:** Build the full graph first, then run a single DFS from `$targetPipelineId` only (not from every node), tracking depth from that root. The goal is to check whether the *new* pipeline exceeds the depth limit, not whether any arbitrary node in the graph does.

```php
public static function hasCycle(int $targetPipelineId, array $newSteps): bool {
    // ... build $graph as before ...

    $visited = [];
    $recursionStack = [];
    return self::dfs($targetPipelineId, $graph, $visited, $recursionStack, 1);
}
```

---

## ImageService.php

### Note — `generateThumbnail()` uses format-specific `imagecreatefrom*` functions
If a new MIME type is added to the `$allowedMimes` whitelist without a matching `match` arm in `generateThumbnail()`, the image load silently returns `false` and throws a RuntimeException with no indication of why. Low priority since MIME types rarely change, but worth keeping in sync.
- **Fix:** Add a `default => imagecreatefromstring(file_get_contents($sourceFile))` arm to the match, as a safe fallback.

---

## Post.php (Model)

### Bug 1 — `getFeed()` returns flat columns, not nested `author` shape
`feed.js` expects `post.thumb_url`, `post.author.username`, and `post.author.avatar_url`. The query returns flat `username` and `avatar_path` columns with no nesting and no `thumb_url`. Feed cards will render broken images and crash on `post.author.username`.
- **Fix:** Map rows in `FeedController` to the expected shape, constructing `thumb_url` from the file-serving URL pattern and nesting `author` as an object.

---

## FilterInterface.php + All Filter Classes

### Bug 1 — All filters return `void` but `PipelineRunner` assigns the return value (critical)
`PipelineRunner` does `$image = $filterInstance->apply($image, $step['params'])`. Every filter returns `void`, so `$image` becomes `null` after the first filter. Every subsequent filter in the pipeline receives `null` and the entire pipeline produces nothing.
- **Fix:** Change `FilterInterface` and all 9 filter classes (`BlurFilter`, `BrightnessFilter`, `ContrastFilter`, `EdgeDetectFilter`, `GrayscaleFilter`, `HueRotateFilter`, `NoiseFilter`, `SaturationFilter`, `SepiaFilter`, `VignetteFilter`) to:
  - Remove `&` from the `$image` parameter
  - Change return type from `void` to `GdImage`
  - Add `return $image` at the end of each `apply()` method

### Bug 2 — `imagecolorallocate()` called inside pixel loops (performance)
`HueRotateFilter`, `SaturationFilter`, `SepiaFilter`, and `VignetteFilter` all call `imagecolorallocate()` once per pixel. On truecolor images this is wasteful and can exhaust the palette on palette-mode images.
- **Fix:** Replace `imagecolorallocate` + `imagesetpixel` with direct bitwise color composition:
```php
$color = ($newR << 16) | ($newG << 8) | $newB;
imagesetpixel($image, $x, $y, $color);
```

---

## filters.sql (seed) + SaturationFilter.php + editor.js FILTERS_META

### Bug 1 — Saturation param key mismatch across three files
`editor.js` sends `level` for saturation, but `SaturationFilter.php` reads `params['value']`, and `filters.sql` seeds the param schema as `value`. The param is silently ignored and the default is always used.
- **Fix:** Pick `level` (consistent with Brightness and Contrast) and update all three places:
  1. `SaturationFilter.php` — change `$params['value']` to `$params['level']`
  2. `filters.sql` — change `"value"` key to `"level"` in Saturation's `params_schema`
  3. `editor.js` `FILTERS_META` — already uses `level` ✓, no change needed

---

## Open items from earlier files

### api.js
- `auth.logout()` passes `undefined` instead of `null` as body. Should be `post('/auth/logout', null)`.

### schema.sql
- `sessions.user_id` is correctly `INT NULL` ✓ — resolved.
- `filters` table is missing `pipeline_id` column. Add:
```sql
ALTER TABLE filters ADD COLUMN pipeline_id INT NULL AFTER is_public;
ALTER TABLE filters ADD CONSTRAINT fk_filters_pipeline FOREIGN KEY (pipeline_id) REFERENCES pipelines(id) ON DELETE SET NULL;
```

### Session.php
- Docblock still says `user_id INT NOT NULL` — update to `INT NULL` to match the schema fix above.
