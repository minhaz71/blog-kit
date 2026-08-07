import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Minimal cart helper used across the storefront.
window.shopkit = {
    async addToCart(productId, qty = 1, variationId = null) {
        const response = await fetch('/cart/add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ product_id: productId, qty, variation_id: variationId }),
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.message || 'Could not add to cart.');
        }

        const data = await response.json();
        document.dispatchEvent(new CustomEvent('cart:updated', { detail: data }));
        return data;
    },

    // Change a line quantity / remove a line from ANY surface (cart drawer,
    // cart page, checkout). Persists to the server cart, then notifies the
    // badge (cart:count) and any open drawer (cart:refresh, re-fetches its
    // fragment). Returns { count, empty, subtotal } so callers can react.
    async _mutateItem(itemId, method, body) {
        const response = await fetch(`/cart/items/${itemId}`, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: body ? JSON.stringify(body) : undefined,
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.message || 'Could not update the cart.');
        }

        const data = await response.json();
        document.dispatchEvent(new CustomEvent('cart:count', { detail: { count: data.count } }));
        document.dispatchEvent(new CustomEvent('cart:refresh', { detail: data }));
        return data;
    },

    setQty(itemId, qty) {
        return this._mutateItem(itemId, 'PATCH', { qty: Math.max(1, Math.min(999, Math.round(qty || 1))) });
    },

    removeItem(itemId) {
        return this._mutateItem(itemId, 'DELETE');
    },

    // Guest pages are served from the full-page cache with cartCount: 0 and
    // ANOTHER visitor's CSRF token baked in. This one request fixes both:
    // the badge hydrates (cart:count only updates the count — cart:updated
    // would also pop the drawer open) and every token on the page is swapped
    // for this session's, so add-to-cart and forms never 419 on cached HTML.
    async hydrateCartCount() {
        try {
            const response = await fetch('/cart/count', { headers: { 'Accept': 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();

            if (data.token) {
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.content = data.token;
                document.querySelectorAll('input[name="_token"]').forEach((el) => (el.value = data.token));
            }

            if (data.count > 0) {
                document.dispatchEvent(new CustomEvent('cart:count', { detail: data }));
            }
        } catch {
            // Badge stays at 0 — never break the page over a count.
        }
    },
};

// Guest pages are served from the full-page cache with ANOTHER visitor's CSRF
// token baked in. Fetch a fresh token for this session so forms (newsletter,
// contact, login) never 419 on cached HTML. This is independent of the store's
// /cart/count endpoint, so a blog install (ecommerce module off) keeps working
// forms without ever touching an ecommerce route.
async function refreshCsrfToken() {
    try {
        const response = await fetch('/csrf-token', { headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const data = await response.json();
        if (data.token) {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.content = data.token;
            document.querySelectorAll('input[name="_token"]').forEach((el) => (el.value = data.token));
        }
    } catch {
        // Non-fatal — never break the page over a token refresh.
    }
}

Alpine.start();
refreshCsrfToken();

// Cart badge hydration only runs when the ecommerce module is on. A blog install
// skips the /cart/count request entirely, so the store never slows the blog.
if (document.body.dataset.ecommerce === '1') {
    window.shopkit.hydrateCartCount();
}

// Floating WhatsApp button: reveal after its configured delay (works on
// cached pages — the element is static, only the reveal is client-side).
(function revealWhatsApp() {
    const fab = document.querySelector('.wa-fab');
    if (!fab) return;
    const delay = Math.max(0, parseInt(fab.dataset.waDelay ?? '3', 10)) * 1000;
    setTimeout(() => fab.classList.add('wa-visible'), delay);
})();
