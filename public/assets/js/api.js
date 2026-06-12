const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');
const BASE_URL = `${BASE}/api/v1`;

async function _request(method, path, body = null, opts = {}) {
    const headers = {};

    const init = {
        method,
        headers,
        credentials: 'include',
        signal: opts.signal ?? null,
    };

    if (opts.formData) {
        init.body = opts.formData;
    } else if (body !== null) {
        headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(body);
    }

    const response = await fetch(`${BASE_URL}${path}`, init);

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
        super(data?.error ?? `HTTP ${status}`);
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
        return post('/auth/register', { username, email, password });
    },

    async login(email, password) {
        return post('/auth/login', { email, password });
    },

    async logout() {
        return post('/auth/logout', null);
    },

    me() {
        return get('/auth/me');
    },
};

const users = {

    getProfile(username) {
        return get(`/users/${encodeURIComponent(username)}`);
    },

    updateMe({ bio, avatar } = {}) {
        const fd = new FormData();

        if (bio !== undefined) {
            fd.append('bio', bio)
        }

        if (avatar !== undefined) {
            fd.append('avatar', avatar)
        }

        return _request('POST', '/users/me', null, {formData: fd});
    },

    follow(username) {
        return post(`/users/${encodeURIComponent(username)}/follow`);
    },

    unfollow(username) {
        return del(`/users/${encodeURIComponent(username)}/follow`);
    },

    getPosts(username, cursor = null) {
        const qs = cursor !== null ? `?cursor=${cursor}` : '';
        return get(`/users/${encodeURIComponent(username)}/posts${qs}`);
    },

    search(q) {
        return get(`/users/search?q=${encodeURIComponent(q)}`);
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
        return get(`/posts/${id}`);
    },

    update(id, fields) {
        return patch(`/posts/${id}`, fields);
    },

    delete(id) {
        return del(`/posts/${id}`);
    },

    export(postId, pipelineId) {
        return post(`/posts/${postId}/export`, { pipeline_id: pipelineId });
    },

    publish(postId, pipelineId) {
        return post(`/posts/${postId}/publish`, { pipeline_id: pipelineId });
    },
};

const filters = {

    list() {
        return get('/filters');
    },

    get(id) {
        return get(`/filters/${id}`);
    },

    create(name, pipelineId) {
        return post('/filters', { name, pipeline_id: pipelineId });
    },

    delete(id) {
        return del(`/filters/${id}`);
    },
};

const pipelines = {

    get(id) {
        return get(`/pipelines/${id}`);
    },

    create(name) {
        return post('/pipelines', { name });
    },

    replaceSteps(id, steps) {
        return put(`/pipelines/${id}/steps`, { steps } );
    },

    delete(id) {
        return del(`/pipelines/${id}`);
    },

    // existing post
    applyToPost(id, postId, signal) {
        return post(`/pipelines/${id}/apply`, { post_id: postId }, { signal });
    },

    // raw canvas image
    preview(id, imageB64, signal) {
        return post(`/pipelines/${id}/preview`, { image_b64: imageB64 }, { signal });
    },
};

const feed = {
    
    get(cursor = null) {
        const qs = cursor !== null ? `?cursor=${cursor}` : '';
        return get(`/feed${qs}`);
    },
};

const chat = {

    listConversations() {
        return get('/chat/conversations');
    },

    createConversation(username) {
        return post('/chat/conversations', { username });
    },

    getMessages(id, qs = '') {
        return get(`/chat/conversations/${id}/messages${qs}`);
    },
    
    sendMessage(id, body) {
        return post(`/chat/conversations/${id}/messages`, { body });
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