/**
 * Slug availability checker — reusable across all dashboard forms.
 * 
 * Usage:
 *   initSlugChecker({
 *     inputSelector: 'input[name="slug"]',
 *     nameSelector:  'input[name="nome"]',   // auto-generate slug from name
 *     type:          'product',               // product | category | vendor
 *     excludeId:     0,                       // current entity ID (for edits)
 *   });
 */
(function () {
  window.initSlugChecker = function (opts) {
    const slugInput = document.querySelector(opts.inputSelector);
    const nameInput = opts.nameSelector ? document.querySelector(opts.nameSelector) : null;
    const type = opts.type || 'product';
    const excludeId = opts.excludeId || 0;
    if (!slugInput) return;

    // Create feedback element
    const fb = document.createElement('div');
    fb.className = 'mt-1.5 text-xs flex items-center gap-1.5 transition-all duration-200';
    fb.style.display = 'none';
    slugInput.parentNode.appendChild(fb);

    let debounceTimer = null;
    let lastChecked = '';

    function generateSlug(text) {
      const map = {'à':'a','á':'a','â':'a','ã':'a','ä':'a','å':'a','è':'e','é':'e','ê':'e','ë':'e','ì':'i','í':'i','î':'i','ï':'i','ò':'o','ó':'o','ô':'o','õ':'o','ö':'o','ù':'u','ú':'u','û':'u','ü':'u','ñ':'n','ç':'c','ý':'y','ÿ':'y'};
      let s = text.toLowerCase();
      for (const [k, v] of Object.entries(map)) s = s.replaceAll(k, v);
      s = s.replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
      return s || '';
    }

    function setFeedback(state, message, suggestion) {
      fb.style.display = 'flex';
      fb.innerHTML = '';

      if (state === 'checking') {
        fb.className = 'mt-1.5 text-xs flex items-center gap-1.5 text-zinc-500';
        fb.innerHTML = '<svg class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-30"></circle><path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg> Verificando...';
        slugInput.classList.remove('border-greenx', 'border-red-500');
        slugInput.classList.add('border-yellow-500/50');
      } else if (state === 'available') {
        fb.className = 'mt-1.5 text-xs flex items-center gap-1.5 text-greenx';
        fb.innerHTML = '<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> ' + message;
        slugInput.classList.remove('border-red-500', 'border-yellow-500/50');
        slugInput.classList.add('border-greenx');
      } else if (state === 'taken') {
        fb.className = 'mt-1.5 text-xs flex items-center gap-1.5 text-red-400';
        let html = '<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" d="M15 9l-6 6M9 9l6 6"/></svg> ' + message;
        if (suggestion) {
          html += ' <button type="button" class="slug-suggestion underline text-amber-400 hover:text-amber-300 cursor-pointer">Usar: ' + suggestion + '</button>';
        }
        fb.innerHTML = html;
        slugInput.classList.remove('border-greenx', 'border-yellow-500/50');
        slugInput.classList.add('border-red-500');

        // Handle suggestion click
        const btn = fb.querySelector('.slug-suggestion');
        if (btn) {
          btn.addEventListener('click', function () {
            slugInput.value = suggestion;
            checkSlug(suggestion);
          });
        }
      } else {
        fb.style.display = 'none';
        slugInput.classList.remove('border-greenx', 'border-red-500', 'border-yellow-500/50');
      }
    }

    function checkSlug(value) {
      const slug = generateSlug(value);
      if (!slug || slug.length < 2) {
        setFeedback('hide');
        lastChecked = '';
        return;
      }
      if (slug === lastChecked) return;
      lastChecked = slug;

      setFeedback('checking');

      fetch('/api/check_slug?type=' + encodeURIComponent(type) + '&slug=' + encodeURIComponent(slug) + '&exclude_id=' + excludeId)
        .then(r => r.json())
        .then(data => {
          if (data.slug !== slug && data.slug !== lastChecked) return; // stale
          if (data.available) {
            setFeedback('available', 'Slug "' + data.slug + '" disponível');
          } else {
            setFeedback('taken', 'Slug "' + data.slug + '" já está em uso.', data.suggestion);
          }
        })
        .catch(() => {
          setFeedback('hide');
        });
    }

    // Debounced input check
    slugInput.addEventListener('input', function () {
      clearTimeout(debounceTimer);
      const val = this.value.trim();
      if (!val) { setFeedback('hide'); lastChecked = ''; return; }
      debounceTimer = setTimeout(() => checkSlug(val), 400);
    });

    // Auto-fill slug from name if slug is empty
    if (nameInput) {
      nameInput.addEventListener('input', function () {
        if (slugInput.value.trim() !== '') return; // user has custom slug
        clearTimeout(debounceTimer);
        const slug = generateSlug(this.value);
        if (!slug) { setFeedback('hide'); return; }
        debounceTimer = setTimeout(() => checkSlug(slug), 600);
      });
    }

    // Check on page load if slug already has a value
    if (slugInput.value.trim()) {
      setTimeout(() => checkSlug(slugInput.value.trim()), 300);
    }
  };
})();
