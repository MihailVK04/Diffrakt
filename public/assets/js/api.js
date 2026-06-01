const BASE_URL = '/api/v1';
const TOKEN_KEY = 'diffrakt_token';

// ---------------------------------------------------------------------------
// Token store
//
// The token lives only in memory (a plain JS variable) for the lifetime of the
// tab. On hard reload the user must log in again — this is intentional and safe
// because storing JWTs in localStorage exposes them to XSS.
//
// If you need persistence across reloads (e.g. "remember me"), swap
// _tokenStore for a sessionStorage or localStorage backed version here — the
// rest of the code never changes.
// ---------------------------------------------------------------------------

let _token = null;

const tokenStore = {
    get() { return _token},
    set(t) { _token = t; },
    clear() { _token = null; },
};

async function _request(method, path, body = null, opts = {}) {
    const headers = {};

    const token = tokenStore.get();
    if (token) {
        headers['Authorization'] = 'Bearer ${token}';
    }

    const init = {
        method,
        headers,
        signal: opts.signal ?? null,
    };

    if (opts.formData) {
        init.body = opts.formData;
    } else if (body !== null) {
        headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(body);
    }

    const response = await fetch('${BASE_URL}${path}', init);

    let data;
    try {
        data = await response.json();
    } catch {
        data = {};
    }

    if (!response.ok) {
        throw new ApiError(response.status, data);
    }

    return data;
}

class ApiError extends Error {
    constructor(status, data) {
        super(data?.error ?? 'HTTP ${status}');
        this.status = status;
        this.errors = data?.errors ?? {};
        this.data = data;
    }
}

const get = (path, opts) => _request('GET', path, null, opts);
const post = (path, body, opts) => _request('POST', path, body, opts);
const patch = (path, body, opts) => _request('PATCH', path, body, opts);
const put = (path, body, opts) => _request('PUT', path, body, opts);
const del = (path, opts) => _request('DELETE', path, null, opts);

const auth = {

    async register(username, email, password) {
        const data = await post('/auth/register', { username, email, password });
        tokenStore.set(data.token);
        return data;
    },

    async login(email, password) {
        const data = await post('/auth/login', { email, password });
        tokenStore.set(data.token);
        return data;
    },

    async logout() {
        try {
            await post('/auth/logout');
        } finally {
            tokenStore.clear();
        }
    },

    me() {
        return get('/auth/me');
    },

    getToken: () => tokenStore.get(),
    setToken: (t) => tokenStore.set(t),
    clearToken: () => tokenStore.clear(),
    isLoggedIn: () => tokenStore.get() !== null,
};

const users = {

    getProfile(username) {
        return get('/users/${encodeURIComponent(username)}');
    },

    updateMe({ bio, avatar } = {}) {
        const fd = new FormData();

        if (bio !== undefined) {
            fd.append('bio', bio)
        }

        if (avatar !== undefined) {
            fd.append('avatar', avatar)
        }

        return _request('PATCH', '/users/me', null, {formData: fd});
    },

    follow(username) {
        return post('/users/${encodeURIComponent(username)}/follow');
    },

    unfollow(username) {
        return del('/users/${encodeURIComponent(username)}/follow');
    },
};

const posts = {

    upload(imageFile, caption, visibility = 'public') {
        const fd = new FormData();
        fd.append('image', imageFile);
        fd.append('caption', caption);
        fd.append('visibility', visibility);
        return _request('POST', '/posts', null, { formData: fd });
    },

    get(id) {
        return get('/posts/${id}');
    },

    update(id, fields) {
        return patch('/posts/${id}', fields);
    },

    delete(id) {
        return del('/posts/${id}');
    },

    export(postId, pipelineId) {
        return post('/posts/${postId}/export', { pipeline_id: pipelineId });
    },
};

const filters = {

    list() {
        return get('/filters');
    },

    get(id) {
        return get('/filters/${id}');
    },

    create(name, pipelineId) {
        return post('/filters', { name, pipeline_id: pipelineId });
    },

    delete(id) {
        return del('/filters/${id}');
    },
};

const pipelines = {

    get(id) {
        return get('/pipelines/${id}');
    },

    create(name) {
        return post('/pipelines/', { name });
    },

    replaceSteps(id, steps) {
        return put('/pipelines/${id}/steps', steps);
    },

    delete(id) {
        return del('/pipelines/${id}');
    },

    apply(id, imageB64, signal) {
        return post('/pipelines/${id}/apply', { image_b64: imageB64 }, { signal });
    },
};

const feed = {
    
    get(cursor = null) {
        const qs = cursor !== null ? '?cursor=${cursor}' : '';
        return get('/feed${qs}');
    },
};

const api = {
    auth,
    users,
    posts,
    filters,
    pipelines,
    feed,
    ApiError,
};

export default api;
export { ApiError };