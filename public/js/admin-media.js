(function () {
    'use strict';

    const MAX_IMAGE_BYTES = 20 * 1024 * 1024;
    const MAX_VIDEO_BYTES = 100 * 1024 * 1024;
    const IMAGE_ACCEPT = 'image/jpeg,image/png,image/webp,image/gif';
    const VIDEO_ACCEPT = 'video/mp4,video/webm';

    function escapeHtmlAttr(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function formatBytes(bytes) {
        if (bytes >= 1024 * 1024) {
            return (bytes / (1024 * 1024)).toFixed(1).replace(/\.0$/, '') + ' MB';
        }
        return Math.round(bytes / 1024) + ' KB';
    }

    function validateUploadFile(file, kind) {
        if (!file) {
            return 'No se recibió ningún archivo.';
        }
        const isVideo = kind === 'video';
        const max = isVideo ? MAX_VIDEO_BYTES : MAX_IMAGE_BYTES;
        const allowed = isVideo
            ? ['video/mp4', 'video/webm']
            : ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if (file.type && !allowed.includes(file.type)) {
            return isVideo
                ? 'Solo se admiten vídeos MP4 o WebM.'
                : 'Solo se admiten imágenes JPG, PNG, WebP o GIF.';
        }
        if (file.size > max) {
            return 'El archivo supera el máximo de ' + formatBytes(max) + '.';
        }
        return null;
    }

    function setRowBusy(row, busy, label) {
        row.classList.toggle('is-busy', !!busy);
        row.querySelectorAll('[data-action], .media-dropzone').forEach((el) => {
            if (el.tagName === 'BUTTON' || el.tagName === 'INPUT') {
                el.disabled = !!busy;
            }
        });
        row.querySelectorAll('.media-busy').forEach((el) => {
            el.hidden = !busy;
            const labelEl = el.querySelector('.media-busy-label');
            if (labelEl && label) {
                labelEl.textContent = label;
            }
        });
    }

    function showRowError(row, message) {
        const errorEl = row.querySelector('[data-field=error]');
        if (!errorEl) {
            if (message) {
                window.alert(message);
            }
            return;
        }
        if (!message) {
            errorEl.hidden = true;
            errorEl.textContent = '';
            return;
        }
        errorEl.hidden = false;
        errorEl.textContent = message;
    }

    function updateLibraryLink(row, mediaId) {
        const link = row.querySelector('[data-field=library-link]');
        if (!link) {
            return;
        }
        if (!mediaId) {
            link.hidden = true;
            link.setAttribute('href', '#');
            return;
        }
        link.hidden = false;
        link.setAttribute('href', '/gestor/media/' + encodeURIComponent(String(mediaId)));
    }

    function updateMeta(row, media) {
        const meta = row.querySelector('[data-field=meta]');
        if (!meta) {
            return;
        }
        const width = media?.width;
        const height = media?.height;
        if (!width || !height) {
            meta.hidden = true;
            meta.innerHTML = '';
            return;
        }
        meta.hidden = false;
        meta.innerHTML = '<span class="enhancement-badge">' + width + '×' + height + ' px</span>';
    }

    function featuredEmptyHtml() {
        return (
            '<div class="featured-picker-empty">' +
            '<span class="featured-picker-empty-icon" aria-hidden="true">' +
            '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8">' +
            '<rect x="3" y="3" width="18" height="18" rx="2"/>' +
            '<circle cx="8.5" cy="8.5" r="1.5"/>' +
            '<path d="M21 15l-5-5L5 21"/>' +
            '</svg></span>' +
            '<strong>Añadir imagen de portada</strong>' +
            '<span>Arrastra aquí, o haz clic para abrir la biblioteca</span>' +
            '</div>' +
            '<span class="media-busy" hidden><span class="media-busy-spinner"></span><span class="media-busy-label">Subiendo…</span></span>'
        );
    }

    function syncFeaturedChecklist(hasMedia) {
        const item = document.querySelector('[data-checklist=featured]');
        if (!item) {
            return;
        }
        item.classList.toggle('is-ok', !!hasMedia);
        item.classList.toggle('is-warn', !hasMedia);
    }

    function applyMediaToRow(row, media, options) {
        const opts = options || {};
        const urlInput = row.querySelector('[data-field=url]');
        const urlVisible = row.querySelector('[data-field=url-visible]');
        const mediaIdInput = row.querySelector('[data-field=mediaId]');
        const altInput = row.querySelector('[data-field=alt]');
        const captionInput = row.querySelector('[data-field=caption]');
        const preview = row.querySelector('[data-field=preview]');
        const clearBtn = row.querySelector('[data-action=clear-featured]');
        const url = media.url || '';
        const mediaId = opts.clearMediaId ? '' : (media.id || '');

        if (urlInput) {
            urlInput.value = url;
        }
        if (urlVisible) {
            urlVisible.value = url;
        }
        if (mediaIdInput) {
            mediaIdInput.value = mediaId;
        }
        if (altInput && !altInput.value && media.alt) {
            altInput.value = media.alt;
        }
        if (captionInput && !captionInput.value && media.caption) {
            captionInput.value = media.caption;
        }

        if (preview) {
            const safeUrl = escapeHtmlAttr(url);
            const busyHtml = '<span class="media-busy" hidden><span class="media-busy-spinner"></span><span class="media-busy-label">Subiendo…</span></span>';
            if (preview.classList.contains('featured-picker-preview')) {
                preview.innerHTML =
                    '<img src="' + safeUrl + '" alt="">' +
                    '<span class="featured-picker-overlay">Cambiar imagen</span>' +
                    busyHtml;
                preview.setAttribute('aria-label', 'Cambiar imagen principal');
            } else if (preview.classList.contains('gallery-preview')) {
                preview.innerHTML =
                    '<img src="' + safeUrl + '" alt="">' +
                    '<span class="gallery-preview-overlay">Cambiar</span>' +
                    busyHtml;
                preview.setAttribute('aria-label', 'Cambiar imagen');
            } else {
                preview.innerHTML = '<img src="' + safeUrl + '" alt="">';
            }
            preview.removeAttribute('data-empty');
            preview.hidden = false;
        }

        row.classList.add('has-media');
        if (clearBtn) {
            clearBtn.hidden = false;
        }
        updateLibraryLink(row, mediaId);
        updateMeta(row, media);
        showRowError(row, '');
        if (row.classList.contains('featured-picker')) {
            syncFeaturedChecklist(!!url);
        }
    }

    function clearMediaRow(row) {
        row.querySelectorAll('[data-field=mediaId], [data-field=url], [data-field=alt], [data-field=caption], [data-field=url-visible]').forEach((input) => {
            input.value = '';
        });
        const preview = row.querySelector('[data-field=preview]');
        if (preview) {
            preview.setAttribute('data-empty', '1');
            if (preview.classList.contains('featured-picker-preview')) {
                preview.innerHTML = featuredEmptyHtml();
                preview.setAttribute('aria-label', 'Elegir o subir imagen principal');
            } else if (preview.classList.contains('gallery-preview')) {
                preview.innerHTML =
                    '<span class="gallery-preview-empty">Añadir</span>' +
                    '<span class="media-busy" hidden><span class="media-busy-spinner"></span></span>';
                preview.setAttribute('aria-label', 'Elegir o subir imagen');
            } else {
                preview.textContent = 'Sin imagen';
            }
        }
        row.classList.remove('has-media');
        const clearBtn = row.querySelector('[data-action=clear-featured]');
        if (clearBtn) {
            clearBtn.hidden = true;
        }
        updateLibraryLink(row, '');
        updateMeta(row, null);
        showRowError(row, '');
        if (row.classList.contains('featured-picker')) {
            syncFeaturedChecklist(false);
        }
    }

    async function uploadFile(uploadUrl, csrf, file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('_token', csrf);
        const response = await fetch(uploadUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.error || 'Error al subir.');
        }
        if (!data.media) {
            throw new Error('Respuesta de subida incompleta.');
        }
        return data.media;
    }

    function initUploadZone(zone) {
        const input = zone.querySelector('input[type=file]') || document.getElementById('media-file-input');
        const uploadUrl = zone.dataset.uploadUrl;
        const csrf = zone.dataset.csrf;

        if (!input || !uploadUrl) {
            return;
        }

        const uploadFiles = async (files) => {
            zone.classList.add('is-busy');
            for (const file of files) {
                const kind = (input.accept || '').includes('video') ? 'video' : 'image';
                const validationError = validateUploadFile(file, kind);
                if (validationError) {
                    window.alert(validationError);
                    continue;
                }
                try {
                    const media = await uploadFile(uploadUrl, csrf, file);
                    if (zone.dataset.onUpload === 'reload') {
                        window.location.reload();
                        return;
                    }
                    document.dispatchEvent(new CustomEvent('media:uploaded', { detail: media }));
                } catch (err) {
                    window.alert(err.message || 'Error al subir el archivo.');
                }
            }
            zone.classList.remove('is-busy');
        };

        zone.addEventListener('click', (e) => {
            if (e.target.closest('button,[data-close-modal]')) {
                return;
            }
            input.click();
        });
        input.addEventListener('change', () => {
            if (input.files?.length) {
                uploadFiles(Array.from(input.files));
                input.value = '';
            }
        });

        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('dragover');
        });
        zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('dragover');
            if (e.dataTransfer?.files?.length) {
                uploadFiles(Array.from(e.dataTransfer.files));
            }
        });
    }

    function initMediaPicker() {
        const modal = document.getElementById('media-picker-modal');
        if (!modal) {
            return;
        }

        const grid = modal.querySelector('#media-picker-grid');
        const searchInput = modal.querySelector('#media-picker-search');
        const statusEl = modal.querySelector('#media-picker-status');
        const moreBtn = modal.querySelector('#media-picker-more');
        const uploadZone = modal.querySelector('#media-picker-upload');
        const fileInput = uploadZone?.querySelector('input[type=file]');
        const uploadTitle = modal.querySelector('.media-picker-upload-title');
        const uploadHint = modal.querySelector('.media-picker-upload-hint');
        const apiUrl = modal.dataset.apiUrl;
        let targetRow = null;
        let pickerKind = 'image';
        let pickerTarget = null;
        let page = 1;
        let totalPages = 1;
        let searchTimer = null;
        let loading = false;

        const setStatus = (text) => {
            if (statusEl) {
                statusEl.textContent = text || '';
            }
        };

        const closeModal = () => {
            modal.hidden = true;
            targetRow = null;
            pickerTarget = null;
            pickerKind = 'image';
            document.body.classList.remove('media-modal-open');
        };

        const openModal = () => {
            modal.hidden = false;
            document.body.classList.add('media-modal-open');
            searchInput?.focus();
        };

        const syncUploadAccept = () => {
            if (!fileInput) {
                return;
            }
            const isVideo = pickerKind === 'video';
            fileInput.accept = isVideo ? VIDEO_ACCEPT : IMAGE_ACCEPT;
            if (uploadTitle) {
                uploadTitle.textContent = isVideo ? 'Subir vídeo' : 'Subir archivo';
            }
            if (uploadHint) {
                uploadHint.textContent = isVideo
                    ? 'Arrastra aquí o haz clic · MP4, WebM · máx. 100 MB'
                    : 'Arrastra aquí o haz clic · JPG, PNG, WebP, GIF · máx. 20 MB';
            }
        };

        modal.querySelectorAll('[data-close-modal]').forEach((el) => {
            el.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });

        const selectItem = (item) => {
            if (pickerTarget === 'video') {
                document.dispatchEvent(new CustomEvent('media:selected', {
                    detail: { target: 'video', media: item },
                }));
                closeModal();
                return;
            }
            if (pickerTarget === 'wysiwyg') {
                document.dispatchEvent(new CustomEvent('media:selected', {
                    detail: { target: 'wysiwyg', media: item },
                }));
                closeModal();
                return;
            }
            if (!targetRow) {
                return;
            }
            applyMediaToRow(targetRow, item);
            closeModal();
        };

        const renderItems = (items, append) => {
            if (!append) {
                grid.innerHTML = '';
            }
            if (!items.length && !append) {
                grid.innerHTML = '<p class="hint">No hay archivos. Sube uno desde la zona superior.</p>';
                return;
            }

            items.forEach((item) => {
                const card = document.createElement('button');
                card.type = 'button';
                card.className = 'media-picker-item';
                const isVideo = (item.mimeType || '').startsWith('video/');
                const label = item.title || item.originalFilename || (isVideo ? 'Vídeo' : 'Imagen');
                card.innerHTML = isVideo
                    ? '<span class="media-picker-video-badge">▶ Vídeo</span><span>' + escapeHtmlAttr(label) + '</span>'
                    : '<img src="' + escapeHtmlAttr(item.url) + '" alt=""><span>' + escapeHtmlAttr(label) + '</span>';
                card.addEventListener('click', () => selectItem(item));
                grid.appendChild(card);
            });
        };

        const loadMedia = async (append) => {
            if (loading) {
                return;
            }
            loading = true;
            setStatus(append ? 'Cargando más…' : 'Cargando…');
            if (!append) {
                grid.classList.add('is-loading');
            }
            try {
                const q = searchInput?.value?.trim() || '';
                const url = new URL(apiUrl, window.location.origin);
                url.searchParams.set('page', String(page));
                url.searchParams.set('kind', pickerKind);
                if (q) {
                    url.searchParams.set('q', q);
                }
                const response = await fetch(url, { credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.error || 'No se pudo cargar la biblioteca.');
                }
                totalPages = data.totalPages || 1;
                renderItems(data.items || [], append);
                const total = data.total || 0;
                setStatus(total ? total + ' archivo' + (total === 1 ? '' : 's') : 'Sin resultados');
                if (moreBtn) {
                    moreBtn.hidden = page >= totalPages;
                }
            } catch (err) {
                if (!append) {
                    grid.innerHTML = '<p class="hint">' + escapeHtmlAttr(err.message) + '</p>';
                }
                setStatus('Error al cargar');
            } finally {
                loading = false;
                grid.classList.remove('is-loading');
            }
        };

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(() => {
                    page = 1;
                    loadMedia(false);
                }, 280);
            });
        }

        moreBtn?.addEventListener('click', () => {
            if (page < totalPages) {
                page += 1;
                loadMedia(true);
            }
        });

        document.addEventListener('media:open-picker', (e) => {
            targetRow = e.detail?.row || null;
            pickerKind = e.detail?.kind || 'image';
            pickerTarget = e.detail?.target || (targetRow ? 'row' : null);
            page = 1;
            if (searchInput) {
                searchInput.value = '';
            }
            syncUploadAccept();
            openModal();
            loadMedia(false);
        });

        document.addEventListener('media:uploaded', (e) => {
            if (pickerTarget === 'video' && e.detail) {
                document.dispatchEvent(new CustomEvent('media:selected', {
                    detail: { target: 'video', media: e.detail },
                }));
                closeModal();
                return;
            }
            if (pickerTarget === 'wysiwyg' && e.detail) {
                document.dispatchEvent(new CustomEvent('media:selected', {
                    detail: { target: 'wysiwyg', media: e.detail },
                }));
                closeModal();
                return;
            }
            if (targetRow && e.detail) {
                applyMediaToRow(targetRow, e.detail);
                closeModal();
            } else {
                page = 1;
                loadMedia(false);
            }
        });

        if (uploadZone) {
            initUploadZone(uploadZone);
        }
    }

    function bindDropZone(preview, onFile) {
        if (!preview) {
            return;
        }
        ['dragenter', 'dragover'].forEach((evt) => {
            preview.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                preview.classList.add('is-dragover');
            });
        });
        preview.addEventListener('dragleave', (e) => {
            if (!preview.contains(e.relatedTarget)) {
                preview.classList.remove('is-dragover');
            }
        });
        preview.addEventListener('drop', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            preview.classList.remove('is-dragover');
            const file = e.dataTransfer?.files?.[0];
            if (file) {
                await onFile(file);
            }
        });
    }

    function initGalleryEditor(editor) {
        if (!editor) {
            return;
        }

        const fieldPrefix = editor.dataset.fieldPrefix || 'images';
        const single = editor.dataset.single === '1';
        const template = editor.querySelector('[data-gallery-row-template], #gallery-row-template');
        const rowsContainer = editor.querySelector('[data-gallery-rows], #gallery-rows');

        const escapeRegExp = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const indexedNamePattern = new RegExp(escapeRegExp(fieldPrefix) + '\\[\\d+\\]');

        const addRow = () => {
            if (!template || !rowsContainer || single) {
                return;
            }
            const index = rowsContainer.querySelectorAll('[data-gallery-row]').length;
            const html = template.innerHTML.replace(/__INDEX__/g, String(index));
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();
            const row = wrapper.firstElementChild;
            rowsContainer.appendChild(row);
            bindRow(row);
            reindexRows();
        };

        const uploadFileToRow = async (row, file) => {
            if (!file) {
                return;
            }
            const validationError = validateUploadFile(file, 'image');
            if (validationError) {
                showRowError(row, validationError);
                return;
            }
            const uploadUrl = editor.dataset.uploadUrl;
            const csrf = editor.dataset.uploadCsrf;
            if (!uploadUrl || !csrf) {
                showRowError(row, 'Falta la configuración de subida.');
                return;
            }

            setRowBusy(row, true, 'Subiendo…');
            showRowError(row, '');
            try {
                const media = await uploadFile(uploadUrl, csrf, file);
                applyMediaToRow(row, media);
            } catch (err) {
                showRowError(row, err.message || 'No se pudo subir la imagen.');
            } finally {
                setRowBusy(row, false);
            }
        };

        const bindRow = (row) => {
            row.querySelectorAll('[data-action=pick-media]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    document.dispatchEvent(new CustomEvent('media:open-picker', { detail: { row } }));
                });
            });

            row.querySelectorAll('[data-action=upload-media]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const input = row.querySelector('[data-field=file]');
                    if (input) {
                        input.click();
                    }
                });
            });

            const fileInput = row.querySelector('[data-field=file]');
            if (fileInput) {
                fileInput.addEventListener('change', async () => {
                    const file = fileInput.files?.[0];
                    fileInput.value = '';
                    await uploadFileToRow(row, file);
                });
            }

            const preview = row.querySelector('.media-dropzone, [data-field=preview]');
            bindDropZone(preview, (file) => uploadFileToRow(row, file));

            const syncManualUrl = (value) => {
                const trimmed = value.trim();
                if (!trimmed) {
                    if (row.classList.contains('featured-picker') || row.querySelector('[data-action=clear-featured]')) {
                        clearMediaRow(row);
                    } else {
                        applyMediaToRow(row, { url: '', id: '' }, { clearMediaId: true });
                        row.classList.remove('has-media');
                        const previewEl = row.querySelector('[data-field=preview]');
                        if (previewEl?.classList.contains('gallery-preview')) {
                            previewEl.setAttribute('data-empty', '1');
                            previewEl.innerHTML =
                                '<span class="gallery-preview-empty">Añadir</span>' +
                                '<span class="media-busy" hidden><span class="media-busy-spinner"></span></span>';
                        }
                    }
                    return;
                }
                // Manual URL overrides library mediaId
                applyMediaToRow(row, { url: trimmed, id: '' }, { clearMediaId: true });
            };

            const urlInput = row.querySelector('[data-field=url]');
            const urlVisible = row.querySelector('[data-field=url-visible]');
            urlInput?.addEventListener('change', () => syncManualUrl(urlInput.value));
            urlVisible?.addEventListener('change', () => {
                if (urlInput) {
                    urlInput.value = urlVisible.value;
                }
                syncManualUrl(urlVisible.value);
            });

            row.querySelector('[data-action=remove-row]')?.addEventListener('click', () => {
                row.remove();
                reindexRows();
            });

            row.querySelector('[data-action=clear-featured]')?.addEventListener('click', () => {
                clearMediaRow(row);
            });

            row.querySelector('[data-action=move-up]')?.addEventListener('click', () => {
                const prev = row.previousElementSibling;
                if (prev) {
                    rowsContainer.insertBefore(row, prev);
                    reindexRows();
                }
            });

            row.querySelector('[data-action=move-down]')?.addEventListener('click', () => {
                const next = row.nextElementSibling;
                if (next) {
                    rowsContainer.insertBefore(next, row);
                    reindexRows();
                }
            });
        };

        const reindexRows = () => {
            if (single) {
                return;
            }
            rowsContainer?.querySelectorAll('[data-gallery-row]').forEach((row, index) => {
                row.querySelectorAll(`[name^="${fieldPrefix}["]`).forEach((input) => {
                    input.name = input.name.replace(indexedNamePattern, `${fieldPrefix}[${index}]`);
                });
            });
        };

        let draggedRow = null;
        rowsContainer?.addEventListener('dragstart', (e) => {
            if (single) {
                return;
            }
            const handle = e.target.closest('.gallery-drag');
            if (!handle) {
                return;
            }
            const row = handle.closest('[data-gallery-row]');
            if (!row) {
                return;
            }
            draggedRow = row;
            row.classList.add('dragging');
            e.dataTransfer?.setData('text/plain', 'reorder');
        });
        rowsContainer?.addEventListener('dragend', () => {
            if (draggedRow) {
                draggedRow.classList.remove('dragging');
                draggedRow = null;
                reindexRows();
            }
        });
        rowsContainer?.addEventListener('dragover', (e) => {
            if (single || !draggedRow) {
                return;
            }
            e.preventDefault();
            const row = e.target.closest('[data-gallery-row]');
            if (!row || row === draggedRow) {
                return;
            }
            const rect = row.getBoundingClientRect();
            const after = e.clientY > rect.top + rect.height / 2;
            if (after) {
                row.after(draggedRow);
            } else {
                row.before(draggedRow);
            }
        });

        editor.closest('form')?.addEventListener('submit', () => reindexRows());

        editor.querySelector('[data-action=add-row]')?.addEventListener('click', addRow);
        rowsContainer?.querySelectorAll('[data-gallery-row]').forEach(bindRow);
    }

    function initGalleryEditors() {
        document.querySelectorAll('[data-gallery-editor], #gallery-editor').forEach((editor) => {
            if (editor.dataset.galleryInitialized === '1') {
                return;
            }
            editor.dataset.galleryInitialized = '1';
            initGalleryEditor(editor);
        });
    }

    document.querySelectorAll('#media-upload-zone').forEach((zone) => {
        zone.dataset.onUpload = 'reload';
        initUploadZone(zone);
    });

    initMediaPicker();
    initGalleryEditors();
})();
