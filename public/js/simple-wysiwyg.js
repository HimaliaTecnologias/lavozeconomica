(function () {
    if (typeof tinymce === 'undefined') {
        return;
    }

    const areas = document.querySelectorAll('textarea.simple-wysiwyg');
    if (areas.length === 0) {
        return;
    }

    const simplePreset = {
        menubar: false,
        statusbar: false,
        plugins: 'lists link autoresize',
        toolbar: 'bold italic underline | bullist numlist | link | removeformat',
    };

    const fullPreset = {
        menubar: 'edit insert format table',
        statusbar: true,
        plugins: 'advlist autolink lists link image table code fullscreen searchreplace visualblocks wordcount autoresize charmap',
        toolbar:
            'undo redo | blocks | bold italic underline strikethrough | ' +
            'alignleft aligncenter alignright | bullist numlist outdent indent | ' +
            'blockquote link image table hr | removeformat | code fullscreen',
        block_formats: 'Párrafo=p; Encabezado 2=h2; Encabezado 3=h3; Encabezado 4=h4; Cita=blockquote',
        extended_valid_elements:
            'iframe[src|width|height|frameborder|allow|allowfullscreen|style|class|title|loading],' +
            'span[*],div[*],table[*],thead[*],tbody[*],tr[*],td[*],th[*],img[*],a[*]',
        invalid_elements: 'script',
        entity_encoding: 'raw',
        convert_urls: false,
        relative_urls: false,
        image_caption: true,
        table_default_attributes: { border: '0' },
        table_default_styles: { width: '100%' },
    };

    const initEditor = (textarea) => {
        const height = parseInt(textarea.dataset.editorHeight || '200', 10);
        const maxLength = parseInt(textarea.getAttribute('maxlength') || '0', 10);
        const preset = textarea.dataset.editorPreset === 'full' ? fullPreset : simplePreset;

        tinymce.init({
            target: textarea,
            base_url: '/vendor/tinymce',
            suffix: '.min',
            language: 'es',
            language_url: '/vendor/tinymce/langs/es.js',
            branding: false,
            promotion: false,
            license_key: 'gpl',
            height,
            min_height: height,
            toolbar_mode: 'wrap',
            content_style: 'body { font-family: Inter, system-ui, sans-serif; font-size: 15px; line-height: 1.55; color: #333; margin: 12px 16px; } img, table { max-width: 100%; } img { height: auto; }',
            link_default_target: '_blank',
            link_assume_external_targets: true,
            autoresize_bottom_margin: 12,
            autoresize_min_height: height,
            ...preset,
            setup(editor) {
                if (maxLength > 0) {
                    editor.on('keydown', (event) => {
                        const text = editor.getContent({ format: 'text' }).trim();
                        const allowedKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Tab'];
                        if (text.length >= maxLength && !allowedKeys.includes(event.key) && !event.ctrlKey && !event.metaKey) {
                            event.preventDefault();
                        }
                    });
                }
            },
        });
    };

    areas.forEach(initEditor);

    document.querySelectorAll('form').forEach((form) => {
        if (!form.querySelector('textarea.simple-wysiwyg')) {
            return;
        }
        form.addEventListener('submit', () => {
            tinymce.triggerSave();
        });
    });
})();
