(() => {
    const nav = document.querySelector('[data-main-nav]');
    if (!nav) {
        return;
    }

    const toggle = nav.querySelector('[data-main-nav-toggle]');
    const panel = nav.querySelector('[data-main-nav-panel]');
    const desktopMq = window.matchMedia('(max-width: 1024px)');

    const closeDropdowns = (except = null) => {
        nav.querySelectorAll('[data-nav-dropdown].is-open').forEach((item) => {
            if (except && item === except) {
                return;
            }
            item.classList.remove('is-open');
            const trigger = item.querySelector('[data-nav-dropdown-trigger]');
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    };

    nav.querySelectorAll('[data-nav-dropdown]').forEach((item) => {
        const trigger = item.querySelector('[data-nav-dropdown-trigger]');
        if (!trigger) {
            return;
        }

        trigger.addEventListener('click', (event) => {
            const href = trigger.getAttribute('href') || '';
            const isHash = href === '#' || href.startsWith('#');

            if (!desktopMq.matches) {
                // Desktop: click abre/cierra el desplegable (además del hover).
                if (isHash || item.querySelector('.nav-submenu')) {
                    event.preventDefault();
                    const willOpen = !item.classList.contains('is-open');
                    closeDropdowns(item);
                    item.classList.toggle('is-open', willOpen);
                    trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                }
                return;
            }

            // Móvil: el panel ya muestra submenús; evitar saltar a "#".
            if (isHash) {
                event.preventDefault();
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }
        if (!event.target.closest('[data-nav-dropdown]')) {
            closeDropdowns();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeDropdowns();
        }
    });

    if (!toggle || !panel) {
        return;
    }

    const label = toggle.querySelector('.main-nav-toggle-label');

    const setOpen = (open) => {
        nav.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (label) {
            label.textContent = open ? 'Cerrar' : 'Menú';
        }
        document.body.classList.toggle('main-nav-lock', open);
        if (!open) {
            closeDropdowns();
        }
    };

    toggle.addEventListener('click', () => {
        setOpen(!nav.classList.contains('is-open'));
    });

    panel.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (desktopMq.matches && !(link.getAttribute('href') || '').startsWith('#')) {
                setOpen(false);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && nav.classList.contains('is-open')) {
            setOpen(false);
            toggle.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (!nav.classList.contains('is-open')) {
            return;
        }
        if (!nav.contains(event.target)) {
            setOpen(false);
        }
    });

    desktopMq.addEventListener('change', (event) => {
        if (!event.matches) {
            setOpen(false);
            closeDropdowns();
        }
    });
})();
