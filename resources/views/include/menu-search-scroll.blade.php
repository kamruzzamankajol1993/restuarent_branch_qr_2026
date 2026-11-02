<script>
(function () {
    const grid = document.getElementById('append-data') || document.getElementById('append-food-data');
    const input = document.querySelector('.rapp-search-input');
    if (!grid || !input) return;

    let nextPage = grid.dataset.nextPage || '';
    let busy = false;
    let searchTimer;
    let revision = 0;
    const loader = document.querySelector('.ajax-load');

    function readPage(html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        return { doc: doc, grid: doc.getElementById(grid.id) };
    }

    function search() {
        const current = ++revision;
        const url = new URL(window.location.href);
        const term = input.value.trim();
        if (term) url.searchParams.set('search', term);
        else url.searchParams.delete('search');
        url.searchParams.delete('page');
        busy = true;
        if (loader) loader.style.display = 'block';

        fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => { if (!response.ok) throw new Error('Search failed'); return response.text(); })
            .then(html => {
                if (current !== revision) return;
                const parsed = readPage(html);
                if (!parsed.grid) throw new Error('Invalid search response');
                grid.innerHTML = parsed.grid.innerHTML;
                nextPage = parsed.grid.dataset.nextPage || '';
                grid.dataset.nextPage = nextPage;
                const heading = document.querySelector('.rapp-cat-group-head');
                const updatedHeading = parsed.doc.querySelector('.rapp-cat-group-head');
                if (heading && updatedHeading) heading.innerHTML = updatedHeading.innerHTML;
                window.history.replaceState(null, '', url.toString());
                window.qrRefreshFoodQuantities?.();
            })
            .catch(() => { if (current === revision) window.showSweetAlert?.('error', 'Search unavailable', 'Please retry.'); })
            .finally(() => { if (current === revision) { busy = false; if (loader) loader.style.display = 'none'; } });
    }

    input.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(search, 350);
    });

    async function loadNext() {
        if (busy || !nextPage) return;
        busy = true;
        const current = revision;
        const loadingUrl = nextPage;
        if (loader) loader.style.display = 'block';
        try {
            const response = await fetch(loadingUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('Unable to load more dishes');
            const parsed = readPage(await response.text());
            if (current !== revision) return;
            if (!parsed.grid) throw new Error('Invalid menu response');
            grid.insertAdjacentHTML('beforeend', parsed.grid.innerHTML);
            nextPage = parsed.grid.dataset.nextPage || '';
            grid.dataset.nextPage = nextPage;
            window.qrRefreshFoodQuantities?.();
        } catch (error) {
            // Keep nextPage for retry on the next scroll rather than losing dishes.
            console.warn(error);
        } finally {
            if (current === revision) { busy = false; if (loader) loader.style.display = 'none'; }
        }
    }

    window.addEventListener('scroll', () => {
        if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 250) loadNext();
    }, { passive: true });
})();
</script>
