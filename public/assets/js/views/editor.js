/**
 * public/assets/js/views/editor.js — EditorView
 *
 * The photo editor. Handles two routes:
 *
 *   /editor          — fresh editor, no post loaded. User uploads a new image.
 *   /editor/:postId  — loads an existing post and its pipeline for editing.
 *
 * Responsibilities:
 *   - Image upload (new post) or load existing post thumbnail.
 *   - Load the available filters list via GET /filters.
 *   - Build and manage a pipeline (ordered list of steps).
 *   - Live preview — runs the pipeline client-side via pipeline.js on every
 *     change. Debounced so rapid slider moves don't flood the canvas.
 *   - Save pipeline via PUT /pipelines/{id}/steps.
 *   - Export via POST /posts/{id}/export — server-side GD render.
 *   - Save current pipeline as a named composite filter via POST /filters.
 *
 * View contract (expected by app.js):
 *   constructor(container, params)   — params.postId (string|undefined)
 *   async render()
 *   destroy()
 *
 * API used:
 *   api.filters.list()                          GET  /filters
 *   api.posts.upload(file, caption, visibility) POST /posts
 *   api.posts.get(id)                           GET  /posts/{id}
 *   api.posts.export(postId, pipelineId)        POST /posts/{id}/export
 *   api.pipelines.create(name)                  POST /pipelines
 *   api.pipelines.get(id)                       GET  /pipelines/{id}
 *   api.pipelines.replaceSteps(id, steps)       PUT  /pipelines/{id}/steps
 *   api.filters.create(name, pipelineId)        POST /filters
 *
 * Filter ID → name mapping mirrors canvas.js / the DB filters table:
 *   1 Gaussian Blur  2 Grayscale  3 Sepia      4 Brightness  5 Contrast
 *   6 Saturation     7 Hue Rotate 8 Vignette   9 Digital Noise  10 Edge Detect
 */
 
import api from '../api.js';
import { runPipeline } from '../filters/pipeline.js';
 
const FILTERS_META = [
    { id: 1, name: 'Gaussian Blur', params: [{ key: 'intensity', label: 'Intensity', type: 'slider', min: 1, max: 50, default: 5 }] },
    { id: 2, name: 'Grayscale', params: [] },
    { id: 3, name: 'Sepia', params: [] },
    { id: 4, name: 'Brightness', params: [{ key: 'level', label: 'Level', type: 'slider', min: -255, max: 255, default: 20 }] },
    { id: 5, name: 'Contrast', params: [{ key: 'level', label: 'Level', type: 'slider', min: -255, max: 255, default: 30 }] },
    { id: 6, name: 'Saturation', params: [{ key: 'level', label: 'Level', type: 'slider', min: -100, max: 0, default: -50 }] },
    { id: 7, name: 'Hue Rotate', params: [{ key: 'angle', label: 'Angle', type: 'slider', min: 0, max: 360, default: 180 }] },
    { id: 8, name: 'Vignette', params: [] },
    { id: 9, name: 'Digital Noise', params: [{ key: 'intensity', label: 'Intensity', type: 'slider', min: 1, max: 100, default: 15 }] },
    { id: 10, name: 'Edge Detect', params: [] },
];
 
const FILTER_META_BY_ID = Object.fromEntries(FILTERS_META.map(f => [f.id, f]));

const PREVIEW_DEBOUNCE_MS = 120;
 
export class EditorView {
 
    constructor(container, params) {
        this._container = container;
        this._postId = params.postId ? parseInt(params.postId, 10) : null;
 
        this._post = null;
        this._pipeline = null;
        this._steps = [];
        this._imageEl = null;
        this._abortCtrl = null;
        this._listeners = [];
 
        this._previewTimer = null;
        this._previewPending = false;
    }
 
    async render() {
        this._container.innerHTML = this._buildShellHTML();
 
        this._previewCanvas = this._container.querySelector('[id="editor-canvas"]');
        this._filterList = this._container.querySelector('[id="editor-filter-list"]');
        this._stepList = this._container.querySelector('[id="editor-step-list"]');
        this._globalError = this._container.querySelector('[id="editor-error"]');
        this._exportBtn = this._container.querySelector('[id="editor-export-btn"]');
        this._saveBtn = this._container.querySelector('[id="editor-save-btn"]');
        this._saveFilterBtn = this._container.querySelector('[id="editor-save-filter-btn"]');
        this._uploadSection = this._container.querySelector('[id="editor-upload-section"]');
        this._uploadInput = this._container.querySelector('[id="editor-upload-input"]');
 
        this._bindUpload();
        this._bindSave();
        this._bindExport();
        this._bindSaveFilter();
        
        this._boundStepAction = this._onStepAction.bind(this);
        this._boundParamInput = this._onParamInput.bind(this);

        this._stepList.addEventListener('click', this._boundStepAction);
        this._stepList.addEventListener('input', this._boundParamInput);

        this._listeners.push({ el: this._stepList, type: 'click', fn: this._boundStepAction });
        this._listeners.push({ el: this._stepList, type: 'input', fn: this._boundParamInput });

        if (this._postId) {
            await this._loadPost(this._postId);
        } else {
            this._previewCanvas.hidden = true;
        }
 
        this._renderFilterPicker();
        await this._loadAndRenderUserFilters();
    }
 
    destroy() {
        for (const { el, type, fn } of this._listeners) {
            el.removeEventListener(type, fn);
        }
        this._listeners = [];

        if (this._abortCtrl) {
            this._abortCtrl.abort();
            this._abortCtrl = null;
        }
        if (this._previewTimer) {
            clearTimeout(this._previewTimer);
            this._previewTimer = null;
        }
    }
 
    async _loadPost(postId) {
        this._setGlobalError('');
        this._abortCtrl = new AbortController();
 
        try {
            const raw = await api.posts.get(postId);
            this._post = raw.post ?? raw;
 
            const imageUrl = this._post.processed_url ?? this._post.original_url ?? this._post.thumb_url;
            this._imageEl = await this._loadImage(imageUrl);
 
            if (this._post.pipeline_id) {
                const pipelineResponse = await api.pipelines.get(this._post.pipeline_id);
                this._pipeline = pipelineResponse.pipeline ?? pipelineResponse;
                this._steps = this._pipeline.steps ?? [];
            } else {
                this._pipeline = await api.pipelines.create(`Post ${postId} pipeline`);
                this._steps = [];
            }
 
            this._uploadSection.hidden = true;
            this._previewCanvas.hidden = false;
 
            this._renderStepList();
            this._schedulePreview();
 
        } catch (err) {
            if (err.name === 'AbortError') return;
            const msg = err.status === 404 ? 'Post not found.' : (err.message ?? 'Could not load post.');
            this._setGlobalError(msg);
        } finally {
            this._abortCtrl = null;
        }
    }
 
    _bindUpload() {
        const handler =  async () => {
            const file = this._uploadInput.files[0];
            if (!file) return;
 
            const uploadBtn = this._container.querySelector('[id="editor-upload-btn"]');
            if (uploadBtn) uploadBtn.disabled = true;
            this._setGlobalError('');
 
            try {
                this._post = await api.posts.upload(file, '', 'public');
                this._postId = this._post.id;
                const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');
                this._imageEl = await this._loadImage(this._post.thumb_url);
                this._pipeline = await api.pipelines.create(`Post ${this._postId} pipeline`);
                this._steps = [];
 
                history.replaceState(null, '', `${BASE}/editor/${this._postId}`);
 
                this._uploadSection.hidden = true;
                this._previewCanvas.hidden = false;
 
                this._renderStepList();
                this._schedulePreview();
 
            } catch (err) {
                this._setGlobalError(err.message ?? 'Upload failed. Please try again.');
            } finally {
                if (uploadBtn) uploadBtn.disabled = false;
            }
        };

        this._uploadInput.addEventListener('change', handler);
        this._listeners.push({ el: this._uploadInput, type: 'change', fn: handler});
    }
 
    _renderFilterPicker() {
        this._filterList.innerHTML = FILTERS_META.map(f => `
<button
    class="editor__filter-btn btn btn--ghost"
    data-filter-id="${f.id}"
    type="button"
>${this._esc(f.name)}</button>`).join('');
 
        const handler = (e) => {
            const btn = e.target.closest('[data-filter-id]');
            if (!btn) return;
            if (!this._imageEl) {
                this._setGlobalError('Upload or load an image first.');
                return;
            }
            this._addStep(parseInt(btn.dataset.filterId, 10));
        };

        this._filterList.addEventListener('click', handler);
        this._listeners.push({ el: this._filterList, type: 'click', fn: handler});
    }
 
    _addStep(filterId) {
        const meta = FILTER_META_BY_ID[filterId];
        if (!meta) return;
 
        const params = {};
        for (const p of meta.params) {
            params[p.key] = p.default;
        }
 
        this._steps.push({ filter_id: filterId, sub_pipeline_id: null, params, });
 
        this._renderStepList();
        this._schedulePreview();
    }
 
    _renderStepList() {
        if (this._steps.length === 0) {
            this._stepList.innerHTML = '<li class="editor__step-empty">No filters added yet.</li>';
            return;
        }
 
        this._stepList.innerHTML = this._steps.map((step, index) => {
            const meta = FILTER_META_BY_ID[step.filter_id];
            const name = meta ? this._esc(meta.name) : `Filter ${step.filter_id}`;
            const controls = meta ? meta.params.map(p => this._buildParamControlHTML(index, p, step.params[p.key])).join('') : '';
 
            return `
<li class="editor__step" data-step-index="${index}">
    <div class="editor__step-header">
        <span class="editor__step-name">${name}</span>
        <div class="editor__step-actions">
            <button class="btn btn--ghost editor__step-up"   data-action="up"     data-index="${index}" type="button" ${index === 0 ? 'disabled' : ''} aria-label="Move up">↑</button>
            <button class="btn btn--ghost editor__step-down" data-action="down"   data-index="${index}" type="button" ${index === this._steps.length - 1 ? 'disabled' : ''} aria-label="Move down">↓</button>
            <button class="btn btn--ghost editor__step-del"  data-action="delete" data-index="${index}" type="button" aria-label="Remove filter">✕</button>
        </div>
    </div>
    ${controls ? `<div class="editor__step-controls">${controls}</div>` : ''}
</li>`;
        }).join('');
 
    }
 
    _buildParamControlHTML(stepIndex, paramMeta, currentValue) {
        const val = currentValue ?? paramMeta.default;
        return `
<div class="editor__param">
    <label class="editor__param-label">
        ${this._esc(paramMeta.label)}
        <span class="editor__param-value" id="param-val-${stepIndex}-${paramMeta.key}">${val}</span>
    </label>
    <input
        class="editor__param-slider"
        type="range"
        min="${paramMeta.min}"
        max="${paramMeta.max}"
        value="${val}"
        data-step-index="${stepIndex}"
        data-param-key="${paramMeta.key}"
    >
</div>`;
    }
 
    _onStepAction(e) {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
 
        const index = parseInt(btn.dataset.index, 10);
        const action = btn.dataset.action;
 
        if (action === 'delete') {
            this._steps.splice(index, 1);
        } else if (action === 'up' && index > 0) {
            [this._steps[index - 1], this._steps[index]] = [this._steps[index], this._steps[index - 1]];
        } else if (action === 'down' && index < this._steps.length - 1) {
            [this._steps[index], this._steps[index + 1]] = [this._steps[index + 1], this._steps[index]];
        }
 
        this._renderStepList();
        this._schedulePreview();
    }
 
    _onParamInput(e) {
        const input = e.target.closest('[data-param-key]');
        if (!input) return;
 
        const stepIndex = parseInt(input.dataset.stepIndex, 10);
        const paramKey = input.dataset.paramKey;
        const value = parseInt(input.value, 10);
 
        this._steps[stepIndex].params[paramKey] = value;
 
        const label = this._stepList.querySelector(`[id="param-val-${stepIndex}-${paramKey}"]`);
        if (label) label.textContent = value;
 
        this._schedulePreview();
    }
 
    _schedulePreview() {
        if (this._previewTimer) clearTimeout(this._previewTimer);
        this._previewTimer = setTimeout(() => {
            this._previewTimer = null;
            this._runPreview();
        }, PREVIEW_DEBOUNCE_MS);
    }
 
    _runPreview() {
        if (!this._imageEl || this._previewPending) return;
 
        this._previewPending = true;
 
        try {
            const dataUrl = runPipeline(this._imageEl, this._steps);
            const ctx = this._previewCanvas.getContext('2d');
            const img = new Image();
 
            img.onload = () => {
                this._previewCanvas.width = img.naturalWidth;
                this._previewCanvas.height = img.naturalHeight;
                ctx.drawImage(img, 0, 0);
                this._previewPending = false;
            };
 
            img.onerror = () => {
                this._previewPending = false;
            };
 
            img.src = dataUrl;
        } catch (err) {
            console.error('[editor.js] Preview failed:', err);
            this._previewPending = false;
        }
    }
 
    _bindSave() {
        const handler = async () => {
            if (!this._postId || !this._pipeline) {
                this._setGlobalError('Upload an image first.');
                return;
            }

            this._saveBtn.disabled = true;
            this._setGlobalError('');

            const steps = this._steps.map((step, i) => ({
                step_order: i + 1,
                filter_id: step.filter_id,
                sub_pipeline_id: null,
                params: step.params ?? {},
            }));

            try {
                await api.pipelines.replaceSteps(this._pipeline.id, steps);
                await api.posts.publish(this._postId, this._pipeline.id);
                this._showToast('Post published.');
            } catch (err) {
                this._setGlobalError(err.message ?? 'Publish failed. Please try again.');
            } finally {
                this._saveBtn.disabled = false;
            }
        };

        this._saveBtn.addEventListener('click', handler);
        this._listeners.push({ el: this._saveBtn, type: 'click', fn: handler });
    }
 
    _bindExport() {
        const handler = async () => {
            if (!this._postId || !this._pipeline) {
                this._setGlobalError('Save your pipeline before exporting.');
                return;
            }

            this._exportBtn.disabled = true;
            this._setGlobalError('');

            try {
                const steps = this._steps.map((step, i) => ({
                    step_order: i + 1,
                    filter_id: step.filter_id,
                    sub_pipeline_id: null,
                    params: step.params ?? {},
                }));

                await api.pipelines.replaceSteps(this._pipeline.id, steps);
                const result = await api.posts.export(this._postId, this._pipeline.id);

                const a = document.createElement('a');
                a.href = result.download_url;
                a.download = `diffrakt-export-${this._postId}.jpg`;
                a.click();

                this._showToast('Export ready — downloading.');
            } catch (err) {
                this._setGlobalError(err.message ?? 'Export failed. Please try again.');
            } finally {
                this._exportBtn.disabled = false;
            }
        };

        this._exportBtn.addEventListener('click', handler);
        this._listeners.push({ el: this._exportBtn, type: 'click', fn: handler });
    }
 
    _bindSaveFilter() {
        const form = this._container.querySelector('[id="editor-save-filter-form"]');
        const input = this._container.querySelector('[id="editor-save-filter-input"]');
        const confirmBtn = this._container.querySelector('[id="editor-save-filter-confirm"]');
        const cancelBtn = this._container.querySelector('[id="editor-save-filter-cancel"]');

        const showHandler = () => {
            if (!this._pipeline || this._steps.length === 0) {
                this._setGlobalError('Add at least one filter step before saving as a filter.');
                return;
            }
            form.hidden = false;
            this._saveFilterBtn.hidden = true;
            input.focus();
        };

        const cancelHandler = () => {
            form.hidden = true;
            this._saveFilterBtn.hidden = false;
            input.value = '';
        };

        const confirmHandler = async () => {
            const name = input.value.trim();
            if (!name) return;

            confirmBtn.disabled = true;
            this._setGlobalError('');

            try {
                const steps = this._steps.map((step, i) => ({
                    step_order: i + 1,
                    filter_id: step.filter_id,
                    sub_pipeline_id: null,
                    params: step.params ?? {},
                }));
                await api.pipelines.replaceSteps(this._pipeline.id, steps);
                await api.filters.create(name, this._pipeline.id);
                this._showToast(`Filter "${name}" saved.`);
                cancelHandler();
                await this._loadAndRenderUserFilters();
            } catch (err) {
                this._setGlobalError(err.message ?? 'Could not save filter. Please try again.');
            } finally {
                confirmBtn.disabled = false;
            }
        };

        this._saveFilterBtn.addEventListener('click', showHandler);
        confirmBtn.addEventListener('click', confirmHandler);
        cancelBtn.addEventListener('click', cancelHandler);

        this._listeners.push({ el: this._saveFilterBtn, type: 'click', fn: showHandler });
        this._listeners.push({ el: confirmBtn, type: 'click', fn: confirmHandler });
        this._listeners.push({ el: cancelBtn, type: 'click', fn: cancelHandler });
    }
 
    _buildShellHTML() {
        return `
<main class="editor">
    <p id="editor-error" class="editor__error" aria-live="polite" hidden></p>
 
    <div class="editor__layout">
 
        <!-- Left panel: canvas preview -->
        <section class="editor__preview-panel">
            <canvas id="editor-canvas" class="editor__canvas"></canvas>
 
            <div id="editor-upload-section" class="editor__upload">
                <label class="editor__upload-label btn btn--primary" for="editor-upload-input">
                    Choose photo
                </label>
                <input
                    id="editor-upload-input"
                    class="editor__upload-input"
                    type="file"
                    accept="image/*"
                >
            </div>
 
            <div class="editor__preview-actions">
                <div class="editor__preview-actions-row">
                    <button id="editor-save-btn" class="btn btn--primary" type="button">Publish</button>
                    <button id="editor-export-btn"      class="btn btn--secondary" type="button">Export</button>
                    <button id="editor-save-filter-btn" class="btn btn--ghost"     type="button">Save as filter</button>
                </div>

                <div id="editor-save-filter-form" class="editor__save-filter-form" hidden>
                    <input id="editor-save-filter-input" class="form__input" type="text" placeholder="Filter name" maxlength="80">
                    <button id="editor-save-filter-confirm" class="btn btn--primary" type="button">Save</button>
                    <button id="editor-save-filter-cancel" class="btn btn--ghost" type="button">Cancel</button>
                </div>
            </div>
        </section>
 
        <!-- Right panel: filter picker + step list -->
        <section class="editor__controls-panel">
            <h2 class="editor__section-title">Filters</h2>
            <div id="editor-filter-list" class="editor__filter-list"></div>

            <h2 class="editor__section-title">My Filters</h2>
            <div id="editor-user-filters" class="editor__filter-list"></div>
 
            <h2 class="editor__section-title">Pipeline</h2>
            <ul id="editor-step-list" class="editor__step-list"></ul>
        </section>
 
    </div>
</main>`;
    }

    _loadImage(url) {
        const BASE = (document.querySelector('base')?.getAttribute('href') ?? '/').replace(/\/$/, '');
        const fullUrl = url.startsWith('http') ? url : `${BASE}/${url.replace(/^\//, '')}`;

        return new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => resolve(img);
            img.onerror = () => reject(new Error(`Failed to load image: ${fullUrl}`));
            img.src = fullUrl;
        });
    }
 
    _setGlobalError(message) {
        this._globalError.textContent = message;
        this._globalError.hidden = !message;
    }
 
    _showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'editor__toast';
        toast.textContent = message;
        this._container.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    async _loadAndRenderUserFilters() {
        try {
            const data = await api.filters.list();
            const filters = data.filters ?? [];
            const userFilters = filters.filter(f => f.pipeline_id !== null);

            const section = this._container.querySelector('[id="editor-user-filters"]');
            if (!section) return;

            if (userFilters.length === 0) {
                section.innerHTML = '';
                return;
            }

            section.innerHTML = userFilters.map(f => `
                <button
                    class="editor__filter-btn btn btn--ghost"
                    data-pipeline-id="${f.pipeline_id}"
                    type="button"
                >${this._esc(f.name)}</button>
            `).join('');

            if (this._boundUserFilterClick) {
                section.removeEventListener('click', this._boundUserFilterClick);
            }
            this._boundUserFilterClick = this._onUserFilterClick.bind(this);
            section.addEventListener('click', this._boundUserFilterClick);
            this._listeners.push({ el: section, type: 'click', fn: this._boundUserFilterClick });

        } catch {
            // non-critical
        }
    }

    async _onUserFilterClick(e) {
        const btn = e.target.closest('[data-pipeline-id]');
        if (!btn) return;
        if (!this._imageEl) {
            this._setGlobalError('Upload or load an image first.');
            return;
        }

        const pipelineId = parseInt(btn.dataset.pipelineId, 10);
        try {
            const data = await api.pipelines.get(pipelineId);
            const steps = data.pipeline?.steps ?? data.steps ?? [];
            for (const step of steps) {
                this._steps.push({
                    filter_id: step.filter_id,
                    sub_pipeline_id: null,
                    params: step.params ?? {},
                });
            }
            this._renderStepList();
            this._schedulePreview();
        } catch (err) {
            this._setGlobalError(err.message ?? 'Could not load filter.');
        }
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
 