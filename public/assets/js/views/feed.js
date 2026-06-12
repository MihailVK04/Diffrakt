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

        this._handleReactionClick = this._handleReactionClick.bind(this);
    }

    async render() {
        this._container.innerHTML = this._buildShellHTML();

        this._list = this._container.querySelector('[id="feed-list"]');
        this._sentinel = this._container.querySelector('[id="feed-sentinel"]');
        this._emptyMsg = this._container.querySelector('[id="feed-empty"]');
        this._errorMsg = this._container.querySelector('[id="feed-error"]');

        this._list.addEventListener('click', this._handleReactionClick);

        this._observer = new IntersectionObserver(entries => {
            if (entries[0].isIntersecting) {
                this._loadPage();
            }
        });
        this._observer.observe(this._sentinel);
    }

    destroy() {
        if (this._list) {
            this._list.removeEventListener('click', this._handleReactionClick);
        }

        if (this._observer) {
            this._observer.disconnect();
            this._observer = null;
        }

        if (this._abortCtrl) {
            this._abortCtrl.abort();
            this._abortCtrl = null;
        }
    }

    async _handleReactionClick(e) {
        const btn = e.target.closest('.feed__action-btn[data-action="like"]');
        if (!btn) return;

        e.preventDefault();

        const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');
        const postId = btn.dataset.postId;
        const isCurrentlyActive = btn.classList.contains('is-active');
        const countSpan = btn.querySelector('.feed__action-count');

        if (isCurrentlyActive) {
            btn.classList.remove('is-active');
            if (countSpan) countSpan.textContent = Math.max(0, parseInt(countSpan.textContent, 10) - 1);
        } else {
            btn.classList.add('is-active');
            if (countSpan) countSpan.textContent = parseInt(countSpan.textContent, 10) + 1;
        }

        try {
            const method = isCurrentlyActive ? 'DELETE' : 'POST';
            const body = isCurrentlyActive ? null : JSON.stringify({ reaction: 'like' });
            const headers = isCurrentlyActive ? {} : { 'Content-Type': 'application/json' };
            
            await fetch(`${BASE}/api/v1/posts/${postId}/react`, {
                method,
                headers,
                body,
                credentials: 'include'
            });
        } catch (err) {
            console.error('[Optimistic UI] Reaction failed to sync with server:', err);
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

    _showEmpty() { this._emptyMsg.hidden = false; }
    _showError(message) { this._errorMsg.textContent = message; this._errorMsg.hidden = false; }
    _hideError() { this._errorMsg.textContent = ''; this._errorMsg.hidden = true; }

    _buildShellHTML() {
        return `
<main class="feed">
    <h1 class="feed__title">Feed</h1>
    <ul id="feed-list" class="feed__list"></ul>
    <p id="feed-empty" class="feed__empty" hidden>Nothing here yet — follow some users to see their posts.</p>
    <p id="feed-error" class="feed__error" aria-live="polite" hidden></p>
    <div id="feed-sentinel" class="feed__sentinel" aria-hidden="true"></div>
</main>`;
    }

    _buildPostHTML(post) {
        const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');

        const authorUrl = `/profile/${encodeURIComponent(post.author.username)}`;
        const postUrl = `/post/${encodeURIComponent(post.id)}`;

        const thumbUrl  = post.thumb_url?.startsWith('http')
            ? post.thumb_url
            : `${BASE}/${(post.thumb_url ?? '').replace(/^\//, '')}`;

        const avatarUrl = post.author.avatar_url?.startsWith('http')
            ? post.author.avatar_url
            : post.author.avatar_url
                ? `${BASE}/${post.author.avatar_url.replace(/^\//, '')}`
                : null;

        const avatarHTML = avatarUrl
            ? `<img class="feed__avatar" src="${this._esc(avatarUrl)}" alt="${this._esc(post.author.username)}'s avatar" width="32" height="32">`
            : `<span class="feed__avatar feed__avatar--placeholder" aria-hidden="true"></span>`;

        const captionHTML = post.caption ? `<p class="feed__caption">${this._esc(post.caption)}</p>` : '';
        const dateISO   = new Date(post.created_at).toISOString();
        const dateLabel = new Date(post.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
        
        const currentUser = window.app.getCurrentUser();
        const isOwner = currentUser && currentUser.username === post.author.username;
        
        const likeIcon = `${BASE}/assets/icons/like_icon.png`;
        const commentIcon = `${BASE}/assets/icons/comment_icon.png`;

        let reactionsHTML = '';
        if (!isOwner && currentUser) {
            reactionsHTML = `
            <div class="feed__actions">
                <button class="feed__action-btn ${post.user_reaction === 'like' ? 'is-active' : ''}" data-post-id="${post.id}" data-action="like">
                    <img src="${likeIcon}" alt="Like" class="action-icon">
                    <span class="feed__action-count">${post.like_count || 0}</span>
                </button>
                <a href="${this._esc(postUrl)}" class="feed__action-btn" data-link>
                    <img src="${commentIcon}" alt="Comment" class="action-icon">
                    <span class="feed__action-count">${post.comment_count || 0}</span>
                </a>
            </div>`;
        } else {
            reactionsHTML = `
            <div class="feed__actions">
                <span class="feed__action-btn">
                    <img src="${likeIcon}" alt="Like" class="action-icon">
                    <span class="feed__action-count">${post.like_count || 0}</span>
                </span>
                <span class="feed__action-btn">
                    <img src="${commentIcon}" alt="Comment" class="action-icon">
                    <span class="feed__action-count">${post.comment_count || 0}</span>
                </span>
            </div>`;
        }
 
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
        <img class="feed__image" src="${this._esc(thumbUrl)}" alt="Post image" loading="lazy">
    </a>
    ${reactionsHTML}
    ${captionHTML}
</article>`;
    }

    _esc(value) {
        return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
}