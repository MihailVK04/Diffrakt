/**
 * app.js — Diffrakt SPA entry point
 *
 * Responsibilities:
 *  1. Define the client-side route table
 *  2. Handle popstate and in-app link navigation
 *  3. Resolve the current route, guard protected routes,
 *     instantiate the matching view class and call render()
 *  4. Expose a navigate(path) helper for views to trigger transitions
 *
 * View contract:
 *  Every view must be a class that accepts (container, params) in its
 *  constructor and exposes a render() method.
 *
 *  class SomeView {
 *      constructor(container, params) { ... }
 *      render() { ... }           // sync or async — app.js awaits it
 *      destroy() { ... }          // optional — called before next render
 *  }
 *
 * Route params:
 *  Dynamic segments are declared as :param in the path pattern and
 *  passed to the view constructor as a plain object, e.g.
 *  /posts/:id  →  params = { id: '42' }
 */

import api from './api.js';
import { Nav } from './components/nav.js';
import { FeedView } from './views/feed.js';
import { EditorView } from './views/editor.js';
import { ProfileView } from './views/profile.js';
import { HomeView } from './views/home.js';

const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');

const ROUTES = [
    { pattern: '/', view: HomeView, auth: false},
    { pattern: '/feed', view: FeedView, auth: true},
    { pattern: '/editor', view: EditorView, auth: true},
    { pattern: '/editor/:postId', view: EditorView, auth: true},
    { pattern: '/profile/:username', view: ProfileView, auth: false},
];

let _currentView = null;
let _currentUser = null;
let _userFetched = false;

const nav = new Nav(document.getElementById('nav'));

async function fetchCurrentUser() {
    try {
        const user = await api.auth.me();
        return user ?? null;
    } catch {
        return null;
    }
}

async function refreshUser() {
    _currentUser = await fetchCurrentUser();
    _userFetched = true;
    nav.render(_currentUser);
    return _currentUser;
}

function compilePattern(pattern) {
    const keys = [];
    const regexSource = pattern
        .replace(/\//g, '\\/')
        .replace(/:([a-zA-Z_][a-zA-Z0-9_]*)/g, (_, key) => {
            keys.push(key);
            return '([^\\/]+)';
        });
    return { regex: new RegExp(`^${regexSource}$`), keys };
}

function matchRoute(pathname) {
    for (const route of ROUTES) {
        const { regex, keys } = compilePattern(route.pattern);
        const match = pathname.match(regex);
        if (match) {
            const params = {};
            keys.forEach((key, i) => {
                params[key] = decodeURIComponent(match[i+1]);
            });
            return { route, params };
        }
    }

    return null;
}

async function renderRoute(pathname) {
    const container = document.getElementById('app');
    if (!container) {
        console.error('[app] #app container not found in DOM');
        return;
    }

    const matched = matchRoute(pathname);
    if (!matched) {
        renderNotFound(container);
        return;
    }

    const { route, params } = matched;

    if (route.auth) {
        if (!_userFetched) {
            _currentUser = await fetchCurrentUser();
            _userFetched = true;
        }
        if (!_currentUser) {
            navigate('/');
            return;
        }
    }

    if (_currentView && typeof _currentView.destroy === 'function') {
        _currentView.destroy();
    }
    _currentView = null;

    container.innerHTML = '';

    try {
        const view = new route.view(container, params);
        _currentView = view;
        await view.render();
        _updateActiveLink(pathname);
    } catch (err) {
        console.error('[app] View render error:', err);
        renderError(container, err);
    }
}

function renderNotFound(container) {
    container.innerHTML = `
        <div class="app-error">
            <h2>404</h2>
            <p>Page not found.</p>
            <a href="/feed" data-link>Go to feed</a>
        </div>
    `;
}

function renderError(container, err) {
    const msg = document.createElement('p');
    msg.textContent = err.message ?? 'Unknown error';
    container.innerHTML = '<div class="app-error"><h2>Something went wrong</h2></div>';
    container.querySelector('.app-error').appendChild(msg);
}

function navigate(path, _state = {}) {
    window.history.pushState(_state, '', BASE + path);
    renderRoute(path);
}

function handleLinkClick(e) {
    const anchor = e.target.closest('a[data-link]');
    if (!anchor) return;
 
    const href = anchor.getAttribute('href');
    if (!href || href.startsWith('http') || href.startsWith('//')) return;
 
    e.preventDefault();
    navigate(href);
}

function handlePopState() {
    renderRoute(window.location.pathname.replace(BASE, '') || '/');
}

window.app = {
    navigate,
    refreshUser,
    getCurrentUser: () => _currentUser,
};

document.addEventListener('DOMContentLoaded', async () => {
    document.addEventListener('click', handleLinkClick);
    window.addEventListener('popstate', handlePopState);

    _currentUser = await fetchCurrentUser();
    _userFetched = true;
    nav.render(_currentUser);

    renderRoute(window.location.pathname.replace(BASE, '') || '/');
});

function _updateActiveLink(path) {
    const links = document.querySelectorAll('#nav .nav__link');
    for (const link of links) {
        const href = link.getAttribute('href');
        const isActive = href === path || (href !== '/' && path.startsWith(href));
        link.setAttribute('aria-current', isActive ? 'page' : 'false');
    }
}