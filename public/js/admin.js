(function () {
    'use strict';

    const body = document.body;
    const THEME_KEY = 'mymedia-admin-theme';
    const SIDEBAR_KEY = 'mymedia-admin-sidebar-collapsed';

    function applyTheme(theme) {
        body.classList.toggle('admin-theme-dark', theme === 'dark');
        const toggle = document.getElementById('admin-theme-toggle');
        if (toggle) {
            const label = toggle.querySelector('span:last-child');
            if (label) label.textContent = theme === 'dark' ? 'Modo claro' : 'Modo oscuro';
        }
    }

    const savedTheme = localStorage.getItem(THEME_KEY);
    if (savedTheme === 'dark') applyTheme('dark');

    const themeToggle = document.getElementById('admin-theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            const next = body.classList.contains('admin-theme-dark') ? 'light' : 'dark';
            localStorage.setItem(THEME_KEY, next);
            applyTheme(next);
        });
    }

    const menuToggle = document.getElementById('admin-menu-toggle');
    const backdrop = document.getElementById('admin-sidebar-backdrop');
    const app = document.querySelector('.admin-app');
    const mqMobile = window.matchMedia('(max-width: 960px)');

    if (menuToggle && app) {
        function isMobile() {
            return mqMobile.matches;
        }

        function setToggleState(expanded) {
            menuToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            menuToggle.setAttribute('aria-label', expanded ? 'Ocultar menú' : 'Mostrar menú');
        }

        function closeMobileSidebar() {
            app.classList.remove('sidebar-open');
            if (backdrop) backdrop.hidden = true;
            setToggleState(false);
        }

        function applyDesktopCollapsed(collapsed) {
            app.classList.toggle('sidebar-collapsed', collapsed);
            setToggleState(!collapsed);
        }

        // Restore desktop preference (mobile starts closed)
        if (!isMobile() && localStorage.getItem(SIDEBAR_KEY) === '1') {
            applyDesktopCollapsed(true);
        } else if (!isMobile()) {
            setToggleState(true);
        } else {
            setToggleState(false);
        }
        document.documentElement.classList.remove('admin-sidebar-pref-collapsed');

        menuToggle.addEventListener('click', function () {
            if (isMobile()) {
                const open = app.classList.toggle('sidebar-open');
                if (backdrop) backdrop.hidden = !open;
                setToggleState(open);
                return;
            }

            const collapsed = !app.classList.contains('sidebar-collapsed');
            applyDesktopCollapsed(collapsed);
            localStorage.setItem(SIDEBAR_KEY, collapsed ? '1' : '0');
        });

        if (backdrop) {
            backdrop.addEventListener('click', closeMobileSidebar);
        }

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (isMobile()) {
                closeMobileSidebar();
            }
        });

        mqMobile.addEventListener('change', function () {
            app.classList.remove('sidebar-open');
            if (backdrop) backdrop.hidden = true;

            if (isMobile()) {
                app.classList.remove('sidebar-collapsed');
                setToggleState(false);
            } else {
                const collapsed = localStorage.getItem(SIDEBAR_KEY) === '1';
                applyDesktopCollapsed(collapsed);
            }
        });
    }

    document.querySelectorAll('[data-toast]').forEach(function (el) {
        const root = document.getElementById('admin-toast-root');
        if (!root) return;
        const toast = document.createElement('div');
        toast.className = 'admin-toast ' + (el.classList.contains('flash-error') ? 'admin-toast--error' : el.classList.contains('flash-info') ? 'admin-toast--info' : 'admin-toast--success');
        toast.textContent = el.textContent.trim();
        root.appendChild(toast);
        el.remove();
        setTimeout(function () {
            toast.classList.add('is-out');
            setTimeout(function () { toast.remove(); }, 300);
        }, 4200);
    });

    document.addEventListener('keydown', function (e) {
        const tag = (e.target && e.target.tagName) || '';
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || e.target?.isContentEditable) return;
        if (e.key === 'n' || e.key === 'N') {
            const link = document.querySelector('[data-shortcut-new]');
            if (link) {
                e.preventDefault();
                window.location.href = link.href;
            }
        }
        if (e.key === '/') {
            const search = document.querySelector('.admin-filters input[type="search"], #filter-q');
            if (search) {
                e.preventDefault();
                search.focus();
            }
        }
    });
})();
