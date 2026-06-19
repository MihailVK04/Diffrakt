import api from '../api.js';

export class PostView {

    constructor(container, params) {
        this._container = container;
        this._postId = params.postId ? parseInt(params.postId, 10) : null;
    }

    async render() {
        this._container.innerHTML = `<main class="post-view"><p>Loading…</p></main>`;

        try {
            const raw = await api.posts.get(this._postId);
            const post = raw.post ?? raw;

            const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');
            const imageUrl = (post.processed_url ?? post.original_url ?? post.thumb_url ?? '');
            const fullUrl = imageUrl.startsWith('http') ? imageUrl : `${BASE}/${imageUrl.replace(/^\//, '')}`;

            const originalUrl = post.original_url?.startsWith('http') ? post.original_url : `${BASE}/${(post.original_url ?? '').replace(/^\//, '')}`;
            
            let processedBtnHTML = '';
            if (post.processed_url) {
                const processedUrl = post.processed_url.startsWith('http') ? post.processed_url : `${BASE}/${post.processed_url.replace(/^\//, '')}`;
                processedBtnHTML = `<a href="${this._esc(processedUrl)}" class="btn btn--primary" target="_blank" download>Download Processed</a>`;
            }

            this._container.innerHTML = `
<main class="post-view">
    <button class="btn btn--ghost post-view__back" id="post-back">← Back</button>
    <figure class="post-view__figure">
        <img
            class="post-view__image"
            src="${this._esc(fullUrl)}"
            alt="${post.caption ? this._esc(post.caption) : 'Post image'}"
        >
        ${post.caption ? `<figcaption class="post-view__caption">${this._esc(post.caption)}</figcaption>` : ''}
    </figure>

    <div class="post-view__actions" style="display: flex; gap: 1rem; margin-top: 1.5rem; justify-content: center;">
        <a href="${this._esc(originalUrl)}" class="btn btn--ghost" target="_blank" download="original_${post.id}.jpg">Download Original</a>
        ${processedBtnHTML}
    </div>
</main>`;

            this._container.querySelector('#post-back').addEventListener('click', () => {
                history.back();
            });

        } catch (err) {
            this._container.innerHTML = `<main class="post-view"><p class="post-view__error">${this._esc(err.message ?? 'Could not load post.')}</p></main>`;
        }
    }

    destroy() {}

    _esc(value) {
        return String(value)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#39;');
    }
}