import api from '../api.js';

export class PostView {
    constructor(container, params) {
        this._container = container;
        this._postId = params.postId;

        this._post = null;
        this._comments = [];
        this._loading = true;

        this._handleGlobalClick = this._handleGlobalClick.bind(this);
        this._handleGlobalSubmit = this._handleGlobalSubmit.bind(this);
    }

    async render() {
        this._container.innerHTML = `<main class="post-view post-view--loading"><p>Loading post...</p></main>`;
        const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');

        try {
            const [postRes, commentsRes] = await Promise.all([
                fetch(`${BASE}/api/v1/posts/${this._postId}`).then(r => r.json()),
                fetch(`${BASE}/api/v1/posts/${this._postId}/comments`).then(r => r.json())
            ]);

            if (postRes.error || !postRes.post) {
                throw new Error(postRes.error || 'Post not found');
            }

            this._post = postRes.post;
            this._comments = commentsRes.comments || [];
            this._loading = false;

            this._renderContent();
        } catch (err) {
            this._container.innerHTML = `
                <main class="post-view post-view--error">
                    <a href="/feed" class="post-view__back" data-link>← Back to feed</a>
                    <p class="post-view__error">${this._esc(err.message)}</p>
                </main>`;
        }
    }

    destroy() {
        if (this._container) {
            this._container.removeEventListener('click', this._handleGlobalClick);
            this._container.removeEventListener('submit', this._handleGlobalSubmit);
        }
    }

    _renderContent() {
        const currentUser = window.app.getCurrentUser();
        const isOwner = currentUser && currentUser.id === this._post.user_id;

        this._container.innerHTML = this._buildHTML(isOwner, currentUser);

        this._container.addEventListener('click', this._handleGlobalClick);
        this._container.addEventListener('submit', this._handleGlobalSubmit);
    }

    async _handleGlobalClick(e) {
        const mainToggleBtn = e.target.closest('.post-view__main-comment-toggle');
        if (mainToggleBtn) {
            e.preventDefault();
            const form = this._container.querySelector('.post-view__comment-form');
            form.classList.remove('u-hidden');
            mainToggleBtn.classList.add('u-hidden');
            form.querySelector('textarea').focus();
            return;
        }

        const mainCancelBtn = e.target.closest('.btn--cancel-main');
        if (mainCancelBtn) {
            e.preventDefault();
            const form = this._container.querySelector('.post-view__comment-form');
            const toggleBtn = this._container.querySelector('.post-view__main-comment-toggle');
            form.classList.add('u-hidden');
            form.querySelector('textarea').value = '';
            toggleBtn.classList.remove('u-hidden');
            return;
        }

        const replyToggleBtn = e.target.closest('.btn--toggle-reply');
        if (replyToggleBtn) {
            e.preventDefault();
            const commentId = replyToggleBtn.dataset.commentId;
            const form = this._container.querySelector(`#reply-form-${commentId}`);
            if (form) {
                form.classList.remove('u-hidden');
                form.querySelector('textarea').focus();
            }
            return;
        }

        const cancelReplyBtn = e.target.closest('.btn--cancel-reply');
        if (cancelReplyBtn) {
            e.preventDefault();
            const commentId = cancelReplyBtn.dataset.commentId;
            const form = this._container.querySelector(`#reply-form-${commentId}`);
            if (form) {
                form.classList.add('u-hidden');
                form.querySelector('textarea').value = '';
            }
            return;
        }

        const reactionBtn = e.target.closest('.feed__action-btn[data-action="like"]');
        if (reactionBtn) {
            e.preventDefault();
            await this._processReaction(reactionBtn);
        }
    }

    async _handleGlobalSubmit(e) {
        if (!e.target.matches('.post-view__comment-form') && !e.target.matches('.post-view__reply-form')) {
            return;
        }

        e.preventDefault();
        const form = e.target;
        const input = form.querySelector('textarea');
        const body = input.value.trim();
        const errorEl = form.querySelector('.form__error');
        const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');

        const parentId = form.dataset.parentId ? parseInt(form.dataset.parentId, 10) : null;

        if (!body) return;

        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        if (errorEl) errorEl.textContent = '';

        try {
            const payload = { body: body };
            if (parentId) payload.parent_id = parentId; 

            const response = await fetch(`${BASE}/api/v1/posts/${this._postId}/comments`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || data.body || 'Failed to post comment');
            }

            input.value = '';
            
            if (form.classList.contains('post-view__comment-form')) {
                form.classList.add('u-hidden');
                const toggleBtn = this._container.querySelector('.post-view__main-comment-toggle');
                if (toggleBtn) toggleBtn.classList.remove('u-hidden');
            } else {
                form.classList.add('u-hidden');
            }
            
            const commentsRes = await fetch(`${BASE}/api/v1/posts/${this._postId}/comments`).then(r => r.json());
            this._comments = commentsRes.comments || [];
            
            const commentsList = this._container.querySelector('.post-view__comments-list');
            if (commentsList) {
                commentsList.innerHTML = this._comments.map(c => this._buildCommentHTML(c)).join('');
            }
        } catch (err) {
            if (errorEl) errorEl.textContent = err.message;
        } finally {
            submitBtn.disabled = false;
        }
    }

    async _processReaction(btn) {
        const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');
        const targetId = btn.dataset.targetId;
        const targetType = btn.dataset.type; 
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
            const endpoint = targetType === 'post' 
                ? `${BASE}/api/v1/posts/${targetId}/react` 
                : `${BASE}/api/v1/comments/${targetId}/react`;

            const method = isCurrentlyActive ? 'DELETE' : 'POST';
            const body = isCurrentlyActive ? null : JSON.stringify({ reaction: 'like' });
            const headers = isCurrentlyActive ? {} : { 'Content-Type': 'application/json' };
            
            await fetch(endpoint, { method, headers, body });
        } catch (err) {
            console.error('[Optimistic UI] Reaction sync failed:', err);
        }
    }

    _buildHTML(isOwner, currentUser) {
        const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');
        const post = this._post;
        
        const thumbUrl = (post.thumb_url ?? '').startsWith('http') 
            ? post.thumb_url 
            : `${BASE}/${(post.thumb_url ?? '').replace(/^\//, '')}`;

        const likeIcon = `${BASE}/assets/icons/like_icon.png`;

        let reactionsHTML = '';
        if (!isOwner && currentUser) {
            reactionsHTML = `
            <div class="feed__actions">
                <button class="feed__action-btn ${post.user_reaction === 'like' ? 'is-active' : ''}" data-target-id="${post.id}" data-type="post" data-action="like">
                    <img src="${likeIcon}" alt="Like" class="action-icon">
                    <span class="feed__action-count">${post.like_count || 0}</span>
                </button>
            </div>`;
        } else {
            reactionsHTML = `
            <div class="feed__actions">
                <span class="feed__action-btn">
                    <img src="${likeIcon}" alt="Like" class="action-icon">
                    <span class="feed__action-count">${post.like_count || 0}</span>
                </span>
            </div>`;
        }

        let commentFormHTML = '';
        if (!isOwner && currentUser) {
            commentFormHTML = `
            <div class="post-view__main-comment-toggle" style="margin-bottom: 2rem; cursor: text;">
                <div style="padding: 0.8rem 1rem; border: 1px solid var(--color-border); border-radius: 2rem; color: var(--color-text-muted); background: var(--color-surface); font-size: 0.9rem;">
                    Write a comment...
                </div>
            </div>
            
            <form class="form post-view__comment-form u-hidden" style="margin-bottom: 2rem; padding: 1rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface);">
                <div class="form__field" style="margin-bottom: 0;">
                    <textarea class="form__input form__textarea" placeholder="Write a comment..." required style="min-height: 4rem; resize: vertical; border: none; background: transparent; padding: 0;"></textarea>
                    <span class="form__error" style="color: red; font-size: 0.85rem; display: block; margin-top: 0.5rem;"></span>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem;">
                    <button type="button" class="btn btn--ghost btn--cancel-main" style="padding: 0.4rem 1rem; font-size: 0.85rem;">Cancel</button>
                    <button type="submit" class="btn btn--primary" style="padding: 0.4rem 1.5rem; font-size: 0.85rem;">Post</button>
                </div>
            </form>`;
        } else if (!currentUser) {
            commentFormHTML = `<p style="margin-bottom: 2rem;">Please <a href="/" data-link>log in</a> to comment.</p>`;
        } else {
            commentFormHTML = `<p class="post-view__owner-note" style="margin-bottom: 2rem; font-style: italic; color: var(--color-text-muted);">You cannot comment on your own post.</p>`;
        }

        const commentsListHTML = this._comments.map(c => this._buildCommentHTML(c)).join('');

        return `
        <main class="post-view">
            <a href="/feed" class="post-view__back" data-link>← Back to Feed</a>
            <figure class="post-view__figure" style="margin-bottom: 2rem;">
                <img class="post-view__image" src="${this._esc(thumbUrl)}" alt="Post image">
                ${reactionsHTML}
                ${post.caption ? `<figcaption class="post-view__caption">${this._esc(post.caption)}</figcaption>` : ''}
            </figure>
            <section class="post-view__comments-section">
                <h3 style="margin-bottom: 1.5rem;">Comments</h3>
                
                ${commentFormHTML}
                
                <ul class="post-view__comments-list" style="display: flex; flex-direction: column; gap: 1.5rem; padding: 0;">
                    ${commentsListHTML || '<p class="post-view__no-comments" style="color: var(--color-text-muted);">No comments yet. Be the first!</p>'}
                </ul>
            </section>
        </main>`;
    }

    _buildCommentHTML(comment) {
        const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');
        const avatarUrl = comment.user.avatar_path 
            ? `${BASE}/api/v1/files?path=${encodeURIComponent(comment.user.avatar_path)}`
            : null;
        
        const avatarHTML = avatarUrl
            ? `<img class="feed__avatar" src="${this._esc(avatarUrl)}" alt="${this._esc(comment.user.username)}" width="32" height="32" style="border-radius: 50%; object-fit: cover;">`
            : `<span class="feed__avatar feed__avatar--placeholder" aria-hidden="true" style="width: 32px; height: 32px; border-radius: 50%; background: var(--color-navy); display: inline-block;"></span>`;

        const currentUser = window.app.getCurrentUser();
        const isOwner = currentUser && currentUser.id === comment.user.id;
        const likeIcon = `${BASE}/assets/icons/like_icon.png`;
        const commentIcon = `${BASE}/assets/icons/comment_icon.png`; 
        
        let reactionsHTML = '';
        if (!isOwner && currentUser) {
            reactionsHTML = `
            <div class="feed__actions" style="padding: 0; display: inline-flex;">
                <button class="feed__action-btn ${comment.user_reaction === 'like' ? 'is-active' : ''}" data-target-id="${comment.id}" data-type="comment" data-action="like" style="font-size: 0.85rem;">
                    <img src="${likeIcon}" alt="Like" class="action-icon" style="width: 16px; height: 16px;">
                    <span class="feed__action-count">${comment.like_count || 0}</span>
                </button>
            </div>`;
        } else {
            reactionsHTML = `
            <div class="feed__actions" style="padding: 0; display: inline-flex;">
                <span class="feed__action-btn" style="font-size: 0.85rem;">
                    <img src="${likeIcon}" alt="Like" class="action-icon" style="width: 16px; height: 16px;">
                    <span class="feed__action-count">${comment.like_count || 0}</span>
                </span>
            </div>`;
        }

        const targetParentId = comment.id;

        let replyBtnUI = '';
        let replyFormUI = '';
        if (currentUser) {
            replyBtnUI = `
            <button class="btn btn--ghost btn--toggle-reply btn--comment-reply" data-comment-id="${comment.id}" title="Reply to comment" style="margin-left: 0.5rem; padding: 0.25rem; border-color: transparent;">
                <img src="${commentIcon}" alt="Reply icon" class="action-icon action-icon--comment">
            </button>`;
            
            replyFormUI = `
            <form class="form post-view__reply-form u-hidden" data-parent-id="${targetParentId}" id="reply-form-${comment.id}" style="margin-top: 0.5rem; padding-left: 1rem; border-left: 2px solid var(--color-border);">
                <div class="form__field" style="margin-bottom: 0;">
                    <textarea class="form__input form__textarea" placeholder="Write a reply..." required style="min-height: 3rem; font-size: 0.9rem; padding: 0.5rem; resize: vertical;"></textarea>
                    <span class="form__error" style="color: red; font-size: 0.8rem; display: block; margin-top: 0.2rem;"></span>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem;">
                    <button type="button" class="btn btn--ghost btn--cancel-reply" data-comment-id="${comment.id}" style="font-size: 0.8rem; padding: 0.3rem 0.8rem;">Cancel</button>
                    <button type="submit" class="btn btn--primary" style="font-size: 0.8rem; padding: 0.3rem 1rem;">Reply</button>
                </div>
            </form>`;
        }

        let repliesListHTML = '';
        if (comment.replies && comment.replies.length > 0) {
            const repliesContent = comment.replies.map(reply => this._buildCommentHTML(reply)).join('');
            repliesListHTML = `
            <ul class="post-view__replies-container" style="margin-top: 1rem; padding-left: 1.5rem; border-left: 2px solid var(--color-border); display: flex; flex-direction: column; gap: 1rem;">
                ${repliesContent}
            </ul>`;
        }

        return `
        <li class="post-view__comment" data-comment-id="${comment.id}" style="background: var(--color-surface); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
            <div class="post-view__comment-header" style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                ${avatarHTML}
                <strong style="color: var(--color-navy);">${this._esc(comment.user.username)}</strong>
                <span class="post-view__comment-date" style="font-size: 0.8rem; color: var(--color-text-muted);">${new Date(comment.created_at).toLocaleDateString()}</span>
            </div>
            <p class="post-view__comment-body" style="margin: 0; line-height: 1.5;">${this._esc(comment.body)}</p>
            
            <div style="display: flex; align-items: center; margin-top: 0.5rem;">
                ${reactionsHTML}
                ${replyBtnUI}
            </div>
            
            ${replyFormUI}
            
            ${repliesListHTML}
        </li>`;
    }

    _esc(value) {
        return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
}