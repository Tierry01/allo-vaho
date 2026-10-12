/* ==========================================================================
   Allo Vaho Back-office — Authentification : composants partagés
   Thème, champs, mot de passe, boutons, messages, OTP, force du mot de passe,
   vues, panneau de démonstration.  → resources/js/admin/auth.js
   ========================================================================== */
(function () {
  'use strict';
  const A = window.AVAuth = {};
  const $ = A.$ = (s, r = document) => r.querySelector(s);
  const $$ = A.$$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  A.wait = ms => new Promise(r => setTimeout(r, ms));
  A.icon = (id, cls = '') => `<svg class="av-i ${cls}" aria-hidden="true"><use href="#i-${id}"></use></svg>`;
  A.esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const store = A.store = {
    get(k, d) { try { const v = localStorage.getItem('av.' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem('av.' + k, JSON.stringify(v)); } catch (e) {} }
  };
  const SPINNER = '<svg class="av-auth-spinner" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';
  A.spinner = SPINNER;

  /* ---------- Thème (même clé que le dashboard : av.theme) ---------- */
  function initTheme() {
    const btns = $$('[data-theme-toggle]');
    const sync = () => {
      const dark = document.documentElement.dataset.theme === 'dark';
      btns.forEach(b => { const l = dark ? 'Activer le mode clair' : 'Activer le mode sombre'; b.setAttribute('aria-pressed', String(dark)); b.setAttribute('aria-label', l); b.title = l; });
    };
    btns.forEach(b => b.addEventListener('click', () => {
      const t = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = t; store.set('theme', t); sync();
    }));
    sync();
  }

  /* ---------- Champs ---------- */
  A.field = {
    control: input => input.closest('.av-auth-control'),
    setError(input, msg) {
      const f = input.closest('.av-auth-field'), c = A.field.control(input), err = f && $('[data-field-error]', f);
      c.classList.toggle('is-invalid', !!msg);
      input.setAttribute('aria-invalid', String(!!msg));
      if (err) { err.hidden = !msg; $('span', err).textContent = msg || ''; }
    },
    clear(input) { A.field.setError(input, ''); },
    setDisabled(input, on) { input.disabled = on; A.field.control(input).classList.toggle('is-disabled', on); const t = $('[data-password-toggle]', A.field.control(input)); if (t) t.disabled = on; }
  };
  function initFields(root = document) {
    $$('.av-auth-input', root).forEach(input => {
      const c = A.field.control(input);
      const upd = () => c.classList.toggle('is-filled', !!input.value);
      input.addEventListener('input', () => { upd(); if (input.getAttribute('aria-invalid') === 'true' && !input.hasAttribute('data-live')) A.field.clear(input); });
      upd();
    });
  }
  A.setValue = (input, v) => { input.value = v; input.dispatchEvent(new Event('input', { bubbles: true })); };

  /* ---------- Afficher / masquer le mot de passe ---------- */
  function initPasswordToggles(root = document) {
    $$('[data-password-toggle]', root).forEach(btn => {
      const input = document.getElementById(btn.getAttribute('aria-controls'));
      btn.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.classList.toggle('is-visible', show);
        btn.setAttribute('aria-pressed', String(show));
        btn.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        input.focus({ preventScroll: true });
        const l = input.value.length; try { input.setSelectionRange(l, l); } catch (e) {}
      });
    });
  }
  A.hidePassword = input => {
    const btn = $(`[data-password-toggle][aria-controls="${input.id}"]`);
    input.type = 'password';
    if (btn) { btn.classList.remove('is-visible'); btn.setAttribute('aria-pressed', 'false'); btn.setAttribute('aria-label', 'Afficher le mot de passe'); }
  };

  /* ---------- Bouton à état : largeur fixe (100 %), libellé de chargement explicite ---------- */
  A.setLoading = (btn, on, label) => {
    const lbl = $('[data-label]', btn);
    if (on) {
      btn.dataset.idle = lbl.textContent; lbl.textContent = label || lbl.textContent;
      btn.classList.add('is-loading'); btn.disabled = true; btn.setAttribute('aria-busy', 'true');
      btn.insertAdjacentHTML('afterbegin', SPINNER);
    } else {
      if (btn.dataset.idle) lbl.textContent = btn.dataset.idle;
      btn.classList.remove('is-loading'); btn.disabled = false; btn.removeAttribute('aria-busy');
      $('.av-auth-spinner', btn)?.remove();
    }
  };

  /* ---------- Messages ---------- */
  const MSG_ICON = { error: 'alert', warning: 'clock', success: 'check-circle', info: 'info' };
  A.message = (el, opts) => {
    if (!opts) { el.hidden = true; el.innerHTML = ''; return; }
    const { type = 'error', title, text, code, action } = opts;
    el.className = 'av-auth-message av-auth-message--' + type;
    el.setAttribute('role', type === 'error' || type === 'warning' ? 'alert' : 'status');
    el.innerHTML = `${A.icon(MSG_ICON[type], 'av-auth-message__icon')}<div class="av-auth-message__body"><p class="av-auth-message__title">${title}</p>${text ? `<p class="av-auth-message__text">${text}</p>` : ''}${action || ''}${code ? `<span class="av-auth-message__code">Code : ${A.esc(code)}</span>` : ''}</div>`;
    el.hidden = false;
  };

  /* ---------- Vues d'une carte (transition douce + focus sur le titre) ---------- */
  A.showView = (root, name, { focus = true } = {}) => {
    let shown;
    $$('[data-view]', root).forEach(v => {
      const on = v.dataset.view === name; v.hidden = !on;
      if (on) { shown = v; v.classList.remove('is-entering'); void v.offsetWidth; v.classList.add('is-entering'); }
    });
    if (focus && shown) {
      const t = $('[data-autofocus]', shown) || $('h1', shown);
      if (t) { if (!t.matches('input, button, a')) t.setAttribute('tabindex', '-1'); t.focus({ preventScroll: true }); }
    }
    return shown;
  };

  /* ---------- OTP à 6 chiffres ----------
     const otp = AVAuth.otp(el, { onComplete(code) {} })
     otp.value() · otp.clear() · otp.focus() · otp.setState('error'|'success'|'loading'|'') */
  A.otp = (root, { onComplete } = {}) => {
    const boxes = $$('.av-otp__box', root), n = boxes.length;
    const api = {
      value: () => boxes.map(b => b.value).join(''),
      focus() { (boxes.find(b => !b.value) || boxes[n - 1]).focus(); },
      clear() { boxes.forEach(b => { b.value = ''; b.classList.remove('is-filled'); }); boxes[0].focus(); },
      setState(s) {
        root.classList.remove('is-error', 'is-success', 'is-loading');
        if (s) { void root.offsetWidth; root.classList.add('is-' + s); }
        boxes.forEach(b => { b.disabled = s === 'loading' || s === 'success'; b.setAttribute('aria-invalid', String(s === 'error')); });
      }
    };
    const update = () => {
      boxes.forEach(b => b.classList.toggle('is-filled', !!b.value));
      if (root.classList.contains('is-error')) { root.classList.remove('is-error'); boxes.forEach(b => b.setAttribute('aria-invalid', 'false')); }
      const v = api.value();
      if (v.length === n && /^\d+$/.test(v) && onComplete) onComplete(v);
    };
    const fill = (start, digits) => {
      let k = start;
      for (const d of digits) { if (k >= n) break; boxes[k].value = d; k++; }
      boxes[Math.min(k, n - 1)].focus();
      update();
    };
    boxes.forEach((b, i) => {
      b.addEventListener('focus', () => setTimeout(() => b.select(), 0));
      b.addEventListener('input', () => {
        const d = b.value.replace(/\D/g, '');
        if (!d) { b.value = ''; update(); return; }
        b.value = ''; fill(i, d);            // gère aussi l'auto-remplissage (one-time-code) du code complet
      });
      b.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !b.value && i > 0) { e.preventDefault(); boxes[i - 1].value = ''; boxes[i - 1].focus(); update(); }
        else if (e.key === 'ArrowLeft' && i > 0) { e.preventDefault(); boxes[i - 1].focus(); }
        else if (e.key === 'ArrowRight' && i < n - 1) { e.preventDefault(); boxes[i + 1].focus(); }
        else if (e.key.length === 1 && !/\d/.test(e.key) && !e.ctrlKey && !e.metaKey) e.preventDefault();
      });
      b.addEventListener('paste', e => {
        e.preventDefault();
        const d = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, n);
        if (d) fill(d.length === n ? 0 : i, d);
      });
    });
    return api;
  };

  /* ---------- Compte à rebours ---------- */
  A.mmss = s => String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
  A.countdown = (seconds, onTick, onDone) => {
    let s = seconds; onTick(s);
    const id = setInterval(() => { s--; onTick(s); if (s <= 0) { clearInterval(id); onDone && onDone(); } }, 1000);
    return () => clearInterval(id);
  };

  /* ---------- Force du mot de passe ----------
     Règles d'interface CONFIGURABLES (window.AV_AUTH_CONFIG.passwordRules) ;
     la politique définitive sera fournie par le back-end. */
  const DEFAULT_RULES = [
    { id: 'length', label: 'Au moins 8 caractères', test: v => v.length >= 8 },
    { id: 'upper', label: 'Une lettre majuscule', test: v => /[A-ZÀ-Ý]/.test(v) },
    { id: 'lower', label: 'Une lettre minuscule', test: v => /[a-zß-ÿ]/.test(v) },
    { id: 'digit', label: 'Un chiffre', test: v => /\d/.test(v) }
  ];
  A.strength = (root, input) => {
    const rules = (window.AV_AUTH_CONFIG && window.AV_AUTH_CONFIG.passwordRules) || DEFAULT_RULES;
    const list = $('[data-rules]', root), label = $('[data-strength-label]', root);
    list.innerHTML = rules.map(r => `<li class="av-rule" data-rule="${r.id}"><span class="av-rule__mark">${A.icon('check')}</span><span>${A.esc(r.label)}</span><span class="av-sr-only" data-rule-sr> — à respecter</span></li>`).join('');
    const LABELS = ['—', 'Faible', 'Moyen', 'Bon', 'Excellent'];
    let allMet = false;
    const update = () => {
      const v = input.value;
      const met = rules.map(r => r.test(v));
      allMet = met.every(Boolean);
      met.forEach((ok, i) => { const li = list.children[i]; li.classList.toggle('is-met', ok); $('[data-rule-sr]', li).textContent = ok ? ' — respectée' : ' — à respecter'; });
      const count = met.filter(Boolean).length;
      const bonus = (v.length >= 12 ? 1 : 0) + (/[^A-Za-z0-9]/.test(v) ? 1 : 0);
      const level = !v ? 0 : !allMet ? (count <= 2 ? 1 : 2) : (bonus ? 4 : 3);
      root.dataset.level = level; label.textContent = LABELS[level];
    };
    input.addEventListener('input', update); update();
    return { valid: () => allMet };
  };

  /* ---------- Panneau de démonstration (retiré en production) ---------- */
  A.demo = (onChange) => {
    const root = $('[data-demo]'); const state = {};
    if (!root) return { get: () => undefined, set() {} };
    const toggle = $('[data-demo-toggle]', root), panel = $('[data-demo-panel]', root);
    $$('[data-demo-group]', root).forEach(g => { const on = $('[aria-pressed="true"]', g); state[g.dataset.demoGroup] = on && on.dataset.demoValue; });
    const open = o => { panel.hidden = !o; toggle.setAttribute('aria-expanded', String(o)); };
    toggle.addEventListener('click', () => open(panel.hidden));
    document.addEventListener('click', e => { if (!root.contains(e.target)) open(false); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !panel.hidden) { open(false); toggle.focus(); } });
    const set = (group, value, silent) => {
      const g = $(`[data-demo-group="${group}"]`, root); if (!g) return;
      $$('[data-demo-value]', g).forEach(b => b.setAttribute('aria-pressed', String(b.dataset.demoValue === value)));
      state[group] = value; if (!silent && onChange) onChange(group, value);
    };
    root.addEventListener('click', e => { const b = e.target.closest('[data-demo-value]'); if (b) set(b.closest('[data-demo-group]').dataset.demoGroup, b.dataset.demoValue); });
    return { get: g => state[g], set: (g, v) => set(g, v, true) };
  };

  A.EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  A.init = () => { initTheme(); initFields(); initPasswordToggles(); };
})();
