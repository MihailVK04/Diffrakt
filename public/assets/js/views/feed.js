/**
 * public/assets/js/views/feed.js — FeedView
 *
 * Displays posts from followed users in reverse-chronological order.
 * Implements cursor-based pagination — the last post ID seen is passed as
 * ?cursor= on the next request. A sentinel "Load more" button triggers the
 * next page; the button is hidden once the server signals no more pages.
 *
 * View contract (expected by app.js):
 *   constructor(container, params)
 *   async render()
 *   destroy()          ← cancels in-flight requests, removes listeners
 *
 * API used:
 *   api.feed.get(cursor)   GET /api/v1/feed[?cursor={id}]
 *
 * Expected API response shape:
 *   {
 *     posts: [
 *       {
 *         id, caption, visibility,
 *         thumb_url,                  // served via PHP readfile()
 *         created_at,
 *         author: { username, avatar_url }
 *       },
 *       …
 *     ],
 *     next_cursor: <int|null>        // null means no more pages
 *   }
 */

import api from '../api.js';

export class FeedView {

    constructor(container, params) {
        this._container = container;
        this._params = params;

        this._cursor = null;
        this._hasMore = true;
        this._loading = false;
        this._abortCtrl = null;
        this._observer = null;
    }

    async render() {
        this._container.innerHTML = this._buildShellHTML();

        this._list = this._container.querySelector('[id="feed-list"]');
        this._sentinel = this._container.querySelector('[id="feed-sentinel"]');
        this._emptyMsg = this._container.querySelector('[id="feed-empty"]');
        this._errorMsg = this._container.querySelector('[id="feed-error"]');

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

    async _loadPage() {
        if (this._loading || !this._hasMore) {
            return;
        }

        this._loading = true;
        this._hideError();

        this._abortCtrl = new AbortController();

        try {
            const data = await api.feed.get(this._cursor);
            const posts = data.posts ?? [];
            const nextCursor = data.next_cursor ?? null;

            if (posts.length === 0 && this._cursor === null) {
                this._showEmpty();
            } else {
                this._appendPosts(posts);
            }

            this._cursor = nextCursor;
            this._hasMore = nextCursor !== null;

            if (!this._hasMore && this._observer) {
                this._observer.disconnect();
                this._observer = null;
            }

        } catch (err) {
            
            if (err.name === 'AbortError') {
                return;
            }

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
    <h1 class="feed__title">Feed</h1>
 
    <ul id="feed-list" class="feed__list"></ul>
 
    <p id="feed-empty" class="feed__empty" hidden>
        Nothing here yet — follow some users to see their posts.
    </p>
 
    <p id="feed-error" class="feed__error" aria-live="polite" hidden></p>
 
    <div id="feed-sentinel" class="feed__sentinel" aria-hidden="true"></div>
</main>`;
    }

    _buildPostHTML(post) {
        const authorUrl  = `/profile/${encodeURIComponent(post.author.username)}`;
        const postUrl    = `/editor/${encodeURIComponent(post.id)}`;
        const avatarHTML = post.author.avatar_url
            ? `<img
                   class="feed__avatar"
                   src="${this._esc(post.author.avatar_url)}"
                   alt="${this._esc(post.author.username)}'s avatar"
                   width="32"
                   height="32"
               >`
            : `<span class="feed__avatar feed__avatar--placeholder" aria-hidden="true"></span>`;
 
        const captionHTML = post.caption
            ? `<p class="feed__caption">${this._esc(post.caption)}</p>`
            : '';
 
        const date      = new Date(post.created_at);
        const dateISO   = date.toISOString();
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
            src="${this._esc(post.thumb_url)}"
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