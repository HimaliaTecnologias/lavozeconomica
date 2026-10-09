(function () {
    const app = document.getElementById('sudoku-app');
    if (!app) return;

    const puzzle = JSON.parse(app.dataset.puzzle || '[]');
    const solution = JSON.parse(app.dataset.solution || '[]');
    const gridEl = document.getElementById('sudoku-grid');
    const statusEl = document.getElementById('sudoku-status');
    const progressEl = document.getElementById('sudoku-progress');
    const emptyTotalEl = document.getElementById('sudoku-empty-total');
    const padEl = document.getElementById('sudoku-pad');
    /** @type {HTMLInputElement[]} */
    const inputs = [];
    /** @type {HTMLInputElement|null} */
    let activeInput = null;

    function updateProgress() {
        if (!progressEl || !emptyTotalEl) return;
        const filled = inputs.filter((input) => input.value.trim() !== '').length;
        progressEl.textContent = String(filled);
        emptyTotalEl.textContent = String(inputs.length);
    }

    function setActive(input) {
        if (activeInput && activeInput.parentElement) {
            activeInput.parentElement.classList.remove('sudoku-cell--active');
        }
        activeInput = input;
        if (input && input.parentElement) {
            input.parentElement.classList.add('sudoku-cell--active');
        }
    }

    function focusCell(row, col) {
        const next = inputs.find((input) => Number(input.dataset.row) === row && Number(input.dataset.col) === col);
        if (next) {
            next.focus();
            setActive(next);
        }
    }

    function buildGrid() {
        gridEl.innerHTML = '';
        inputs.length = 0;

        for (let r = 0; r < 9; r++) {
            for (let c = 0; c < 9; c++) {
                const cell = document.createElement('div');
                cell.className = 'sudoku-cell';
                cell.setAttribute('role', 'gridcell');
                if (c % 3 === 0) cell.classList.add('sudoku-cell--col-border');
                if (r % 3 === 0) cell.classList.add('sudoku-cell--row-border');
                if (c === 8) cell.classList.add('sudoku-cell--col-end');
                if (r === 8) cell.classList.add('sudoku-cell--row-end');

                const value = puzzle[r][c];
                if (value !== 0) {
                    cell.textContent = String(value);
                    cell.classList.add('sudoku-cell--fixed');
                } else {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.inputMode = 'numeric';
                    input.pattern = '[1-9]';
                    input.maxLength = 1;
                    input.className = 'sudoku-input';
                    input.dataset.row = String(r);
                    input.dataset.col = String(c);
                    input.setAttribute('aria-label', 'Fila ' + (r + 1) + ', columna ' + (c + 1));
                    input.addEventListener('input', () => {
                        input.value = input.value.replace(/[^1-9]/g, '');
                        cell.classList.remove('sudoku-cell--error', 'sudoku-cell--ok');
                        updateProgress();
                    });
                    input.addEventListener('focus', () => setActive(input));
                    input.addEventListener('keydown', (event) => {
                        const row = Number(input.dataset.row);
                        const col = Number(input.dataset.col);
                        if (event.key === 'ArrowUp') {
                            event.preventDefault();
                            focusNearestEditable(row - 1, col, -1, 0);
                        } else if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            focusNearestEditable(row + 1, col, 1, 0);
                        } else if (event.key === 'ArrowLeft') {
                            event.preventDefault();
                            focusNearestEditable(row, col - 1, 0, -1);
                        } else if (event.key === 'ArrowRight') {
                            event.preventDefault();
                            focusNearestEditable(row, col + 1, 0, 1);
                        } else if (event.key === 'Backspace' || event.key === 'Delete') {
                            input.value = '';
                            cell.classList.remove('sudoku-cell--error', 'sudoku-cell--ok');
                            updateProgress();
                        }
                    });
                    cell.appendChild(input);
                    inputs.push(input);
                }
                gridEl.appendChild(cell);
            }
        }
        updateProgress();
    }

    function focusNearestEditable(startRow, startCol, dRow, dCol) {
        let r = startRow;
        let c = startCol;
        for (let i = 0; i < 81; i++) {
            if (r < 0 || r > 8 || c < 0 || c > 8) return;
            const found = inputs.find((input) => Number(input.dataset.row) === r && Number(input.dataset.col) === c);
            if (found) {
                found.focus();
                setActive(found);
                return;
            }
            r += dRow;
            c += dCol;
        }
    }

    function applyNumber(num) {
        if (!activeInput) {
            const first = inputs.find((input) => input.value.trim() === '') || inputs[0];
            if (first) {
                first.focus();
                setActive(first);
            }
        }
        if (!activeInput) return;
        if (num === 0) {
            activeInput.value = '';
        } else {
            activeInput.value = String(num);
        }
        activeInput.parentElement?.classList.remove('sudoku-cell--error', 'sudoku-cell--ok');
        updateProgress();
    }

    function checkSolution() {
        let complete = true;
        let correct = true;
        inputs.forEach((input) => {
            const r = Number(input.dataset.row);
            const c = Number(input.dataset.col);
            const val = input.value.trim();
            const cell = input.parentElement;
            cell.classList.remove('sudoku-cell--error', 'sudoku-cell--ok');
            if (val === '') {
                complete = false;
                return;
            }
            if (Number(val) === solution[r][c]) {
                cell.classList.add('sudoku-cell--ok');
            } else {
                cell.classList.add('sudoku-cell--error');
                correct = false;
            }
        });
        statusEl.classList.remove('is-ok', 'is-warn');
        if (!complete) {
            statusEl.textContent = 'Completa todas las celdas vacías.';
            statusEl.classList.add('is-warn');
            return;
        }
        if (correct) {
            statusEl.textContent = '¡Correcto! Has resuelto el sudoku del día.';
            statusEl.classList.add('is-ok');
        } else {
            statusEl.textContent = 'Hay errores. Revisa las celdas en rojo.';
            statusEl.classList.add('is-warn');
        }
    }

    function giveHint() {
        const empty = inputs.filter((input) => input.value.trim() === '');
        if (empty.length === 0) {
            statusEl.textContent = 'No quedan celdas vacías.';
            statusEl.classList.remove('is-ok');
            statusEl.classList.add('is-warn');
            return;
        }
        const pick = empty[Math.floor(Math.random() * empty.length)];
        const r = Number(pick.dataset.row);
        const c = Number(pick.dataset.col);
        pick.value = String(solution[r][c]);
        pick.parentElement.classList.remove('sudoku-cell--error');
        pick.parentElement.classList.add('sudoku-cell--ok');
        setActive(pick);
        pick.focus();
        updateProgress();
        statusEl.textContent = 'Pista revelada.';
        statusEl.classList.remove('is-warn');
        statusEl.classList.add('is-ok');
    }

    function resetGrid() {
        inputs.forEach((input) => {
            input.value = '';
            input.parentElement.classList.remove('sudoku-cell--error', 'sudoku-cell--ok');
        });
        statusEl.textContent = '';
        statusEl.classList.remove('is-ok', 'is-warn');
        updateProgress();
    }

    document.getElementById('sudoku-check')?.addEventListener('click', checkSolution);
    document.getElementById('sudoku-hint')?.addEventListener('click', giveHint);
    document.getElementById('sudoku-reset')?.addEventListener('click', resetGrid);

    padEl?.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-num]');
        if (!btn) return;
        applyNumber(Number(btn.getAttribute('data-num')));
    });

    buildGrid();
})();
