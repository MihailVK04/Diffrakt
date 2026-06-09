/**
 * public/assets/js/components/nav.js — Nav
 *
 * Persistent top navigation bar. Rendered outside the #app container so it
 * survives route transitions without being re-created on every render.
 *
 * Authenticated links: Feed | Editor | Profile | Log out
 * Unauthenticated:     hidden entirely (home page has its own auth UI)
 *
 * Usage (called from app.js after auth state is known):
 *   import { Nav } from './components/nav.js';
 *
 *   const nav = new Nav(document.getElementById('nav'));
 *   nav.render(currentUser);   // pass null when logged out
 *   nav.destroy();             // call before re-rendering
 */
 
import api from '../api.js';
 
export class Nav {
 
    constructor(container) {
        this._container = container;
        this._listeners = [];
    }
 
    /**
     * @param {object|null} user  — the current user object, or null if logged out.
     *                              Shape: { id, username, email }
     */
    render(user) {
        if (!user) {
            this._container.innerHTML = '';
            this._container.hidden = true;
            return;
        }
 
        this._container.hidden = false;
        this._container.innerHTML = this._buildHTML(user);
 
        this._bindLogout();
    }
 
    destroy() {
        for (const { el, type, fn } of this._listeners) {
            el.removeEventListener(type, fn);
        }
        this._listeners = [];
    }
 
    _buildHTML(user) {
        const profileHref = `/profile/${encodeURIComponent(user.username)}`;
 
        return `
<div class="nav__inner">
    <a class="nav__brand" href="/feed" data-link>Diffrakt</a>
 
    <ul class="nav__links" role="list">
        <li>
            <a class="nav__link" href="/feed" data-link>Feed</a>
        </li>
        <li>
            <a class="nav__link" href="/editor" data-link>Editor</a>
        </li>
        <li>
            <a class="nav__link" href="${this._esc(profileHref)}" data-link>
                ${this._esc(user.username)}
            </a>
        </li>
    </ul>
 
    <button class="nav__logout btn btn--ghost" type="button" id="nav-logout-btn">
        Log out
    </button>
</div>`;
    }
 
    _bindLogout() {
        const btn = this._container.querySelector('#nav-logout-btn');
        if (!btn) return;
 
        const handler = async () => {
            btn.disabled = true;
            try {
                await api.auth.logout();
            } catch {
                // session may already be gone — proceed regardless
            } finally {
                btn.disabled = false;
            }
            await window.app.refreshUser();
            window.app.navigate('/');
        };
 
        btn.addEventListener('click', handler);
        this._listeners.push({ el: btn, type: 'click', fn: handler });
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