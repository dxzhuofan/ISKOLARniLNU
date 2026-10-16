// LNU Scholarship Portal – student pages
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const csrf = ($('meta[name="csrf"]') || {}).content || '';

  // ---- Menus: hamburger, notifications, profile ----
  const burger = $('#burger'), links = $('#navLinks');
  if (burger) burger.addEventListener('click', () => {
    const open = links.classList.toggle('open');
    burger.setAttribute('aria-expanded', open);
  });
  const pops = [['#bell', '#notifPop'], ['#profBtn', '#profPop']].map(([b, p]) => [$(b), $(p)]).filter(x => x[0] && x[1]);
  function closePops(except) {
    pops.forEach(([b, p]) => { if (p !== except) { p.classList.remove('open'); b.setAttribute('aria-expanded', 'false'); } });
  }
  pops.forEach(([b, p]) => b.addEventListener('click', e => {
    e.stopPropagation();
    const open = !p.classList.contains('open');
    closePops(p);
    p.classList.toggle('open', open);
    b.setAttribute('aria-expanded', open);
  }));
  document.addEventListener('click', e => { if (!e.target.closest('.pop')) closePops(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { closePops(); $$('.modal').forEach(m => m.hidden = true); } });

  // ---- Mark notifications as read ----
  function markAllRead() {
    fetch('actions/notifications.php', {
      method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'csrf=' + encodeURIComponent(csrf), credentials: 'same-origin'
    }).then(r => r.json()).then(j => {
      if (!j.ok) return;
      $$('.alert-row').forEach(a => a.classList.add('read'));
      const c = $('#bellCount'); if (c) c.remove();
    });
  }
  const ma = $('#markAll'); if (ma) ma.addEventListener('click', markAllRead);
  $$('[data-mark-all]').forEach(b => b.addEventListener('click', markAllRead));

  // ---- Scholarship filters (client-side) ----
  const cards = $$('#schCards .sch');
  if (cards.length) {
    const f = { q: $('#fSearch'), type: $('#fType'), sp: $('#fSponsor'), el: $('#fElig'), st: $('#fState') };
    const apply = () => {
      const q = f.q.value.trim().toLowerCase();
      let shown = 0;
      cards.forEach(c => {
        const ok = (!q || c.dataset.text.includes(q)) &&
          (!f.type.value || c.dataset.type === f.type.value) &&
          (!f.sp.value || c.dataset.sponsor === f.sp.value) &&
          (!f.el.value || c.dataset.elig === f.el.value) &&
          (!f.st.value || c.dataset.state.split(' ').includes(f.st.value));
        c.hidden = !ok; if (ok) shown++;
      });
      const em = $('#schEmpty'); if (em) em.hidden = shown > 0;
    };
    Object.values(f).forEach(el => el && el.addEventListener('input', apply));
  }

  // ---- Modals ----
  $$('[data-open-modal]').forEach(b => b.addEventListener('click', () => { const m = $('#' + b.dataset.openModal); if (m) m.hidden = false; }));
  $$('[data-close-modal]').forEach(b => b.addEventListener('click', () => b.closest('.modal').hidden = true));
  $$('.modal').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.hidden = true; }));
  if (location.hash === '#apply') { const b = $('[data-open-modal]'); if (b && !b.disabled) b.click(); }

  // Checklist gate: enable the start button only when every box is ticked
  const gates = $$('[data-gate]'), go = $('#applyGo');
  if (gates.length && go) gates.forEach(g => g.addEventListener('change', () => { go.disabled = !gates.every(x => x.checked); }));

  // Review step: submit enabled only after confirming (and not locked by missing items)
  $$('[data-requires-check]').forEach(chk => {
    const btn = $(chk.dataset.requiresCheck);
    chk.addEventListener('change', () => { btn.disabled = !chk.checked || btn.hasAttribute('data-locked'); });
  });

  // Confirm dialogs on destructive forms
  $$('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));

  // File inputs: block files over 5 MB before upload
  $$('input[type=file]').forEach(i => i.addEventListener('change', () => {
    const file = i.files[0];
    if (file && file.size > 5 * 1024 * 1024) { alert('That file is larger than 5 MB. Choose a smaller PDF, JPG or PNG.'); i.value = ''; }
  }));

  // Auto-hide success messages
  $$('.f-success').forEach(el => setTimeout(() => { el.style.display = 'none'; }, 6000));
})();
