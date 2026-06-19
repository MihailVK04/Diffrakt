import api from '../api.js';

export class HomeView {

    constructor(container, params) {
        this._container = container;
        this._params = params;

        this._listeners = [];
    }

    async render() {
        const user = window.app.getCurrentUser();

        if (user) {
            window.app.navigate('/feed');
            return;
        }

        this._container.innerHTML = this._buildHTML();

        this._loginForm = this._container.querySelector('#login-form');
        this._registerForm = this._container.querySelector('#register-form');
        this._tabLogin = this._container.querySelector('#tab-login');
        this._tabRegister = this._container.querySelector('#tab-register');

        this._bindTabs();
        this._bindLoginForm();
        this._bindRegisterForm();
    }

    destroy() {
        for (const { el, type, fn } of this._listeners) {
            el.removeEventListener(type, fn);
        }
        this._listeners = [];
    }

    _buildHTML() {
        return `
<main class="home">
    <section class="home__hero">
        <h1 class="home__title">Diffrakt</h1>
        <p class="home__tagline">Upload photos. Build filter pipelines. Share the result.</p>
    </section>
 
    <section class="home__auth">
        <div class="tabs" role="tablist">
            <button
                id="tab-login"
                class="tabs__tab tabs__tab--active"
                role="tab"
                aria-selected="true"
                aria-controls="panel-login"
            >Log in</button>
            <button
                id="tab-register"
                class="tabs__tab"
                role="tab"
                aria-selected="false"
                aria-controls="panel-register"
            >Register</button>
        </div>
 
        <!-- Login panel -->
        <div id="panel-login" class="tabs__panel" role="tabpanel" aria-labelledby="tab-login">
            <form id="login-form" class="form" novalidate>
                <div class="form__field">
                    <label class="form__label" for="login-email">Email</label>
                    <input
                        id="login-email"
                        class="form__input"
                        type="email"
                        name="email"
                        autocomplete="email"
                        required
                    >
                    <span class="form__error" id="login-email-error" aria-live="polite"></span>
                </div>
 
                <div class="form__field">
                    <label class="form__label" for="login-password">Password</label>
                    <input
                        id="login-password"
                        class="form__input"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >
                    <span class="form__error" id="login-password-error" aria-live="polite"></span>
                </div>
 
                <p class="form__global-error" id="login-global-error" aria-live="polite"></p>
 
                <button class="form__submit btn btn--primary" type="submit">Log in</button>
            </form>
        </div>
 
        <!-- Register panel -->
        <div id="panel-register" class="tabs__panel tabs__panel--hidden" role="tabpanel" aria-labelledby="tab-register">
            <form id="register-form" class="form" novalidate>
                <div class="form__field">
                    <label class="form__label" for="reg-username">Username</label>
                    <input
                        id="reg-username"
                        class="form__input"
                        type="text"
                        name="username"
                        autocomplete="username"
                        required
                    >
                    <span class="form__error" id="reg-username-error" aria-live="polite"></span>
                </div>
 
                <div class="form__field">
                    <label class="form__label" for="reg-email">Email</label>
                    <input
                        id="reg-email"
                        class="form__input"
                        type="email"
                        name="email"
                        autocomplete="email"
                        required
                    >
                    <span class="form__error" id="reg-email-error" aria-live="polite"></span>
                </div>
 
                <div class="form__field">
                    <label class="form__label" for="reg-password">Password</label>
                    <input
                        id="reg-password"
                        class="form__input"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        required
                    >
                    <span class="form__error" id="reg-password-error" aria-live="polite"></span>
                </div>
 
                <p class="form__global-error" id="reg-global-error" aria-live="polite"></p>
 
                <button class="form__submit btn btn--primary" type="submit">Create account</button>
            </form>
        </div>
    </section>
</main>`;
    }

    _bindTabs() {
        const showLogin = () => this._switchTab('login');
        const showRegister = () => this._switchTab('register');

        this._on(this._tabLogin, 'click', showLogin);
        this._on(this._tabRegister, 'click', showRegister);
    }

    _switchTab(name) {
        const isLogin = name === 'login';

        this._tabLogin.classList.toggle('tabs__tab--active', isLogin);
        this._tabLogin.setAttribute('aria-selected', String(isLogin));

        this._tabRegister.classList.toggle('tabs__tab--active', !isLogin);
        this._tabRegister.setAttribute('aria-selected', String(!isLogin));

        this._container.querySelector('#panel-login').classList.toggle('tabs__panel--hidden', !isLogin);
        this._container.querySelector('#panel-register').classList.toggle('tabs__panel--hidden', isLogin);
    }

    _bindLoginForm() {
        const onSubmit = async (e) => {
            e.preventDefault();
            this._clearErrors(this._loginForm);

            const email = this._loginForm.querySelector('#login-email').value.trim();

            const password = this._loginForm.querySelector('#login-password').value;

            let valid = true;
            if (!email) {
                this._fieldError('login-email-error', 'Email is required.');
                valid = false;
            }

            if (!password) {
                this._fieldError('login-password-error', 'Password is required.');
                valid = false;
            }

            if (!valid) {
                return;
            }

            const submit = this._loginForm.querySelector('[type="submit"]');
            submit.disabled = true;

            try {
                await api.auth.login(email, password);
                await window.app.refreshUser();
                window.app.navigate('/feed');
            } catch (err) {
                this._globalError('login-global-error', err.message ?? 'Login failed. Please try again.');
            } finally {
                submit.disabled = false;
            }
        };

        this._on(this._loginForm, 'submit', onSubmit);
    }

    _bindRegisterForm() {
        const onSubmit = async (e) => {
            e.preventDefault();
            this._clearErrors(this._registerForm);

            const username = this._registerForm.querySelector('#reg-username').value.trim();
            const email = this._registerForm.querySelector('#reg-email').value.trim();
            const password = this._registerForm.querySelector('#reg-password').value;

            let valid = true;
            if (!username) {
                this._fieldError('reg-username-error', 'Username is required.');
                valid = false;
            }

            if (!email) {
                this._fieldError('reg-email-error', 'Email is required.');
                valid = false;
            }

            if (!password) {
                this._fieldError('reg-password-error', 'Password is required.');
                valid = false;
            }

            if (!valid) {
                return;
            }

            const submit = this._registerForm.querySelector('[type="submit"]');
            submit.disabled = true;

            try {
                await api.auth.register(username, email, password);
                await window.app.refreshUser();
                window.app.navigate('/feed');
            } catch (err) {
                 if (err.errors && Object.keys(err.errors).length > 0) {
                    const map = {
                        username: 'reg-username-error',
                        email:    'reg-email-error',
                        password: 'reg-password-error',
                    };
                    for (const [field, errorId] of Object.entries(map)) {
                        if (err.errors[field]) {
                            this._fieldError(errorId, err.errors[field]);
                        }
                    }
                } else {
                    this._globalError('reg-global-error', err.message ?? 'Registration failed. Please try again.');
                }
            } finally {
                submit.disabled = false;
            }
        };

        this._on(this._registerForm, 'submit', onSubmit);
    }

    _on(el, type, fn) {
        el.addEventListener(type, fn);
        this._listeners.push({ el, type, fn });
    }

    _fieldError(id, message) {
        const el = this._container.querySelector(`#${id}`);
        if (el) {
            el.textContent = message;
        }
    }

    _globalError(id, message) {
        const el = this._container.querySelector(`#${id}`);
        if (el) {
            el.textContent = message;
        }
    }

    _clearErrors(form) {
        form.querySelectorAll('.form__error, .form__global-error')
            .forEach(el => { el.textContent = ''; });
    }
}