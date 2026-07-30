{{-- WordPress-style browser autosave for admin create/edit forms. --}}
<script>
(function () {
    'use strict';

    const form = document.querySelector('form[wire\\:submit]');
    if (!form) return;

    const KEY = 'shopkit-autosave:' + location.pathname;
    const MAX_AGE_MS = 24 * 60 * 60 * 1000;

    function fields() {
        return Array.from(form.querySelectorAll('input, textarea, select')).filter((el) => {
            if (['hidden', 'file', 'password'].includes(el.type)) return false;
            return el.id || el.name || el.getAttribute('wire:model') || el.getAttribute('wire:model.live');
        });
    }

    function keyOf(el) {
        return el.getAttribute('wire:model') || el.getAttribute('wire:model.live') || el.id || el.name;
    }

    function snapshot() {
        const data = {};
        for (const el of fields()) {
            const k = keyOf(el);
            if (!k) continue;
            data[k] = el.type === 'checkbox' ? el.checked : el.value;
        }
        // TipTap / rich editor contenteditable bodies.
        form.querySelectorAll('.ProseMirror[contenteditable="true"]').forEach((el, i) => {
            data['__rich:' + i] = el.innerHTML;
        });
        return data;
    }

    function save() {
        try {
            localStorage.setItem(KEY, JSON.stringify({ at: Date.now(), data: snapshot() }));
        } catch (e) { /* storage full/unavailable — autosave is best-effort */ }
    }

    function clear() {
        localStorage.removeItem(KEY);
    }

    function restore(saved) {
        for (const el of fields()) {
            const k = keyOf(el);
            if (!(k in saved.data)) continue;
            if (el.type === 'checkbox') {
                el.checked = !!saved.data[k];
            } else {
                el.value = saved.data[k];
            }
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
        form.querySelectorAll('.ProseMirror[contenteditable="true"]').forEach((el, i) => {
            const html = saved.data['__rich:' + i];
            if (typeof html === 'string' && html !== el.innerHTML) {
                el.innerHTML = html;
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    }

    // Offer restore if a recent draft exists and differs from current state.
    let saved = null;
    try { saved = JSON.parse(localStorage.getItem(KEY)); } catch (e) { /* corrupt entry */ }

    if (saved && Date.now() - saved.at < MAX_AGE_MS
        && JSON.stringify(saved.data) !== JSON.stringify(snapshot())) {
        const bar = document.createElement('div');
        bar.style.cssText = 'position:fixed;bottom:16px;right:16px;z-index:9999;background:#1f2937;color:#fff;'
            + 'padding:10px 16px;border-radius:8px;font-size:13px;display:flex;gap:12px;align-items:center;'
            + 'box-shadow:0 4px 12px rgba(0,0,0,.3)';
        const when = new Date(saved.at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        bar.innerHTML = 'Browser autosave from ' + when
            + ' <button type="button" data-restore style="color:#fbbf24;font-weight:600">Restore</button>'
            + ' <button type="button" data-dismiss style="color:#9ca3af">Dismiss</button>';
        document.body.appendChild(bar);
        bar.querySelector('[data-restore]').addEventListener('click', () => { restore(saved); bar.remove(); });
        bar.querySelector('[data-dismiss]').addEventListener('click', () => { clear(); bar.remove(); });
    }

    setInterval(save, 3000);

    // Keep the last snapshot until it expires or matches the freshly rendered
    // form. A submit event only means an attempt started; validation, network,
    // or upload failures must not discard the recovery draft.
})();
</script>
