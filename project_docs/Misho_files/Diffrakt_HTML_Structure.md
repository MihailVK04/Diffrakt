# Diffrakt — HTML Structure Reference

BEM methodology throughout. Block names match view names (`home`, `feed`, `editor`, `profile`). Shared UI primitives use their own blocks (`btn`, `form`, `tabs`).

---

## Global Shell (`index.html`)

```html
<body>
    <div id="app">
        <!-- active view is rendered here by app.js -->
    </div>
</body>
```

`#app` has no classes. Its content is replaced entirely on every route change.

---

## Global Error States (`app.js`)

Rendered directly into `#app` when a route is not found or a view crashes.

```html
<div class="app-error">
    <h2>404</h2>
    <p>Page not found.</p>
    <a href="/feed" data-link>Go to feed</a>
</div>

<div class="app-error">
    <h2>Something went wrong</h2>
    <p>Error message here.</p>
</div>
```

**Classes:**
- `app-error` — full-page error container

---

## Shared Primitives

### Buttons

```html
<button class="btn btn--primary"   type="button">Label</button>
<button class="btn btn--secondary" type="button">Label</button>
<button class="btn btn--ghost"     type="button">Label</button>
```

**Modifiers:** `btn--primary`, `btn--secondary`, `btn--ghost`

---

### Forms

```html
<form class="form" novalidate>

    <div class="form__field">
        <label class="form__label" for="input-id">Label</label>
        <input id="input-id" class="form__input" type="text">
        <span class="form__error" aria-live="polite">Inline error message</span>
    </div>

    <div class="form__field">
        <label class="form__label" for="textarea-id">Label</label>
        <textarea id="textarea-id" class="form__input form__textarea"></textarea>
    </div>

    <div class="form__field">
        <label class="form__label" for="file-id">Label</label>
        <input id="file-id" class="form__input" type="file">
    </div>

    <p class="form__global-error" aria-live="polite">Form-level error message</p>

    <div class="form__actions">
        <button class="btn btn--primary"  type="submit">Save</button>
        <button class="btn btn--ghost"    type="button">Cancel</button>
    </div>

    <button class="form__submit btn btn--primary" type="submit">Submit</button>

</form>
```

**Elements:**
- `form` — block
- `form__field` — wraps label + input + error
- `form__label` — `<label>`
- `form__input` — `<input>` or `<textarea>`
- `form__textarea` — modifier on `form__input` for `<textarea>`
- `form__error` — per-field inline error (`<span>`, `aria-live="polite"`)
- `form__global-error` — form-level error (`<p>`, `aria-live="polite"`)
- `form__actions` — button row (used in edit forms)
- `form__submit` — submit button (used in login/register, no `form__actions` wrapper)

---

### Tabs

```html
<div class="tabs" role="tablist">
    <button id="tab-login"    class="tabs__tab tabs__tab--active" role="tab" aria-selected="true"  aria-controls="panel-login">Log in</button>
    <button id="tab-register" class="tabs__tab"                  role="tab" aria-selected="false" aria-controls="panel-register">Register</button>
</div>

<div id="panel-login"    class="tabs__panel"               role="tabpanel">...</div>
<div id="panel-register" class="tabs__panel tabs__panel--hidden" role="tabpanel">...</div>
```

**Elements:**
- `tabs` — container (`role="tablist"`)
- `tabs__tab` — individual tab button
- `tabs__tab--active` — active tab modifier (toggled by JS)
- `tabs__panel` — panel container
- `tabs__panel--hidden` — hides inactive panel (toggled by JS)

---

## HomeView (`/`)

```html
<main class="home">

    <section class="home__hero">
        <h1 class="home__title">Diffrakt</h1>
        <p class="home__tagline">Upload photos. Build filter pipelines. Share the result.</p>
    </section>

    <section class="home__auth">

        <!-- Tabs -->
        <div class="tabs" role="tablist">
            <button id="tab-login"    class="tabs__tab tabs__tab--active" ...>Log in</button>
            <button id="tab-register" class="tabs__tab" ...>Register</button>
        </div>

        <!-- Login panel -->
        <div id="panel-login" class="tabs__panel" role="tabpanel">
            <form id="login-form" class="form" novalidate>
                <div class="form__field">
                    <label class="form__label" for="login-email">Email</label>
                    <input id="login-email" class="form__input" type="email">
                    <span class="form__error" id="login-email-error" aria-live="polite"></span>
                </div>
                <div class="form__field">
                    <label class="form__label" for="login-password">Password</label>
                    <input id="login-password" class="form__input" type="password">
                    <span class="form__error" id="login-password-error" aria-live="polite"></span>
                </div>
                <p class="form__global-error" id="login-global-error" aria-live="polite"></p>
                <button class="form__submit btn btn--primary" type="submit">Log in</button>
            </form>
        </div>

        <!-- Register panel -->
        <div id="panel-register" class="tabs__panel tabs__panel--hidden" role="tabpanel">
            <form id="register-form" class="form" novalidate>
                <div class="form__field">
                    <label class="form__label" for="reg-username">Username</label>
                    <input id="reg-username" class="form__input" type="text">
                    <span class="form__error" id="reg-username-error" aria-live="polite"></span>
                </div>
                <div class="form__field">
                    <label class="form__label" for="reg-email">Email</label>
                    <input id="reg-email" class="form__input" type="email">
                    <span class="form__error" id="reg-email-error" aria-live="polite"></span>
                </div>
                <div class="form__field">
                    <label class="form__label" for="reg-password">Password</label>
                    <input id="reg-password" class="form__input" type="password">
                    <span class="form__error" id="reg-password-error" aria-live="polite"></span>
                </div>
                <p class="form__global-error" id="reg-global-error" aria-live="polite"></p>
                <button class="form__submit btn btn--primary" type="submit">Create account</button>
            </form>
        </div>

    </section>

</main>
```

**Elements:**
- `home` — page root
- `home__hero` — hero/branding section
- `home__title` — `<h1>`
- `home__tagline` — subtitle `<p>`
- `home__auth` — auth forms section

---

## FeedView (`/feed`)

```html
<main class="feed">

    <h1 class="feed__title">Feed</h1>

    <ul id="feed-list" class="feed__list">

        <li class="feed__item">
            <article class="feed__card">

                <header class="feed__card-header">
                    <a class="feed__author" href="/profile/username" data-link>
                        <!-- With avatar -->
                        <img class="feed__avatar" src="..." alt="username's avatar" width="32" height="32">
                        <!-- Without avatar -->
                        <span class="feed__avatar feed__avatar--placeholder" aria-hidden="true"></span>

                        <span class="feed__username">username</span>
                    </a>
                    <time class="feed__date" datetime="2025-01-01T00:00:00.000Z">Jan 1, 2025</time>
                </header>

                <a class="feed__image-link" href="/editor/42" data-link>
                    <img class="feed__image" src="..." alt="caption or fallback" loading="lazy">
                </a>

                <!-- Optional — only rendered when caption exists -->
                <p class="feed__caption">Caption text</p>

            </article>
        </li>

    </ul>

    <!-- Shown only when first page returns zero posts -->
    <p id="feed-empty" class="feed__empty" hidden>
        Nothing here yet — follow some users to see their posts.
    </p>

    <!-- Shown on fetch error -->
    <p id="feed-error" class="feed__error" aria-live="polite" hidden></p>

    <!-- Invisible sentinel watched by IntersectionObserver -->
    <div id="feed-sentinel" class="feed__sentinel" aria-hidden="true"></div>

</main>
```

**Elements:**
- `feed` — page root
- `feed__title` — `<h1>`
- `feed__list` — `<ul>` of post cards
- `feed__item` — `<li>` wrapper per post
- `feed__card` — `<article>` post card
- `feed__card-header` — author row + date
- `feed__author` — `<a>` wrapping avatar + username
- `feed__avatar` — `<img>` avatar
- `feed__avatar--placeholder` — `<span>` shown when no avatar
- `feed__username` — `<span>` username text
- `feed__date` — `<time>`
- `feed__image-link` — `<a>` wrapping the post image
- `feed__image` — `<img>` post thumbnail
- `feed__caption` — `<p>` optional caption
- `feed__empty` — zero-state message (hidden by default)
- `feed__error` — error message (hidden by default)
- `feed__sentinel` — invisible scroll trigger (zero height, `aria-hidden`)

---

## ProfileView (`/profile/:username`)

### Loading state

```html
<main class="profile profile--loading" aria-busy="true">
    <p>Loading…</p>
</main>
```

### Error state

```html
<main class="profile profile--error">
    <p class="profile__error">User not found.</p>
</main>
```

### Full profile

```html
<main class="profile">

    <header class="profile__header">

        <!-- With avatar -->
        <img class="profile__avatar" src="..." alt="username's avatar" width="96" height="96" id="profile-avatar-preview">
        <!-- Without avatar -->
        <span class="profile__avatar profile__avatar--placeholder" aria-hidden="true" id="profile-avatar-preview"></span>

        <div class="profile__info">
            <h1 class="profile__username">username</h1>

            <ul class="profile__stats">
                <li class="profile__stat">
                    <span class="profile__stat-value">42</span>
                    <span class="profile__stat-label">posts</span>
                </li>
                <li class="profile__stat">
                    <span class="profile__stat-value" id="profile-follower-count">128</span>
                    <span class="profile__stat-label">followers</span>
                </li>
                <li class="profile__stat">
                    <span class="profile__stat-value">64</span>
                    <span class="profile__stat-label">following</span>
                </li>
            </ul>

            <!-- Own profile -->
            <button id="profile-edit-toggle" class="btn btn--secondary">Edit profile</button>

            <!-- Authenticated viewing someone else — following=false -->
            <button id="profile-follow-btn" class="btn btn--primary" data-following="false">Follow</button>
            <!-- Authenticated viewing someone else — following=true -->
            <button id="profile-follow-btn" class="btn btn--secondary" data-following="true">Unfollow</button>

            <!-- Unauthenticated — no button -->

        </div>
    </header>

    <!-- Bio — always present, empty when no bio set -->
    <p class="profile__bio" id="profile-bio">Bio text here</p>
    <p class="profile__bio profile__bio--empty" id="profile-bio"></p>

    <!-- Edit form — own profile only, hidden by default -->
    <form id="profile-edit-form" class="form profile__edit-form" hidden novalidate>
        <div class="form__field">
            <label class="form__label" for="profile-avatar-input">Avatar</label>
            <input id="profile-avatar-input" class="form__input" type="file" name="avatar" accept="image/*">
        </div>
        <div class="form__field">
            <label class="form__label" for="profile-bio-input">Bio</label>
            <textarea id="profile-bio-input" class="form__input form__textarea" name="bio" rows="3"></textarea>
        </div>
        <p class="form__global-error" id="profile-edit-error" aria-live="polite"></p>
        <div class="form__actions">
            <button class="btn btn--primary" type="submit">Save</button>
            <button class="btn btn--ghost"   type="button" id="profile-edit-cancel">Cancel</button>
        </div>
    </form>

    <!-- Post grid -->
    <section class="profile__posts">
        <ul id="profile-posts-grid" class="profile__post-grid">
            <li class="profile__post-item">
                <a class="profile__post-link" href="/editor/42" data-link>
                    <img class="profile__post-thumb" src="..." alt="caption or fallback" loading="lazy">
                </a>
            </li>
        </ul>

        <!-- Zero state — hidden by default -->
        <p id="profile-posts-empty" class="profile__no-posts" hidden>No posts yet.</p>

        <!-- Error — hidden by default -->
        <p id="profile-posts-error" class="profile__posts-error" aria-live="polite" hidden></p>

        <!-- Invisible scroll trigger -->
        <div id="profile-posts-sentinel" class="profile__sentinel" aria-hidden="true"></div>
    </section>

</main>
```

**Elements:**
- `profile` — page root
- `profile--loading` — modifier during fetch
- `profile--error` — modifier on error
- `profile__error` — error message `<p>`
- `profile__header` — avatar + info row
- `profile__avatar` — `<img>` (96×96)
- `profile__avatar--placeholder` — `<span>` when no avatar
- `profile__info` — username + stats + action
- `profile__username` — `<h1>`
- `profile__stats` — `<ul>` stats row
- `profile__stat` — `<li>` single stat
- `profile__stat-value` — number `<span>`
- `profile__stat-label` — label `<span>`
- `profile__bio` — bio `<p>`
- `profile__bio--empty` — modifier when bio is not set
- `profile__edit-form` — modifier on `.form` for the edit form
- `profile__posts` — `<section>` containing the post grid
- `profile__post-grid` — `<ul>` thumbnail grid
- `profile__post-item` — `<li>` grid cell
- `profile__post-link` — `<a>` wrapping thumbnail
- `profile__post-thumb` — `<img>` thumbnail
- `profile__no-posts` — zero-state message
- `profile__posts-error` — error message
- `profile__sentinel` — invisible scroll trigger

---

## EditorView (`/editor`, `/editor/:postId`)

```html
<main class="editor">

    <p id="editor-error" class="editor__error" aria-live="polite" hidden></p>

    <div class="editor__layout">

        <!-- Left panel -->
        <section class="editor__preview-panel">

            <canvas id="editor-canvas" class="editor__canvas"></canvas>

            <!-- Shown when no image loaded yet -->
            <div id="editor-upload-section" class="editor__upload">
                <label class="editor__upload-label btn btn--primary" for="editor-upload-input">
                    Choose photo
                </label>
                <input id="editor-upload-input" class="editor__upload-input" type="file" accept="image/*">
            </div>

            <div class="editor__preview-actions">
                <button id="editor-save-btn"        class="btn btn--primary"   type="button">Save pipeline</button>
                <button id="editor-export-btn"      class="btn btn--secondary" type="button">Export</button>
                <button id="editor-save-filter-btn" class="btn btn--ghost"     type="button">Save as filter</button>
            </div>

        </section>

        <!-- Right panel -->
        <section class="editor__controls-panel">

            <h2 class="editor__section-title">Filters</h2>

            <div id="editor-filter-list" class="editor__filter-list">
                <button class="editor__filter-btn btn btn--ghost" data-filter-id="1" type="button">Gaussian Blur</button>
                <button class="editor__filter-btn btn btn--ghost" data-filter-id="2" type="button">Grayscale</button>
                <!-- … one button per filter (10 total) … -->
            </div>

            <h2 class="editor__section-title">Pipeline</h2>

            <ul id="editor-step-list" class="editor__step-list">

                <!-- Zero state -->
                <li class="editor__step-empty">No filters added yet.</li>

                <!-- Active step -->
                <li class="editor__step" data-step-index="0">
                    <div class="editor__step-header">
                        <span class="editor__step-name">Gaussian Blur</span>
                        <div class="editor__step-actions">
                            <button class="btn btn--ghost editor__step-up"   data-action="up"     data-index="0" type="button" aria-label="Move up">↑</button>
                            <button class="btn btn--ghost editor__step-down" data-action="down"   data-index="0" type="button" aria-label="Move down">↓</button>
                            <button class="btn btn--ghost editor__step-del"  data-action="delete" data-index="0" type="button" aria-label="Remove filter">✕</button>
                        </div>
                    </div>

                    <!-- Only rendered for filters that have params -->
                    <div class="editor__step-controls">
                        <div class="editor__param">
                            <label class="editor__param-label">
                                Intensity
                                <span class="editor__param-value" id="param-val-0-intensity">5</span>
                            </label>
                            <input
                                class="editor__param-slider"
                                type="range"
                                min="1" max="50" value="5"
                                data-step-index="0"
                                data-param-key="intensity"
                            >
                        </div>
                    </div>

                </li>

                <!-- Step with no params (e.g. Grayscale) — no controls div -->
                <li class="editor__step" data-step-index="1">
                    <div class="editor__step-header">
                        <span class="editor__step-name">Grayscale</span>
                        <div class="editor__step-actions">
                            <button class="btn btn--ghost editor__step-up"   ...>↑</button>
                            <button class="btn btn--ghost editor__step-down" ...>↓</button>
                            <button class="btn btn--ghost editor__step-del"  ...>✕</button>
                        </div>
                    </div>
                </li>

            </ul>

        </section>

    </div>

    <!-- Toast — appended dynamically, removed after 3s -->
    <div class="editor__toast">Pipeline saved.</div>

</main>
```

**Elements:**
- `editor` — page root
- `editor__error` — global error bar (hidden by default, `aria-live="polite"`)
- `editor__layout` — two-column layout wrapper
- `editor__preview-panel` — left column (canvas + actions)
- `editor__canvas` — `<canvas>` live preview
- `editor__upload` — upload section shown before image is loaded
- `editor__upload-label` — `<label>` styled as a button (also has `btn btn--primary`)
- `editor__upload-input` — visually hidden `<input type="file">`
- `editor__preview-actions` — row of save/export buttons
- `editor__controls-panel` — right column (filter picker + step list)
- `editor__section-title` — `<h2>` panel heading
- `editor__filter-list` — grid/row of filter picker buttons
- `editor__filter-btn` — individual filter button (also has `btn btn--ghost`)
- `editor__step-list` — `<ul>` pipeline steps
- `editor__step-empty` — `<li>` zero state
- `editor__step` — `<li>` active step
- `editor__step-header` — step name + action buttons row
- `editor__step-name` — `<span>` filter name
- `editor__step-actions` — wrapper for move/delete buttons
- `editor__step-up` — move up button (also has `btn btn--ghost`)
- `editor__step-down` — move down button (also has `btn btn--ghost`)
- `editor__step-del` — delete button (also has `btn btn--ghost`)
- `editor__step-controls` — param controls container (absent for parameterless filters)
- `editor__param` — single param row
- `editor__param-label` — `<label>` containing name + current value
- `editor__param-value` — `<span>` current value display (updated live by JS)
- `editor__param-slider` — `<input type="range">`
- `editor__toast` — transient success notification (appended/removed by JS)
