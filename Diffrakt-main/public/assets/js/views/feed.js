import api from '../api.js';

export class FeedView {

    constructor(container, params) {
        this._container = container;
        this._params = params;

        this._scope = 'following';
        this._cursor = null;
        this._hasMore = true;
        this._loading = false;
        this._abortCtrl = null;
        this._observer = null;
        this._loadedPosts = [];
    }

    async render() {
        this._container.innerHTML = this._buildShellHTML();

        this._list = this._container.querySelector('[id="feed-list"]');
        this._sentinel = this._container.querySelector('[id="feed-sentinel"]');
        this._emptyMsg = this._container.querySelector('[id="feed-empty"]');
        this._errorMsg = this._container.querySelector('[id="feed-error"]');
        this._toggleBtn = this._container.querySelector('[id="feed-scope-toggle"]');
        
        this._exportBtn = this._container.querySelector('[id="feed-export-btn"]');
        this._exportInput = this._container.querySelector('[id="export-count"]');

        this._exportInput.addEventListener('input', () => {
            const max = this._loadedPosts.length;
            let val = parseInt(this._exportInput.value, 10);
            if (val > max) {
                this._exportInput.value = max;
            }
        });

        this._toggleBtn.addEventListener('click', () => {
            this._setScope(this._scope === 'following' ? 'all' : 'following');
        });

        this._exportBtn.addEventListener('click', () => {
            if (this._loadedPosts.length === 0) {
                alert("Няма заредени постове за експорт.");
                return;
            }

            const count = parseInt(this._exportInput.value, 10);
            
            if (isNaN(count) || count <= 0) {
                alert("Моля, въведете валидно число, по-голямо от 0.");
                return;
            }

            const limit = Math.min(count, this._loadedPosts.length);
            const postsToExport = this._loadedPosts.slice(0, limit);

            const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');
            const currentOrigin = window.location.origin;

            const linksHTML = postsToExport.map(post => {
                const url = `${currentOrigin}${BASE}/exported-post/${post.id}`;
                const date = new Date(post.created_at).toLocaleString();
                return `<li><a href="${url}" target="_blank">Post by ${this._esc(post.author.username)} (${date})</a></li>`;
            }).join('');

            const htmlContent = `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Exported Posts</title>
</head>
<body>
<h1>Exported Posts Links</h1>
<ul>
${linksHTML}
</ul>
</body>
</html>`;

            const blob = new Blob([htmlContent], { type: 'text/html' });
            const blobUrl = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = blobUrl;
            a.download = `diffrakt_export.html`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(blobUrl);
        });

        this._observer = new IntersectionObserver(entries => {
            if (entries[0].isIntersecting) {
                this._loadPage();
            }
        });
        this._observer.observe(this._sentinel);
    }

    destroy() {
        if (this._observer) {
            this._observer.disconnect();
            this._observer = null;
        }

        if (this._abortCtrl) {
            this._abortCtrl.abort();
            this._abortCtrl = null;
        }
    }

    _setScope(scope) {
        if (this._loading) return;

        if (this._abortCtrl) {
            this._abortCtrl.abort();
            this._abortCtrl = null;
        }

        this._scope = scope;
        this._cursor = null;
        this._hasMore = true;
        
        this._loadedPosts = [];
        this._exportInput.max = 0;
        this._exportInput.value = 1;

        this._list.innerHTML = '';
        this._hideError();
        this._emptyMsg.hidden = true;

        this._toggleBtn.textContent = scope === 'all'
            ? 'Show: Following + Me'
            : 'Show: Following only';
        this._toggleBtn.setAttribute('aria-pressed', scope === 'all' ? 'true' : 'false');

        if (!this._observer) {
            this._observer = new IntersectionObserver(entries => {
                if (entries[0].isIntersecting) {
                    this._loadPage();
                }
            });
        }
        this._observer.observe(this._sentinel);

        this._loadPage();
    }

    async _loadPage() {
        if (this._loading || !this._hasMore) return;

        this._loading = true;
        this._hideError();

        this._abortCtrl = new AbortController();

        try {
            const data = await api.feed.get(this._cursor, this._scope, { signal: this._abortCtrl.signal });
            const posts = data.posts ?? [];
            const nextCursor = data.next_cursor ?? null;

            if (posts.length === 0 && this._cursor === null) {
                this._showEmpty();
            } else {
                this._loadedPosts.push(...posts);
                this._exportInput.max = this._loadedPosts.length;
                this._appendPosts(posts);
            }

            this._cursor = nextCursor;
            this._hasMore = nextCursor !== null;

            if (!this._hasMore && this._observer) {
                this._observer.disconnect();
                this._observer = null;
            }

        } catch (err) {
            if (err.name === 'AbortError') return;
            this._showError(err.message ?? 'Could not load feed. Please try again.');
        } finally {
            this._loading = false;
            this._abortCtrl = null;
        }
    }

    _appendPosts(posts) {
        const fragment = document.createDocumentFragment();

        for (const post of posts) {
            const li = document.createElement('li');
            li.className = 'feed__item';
            li.innerHTML = this._buildPostHTML(post);
            fragment.appendChild(li);
        }

        this._list.appendChild(fragment);
    }

    _showEmpty() {
        this._emptyMsg.hidden = false;
    }

    _showError(message) {
        this._errorMsg.textContent = message;
        this._errorMsg.hidden = false;
    }

    _hideError() {
        this._errorMsg.textContent = '';
        this._errorMsg.hidden = true;
    }

    _buildShellHTML() {
        return `
<main class="feed">
    <div class="feed__header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h1 class="feed__title" style="margin: 0;">Feed</h1>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <label for="export-count" style="font-size: 0.9rem;">Export top:</label>
            <input type="number" id="export-count" min="1" max="0" value="1" style="width: 60px; padding: 0.3rem; border: 1px solid #ccc; border-radius: 4px;" />
            <button
                id="feed-export-btn"
                type="button"
                class="btn btn--primary"
            >Export</button>
            <button
                id="feed-scope-toggle"
                type="button"
                class="btn btn--ghost feed__scope-toggle"
                aria-pressed="false"
            >Show: Following only</button>
        </div>
    </div>

    <ul id="feed-list" class="feed__list"></ul>

    <p id="feed-empty" class="feed__empty" hidden>
        Nothing here yet — follow some users to see their posts.
    </p>

    <p id="feed-error" class="feed__error" aria-live="polite" hidden></p>

    <div id="feed-sentinel" class="feed__sentinel" aria-hidden="true"></div>
</main>`;
    }

    _buildPostHTML(post) {
        const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');

        const authorUrl = `/profile/${encodeURIComponent(post.author.username)}`;
        const postUrl = `/post/${encodeURIComponent(post.id)}`;

        const thumbUrl = post.thumb_url?.startsWith('http')
            ? post.thumb_url
            : `${BASE}/${(post.thumb_url ?? '').replace(/^\//, '')}`;

        const avatarUrl = post.author.avatar_url?.startsWith('http')
            ? post.author.avatar_url
            : post.author.avatar_url
                ? `${BASE}/${post.author.avatar_url.replace(/^\//, '')}`
                : null;

        const avatarHTML = avatarUrl
            ? `<img
                class="feed__avatar"
                src="${this._esc(avatarUrl)}"
                alt="${this._esc(post.author.username)}'s avatar"
                width="32"
                height="32"
            >`
            : `<span class="feed__avatar feed__avatar--placeholder" aria-hidden="true"></span>`;

        const captionHTML = post.caption
            ? `<p class="feed__caption">${this._esc(post.caption)}</p>`
            : '';

        const date = new Date(post.created_at);
        const dateISO = date.toISOString();
        const dateLabel = date.toLocaleDateString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric',
        });

        return `
<article class="feed__card">
    <header class="feed__card-header">
        <a class="feed__author" href="${this._esc(authorUrl)}" data-link>
            ${avatarHTML}
            <span class="feed__username">${this._esc(post.author.username)}</span>
        </a>
        <time class="feed__date" datetime="${dateISO}">${dateLabel}</time>
    </header>

    <a class="feed__image-link" href="${this._esc(postUrl)}" data-link>
        <img
            class="feed__image"
            src="${this._esc(thumbUrl)}"
            alt="${post.caption ? this._esc(post.caption) : 'Photo by ' + this._esc(post.author.username)}"
            loading="lazy"
        >
    </a>

    ${captionHTML}
</article>`;
    }

    _esc(value) {
        return String(value)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#39;')
    }
}