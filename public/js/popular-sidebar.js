(function () {
    document.querySelectorAll('.popular-sidebar').forEach((root) => {
        const list = root.querySelector('[data-popular-list]');
        const tabs = root.querySelectorAll('.popular-period-tab');
        const url = root.dataset.popularUrl;
        if (!list || !url || tabs.length === 0) {
            return;
        }

        let loading = false;
        let currentPeriod = root.dataset.popularPeriod || 'week';

        function setActive(period) {
            currentPeriod = period;
            root.dataset.popularPeriod = period;
            tabs.forEach((tab) => {
                const active = tab.dataset.period === period;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        async function loadPeriod(period) {
            if (loading || period === currentPeriod) {
                return;
            }

            loading = true;
            root.classList.add('is-loading');
            setActive(period);

            try {
                const endpoint = new URL(url, window.location.origin);
                endpoint.searchParams.set('period', period);
                endpoint.searchParams.set('limit', root.dataset.popularLimit || '5');
                endpoint.searchParams.set('home', root.dataset.popularHome || '0');
                if (root.dataset.popularExclude) {
                    endpoint.searchParams.set('exclude', root.dataset.popularExclude);
                }
                if (root.dataset.popularCategories) {
                    endpoint.searchParams.set('categories', root.dataset.popularCategories);
                }

                const response = await fetch(endpoint.toString(), {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                if (typeof data.html === 'string') {
                    list.innerHTML = data.html;
                }
            } catch (e) {
                // Keep previous list on network errors.
            } finally {
                loading = false;
                root.classList.remove('is-loading');
            }
        }

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const period = tab.dataset.period;
                if (period) {
                    loadPeriod(period);
                }
            });
        });
    });
})();
