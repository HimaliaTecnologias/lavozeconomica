(function () {
    'use strict';

    const form = document.getElementById('articles-bulk-form');
    const table = document.getElementById('articles-table');
    if (!form || !table) {
        return;
    }

    const selectAll = document.getElementById('articles-select-all');
    const actionSelect = document.getElementById('articles-bulk-action');
    const applyBtn = document.getElementById('articles-bulk-apply');
    const countEl = document.getElementById('articles-bulk-count');
    const rowChecks = () => Array.from(table.querySelectorAll('.article-row-check'));

    function selectedCount() {
        return rowChecks().filter((input) => input.checked).length;
    }

    function syncSelection() {
        const checks = rowChecks();
        const selected = checks.filter((input) => input.checked);
        const total = checks.length;
        const count = selected.length;

        checks.forEach((input) => {
            const row = input.closest('tr');
            if (row) {
                row.classList.toggle('is-selected', input.checked);
            }
        });

        if (selectAll) {
            selectAll.checked = total > 0 && count === total;
            selectAll.indeterminate = count > 0 && count < total;
        }

        if (countEl) {
            const template = countEl.dataset.template || '%d seleccionada(s)';
            countEl.textContent = template.replace('%d', String(count));
        }

        if (applyBtn) {
            applyBtn.disabled = count === 0 || !actionSelect || actionSelect.value === '';
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowChecks().forEach((input) => {
                input.checked = selectAll.checked;
            });
            syncSelection();
        });
    }

    table.addEventListener('change', function (event) {
        if (event.target && event.target.classList.contains('article-row-check')) {
            syncSelection();
        }
    });

    if (actionSelect) {
        actionSelect.addEventListener('change', syncSelection);
    }

    form.addEventListener('submit', function (event) {
        const count = selectedCount();
        const action = actionSelect ? actionSelect.value : '';
        if (count === 0 || action === '') {
            event.preventDefault();
            return;
        }

        if (action === 'delete') {
            const message = count === 1
                ? '¿Eliminar definitivamente 1 noticia? Esta acción no se puede deshacer.'
                : '¿Eliminar definitivamente ' + count + ' noticias? Esta acción no se puede deshacer.';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
            return;
        }

        if (action === 'trash') {
            const message = count === 1
                ? '¿Mover 1 noticia a la papelera?'
                : '¿Mover ' + count + ' noticias a la papelera?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        }
    });

    document.querySelectorAll('[data-confirm]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            const message = button.getAttribute('data-confirm');
            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    syncSelection();
})();
