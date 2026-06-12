import api from '../api.js';
 
const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');
 
const POLL_INTERVAL_MS  = 3000;
const HISTORY_PAGE_SIZE = 30;
 
export class ChatView {
 
    constructor(container, params) {
        this._container  = container;
        this._params     = params;
 
        this._conversations    = [];
        this._activeId         = null;

        this._messages         = [];
        this._histCursor       = null;
        this._histHasMore      = true;
        this._histLoading      = false;
        this._lastMessageId    = 0;
        this._pollTimer        = null;
        this._abortCtrl        = null;
 
        this._convList         = null;
        this._thread           = null;
        this._messageList      = null;
        this._topSentinel      = null;
        this._histObserver     = null;
        this._sendForm         = null;
        this._sendInput        = null;
        this._sendBtn          = null;
        this._threadEmpty      = null;
        this._threadError      = null;
        this._newConvBtn       = null;
        this._newConvPanel     = null;
        this._newConvSearch    = null;
        this._newConvResults   = null;
        this._newConvError     = null;
    }
 
    async render() {
        this._container.innerHTML = this._buildShellHTML();
        this._bindRefs();
        this._bindListeners();
        await this._loadConversations();
 
        if (this._params?.convId) {
            const id = parseInt(this._params.convId, 10);
            if (!isNaN(id)) {
                await this._openConversation(id);
            }
        }
    }
 
    destroy() {
        this._stopPoll();
 
        if (this._histObserver) {
            this._histObserver.disconnect();
            this._histObserver = null;
        }
 
        if (this._abortCtrl) {
            this._abortCtrl.abort();
            this._abortCtrl = null;
        }
    }
 
    _bindRefs() {
        const q = id => this._container.querySelector(`[data-ref="${id}"]`);
 
        this._convList        = q('conv-list');
        this._thread          = q('thread');
        this._messageList     = q('message-list');
        this._topSentinel     = q('top-sentinel');
        this._sendForm        = q('send-form');
        this._sendInput       = q('send-input');
        this._sendBtn         = q('send-btn');
        this._threadEmpty     = q('thread-empty');
        this._threadError     = q('thread-error');
        this._newConvBtn      = q('new-conv-btn');
        this._newConvPanel    = q('new-conv-panel');
        this._newConvSearch   = q('new-conv-search');
        this._newConvResults  = q('new-conv-results');
        this._newConvError    = q('new-conv-error');
    }
 
    _bindListeners() {
        this._convList.addEventListener('click', e => {
            const item = e.target.closest('[data-conv-id]');
            if (item) {
                this._openConversation(parseInt(item.dataset.convId, 10));
            }
        });
 
        this._sendForm.addEventListener('submit', e => {
            e.preventDefault();
            this._sendMessage();
        });
 
        this._sendInput.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this._sendMessage();
            }
        });
 
        this._newConvBtn.addEventListener('click', () => {
            this._toggleNewConvPanel();
        });
 
        let searchTimer = null;
        this._newConvSearch.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => this._searchUsers(), 250);
        });
 
        this._histObserver = new IntersectionObserver(entries => {
            if (entries[0].isIntersecting) {
                this._loadHistoryPage();
            }
        });
        this._histObserver.observe(this._topSentinel);
    }
 
    async _loadConversations() {
        try {
            const data = await api.chat.listConversations();
            this._conversations = data.conversations ?? [];
            this._renderConvList();
        } catch (err) {
            this._convList.innerHTML = `<p class="chat__conv-error">${this._esc(err.message)}</p>`;
        }
    }
 
    _renderConvList() {
        if (this._conversations.length === 0) {
            this._convList.innerHTML = `
                <p class="chat__conv-empty">
                    No conversations yet. Start one by clicking <strong>+</strong> above.
                </p>`;
            return;
        }
 
        const fragment = document.createDocumentFragment();
 
        for (const conv of this._conversations) {
            const li = document.createElement('li');
            li.className = 'chat__conv-item';
            li.dataset.convId = conv.id;
 
            if (conv.id === this._activeId) {
                li.classList.add('chat__conv-item--active');
            }
 
            const avatarHTML = conv.other_user.avatar_url
                ? `<img class="chat__conv-avatar" src="${this._esc(this._absUrl(conv.other_user.avatar_url))}" alt="" width="36" height="36">`
                : `<span class="chat__conv-avatar chat__conv-avatar--placeholder" aria-hidden="true"></span>`;
 
            const previewText = conv.last_message_body
                ? this._esc(this._truncate(conv.last_message_body, 45))
                : '<em>No messages yet</em>';
 
            li.innerHTML = `
                <div class="chat__conv-avatar-wrap">${avatarHTML}</div>
                <div class="chat__conv-meta">
                    <span class="chat__conv-username">${this._esc(conv.other_user.username)}</span>
                    <span class="chat__conv-preview">${previewText}</span>
                </div>`;
 
            fragment.appendChild(li);
        }
 
        this._convList.innerHTML = '';
        this._convList.appendChild(fragment);
    }
 
    async _openConversation(id) {
        this._stopPoll();
 
        this._activeId      = id;
        this._messages      = [];
        this._histCursor    = null;
        this._histHasMore   = true;
        this._lastMessageId = 0;
 
        for (const el of this._convList.querySelectorAll('[data-conv-id]')) {
            el.classList.toggle('chat__conv-item--active', parseInt(el.dataset.convId, 10) === id);
        }
 
        this._thread.hidden = false;
        this._messageList.innerHTML = '';
        this._setThreadError(null);
        this._threadEmpty.hidden = true;
        this._sendInput.value = '';
 
        const newPath = `/chat/${id}`;
        window.history.replaceState({}, '', BASE + newPath);
 
        await this._loadHistoryPage();
 
        this._startPoll();
    }
 
    async _loadHistoryPage() {
        if (this._histLoading || !this._histHasMore || this._activeId === null) {
            return;
        }
 
        this._histLoading = true;
 
        const prevHeight = this._messageList.scrollHeight;
 
        try {
            const qs   = this._histCursor !== null ? `?cursor=${this._histCursor}` : '';
            const data = await api.chat.getMessages(this._activeId, qs);
            const msgs = data.messages ?? [];
 
            if (msgs.length === 0 && this._messages.length === 0) {
                this._threadEmpty.hidden = false;
            }
 
            this._histCursor = data.next_cursor ?? null;
            this._histHasMore = this._histCursor !== null;
 
            if (msgs.length > 0) {
                this._prependMessages(msgs);
 
                if (this._lastMessageId === 0) {
                    this._lastMessageId = this._messages[this._messages.length - 1]?.id ?? 0;
                }
            }
 
            if (!this._histHasMore && this._histObserver) {
                this._histObserver.unobserve(this._topSentinel);
            }
 
            if (prevHeight === 0) {
                this._messageList.scrollTop = this._messageList.scrollHeight;
            } else {
                this._messageList.scrollTop = this._messageList.scrollHeight - prevHeight;
            }
 
        } catch (err) {
            this._setThreadError(err.message ?? 'Could not load messages.');
        } finally {
            this._histLoading = false;
        }
    }
 
    _prependMessages(msgs) {
        const currentUser = window.app.getCurrentUser();
        const fragment    = document.createDocumentFragment();
 
        for (const msg of msgs) {
            this._messages.unshift(msg);
            fragment.appendChild(this._buildMessageEl(msg, currentUser));
        }
 
        this._messageList.prepend(fragment);
    }
 
    _startPoll() {
        this._pollTimer = setInterval(() => this._poll(), POLL_INTERVAL_MS);
    }
 
    _stopPoll() {
        if (this._pollTimer !== null) {
            clearInterval(this._pollTimer);
            this._pollTimer = null;
        }
    }
 
    async _poll() {
        if (this._activeId === null) {
            return;
        }
 
        try {
            const data = await api.chat.getMessages(this._activeId, `?after=${this._lastMessageId}`);
            const newMsgs = data.messages ?? [];
 
            if (newMsgs.length === 0) {
                return;
            }
 
            const currentUser  = window.app.getCurrentUser();
            const wasAtBottom  = this._isScrolledToBottom();
            const fragment     = document.createDocumentFragment();
 
            for (const msg of newMsgs) {
                this._messages.push(msg);
                fragment.appendChild(this._buildMessageEl(msg, currentUser));
            }
 
            this._messageList.appendChild(fragment);
            this._lastMessageId = newMsgs[newMsgs.length - 1].id;
            this._threadEmpty.hidden = true;
 
            if (wasAtBottom) {
                this._messageList.scrollTop = this._messageList.scrollHeight;
            }

            this._loadConversations();
 
        } catch {
        }
    }
 
    async _sendMessage() {
        const body = this._sendInput.value.trim();
        if (!body || this._activeId === null) {
            return;
        }
 
        this._sendBtn.disabled  = true;
        this._sendInput.disabled = true;
 
        try {
            const data = await api.chat.sendMessage(this._activeId, body);
            const msg  = data.message;
 
            this._sendInput.value = '';
 
            const currentUser = window.app.getCurrentUser();
            const el = this._buildMessageEl(msg, currentUser);
            this._messageList.appendChild(el);
            this._messageList.scrollTop = this._messageList.scrollHeight;
 
            this._messages.push(msg);
            this._lastMessageId = msg.id;
            this._threadEmpty.hidden = true;
 
            this._loadConversations();
 
        } catch (err) {
            this._setThreadError(err.message ?? 'Could not send message.');
        } finally {
            this._sendBtn.disabled   = false;
            this._sendInput.disabled = false;
            this._sendInput.focus();
        }
    }
 
    _toggleNewConvPanel() {
        const hidden = this._newConvPanel.hidden;
        this._newConvPanel.hidden = !hidden;
 
        if (!hidden) {
            this._newConvSearch.value   = '';
            this._newConvResults.innerHTML = '';
            this._newConvError.hidden   = true;
        } else {
            this._newConvSearch.focus();
        }
    }
 
    async _searchUsers() {
        const q = this._newConvSearch.value.trim();
 
        if (q.length < 2) {
            this._newConvResults.innerHTML = '';
            return;
        }
 
        try {
            const data  = await api.users.search(q);
            const users = data.users ?? [];
            this._renderNewConvResults(users);
        } catch {
        }
    }
 
    _renderNewConvResults(users) {
        if (users.length === 0) {
            this._newConvResults.innerHTML = `<li class="chat__new-conv-none">No mutual follows found.</li>`;
            return;
        }
 
        const fragment = document.createDocumentFragment();
 
        for (const user of users) {
            const li = document.createElement('li');
            li.className = 'chat__new-conv-result';
 
            const avatarHTML = user.avatar_url
                ? `<img class="chat__new-conv-avatar" src="${this._esc(this._absUrl(user.avatar_url))}" alt="" width="28" height="28">`
                : `<span class="chat__new-conv-avatar chat__new-conv-avatar--placeholder" aria-hidden="true"></span>`;
 
            li.innerHTML = `
                ${avatarHTML}
                <span class="chat__new-conv-name">${this._esc(user.username)}</span>`;
 
            li.addEventListener('click', () => this._startConversation(user.username));
            fragment.appendChild(li);
        }
 
        this._newConvResults.innerHTML = '';
        this._newConvResults.appendChild(fragment);
    }
 
    async _startConversation(username) {
        this._newConvError.hidden = true;
 
        try {
            const data = await api.chat.createConversation(username);
            const conv = data.conversation;
 
            this._newConvPanel.hidden = true;
            this._newConvSearch.value = '';
            this._newConvResults.innerHTML = '';
 
            // Add to list if not already there, then open
            const exists = this._conversations.find(c => c.id === conv.id);
 
            if (!exists) {
                this._conversations.push({
                    id: conv.id,
                    created_at: conv.created_at,
                    other_user: conv.other_user,
                    last_message_body: null,
                    last_message_at: null,
                });
                this._renderConvList();
            }
 
            await this._openConversation(conv.id);
 
        } catch (err) {
            this._newConvError.textContent = err.message ?? 'Could not open conversation.';
            this._newConvError.hidden = false;
        }
    }
 
    _buildMessageEl(msg, currentUser) {
        const isMine = currentUser && msg.sender_id === currentUser.id;
        const el     = document.createElement('li');
 
        el.className = `chat__message ${isMine ? 'chat__message--mine' : 'chat__message--theirs'}`;
        el.dataset.msgId = msg.id;
 
        const date      = new Date(msg.created_at);
        const timeLabel = date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
        const dateISO   = date.toISOString();
 
        el.innerHTML = `
            <span class="chat__message-body">${this._esc(msg.body)}</span>
            <time class="chat__message-time" datetime="${dateISO}">${timeLabel}</time>`;
 
        return el;
    }
 
    _buildShellHTML() {
        return `
<main class="chat">
    <aside class="chat__sidebar">
        <header class="chat__sidebar-header">
            <h1 class="chat__sidebar-title">Messages</h1>
            <button
                class="chat__new-conv-btn"
                data-ref="new-conv-btn"
                aria-label="Start new conversation"
                title="Start new conversation"
            >+</button>
        </header>
 
        <div class="chat__new-conv-panel" data-ref="new-conv-panel" hidden>
            <input
                class="chat__new-conv-search"
                data-ref="new-conv-search"
                type="search"
                placeholder="Search by username…"
                autocomplete="off"
            >
            <p class="chat__new-conv-error" data-ref="new-conv-error" aria-live="polite" hidden></p>
            <ul class="chat__new-conv-results" data-ref="new-conv-results"></ul>
        </div>
 
        <ul class="chat__conv-list" data-ref="conv-list"></ul>
    </aside>
 
    <section class="chat__thread" data-ref="thread" hidden>
        <div class="chat__message-wrap">
            <div data-ref="top-sentinel" class="chat__top-sentinel" aria-hidden="true"></div>
 
            <ul class="chat__message-list" data-ref="message-list"></ul>
 
            <p class="chat__thread-empty" data-ref="thread-empty" hidden>
                No messages yet. Say hello!
            </p>
 
            <p class="chat__thread-error" data-ref="thread-error" aria-live="polite" hidden></p>
        </div>
 
        <div class="chat__send-wrap">
            <div data-ref="send-form" class="chat__send-form">
                <textarea
                    class="chat__send-input"
                    data-ref="send-input"
                    placeholder="Write a message…"
                    rows="1"
                    maxlength="2000"
                    autocomplete="off"
                ></textarea>
                <button class="chat__send-btn" data-ref="send-btn" type="button">Send</button>
            </div>
        </div>
    </section>
 
    <div class="chat__placeholder" id="chat-placeholder">
        <p>Select a conversation or start a new one.</p>
    </div>
</main>`;
    }

    _isScrolledToBottom() {
        const el = this._messageList;
        return el.scrollHeight - el.scrollTop - el.clientHeight < 40;
    }
 
    _setThreadError(msg) {
        if (msg) {
            this._threadError.textContent = msg;
            this._threadError.hidden = false;
        } else {
            this._threadError.textContent = '';
            this._threadError.hidden = true;
        }
    }
 
    _absUrl(url) {
        if (!url) return '';
        if (url.startsWith('http')) return url;
        return `${BASE}/${url.replace(/^\//, '')}`;
    }
 
    _truncate(str, max) {
        return str.length > max ? str.slice(0, max) + '…' : str;
    }
 
    _esc(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
}
