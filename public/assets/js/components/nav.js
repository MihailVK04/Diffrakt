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
        this._bindSearch();
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

    <div class="nav__search">
        <input
            class="nav__search-input"
            type="search"
            placeholder="Search users…"
            autocomplete="off"
            aria-label="Search users"
            id="nav-search-input"
        />
        <ul class="nav__search-dropdown" id="nav-search-dropdown" role="listbox" hidden></ul>
    </div>
 
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

    _bindSearch() {
        const input    = this._container.querySelector('#nav-search-input');
        const dropdown = this._container.querySelector('#nav-search-dropdown');
        if (!input || !dropdown) return;

        let debounceTimer = null;
        let activeIndex   = -1;

        const showResults = (users) => {
            if (!users.length) {
                dropdown.hidden = true;
                dropdown.innerHTML = '';
                return;
            }

            dropdown.innerHTML = users.map((u, i) => `
                <li class="nav__search-item" role="option" data-username="${this._esc(u.username)}" data-index="${i}">
                    <span class="nav__search-username">${this._esc(u.username)}</span>
                    ${u.bio ? `<span class="nav__search-bio">${this._esc(u.bio.slice(0, 40))}</span>` : ''}
                </li>
            `).join('');

            activeIndex = -1;
            dropdown.hidden = false;
        };

        const closeDropdown = () => {
            dropdown.hidden = true;
            dropdown.innerHTML = '';
            activeIndex = -1;
        };

        const navigateToUser = (username) => {
            closeDropdown();
            input.value = '';
            window.app.navigate(`/profile/${encodeURIComponent(username)}`);
        };

        // Debounced input handler
        const onInput = () => {
            clearTimeout(debounceTimer);
            const q = input.value.trim();
            if (q.length < 2) { closeDropdown(); return; }

            debounceTimer = setTimeout(async () => {
                try {
                    const data = await api.users.search(q);
                    showResults(data.users ?? []);
                } catch {
                    closeDropdown();
                }
            }, 300);
        };

        // Keyboard navigation
        const onKeydown = (e) => {
            const items = [...dropdown.querySelectorAll('.nav__search-item')];
            if (!items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = (activeIndex + 1) % items.length;
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = (activeIndex - 1 + items.length) % items.length;
            } else if (e.key === 'Enter' && activeIndex >= 0) {
                e.preventDefault();
                navigateToUser(items[activeIndex].dataset.username);
                return;
            } else if (e.key === 'Escape') {
                closeDropdown();
                return;
            }

            items.forEach((el, i) => el.classList.toggle('is-active', i === activeIndex));
        };

        // Click on a result
        const onDropdownClick = (e) => {
            const item = e.target.closest('.nav__search-item');
            if (item) navigateToUser(item.dataset.username);
        };

        // Close on outside click
        const onDocumentClick = (e) => {
            if (!this._container.contains(e.target)) closeDropdown();
        };

        input.addEventListener('input', onInput);
        input.addEventListener('keydown', onKeydown);
        dropdown.addEventListener('click', onDropdownClick);
        document.addEventListener('click', onDocumentClick);

        this._listeners.push(
            { el: input,    type: 'input',   fn: onInput },
            { el: input,    type: 'keydown', fn: onKeydown },
            { el: dropdown, type: 'click',   fn: onDropdownClick },
            { el: document, type: 'click',   fn: onDocumentClick },
        );
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