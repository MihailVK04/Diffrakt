/**
 * public/assets/js/views/profile.js — ProfileView
 *
 * Public user profile page. Accessible without authentication.
 *
 * Responsibilities:
 *   - Fetch and display the user's profile (avatar, username, bio,
 *     follower/following counts, post grid).
 *   - If the viewer is authenticated and looking at someone else's profile,
 *     show a Follow / Unfollow button.
 *   - If the viewer is looking at their own profile, show an Edit profile
 *     form (bio + avatar upload).
 *
 * View contract (expected by app.js):
 *   constructor(container, params)   — params.username from the route
 *   async render()
 *   destroy()
 *
 * API used:
 *   api.users.getProfile(username)              GET /api/v1/users/{username}
 *   api.users.getPosts(username, cursor)        GET /api/v1/users/{username}/posts[?cursor={id}]
 *   api.users.follow(username)                  POST /api/v1/users/{username}/follow
 *   api.users.unfollow(username)                DELETE /api/v1/users/{username}/follow
 *   api.users.updateMe({ bio, avatar })         PATCH /api/v1/users/me
 *
 * Expected GET /users/{username} response shape:
 *   {
 *     username, bio, avatar_url,
 *     follower_count, following_count, post_count,
 *     is_following      // bool — whether the current viewer follows this user
 *   }
 *
 * Expected GET /users/{username}/posts response shape:
 *   {
 *     posts: [ { id, thumb_url, caption } ],
 *     next_cursor: <int|null>
 *   }
 */

import api from '../api.js';

export class ProfileView {

    constructor(container, params) {
        this._container = container;
        this._username = params.username;

        this._profile = null;
        this._abortCtrl = null;
        this._postsCursor    = null;
        this._postsHasMore   = true;
        this._postsLoading   = false;
        this._postsAbortCtrl = null;
        this._observer       = null;
    }

    async render() {
        this._container.innerHTML = this._buildLoadingHTML();

        this._abortCtrl = new AbortController();
        
        try {

            const raw = await api.users.getProfile(this._username);
            this._profile = raw.user ?? raw;

        } catch (err) {

            if (err.name === 'AbortError') {
                return;
            }

            const msg = err.status === 404 ? 'User not found.' : (err.message ?? 'Could not load profile. Please try again.');

            this._container.innerHTML = this._buildErrorHTML(msg);
            return;

        } finally {

            this._abortCtrl = null;

        }

        const currentUser = window.app.getCurrentUser();
        const isOwnProfile = currentUser?.username === this._username;
        const isAuthenticated = currentUser !== null;

        this._container.innerHTML = this._buildProfileHTML(this._profile, isOwnProfile, isAuthenticated);

        if (isOwnProfile) {
            this._bindEditForm();
        } else if (isAuthenticated) {
            this._bindFollowButton();
        }

        const sentinel = this._container.querySelector('[id="profile-posts-sentinel"]');
        this._observer = new IntersectionObserver(entries => {
            if (entries[0].isIntersecting) {
                this._loadPostsPage();
            }
        });
        this._observer.observe(sentinel)
    }

    destroy() {
        if (this._abortCtrl) {
            this._abortCtrl.abort();
            this._abortCtrl = null;
        }

        if (this._observer) {
            this._observer.disconnect();
            this._observer = null;
        }
 
        if (this._postsAbortCtrl) {
            this._postsAbortCtrl.abort();
            this._postsAbortCtrl = null;
        }
    }

    async _loadPostsPage() {
        if (this._postsLoading || !this._postsHasMore) return;
 
        this._postsLoading   = true;
        this._postsAbortCtrl = new AbortController();
 
        const grid = this._container.querySelector('[id="profile-posts-grid"]');
        const errorEl = this._container.querySelector('[id="profile-posts-error"]');
        const emptyEl = this._container.querySelector('[id="profile-posts-empty"]');
 
        if (errorEl) { 
            errorEl.hidden = true; errorEl.textContent = ''; 
        }
 
        try {
            const data = await api.users.getPosts(this._username, this._postsCursor);
 
            const posts = data.posts ?? [];
            const nextCursor = data.next_cursor ?? null;
 
            if (posts.length === 0 && this._postsCursor === null) {
                if (emptyEl) {
                    emptyEl.hidden = false;
                }
            } else {
                this._appendPostItems(grid, posts);
            }
 
            this._postsCursor = nextCursor;
            this._postsHasMore = nextCursor !== null;
 
            if (!this._postsHasMore && this._observer) {
                this._observer.disconnect();
                this._observer = null;
            }
 
        } catch (err) {
            if (err.name === 'AbortError') return;
 
            if (errorEl) {
                errorEl.textContent = err.message ?? 'Could not load posts.';
                errorEl.hidden = false;
            }
        } finally {
            this._postsLoading = false;
            this._postsAbortCtrl = null;
        }
    }

    _appendPostItems(grid, posts) {
        const fragment = document.createDocumentFragment();
 
        for (const post of posts) {
            const li = document.createElement('li');
            li.className = 'profile__post-item';
            li.innerHTML = this._buildPostItemHTML(post);
            fragment.appendChild(li);
        }
 
        grid.appendChild(fragment);
    }

    _bindFollowButton() {
        const btn = this._container.querySelector('[id="profile-follow-btn"]');

        if (!btn) {
            return;
        }

        btn.addEventListener('click', async () => {
            btn.disabled = true;
            const following = btn.dataset.following === 'true';
            
            try {
                if (following) {
                    await api.users.unfollow(this._username);
                } else {
                    await api.users.follow(this._username);
                }

                const nowFollowing = !following;
                btn.dataset.following = String(nowFollowing);
                btn.textContent = nowFollowing ? 'Unfollow' : 'Follow';
                btn.classList.toggle('btn--secondary', nowFollowing);
                btn.classList.toggle('btn--primary', !nowFollowing);

                const countEl = this._container.querySelector('[id="profile-follower-count"]');
                if (countEl) {
                    const current = parseInt(countEl.textContent, 10);
                    countEl.textContent = nowFollowing ? current + 1 : current - 1;
                }

            } catch (err) {
                btn.dataset.following = String(following);
                btn.textContent = following ? 'Unfollow' : 'Follow';
                btn.classList.toggle('btn--secondary', following);
                btn.classList.toggle('btn--primary', !following);

                const countEl = this._container.querySelector('[id="profile-follower-count"]');
                if (countEl) {
                    const current = parseInt(countEl.textContent, 10);
                    countEl.textContent = following ? current + 1 : current - 1;
                }
            } finally {
                btn.disabled = false;
            }
        });
    }

    _bindEditForm() {
        const toggleBtn = this._container.querySelector('[id="profile-edit-toggle"]');
        const form = this._container.querySelector('[id="profile-edit-form"]');
        const cancelBtn = this._container.querySelector('[id="profile-edit-cancel"]');
        const errorEl = this._container.querySelector('[id="profile-edit-error"]');
        const avatarInput = this._container.querySelector('[id="profile-avatar-input"]');
        const avatarPreview = this._container.querySelector('[id="profile-avatar-preview"]');
 
        if (!toggleBtn || !form) {
            return;
        }
 
        toggleBtn.addEventListener('click', () => {
            form.hidden     = false;
            toggleBtn.hidden = true;
        });
 
        cancelBtn.addEventListener('click', () => {
            form.hidden      = true;
            toggleBtn.hidden = false;
            errorEl.textContent = '';
        });
 
        avatarInput.addEventListener('change', () => {
            const file = avatarInput.files[0];
            if (!file) return;
            const url = URL.createObjectURL(file);
            avatarPreview.src = url;
        });
 
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorEl.textContent = '';
 
            const bio = form.querySelector('[id="profile-bio-input"]').value;
            const avatar = avatarInput.files[0] ?? undefined;
 
            const submit = form.querySelector('[type="submit"]');
            submit.disabled = true;
 
            try {
                await api.users.updateMe({ bio, avatar });
 
                const bioEl = this._container.querySelector('[id="profile-bio"]');
                if (bioEl) bioEl.textContent = bio;
 
                form.hidden = true;
                toggleBtn.hidden = false;
            } catch (err) {
                errorEl.textContent = err.message ?? 'Update failed. Please try again.';
            } finally {
                submit.disabled = false;
            }
        });
    }

    _buildLoadingHTML() {
        return `<main class="profile profile--loading" aria-busy="true"><p>Loading…</p></main>`;
    }
 
    _buildErrorHTML(message) {
        return `
<main class="profile profile--error">
    <p class="profile__error">${this._esc(message)}</p>
</main>`;
    }
 
    _buildProfileHTML(profile, isOwnProfile, isAuthenticated) {
        const avatarHTML = profile.avatar_url
            ? `<img
                   class="profile__avatar"
                   src="${this._esc(profile.avatar_url)}"
                   alt="${this._esc(profile.username)}'s avatar"
                   width="96"
                   height="96"
                   id="profile-avatar-preview"
               >`
            : `<span class="profile__avatar profile__avatar--placeholder" aria-hidden="true" id="profile-avatar-preview"></span>`;
 
        const bioHTML = profile.bio
            ? `<p class="profile__bio" id="profile-bio">${this._esc(profile.bio)}</p>`
            : `<p class="profile__bio profile__bio--empty" id="profile-bio"></p>`;
 
        const actionHTML = this._buildActionHTML(profile, isOwnProfile, isAuthenticated);
        const editFormHTML = isOwnProfile ? this._buildEditFormHTML(profile) : '';
 
        return `
<main class="profile">
    <header class="profile__header">
        ${avatarHTML}
 
        <div class="profile__info">
            <h1 class="profile__username">${this._esc(profile.username)}</h1>
 
            <ul class="profile__stats">
                <li class="profile__stat">
                    <span class="profile__stat-value">${this._esc(profile.post_count)}</span>
                    <span class="profile__stat-label">posts</span>
                </li>
                <li class="profile__stat">
                    <span class="profile__stat-value" id="profile-follower-count">${this._esc(profile.follower_count)}</span>
                    <span class="profile__stat-label">followers</span>
                </li>
                <li class="profile__stat">
                    <span class="profile__stat-value">${this._esc(profile.following_count)}</span>
                    <span class="profile__stat-label">following</span>
                </li>
            </ul>
 
            ${actionHTML}
        </div>
    </header>
 
    ${bioHTML}
    ${editFormHTML}
 
    <section class="profile__posts">
        <ul id="profile-posts-grid" class="profile__post-grid"></ul>
        <p id="profile-posts-empty" class="profile__no-posts" hidden>No posts yet.</p>
        <p id="profile-posts-error" class="profile__posts-error" aria-live="polite" hidden></p>
        <div id="profile-posts-sentinel" class="profile__sentinel" aria-hidden="true"></div>
    </section>
</main>`;
    }

    _buildActionHTML(profile, isOwnProfile, isAuthenticated) {
        if (isOwnProfile) {
            return `
<button id="profile-edit-toggle" class="btn btn--secondary">Edit profile</button>`;
        }
 
        if (isAuthenticated) {
            const following = profile.is_following;
            return `
<button
    id="profile-follow-btn"
    class="btn ${following ? 'btn--secondary' : 'btn--primary'}"
    data-following="${following}"
>${following ? 'Unfollow' : 'Follow'}</button>`;
        }
 
        return '';
    }
 
    _buildEditFormHTML(profile) {
        return `
<form id="profile-edit-form" class="form profile__edit-form" hidden novalidate>
    <div class="form__field">
        <label class="form__label" for="profile-avatar-input">Avatar</label>
        <input
            id="profile-avatar-input"
            class="form__input"
            type="file"
            name="avatar"
            accept="image/*"
        >
    </div>
 
    <div class="form__field">
        <label class="form__label" for="profile-bio-input">Bio</label>
        <textarea
            id="profile-bio-input"
            class="form__input form__textarea"
            name="bio"
            rows="3"
        >${this._esc(profile.bio ?? '')}</textarea>
    </div>
 
    <p class="form__global-error" id="profile-edit-error" aria-live="polite"></p>
 
    <div class="form__actions">
        <button class="btn btn--primary" type="submit">Save</button>
        <button class="btn btn--ghost" type="button" id="profile-edit-cancel">Cancel</button>
    </div>
</form>`;
    }

    _buildPostItemHTML(post) {
        const url = `/editor/${encodeURIComponent(post.id)}`;
        const alt = post.caption ? this._esc(post.caption) : `Post by ${this._esc(this._username)}`;
 
        return `
<a class="profile__post-link" href="${this._esc(url)}" data-link>
    <img
        class="profile__post-thumb"
        src="${this._esc(post.thumb_url)}"
        alt="${alt}"
        loading="lazy"
    >
</a>`;
    }
 
    _esc(value) {
        return String(value)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#39;');
    }
}