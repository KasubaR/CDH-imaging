document.addEventListener('DOMContentLoaded', () => {
    const stage = document.querySelector('[data-viewer-stage]');

    if (!stage) {
        return;
    }

    const fullscreenTarget = document.querySelector('[data-viewer-fullscreen-target]');
    const toolbar = document.querySelector('[data-viewer-toolbar]');
    const downloadBtn = document.querySelector('[data-tool="download"]');
    const thumbs = document.querySelectorAll('[data-viewer-thumb]');

    const MIN_ZOOM = 0.25;
    const MAX_ZOOM = 6;
    const ZOOM_STEP = 0.25;

    let focusedKey = 'a';
    let compareMode = false;
    let measureMode = false;
    let dragState = null;

    const panes = {
        a: buildPaneRefs('a'),
        b: buildPaneRefs('b'),
    };

    function buildPaneRefs(key) {
        const root = document.querySelector(`[data-viewer-pane="${key}"]`);

        return {
            key,
            root,
            viewport: root.querySelector('[data-pane-viewport]'),
            img: root.querySelector('[data-pane-image]'),
            svg: root.querySelector('[data-pane-measure-layer]'),
            label: root.querySelector('[data-pane-view-label]'),
            placeholder: root.querySelector('[data-pane-placeholder]'),
            state: defaultPaneState(),
            hasImage: key === 'a',
        };
    }

    function defaultPaneState() {
        return {
            zoom: 1,
            rotate: 0,
            panX: 0,
            panY: 0,
            brightness: 100,
            contrast: 100,
            inverted: false,
        };
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function applyTransform(pane) {
        const { state, img } = pane;

        img.style.transform = `translate(-50%, -50%) translate(${state.panX}px, ${state.panY}px) scale(${state.zoom}) rotate(${state.rotate}deg)`;
        img.style.filter = `brightness(${state.brightness}%) contrast(${state.contrast}%) invert(${state.inverted ? 1 : 0})`;
    }

    function zoomBy(pane, delta) {
        pane.state.zoom = clamp(Math.round((pane.state.zoom + delta) * 100) / 100, MIN_ZOOM, MAX_ZOOM);
        applyTransform(pane);

        if (pane.key === focusedKey) {
            setZoomLabel(pane);
        }
    }

    function setZoomLabel(pane) {
        const label = toolbar.querySelector('[data-zoom-label]');

        if (label) {
            label.textContent = `${Math.round(pane.state.zoom * 100)}%`;
        }
    }

    function setFocused(key) {
        if (!panes[key]) {
            return;
        }

        focusedKey = key;
        Object.values(panes).forEach((pane) => {
            pane.root.classList.toggle('viewer-pane--focused', pane.key === key);
        });
        syncToolbarToFocused();
    }

    function syncToolbarToFocused() {
        const pane = panes[focusedKey];

        setZoomLabel(pane);

        const brightnessInput = toolbar.querySelector('[data-tool="brightness"]');
        const contrastInput = toolbar.querySelector('[data-tool="contrast"]');
        if (brightnessInput) {
            brightnessInput.value = pane.state.brightness;
        }
        if (contrastInput) {
            contrastInput.value = pane.state.contrast;
        }

        const invertBtn = toolbar.querySelector('[data-tool="invert"]');
        if (invertBtn) {
            invertBtn.classList.toggle('viewer-toolbar__btn--active', pane.state.inverted);
        }

        updateDownloadButton(pane);
    }

    function updateDownloadButton(pane) {
        if (!downloadBtn) {
            return;
        }

        const url = pane.hasImage ? pane.img.dataset.downloadUrl : '';

        if (url) {
            downloadBtn.href = url;
            downloadBtn.removeAttribute('aria-disabled');
            downloadBtn.classList.remove('btn--link-muted');
        } else {
            downloadBtn.href = '#';
            downloadBtn.setAttribute('aria-disabled', 'true');
            downloadBtn.classList.add('btn--link-muted');
        }
    }

    function resetPane(pane) {
        pane.state = defaultPaneState();
        applyTransform(pane);
        clearMeasurement(pane);

        if (pane.key === focusedKey) {
            syncToolbarToFocused();
        }
    }

    function clearMeasurement(pane) {
        pane.svg.innerHTML = '';
    }

    function drawMeasurement(pane, x1, y1, x2, y2) {
        const distance = Math.round(Math.hypot(x2 - x1, y2 - y1) / pane.state.zoom);
        const midX = (x1 + x2) / 2;
        const midY = (y1 + y2) / 2;

        pane.svg.innerHTML = `
            <line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}"></line>
            <circle cx="${x1}" cy="${y1}" r="4"></circle>
            <circle cx="${x2}" cy="${y2}" r="4"></circle>
            <text x="${midX}" y="${midY - 10}" text-anchor="middle">${distance} px</text>
        `;
    }

    function clearPaneImage(pane) {
        pane.hasImage = false;
        pane.img.hidden = true;
        pane.img.removeAttribute('src');
        delete pane.img.dataset.downloadUrl;
        pane.state = defaultPaneState();
        applyTransform(pane);
        clearMeasurement(pane);
        pane.root.classList.add('viewer-pane--empty');

        if (pane.placeholder) {
            pane.placeholder.hidden = false;
        }
        if (pane.label) {
            pane.label.textContent = '';
        }
    }

    function setPaneImage(pane, { src, label, downloadUrl }) {
        if (!src) {
            return;
        }

        pane.hasImage = true;
        pane.img.src = src;
        pane.img.alt = label ?? '';
        pane.img.hidden = false;

        if (downloadUrl) {
            pane.img.dataset.downloadUrl = downloadUrl;
        } else {
            delete pane.img.dataset.downloadUrl;
        }

        pane.state = defaultPaneState();
        applyTransform(pane);
        clearMeasurement(pane);
        pane.root.classList.remove('viewer-pane--empty');

        if (pane.placeholder) {
            pane.placeholder.hidden = true;
        }
        if (pane.label) {
            pane.label.textContent = label ?? '';
        }

        if (pane.key === focusedKey) {
            syncToolbarToFocused();
        }
    }

    function startPan(pane, event) {
        dragState = {
            key: pane.key,
            mode: 'pan',
            startX: event.clientX,
            startY: event.clientY,
            originPanX: pane.state.panX,
            originPanY: pane.state.panY,
        };
        pane.viewport.classList.add('viewer-pane__viewport--panning');
    }

    function updatePan(pane, event) {
        pane.state.panX = dragState.originPanX + (event.clientX - dragState.startX);
        pane.state.panY = dragState.originPanY + (event.clientY - dragState.startY);
        applyTransform(pane);
    }

    function startMeasurement(pane, event) {
        const rect = pane.viewport.getBoundingClientRect();

        dragState = {
            key: pane.key,
            mode: 'measure',
            startX: event.clientX - rect.left,
            startY: event.clientY - rect.top,
        };
        clearMeasurement(pane);
    }

    function updateMeasurement(pane, event) {
        const rect = pane.viewport.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;

        drawMeasurement(pane, dragState.startX, dragState.startY, x, y);
    }

    function attachPointerHandlers(pane) {
        const viewport = pane.viewport;

        viewport.addEventListener('pointerdown', (event) => {
            setFocused(pane.key);

            if (!pane.hasImage) {
                return;
            }

            viewport.setPointerCapture(event.pointerId);

            if (measureMode) {
                startMeasurement(pane, event);
            } else {
                startPan(pane, event);
            }
        });

        viewport.addEventListener('pointermove', (event) => {
            if (!dragState || dragState.key !== pane.key) {
                return;
            }

            if (dragState.mode === 'pan') {
                updatePan(pane, event);
            } else {
                updateMeasurement(pane, event);
            }
        });

        ['pointerup', 'pointercancel', 'pointerleave'].forEach((eventName) => {
            viewport.addEventListener(eventName, () => {
                if (dragState && dragState.key === pane.key) {
                    dragState = null;
                    viewport.classList.remove('viewer-pane__viewport--panning');
                }
            });
        });

        viewport.addEventListener('wheel', (event) => {
            if (!pane.hasImage) {
                return;
            }

            event.preventDefault();
            setFocused(pane.key);
            zoomBy(pane, event.deltaY < 0 ? ZOOM_STEP : -ZOOM_STEP);
        }, { passive: false });
    }

    function toggleCompare(btn) {
        compareMode = !compareMode;
        btn.classList.toggle('viewer-toolbar__btn--active', compareMode);
        stage.classList.toggle('viewer-stage--compare', compareMode);
        panes.b.root.hidden = !compareMode;

        if (compareMode) {
            setFocused('b');
        } else {
            clearPaneImage(panes.b);
            setFocused('a');
        }
    }

    function toggleFullscreen() {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            fullscreenTarget.requestFullscreen?.().catch(() => {});
        }
    }

    function printViewer() {
        const saved = {};

        Object.values(panes).forEach((pane) => {
            if (!pane.hasImage) {
                return;
            }

            saved[pane.key] = { zoom: pane.state.zoom, panX: pane.state.panX, panY: pane.state.panY };
            pane.state.zoom = 1;
            pane.state.panX = 0;
            pane.state.panY = 0;
            applyTransform(pane);
        });

        function restore() {
            Object.entries(saved).forEach(([key, value]) => {
                Object.assign(panes[key].state, value);
                applyTransform(panes[key]);
            });
            window.removeEventListener('afterprint', restore);
        }

        window.addEventListener('afterprint', restore);
        window.print();
    }

    Object.values(panes).forEach((pane) => {
        attachPointerHandlers(pane);
        applyTransform(pane);
    });

    document.addEventListener('fullscreenchange', () => {
        const isFullscreen = document.fullscreenElement === fullscreenTarget;
        fullscreenTarget.classList.toggle('viewer-main--fullscreen', isFullscreen);
        toolbar.querySelector('[data-tool="fullscreen"]')?.classList.toggle('viewer-toolbar__btn--active', isFullscreen);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && measureMode) {
            toolbar.querySelector('[data-tool="measure"]')?.click();
        }
    });

    const brightnessInput = toolbar?.querySelector('[data-tool="brightness"]');
    const contrastInput = toolbar?.querySelector('[data-tool="contrast"]');

    // Listen for both: real dragging fires `input` continuously, but some
    // input methods (assistive tech, programmatic value changes) only fire
    // `change` on release — the slider should respond to either.
    ['input', 'change'].forEach((eventName) => {
        brightnessInput?.addEventListener(eventName, () => {
            const pane = panes[focusedKey];
            pane.state.brightness = Number(brightnessInput.value);
            applyTransform(pane);
        });

        contrastInput?.addEventListener(eventName, () => {
            const pane = panes[focusedKey];
            pane.state.contrast = Number(contrastInput.value);
            applyTransform(pane);
        });
    });

    toolbar?.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-tool]');

        if (!btn) {
            return;
        }

        const pane = panes[focusedKey];

        switch (btn.dataset.tool) {
            case 'zoom-in':
                zoomBy(pane, ZOOM_STEP);
                break;
            case 'zoom-out':
                zoomBy(pane, -ZOOM_STEP);
                break;
            case 'rotate':
                pane.state.rotate = (pane.state.rotate + 90) % 360;
                applyTransform(pane);
                break;
            case 'invert':
                pane.state.inverted = !pane.state.inverted;
                applyTransform(pane);
                btn.classList.toggle('viewer-toolbar__btn--active', pane.state.inverted);
                break;
            case 'measure':
                measureMode = !measureMode;
                btn.classList.toggle('viewer-toolbar__btn--active', measureMode);
                Object.values(panes).forEach((p) => {
                    p.viewport.classList.toggle('viewer-pane__viewport--measuring', measureMode);
                    if (!measureMode) {
                        clearMeasurement(p);
                    }
                });
                break;
            case 'compare':
                toggleCompare(btn);
                break;
            case 'fullscreen':
                toggleFullscreen();
                break;
            case 'reset':
                resetPane(pane);
                break;
            case 'print':
                event.preventDefault();
                printViewer();
                break;
            default:
                break;
        }
    });

    downloadBtn?.addEventListener('click', (event) => {
        if (downloadBtn.getAttribute('aria-disabled') === 'true') {
            event.preventDefault();
        }
    });

    thumbs.forEach((thumb) => {
        thumb.addEventListener('click', () => {
            const src = thumb.dataset.imageSrc;
            const label = thumb.dataset.viewLabel;
            const downloadUrl = thumb.dataset.downloadUrl;

            if (!src) {
                return;
            }

            if (compareMode) {
                setPaneImage(panes[focusedKey], { src, label, downloadUrl });
            } else {
                setPaneImage(panes.a, { src, label, downloadUrl });
                thumbs.forEach((t) => t.classList.remove('viewer-thumb--active'));
                thumb.classList.add('viewer-thumb--active');
            }
        });
    });

    syncToolbarToFocused();
});
