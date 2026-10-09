(function () {
    'use strict';

    const textarea = document.getElementById('bodyHtml');
    if (!textarea) {
        return;
    }

    const uploadUrl = textarea.dataset.uploadUrl || '';
    const uploadCsrf = textarea.dataset.uploadCsrf || '';
    const fieldWrap = textarea.closest('.article-body-editor') || textarea.parentElement;

    function setChecklist(key, ok) {
        const item = document.querySelector('[data-checklist="' + key + '"]');
        if (!item) {
            return;
        }
        item.classList.toggle('is-ok', !!ok);
        item.classList.toggle('is-warn', !ok);
    }

    function isBodyEmpty(html) {
        const text = String(html || '')
            .replace(/<[^>]*>/g, ' ')
            .replace(/&nbsp;/gi, ' ')
            .replace(/\s+/g, ' ')
            .trim();
        return text === '';
    }

    function syncBodyChecklist(html) {
        setChecklist('body', !isBodyEmpty(html));
    }

    function showEditorFallback(message) {
        if (!fieldWrap || fieldWrap.querySelector('[data-editor-fallback]')) {
            return;
        }
        const note = document.createElement('p');
        note.className = 'error-msg';
        note.dataset.editorFallback = '1';
        note.textContent = message;
        fieldWrap.appendChild(note);
    }

    if (typeof tinymce === 'undefined') {
        showEditorFallback('No se pudo cargar el editor visual. Recarga la página o comprueba que /vendor/tinymce esté disponible.');
        return;
    }

    function insertImage(url, alt) {
        const editor = tinymce.get('bodyHtml');
        if (!editor) {
            return;
        }
        const safeAlt = (alt || '').replace(/"/g, '&quot;');
        editor.insertContent('<img src="' + url + '" alt="' + safeAlt + '">');
    }

    document.addEventListener('media:selected', function (e) {
        if (e.detail?.target === 'wysiwyg' && e.detail.media?.url) {
            insertImage(e.detail.media.url, e.detail.media.alt || e.detail.media.title || '');
        }
    });

    tinymce.init({
        selector: '#bodyHtml',
        base_url: '/vendor/tinymce',
        suffix: '.min',
        language: 'es',
        language_url: '/vendor/tinymce/langs/es.js',
        height: 820,
        min_height: 720,
        autoresize_min_height: 720,
        autoresize_bottom_margin: 24,
        menubar: 'edit insert format table',
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap',
            'preview', 'anchor', 'searchreplace', 'visualblocks', 'code',
            'fullscreen', 'insertdatetime', 'media', 'table', 'help', 'wordcount', 'autoresize',
        ],
        toolbar:
            'undo redo | blocks | bold italic underline strikethrough | ' +
            'alignleft aligncenter alignright | bullist numlist outdent indent | ' +
            'blockquote link image media_library | removeformat code fullscreen',
        block_formats: 'Párrafo=p; Encabezado 2=h2; Encabezado 3=h3; Encabezado 4=h4; Cita=blockquote',
        content_style:
            'body { font-family: Georgia, "Times New Roman", serif; font-size: 17px; line-height: 1.65; color: #1a1a1a; margin: 1rem 1.25rem; } ' +
            'img { max-width: 100%; height: auto; }',
        promotion: false,
        branding: false,
        license_key: 'gpl',
        convert_urls: false,
        relative_urls: false,
        remove_script_host: false,
        paste_data_images: true,
        image_caption: true,
        setup(editor) {
            editor.ui.registry.addButton('media_library', {
                text: 'Biblioteca',
                tooltip: 'Insertar imagen desde la biblioteca',
                onAction() {
                    document.dispatchEvent(new CustomEvent('media:open-picker', {
                        detail: { kind: 'image', target: 'wysiwyg' },
                    }));
                },
            });

            const sync = () => syncBodyChecklist(editor.getContent());
            editor.on('init input change SetContent undo redo keyup', sync);
        },
        images_upload_handler(blobInfo, progress) {
            if (!uploadUrl || !uploadCsrf) {
                return Promise.reject('Subida no configurada.');
            }

            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('file', blobInfo.blob(), blobInfo.filename());
                formData.append('_token', uploadCsrf);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', uploadUrl);
                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) {
                        progress(e.loaded / e.total * 100);
                    }
                };
                xhr.onload = () => {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (xhr.status >= 200 && xhr.status < 300 && data.media?.url) {
                            resolve(data.media.url);
                        } else {
                            reject(data.error || 'Error al subir la imagen.');
                        }
                    } catch {
                        reject('Error al subir la imagen.');
                    }
                };
                xhr.onerror = () => reject('Error de red al subir.');
                xhr.send(formData);
            });
        },
    });

    const form = textarea.closest('form');
    if (form) {
        form.addEventListener('submit', function () {
            if (tinymce.get('bodyHtml')) {
                tinymce.get('bodyHtml').save();
            }
        });
    }

    // Side checklist for other fields
    const titleInput = document.getElementById('title');
    const leadInput = document.getElementById('lead');
    const categoryInput = document.getElementById('categoryId');

    titleInput?.addEventListener('input', () => setChecklist('title', titleInput.value.trim() !== ''));
    leadInput?.addEventListener('input', () => setChecklist('lead', leadInput.value.trim() !== ''));
    categoryInput?.addEventListener('change', () => setChecklist('category', categoryInput.value !== ''));

    syncBodyChecklist(textarea.value);
})();
