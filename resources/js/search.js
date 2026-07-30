/* ── Live AJAX product search ────────────────────────────────────────
   Loaded as a deferred module and initialized on window load, so it never
   blocks first paint. Attaches to every [data-search] box (desktop +
   mobile header inputs), shows a debounced results dropdown, supports
   keyboard navigation, and logs the settled query for analytics. */

function initSearchBox(box) {
    const input = box.querySelector('input[type="search"]');
    const panel = box.querySelector('[data-search-panel]');
    if (!input || !panel) return;

    const endpoint = box.dataset.endpoint;
    const minChars = parseInt(box.dataset.minChars || '2', 10);
    let displayTimer, logTimer, activeIndex = -1, lastTerm = '', controller = null;

    const close = () => { panel.innerHTML = ''; panel.hidden = true; activeIndex = -1; };

    const render = (data) => {
        if (!data.enabled) return close();
        const term = (data.term || '').trim();
        if (!data.results.length) {
            panel.innerHTML = `<div class="hs-empty">No products match “${escapeHtml(term)}”.</div>
                <a class="hs-all" href="${data.view_all}">Search everything for “${escapeHtml(term)}”</a>`;
            panel.hidden = false;
            return;
        }
        const rows = data.results.map((r, i) => `
            <a class="hs-item" href="${r.url}" data-index="${i}" role="option">
                <span class="hs-thumb">${r.image ? `<img src="${r.image}" alt="" loading="lazy">` : ''}</span>
                <span class="hs-body">
                    <span class="hs-name">${escapeHtml(r.name)}</span>
                    <span class="hs-price">${r.price}${r.on_sale ? ` <s>${r.old_price}</s>` : ''}</span>
                </span>
            </a>`).join('');
        panel.innerHTML = rows + `<a class="hs-all" href="${data.view_all}">View all ${data.total} result${data.total === 1 ? '' : 's'}</a>`;
        panel.hidden = false;
        activeIndex = -1;
    };

    const fetchSuggest = (term, log) => {
        if (controller) controller.abort();
        controller = new AbortController();
        const url = `${endpoint}?q=${encodeURIComponent(term)}${log ? '&log=1' : ''}`;
        return fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then((r) => (r.ok ? r.json() : null))
            .catch(() => null);
    };

    input.addEventListener('input', () => {
        const term = input.value.trim();
        clearTimeout(displayTimer);
        clearTimeout(logTimer);
        if (term.length < minChars) return close();

        displayTimer = setTimeout(async () => {
            const data = await fetchSuggest(term, false);
            if (data && input.value.trim() === term) render(data);
        }, 200);

        // Settle log: fires once the customer stops typing. Results are
        // cached, so this request is cheap and only records analytics.
        logTimer = setTimeout(() => {
            if (term !== lastTerm) { lastTerm = term; fetchSuggest(term, true); }
        }, 1100);
    });

    input.addEventListener('keydown', (e) => {
        const items = [...panel.querySelectorAll('.hs-item, .hs-all')];
        if (e.key === 'ArrowDown' && items.length) {
            e.preventDefault(); activeIndex = Math.min(activeIndex + 1, items.length - 1); highlight(items);
        } else if (e.key === 'ArrowUp' && items.length) {
            e.preventDefault(); activeIndex = Math.max(activeIndex - 1, -1); highlight(items);
        } else if (e.key === 'Enter') {
            if (activeIndex >= 0 && items[activeIndex]) { e.preventDefault(); items[activeIndex].click(); }
            // otherwise the form submits to the full /search page as normal.
        } else if (e.key === 'Escape') {
            close();
        }
    });

    const highlight = (items) => {
        items.forEach((el, i) => el.classList.toggle('is-active', i === activeIndex));
        if (activeIndex >= 0) items[activeIndex].scrollIntoView({ block: 'nearest' });
    };

    document.addEventListener('click', (e) => { if (!box.contains(e.target)) close(); });
    input.addEventListener('focus', () => { if (panel.innerHTML) panel.hidden = false; });
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

window.addEventListener('load', () => {
    document.querySelectorAll('[data-search]').forEach(initSearchBox);
});
