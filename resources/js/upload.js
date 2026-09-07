document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-upload-form]');
    const dropzone = document.querySelector('[data-dropzone]');

    if (!form || !dropzone) {
        return;
    }

    const fileInput = form.querySelector('[data-file-input]');
    const folderInput = form.querySelector('[data-folder-input]');
    const browseFilesBtn = form.querySelector('[data-browse-files]');
    const browseFolderBtn = form.querySelector('[data-browse-folder]');
    const selectedPanel = form.querySelector('[data-upload-selected]');
    const selectedCount = form.querySelector('[data-selected-count]');
    const fileList = form.querySelector('[data-file-list]');
    const errorsPanel = form.querySelector('[data-upload-errors]');
    const submitBtn = form.querySelector('[data-upload-submit]');
    const progress = form.querySelector('[data-upload-progress]');
    const progressBar = form.querySelector('[data-upload-progress-bar]');
    const progressLabel = form.querySelector('[data-upload-progress-label]');

    const chunkUploadUrl = form.dataset.chunkUploadUrl;
    const chunkStatusUrlTemplate = form.dataset.chunkStatusUrlTemplate;
    const examinationKey = form.dataset.examinationId ?? chunkUploadUrl;

    const ACCEPTED_TYPES = ['image/jpeg', 'image/png'];
    const MAX_BYTES = Number.parseInt(form.dataset.maxBytes ?? '', 10) || (15 * 1024 * 1024);
    const CHUNK_BYTES = Number.parseInt(form.dataset.chunkBytes ?? '', 10) || (4 * 1024 * 1024);
    const MAX_CONCURRENT_FILES = 3;
    const MAX_CHUNK_ATTEMPTS = 4;
    const RESUME_STORAGE_KEY = `cdh-upload-resume:${examinationKey}`;
    const maxBytesLabel = formatBytes(MAX_BYTES);

    /** @type {File[]} */
    let selectedFiles = [];
    let uploading = false;

    if ('webkitdirectory' in document.createElement('input')) {
        browseFolderBtn.hidden = false;
    }

    browseFilesBtn.addEventListener('click', () => fileInput.click());
    browseFolderBtn.addEventListener('click', () => folderInput.click());

    fileInput.addEventListener('change', () => {
        addFiles(fileInput.files);
        fileInput.value = '';
    });

    folderInput.addEventListener('change', () => {
        addFiles(folderInput.files);
        folderInput.value = '';
    });

    ['dragover', 'dragenter'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.add('upload-dropzone--active');
        });
    });

    ['dragleave', 'dragend'].forEach((eventName) => {
        dropzone.addEventListener(eventName, () => {
            dropzone.classList.remove('upload-dropzone--active');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        event.preventDefault();
        dropzone.classList.remove('upload-dropzone--active');
        addFiles(event.dataTransfer?.files ?? null);
    });

    dropzone.addEventListener('click', (event) => {
        if (event.target === dropzone) {
            fileInput.click();
        }
    });

    dropzone.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            fileInput.click();
        }
    });

    function addFiles(fileListLike) {
        if (!fileListLike || uploading) {
            return;
        }

        const rejections = [];

        Array.from(fileListLike).forEach((file) => {
            const isDuplicate = selectedFiles.some(
                (existing) => existing.name === file.name && existing.size === file.size,
            );

            if (isDuplicate) {
                return;
            }

            if (!ACCEPTED_TYPES.includes(file.type)) {
                rejections.push(`${file.name} — unsupported file type`);
                return;
            }

            if (file.size > MAX_BYTES) {
                rejections.push(`${file.name} — larger than ${maxBytesLabel}`);
                return;
            }

            file.__uploadState = { status: 'queued', progress: 0, message: null };
            selectedFiles.push(file);
        });

        renderErrors(rejections);
        renderFileList();
    }

    function removeFile(index) {
        const file = selectedFiles[index];

        if (file?.__previewUrl) {
            URL.revokeObjectURL(file.__previewUrl);
        }

        selectedFiles.splice(index, 1);
        renderFileList();
    }

    function renderFileList() {
        fileList.innerHTML = '';

        selectedFiles.forEach((file, index) => {
            const state = file.__uploadState ?? { status: 'queued', progress: 0, message: null };

            const item = document.createElement('li');
            item.className = 'upload-file-list__item';

            const thumb = document.createElement('img');
            thumb.className = 'upload-file-list__thumb';
            thumb.alt = file.name;
            const previewUrl = file.__previewUrl ?? URL.createObjectURL(file);
            file.__previewUrl = previewUrl;
            thumb.src = previewUrl;

            const meta = document.createElement('div');
            meta.className = 'upload-file-list__meta';
            meta.innerHTML = `<span class="upload-file-list__name">${escapeHtml(file.name)}</span><span class="upload-file-list__size">${formatBytes(file.size)}</span>`;

            const status = document.createElement('span');
            status.className = `status-badge ${statusBadgeClass(state.status)}`;
            status.textContent = statusLabel(state);
            item.append(thumb, meta, status);

            if (state.status === 'failed') {
                const retryBtn = document.createElement('button');
                retryBtn.type = 'button';
                retryBtn.className = 'btn btn--secondary';
                retryBtn.textContent = 'Retry';
                retryBtn.addEventListener('click', () => retryFile(index));
                item.append(retryBtn);
            }

            if (state.status !== 'done') {
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn--icon btn--ghost';
                removeBtn.innerHTML = '<span class="material-symbols-outlined">close</span>';
                removeBtn.setAttribute('aria-label', `Remove ${file.name}`);
                removeBtn.disabled = state.status === 'uploading';
                removeBtn.addEventListener('click', () => removeFile(index));
                item.append(removeBtn);
            }

            fileList.append(item);
        });

        selectedPanel.hidden = selectedFiles.length === 0;
        selectedCount.textContent = `${selectedFiles.length} file${selectedFiles.length === 1 ? '' : 's'}`;
        submitBtn.disabled = selectedFiles.length === 0
            || selectedFiles.every((file) => file.__uploadState?.status === 'done');
    }

    function statusBadgeClass(status) {
        return {
            queued: 'status-badge--pending',
            uploading: 'status-badge--pending',
            done: 'status-badge--completed',
            failed: 'status-badge--error',
        }[status] ?? 'status-badge--pending';
    }

    function statusLabel(state) {
        if (state.status === 'queued') {
            return 'Queued';
        }

        if (state.status === 'uploading') {
            return `${state.progress}%`;
        }

        if (state.status === 'done') {
            return 'Done';
        }

        return 'Failed';
    }

    function renderErrors(messages) {
        if (messages.length === 0) {
            return;
        }

        errorsPanel.hidden = false;
        errorsPanel.innerHTML = messages.map((message) => `<p>${escapeHtml(message)}</p>`).join('');
    }

    function clearErrors() {
        errorsPanel.hidden = true;
        errorsPanel.innerHTML = '';
    }

    function formatBytes(bytes) {
        if (bytes < 1024) {
            return `${bytes} B`;
        }

        const units = ['KB', 'MB', 'GB'];
        let value = bytes / 1024;
        let unitIndex = 0;

        while (value >= 1024 && unitIndex < units.length - 1) {
            value /= 1024;
            unitIndex += 1;
        }

        return `${value.toFixed(1)} ${units[unitIndex]}`;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }

    function uuid() {
        if (window.crypto?.randomUUID) {
            return window.crypto.randomUUID();
        }

        // Fallback for older browsers — not cryptographically strong, but this is
        // only ever used as an opaque client-side session key, never for security.
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = (Math.random() * 16) | 0;
            const v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    function resumeKey(file) {
        return `${file.name}|${file.size}|${file.lastModified}`;
    }

    function loadResumeMap() {
        try {
            return JSON.parse(window.localStorage.getItem(RESUME_STORAGE_KEY) ?? '{}');
        } catch {
            return {};
        }
    }

    function saveResumeId(file, uploadId) {
        try {
            const map = loadResumeMap();
            map[resumeKey(file)] = uploadId;
            window.localStorage.setItem(RESUME_STORAGE_KEY, JSON.stringify(map));
        } catch {
            // localStorage unavailable (private browsing, quota) — resume just
            // won't survive a reload for this file. Uploading still works.
        }
    }

    function clearResumeId(file) {
        try {
            const map = loadResumeMap();
            delete map[resumeKey(file)];
            window.localStorage.setItem(RESUME_STORAGE_KEY, JSON.stringify(map));
        } catch {
            // See saveResumeId.
        }
    }

    function chunkCount(file) {
        return Math.max(1, Math.ceil(file.size / CHUNK_BYTES));
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    }

    /**
     * POST one chunk with progress reporting and a few automatic retries —
     * a single flaky chunk shouldn't force re-sending the whole file.
     */
    function uploadChunk(uploadId, index, total, filename, blob, onProgress) {
        const attempt = (attemptNumber) => new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('upload_id', uploadId);
            formData.append('chunk_index', String(index));
            formData.append('total_chunks', String(total));
            formData.append('filename', filename);
            formData.append('chunk', blob, filename);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', chunkUploadUrl, true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());

            xhr.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) {
                    onProgress(event.loaded / event.total);
                }
            });

            xhr.addEventListener('load', () => {
                let payload = null;
                try {
                    payload = JSON.parse(xhr.responseText);
                } catch {
                    // fall through to status-code handling below
                }

                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(payload ?? {});
                    return;
                }

                // A structured rejection (duplicate, oversized, bad metadata) is
                // terminal for this upload_id — retrying it won't help.
                if (xhr.status === 422 && payload) {
                    reject(Object.assign(new Error(payload.message ?? 'Upload rejected.'), { terminal: true, payload }));
                    return;
                }

                reject(new Error(`Chunk upload failed with status ${xhr.status}.`));
            });

            xhr.addEventListener('error', () => reject(new Error('Network error while uploading.')));

            xhr.send(formData);
        }).catch((error) => {
            if (error.terminal || attemptNumber >= MAX_CHUNK_ATTEMPTS) {
                throw error;
            }

            const delay = 500 * attemptNumber;
            return new Promise((r) => setTimeout(r, delay)).then(() => attempt(attemptNumber + 1));
        });

        return attempt(1);
    }

    async function fetchChunkStatus(uploadId) {
        const url = chunkStatusUrlTemplate.replace('__UPLOAD_ID__', uploadId);

        const response = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) {
            return { status: 'not_found', received: [] };
        }

        return response.json();
    }

    async function uploadFile(file) {
        const state = file.__uploadState;
        state.status = 'uploading';
        state.progress = 0;
        state.message = null;
        renderFileList();

        const total = chunkCount(file);
        const resumeMap = loadResumeMap();
        let uploadId = resumeMap[resumeKey(file)];
        let received = new Set();

        if (uploadId) {
            const info = await fetchChunkStatus(uploadId);

            if (info.status === 'completed') {
                markDone(file);
                clearResumeId(file);
                return;
            }

            if (info.status === 'failed' || info.status === 'not_found') {
                // The previous attempt is terminal (or the session expired) —
                // start over under a fresh id rather than reusing a dead one.
                uploadId = null;
            } else {
                received = new Set(info.received ?? []);
            }
        }

        if (!uploadId) {
            uploadId = uuid();
        }

        saveResumeId(file, uploadId);

        const pending = [];
        for (let index = 0; index < total; index++) {
            if (!received.has(index)) {
                pending.push(index);
            }
        }

        let completedChunks = total - pending.length;
        updateFileProgress(file, completedChunks / total);

        let lastResult = null;

        try {
            for (const index of pending) {
                const start = index * CHUNK_BYTES;
                const blob = file.slice(start, start + CHUNK_BYTES);

                lastResult = await uploadChunk(uploadId, index, total, file.name, blob, (fraction) => {
                    updateFileProgress(file, (completedChunks + fraction) / total);
                });

                completedChunks += 1;
                updateFileProgress(file, completedChunks / total);
            }

            if (lastResult?.status === 'completed' || completedChunks === total) {
                markDone(file);
                clearResumeId(file);
            } else {
                throw new Error('Upload did not complete.');
            }
        } catch (error) {
            state.status = 'failed';
            state.message = error.message ?? 'Upload failed.';

            // A terminal rejection means the chunks on the server were already
            // discarded — don't keep pointing a retry at a dead upload_id. A
            // transient/network failure keeps the resume id so retrying (or
            // reloading and re-selecting the file) picks up where it left off.
            if (error.terminal) {
                clearResumeId(file);
            }

            renderFileList();
        }
    }

    function updateFileProgress(file, fraction) {
        file.__uploadState.progress = Math.round(Math.min(1, Math.max(0, fraction)) * 100);
        renderFileList();
        renderAggregateProgress();
    }

    function markDone(file) {
        file.__uploadState.status = 'done';
        file.__uploadState.progress = 100;
        renderFileList();
        renderAggregateProgress();
    }

    function renderAggregateProgress() {
        if (selectedFiles.length === 0) {
            return;
        }

        const totalFraction = selectedFiles.reduce((sum, file) => {
            const state = file.__uploadState;

            if (state.status === 'done') {
                return sum + 1;
            }

            if (state.status === 'failed') {
                return sum + 1;
            }

            return sum + (state.progress ?? 0) / 100;
        }, 0);

        const percent = Math.round((totalFraction / selectedFiles.length) * 100);
        progressBar.style.width = `${percent}%`;
        progressLabel.textContent = `${percent}%`;
    }

    async function retryFile(index) {
        const file = selectedFiles[index];

        if (!file || uploading) {
            return;
        }

        clearErrors();
        await uploadFile(file);
        afterBatchSettled();
    }

    function afterBatchSettled() {
        const failed = selectedFiles.filter((file) => file.__uploadState.status === 'failed');

        if (failed.length > 0) {
            renderErrors(failed.map((file) => `${file.name} — ${file.__uploadState.message ?? 'Upload failed.'}`));
            submitBtn.disabled = false;
            submitBtn.textContent = 'Retry failed uploads';
            return;
        }

        window.location.href = form.dataset.redirectUrl;
    }

    /**
     * Runs `worker` over `items` with at most `limit` running at once — this
     * is what lets several files upload at the same time instead of one at a
     * time, without flooding the browser/server with dozens of parallel
     * requests for a big batch.
     */
    async function runPool(items, limit, worker) {
        let cursor = 0;

        async function next() {
            const current = cursor;
            cursor += 1;

            if (current >= items.length) {
                return;
            }

            await worker(items[current]);
            await next();
        }

        await Promise.all(Array.from({ length: Math.min(limit, items.length) }, next));
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const pendingFiles = selectedFiles.filter((file) => file.__uploadState.status !== 'done');

        if (pendingFiles.length === 0 || uploading) {
            return;
        }

        clearErrors();
        uploading = true;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Uploading…';
        progress.hidden = false;
        renderAggregateProgress();

        await runPool(pendingFiles, MAX_CONCURRENT_FILES, uploadFile);

        uploading = false;
        afterBatchSettled();
    });
});
