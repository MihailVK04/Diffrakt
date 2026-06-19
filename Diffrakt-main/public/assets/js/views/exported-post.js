import api from '../api.js';

export class ExportedPostView {

    constructor(container, params) {
        this._container = container;
        this._postId = params.postId ? parseInt(params.postId, 10) : null;
    }

    async render() {
        const nav = document.getElementById('nav');
        if (nav) nav.style.display = 'none';

        this._container.innerHTML = `<main class="post-view"><p>Loading…</p></main>`;

        try {
            const raw = await api.posts.get(this._postId);
            const post = raw.post ?? raw;

            const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');
            const imageUrl = (post.processed_url ?? post.original_url ?? post.thumb_url ?? '');
            const fullUrl = imageUrl.startsWith('http') ? imageUrl : `${BASE}/${imageUrl.replace(/^\//, '')}`;

            this._container.innerHTML = `
<main class="post-view" style="padding-top: 2rem;">
    <figure class="post-view__figure">
        <img
            class="post-view__image"
            src="${this._esc(fullUrl)}"
            alt="${post.caption ? this._esc(post.caption) : 'Post image'}"
        >
        ${post.caption ? `<figcaption class="post-view__caption">${this._esc(post.caption)}</figcaption>` : ''}
    </figure>
</main>`;

        } catch (err) {
            this._container.innerHTML = `<main class="post-view"><p class="post-view__error">${this._esc(err.message ?? 'Could not load post.')}</p></main>`;
        }
    }

    destroy() {
        const nav = document.getElementById('nav');
        if (nav) nav.style.display = '';
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