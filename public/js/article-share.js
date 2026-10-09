(function () {
    'use strict';

    function initCopyButtons() {
        document.querySelectorAll('.article-share-copy').forEach(function (btn) {
            if (btn.dataset.shareInit === '1') {
                return;
            }
            btn.dataset.shareInit = '1';

            var label = btn.querySelector('.article-share-copy-label');
            var defaultLabel = label ? label.textContent : 'Copiar enlace';

            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-share-url') || '';
                if (!url) {
                    return;
                }

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(url).then(function () {
                        if (label) {
                            label.textContent = 'Enlace copiado';
                            setTimeout(function () {
                                label.textContent = defaultLabel;
                            }, 2200);
                        }
                    }).catch(function () {
                        window.prompt('Copia el enlace:', url);
                    });
                } else {
                    window.prompt('Copia el enlace:', url);
                }
            });
        });
    }

    function initPrintButtons() {
        document.querySelectorAll('.article-share-print').forEach(function (btn) {
            if (btn.dataset.shareInit === '1') {
                return;
            }
            btn.dataset.shareInit = '1';
            btn.addEventListener('click', function () {
                window.print();
            });
        });
    }

    function initNativeShare() {
        if (!navigator.share) {
            return;
        }

        document.querySelectorAll('.article-share-native').forEach(function (btn) {
            btn.hidden = false;
            if (btn.dataset.shareInit === '1') {
                return;
            }
            btn.dataset.shareInit = '1';

            btn.addEventListener('click', function () {
                navigator.share({
                    title: btn.getAttribute('data-share-title') || document.title,
                    text: btn.getAttribute('data-share-text') || '',
                    url: btn.getAttribute('data-share-url') || window.location.href,
                }).catch(function () {
                    /* usuario canceló o error */
                });
            });
        });
    }

    initCopyButtons();
    initPrintButtons();
    initNativeShare();
})();
