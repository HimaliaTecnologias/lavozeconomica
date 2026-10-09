(function () {
    const section = document.querySelector('.article-category-feed');
    if (!section) {
        return;
    }

    const list = document.getElementById('article-category-feed-list');
    const pagination = document.getElementById('article-category-feed-pagination');
    const pagesWrap = document.getElementById('article-category-feed-pages');
    const meta = document.getElementById('article-category-feed-meta');
    const status = document.getElementById('article-category-feed-status');
    const feedUrl = section.dataset.feedUrl;
    const prevBtn = pagination ? pagination.querySelector('[data-feed-prev]') : null;
    const nextBtn = pagination ? pagination.querySelector('[data-feed-next]') : null;

    if (!list || !feedUrl || !pagination) {
        return;
    }

    let page = parseInt(section.dataset.page || '1', 10) || 1;
    let totalPages = parseInt(section.dataset.totalPages || '1', 10) || 1;
    let loading = false;

    function setLoading(active) {
        loading = active;
        if (status) {
            status.hidden = !active;
        }
        if (prevBtn) {
            prevBtn.disabled = active || page <= 1;
        }
        if (nextBtn) {
            nextBtn.disabled = active || page >= totalPages;
        }
        pagination.setAttribute('aria-busy', active ? 'true' : 'false');
    }

    function buildPagesHtml(current, total) {
        let html = '';
        for (let p = 1; p <= total; p++) {
            if (p === current) {
                html += '<span class="list-pagination-current" aria-current="page">' + p + '</span>';
            } else if (p === 1 || p === total || (p >= current - 2 && p <= current + 2)) {
                html += '<button type="button" class="list-pagination-link" data-feed-page="' + p + '">' + p + '</button>';
            } else if (p === current - 3 || p === current + 3) {
                html += '<span class="list-pagination-ellipsis" aria-hidden="true">…</span>';
            }
        }
        return html;
    }

    function syncControls() {
        section.dataset.page = String(page);
        section.dataset.totalPages = String(totalPages);
        if (pagesWrap) {
            pagesWrap.innerHTML = buildPagesHtml(page, totalPages);
        }
        if (meta) {
            meta.textContent = 'Página ' + page + ' de ' + totalPages;
        }
        if (prevBtn) {
            prevBtn.disabled = page <= 1;
        }
        if (nextBtn) {
            nextBtn.disabled = page >= totalPages;
        }
    }

    async function goToPage(target) {
        const next = Math.max(1, Math.min(totalPages, target));
        if (loading || next === page) {
            return;
        }

        setLoading(true);
        try {
            const url = new URL(feedUrl, window.location.origin);
            url.searchParams.set('page', String(next));

            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            if (typeof data.totalPages === 'number' && data.totalPages > 0) {
                totalPages = data.totalPages;
            }
            if (typeof data.page === 'number' && data.page > 0) {
                page = data.page;
            } else {
                page = next;
            }

            list.innerHTML = data.html || '';
            syncControls();
            section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (e) {
            // keep current page on network errors
        } finally {
            setLoading(false);
            syncControls();
        }
    }

    pagination.addEventListener('click', function (event) {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        if (target.closest('[data-feed-prev]')) {
            goToPage(page - 1);
            return;
        }
        if (target.closest('[data-feed-next]')) {
            goToPage(page + 1);
            return;
        }
        const pageBtn = target.closest('[data-feed-page]');
        if (pageBtn) {
            const requested = parseInt(pageBtn.getAttribute('data-feed-page') || '', 10);
            if (!Number.isNaN(requested)) {
                goToPage(requested);
            }
        }
    });
})();
