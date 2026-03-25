/**
 * Global Double-Click / Double-Submit Prevention
 * ──────────────────────────────────────────────
 * Auto-protects ALL <form> submit buttons and AJAX action buttons.
 * Include once in footer.php — works system-wide.
 */
(function () {
  'use strict';

  // ── 1. Form submit prevention ──
  // Intercept all form submits: disable button, show spinner, prevent re-submit.
  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!form || form.tagName !== 'FORM') return;
    // Skip forms with [data-no-guard] attribute
    if (form.hasAttribute('data-no-guard')) return;
    // Skip GET forms (search, filters)
    if ((form.method || 'get').toLowerCase() === 'get') return;

    // Find the submit button
    const btn = form.querySelector('button[type="submit"], button:not([type]), input[type="submit"]');
    if (!btn) return;

    // If already locked, prevent
    if (form.dataset.submitting === '1') {
      e.preventDefault();
      return;
    }

    form.dataset.submitting = '1';
    btn.disabled = true;
    btn.dataset.originalText = btn.innerHTML;
    btn.innerHTML = '<svg class="inline w-4 h-4 mr-1.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-30"></circle><path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg> Processando...';
    btn.classList.add('opacity-70', 'pointer-events-none');

    // Safety: re-enable after 8s in case of network failure (page hasn't navigated)
    setTimeout(function () {
      if (form.dataset.submitting === '1') {
        form.dataset.submitting = '0';
        btn.disabled = false;
        btn.innerHTML = btn.dataset.originalText || 'Enviar';
        btn.classList.remove('opacity-70', 'pointer-events-none');
      }
    }, 8000);
  }, true); // capture phase

  // ── 2. AJAX button click prevention (for inline onclick / fetch buttons) ──
  // Targets buttons with [data-action] or specific classes
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('button[data-action], a[data-action]');
    if (!btn) return;
    if (btn.disabled || btn.dataset.locked === '1') {
      e.preventDefault();
      e.stopPropagation();
      return;
    }
    btn.dataset.locked = '1';
    btn.classList.add('opacity-70', 'pointer-events-none');
    // Re-enable after 3s (AJAX responses should handle this faster if needed)
    setTimeout(function () {
      btn.dataset.locked = '0';
      btn.classList.remove('opacity-70', 'pointer-events-none');
    }, 3000);
  }, true);

  // ── 3. Add-to-cart specific: brief lock on all add_cart forms ──
  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!form || form.tagName !== 'FORM') return;
    const actionInput = form.querySelector('input[name="action"][value="add_cart"]');
    if (!actionInput) return;
    
    const btn = form.querySelector('button');
    if (!btn || btn.disabled) return;

    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<svg class="inline w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-30"></circle><path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg>';

    setTimeout(function () {
      btn.disabled = false;
      btn.innerHTML = orig;
    }, 1500);
  }, false);
})();
