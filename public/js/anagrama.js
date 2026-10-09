(function () {
    const app = document.getElementById('anagrama-app');
    if (!app) return;

    const word = (app.dataset.word || '').toUpperCase();
    const statusEl = document.getElementById('anagrama-status');
    const inputEl = document.getElementById('anagrama-input');

    document.getElementById('anagrama-check')?.addEventListener('click', () => {
        const guess = (inputEl?.value || '').toUpperCase().replace(/\s+/g, '');
        if (guess === '') {
            statusEl.textContent = 'Escribe tu respuesta.';
            return;
        }
        if (guess === word) {
            statusEl.textContent = '¡Correcto! La palabra era «' + word + '».';
            inputEl.disabled = true;
        } else {
            statusEl.textContent = 'No es correcto. Inténtalo otra vez.';
        }
    });

    inputEl?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            document.getElementById('anagrama-check')?.click();
        }
    });
})();
