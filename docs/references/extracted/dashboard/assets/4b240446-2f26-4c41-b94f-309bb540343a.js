/* ==========================================================================
   Allo Vaho Back-office — noyau UI réutilisable (toutes pages)
   Sidebar, dropdowns, modales, drawer, toasts, tooltips, copie, permissions,
   recherche globale, widgets à états (loading / empty / error / restricted).
   ========================================================================== */
(function () {
  'use strict';
  const AV = window.AV = window.AV || {};
  const D = window.AV_DATA;
  const $ = AV.$ = (s, r = document) => r.querySelector(s);
  const $$ = AV.$$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const store = {
    get(k, d) { try { const v = localStorage.getItem('av.' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem('av.' + k, JSON.stringify(v)); } catch (e) {} }
  };
  AV.store = store;

  /* ---------- Utilitaires ---------- */
  AV.esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  AV.icon = (name, cls = '') => `<i data-lucide="${name}" class="av-icon ${cls}" aria-hidden="true"></i>`;
  AV.icons = () => { if (window.lucide && lucide.createIcons) lucide.createIcons({ attrs: { 'stroke-width': 1.75 } }); };
  AV.wait = ms => new Promise(r => setTimeout(r, ms));
  AV.spinner = () => '<svg class="av-spinner" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';
  const nf = new Intl.NumberFormat('fr-FR');
  AV.fmt = {
    num: n => nf.format(Math.round(n)),
    money: n => nf.format(Math.round(n)) + '\u00A0FCFA',
    pct: (n, d = 1) => n.toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d }) + '\u00A0%',
    signed: (n, pct = true, d = 1) => (n > 0 ? '+' : n < 0 ? '−' : '±') + (pct ? AV.fmt.pct(Math.abs(n), d) : nf.format(Math.abs(n))),
    time: d => d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }),
    dateTime: d => d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' }) + ' · ' + AV.fmt.time(d),
    ago: ms => { const s = Math.round(ms / 1000); if (s < 60) return `il y a ${Math.max(s, 1)} s`; const m = Math.round(s / 60); if (m < 60) return `il y a ${m} min`; return `il y a ${Math.round(m / 60)} h`; }
  };

  /* ---------- StatusBadge (mapping central des statuts) ---------- */
  const STATUS = {
    successful: ['success', 'Réussi'], pending: ['warning', 'En attente'], failed: ['danger', 'Échoué'],
    refunded: ['info', 'Remboursé'], cancelled: ['neutral', 'Annulé'],
    operational: ['success', 'Opérationnel'], degraded: ['warning', 'Dégradé'], down: ['danger', 'Indisponible'], checking: ['info', 'Vérification…'],
    open: ['danger', 'Ouvert'], in_progress: ['info', 'En cours'], waiting_user: ['warning', 'Attente utilisateur'],
    waiting_internal: ['neutral', 'Attente interne'], resolved: ['success', 'Résolu'], closed: ['neutral', 'Fermé'],
    investigating: ['warning', 'En analyse'], active: ['success', 'Actif'], inactive: ['neutral', 'Inactif'],
    delivered: ['success', 'Délivré'], received: ['success', 'Reçu']
  };
  AV.status = (key, label) => {
    const [tone, l] = STATUS[key] || ['neutral', key];
    return `<span class="av-badge av-badge--${tone}" data-status="${key}"><span class="av-badge__dot"></span>${AV.esc(label || l)}</span>`;
  };
  const PRIORITY = { low: 'Basse', medium: 'Moyenne', high: 'Haute', critical: 'Critique' };
  AV.priority = p => `<span class="av-priority av-priority--${p}"><span class="av-priority__bars" aria-hidden="true"><i></i><i></i><i></i></span>${PRIORITY[p]}</span>`;
  AV.initials = name => name.split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();

  /* ---------- Permissions ---------- */
  AV.role = store.get('role', 'super_admin');
  if (!D.roles[AV.role]) AV.role = 'super_admin';
  AV.can = perm => { if (!perm) return true; const p = D.roles[AV.role].perms; return p.includes('*') || p.includes(perm); };
  AV.applyPerms = (root = document) => {
    $$('[data-perm]', root).forEach(el => {
      const ok = AV.can(el.dataset.perm);
      if (el.dataset.permMode === 'disable') {
        el.disabled = !ok; el.setAttribute('aria-disabled', String(!ok));
        if (!ok) el.dataset.tooltip = 'Action réservée · rôle ' + D.roles[AV.role].label + ' insuffisant';
        else if (el.dataset.tooltipDefault !== undefined) el.dataset.tooltip = el.dataset.tooltipDefault; else delete el.dataset.tooltip;
      } else el.hidden = !ok;
    });
  };
  AV.setRole = role => {
    AV.role = role; store.set('role', role);
    $$('[data-role-label]').forEach(el => el.textContent = D.roles[role].label);
    $$('[data-set-role]').forEach(el => el.setAttribute('aria-checked', String(el.dataset.setRole === role)));
    AV.renderNav(); AV.applyPerms();
    document.dispatchEvent(new CustomEvent('av:rolechange', { detail: { role } }));
    AV.toast({ type: 'info', title: 'Rôle de démonstration : ' + D.roles[role].label, message: 'Menus et données sensibles ajustés selon les permissions.' });
  };

  /* ---------- Sidebar ---------- */
  const app = () => $('#av-app');
  const isMobile = () => window.matchMedia('(max-width: 1023px)').matches;
  AV.renderNav = () => {
    const el = $('#av-nav'); if (!el) return;
    const openSet = new Set(store.get('navOpen', []));
    el.innerHTML = D.nav.map((sec, si) => {
      if (sec.perm && !AV.can(sec.perm)) return '';
      if (sec.flat) {
        return sec.items.map(it => `<a class="av-nav-link${it.active ? ' is-active' : ''}" href="${it.href || '#'}" ${it.active ? 'aria-current="page"' : ''} data-tooltip="${AV.esc(it.label)}" data-tooltip-when="collapsed">${AV.icon(it.icon)}<span class="av-nav-label">${AV.esc(it.label)}</span></a>`).join('') + '<div class="av-nav-divider" role="separator"></div>';
      }
      const open = openSet.has(sec.id);
      const hasDanger = sec.items.some(i => i.tone === 'danger');
      const items = sec.items.filter(i => AV.can(i.perm)).map(it => `<li><a class="av-nav-sublink" href="#" data-soon="${AV.esc(it.label)}"><span class="av-nav-label">${AV.esc(it.label)}</span>${it.count ? `<span class="av-nav-count${it.tone ? ' av-nav-count--' + it.tone : ''}">${it.count}</span>` : ''}</a></li>`).join('');
      return `<div class="av-nav-section${open ? ' is-open' : ''}" data-section="${sec.id}">
        <button type="button" class="av-nav-section__toggle" aria-expanded="${open}" aria-controls="nav-${sec.id}" data-tooltip="${AV.esc(sec.label)}" data-tooltip-when="collapsed">
          ${AV.icon(sec.icon)}<span class="av-nav-label">${AV.esc(sec.label)}</span>${hasDanger && !open ? '<span class="av-nav-section__dot" aria-label="Éléments urgents"></span>' : ''}${AV.icon('chevron-right', 'av-icon--sm av-nav-section__chevron')}
        </button>
        <div class="av-nav-section__panel" id="nav-${sec.id}" ${open ? '' : 'inert'}><ul class="av-nav-section__list">${items}</ul></div>
      </div>`;
    }).join('');
    AV.icons();
  };
  function toggleSection(sec, force) {
    const open = force ?? !sec.classList.contains('is-open');
    sec.classList.toggle('is-open', open);
    const btn = $('.av-nav-section__toggle', sec), panel = $('.av-nav-section__panel', sec);
    btn.setAttribute('aria-expanded', String(open));
    panel.toggleAttribute('inert', !open);
    const dot = $('.av-nav-section__dot', sec); if (dot) dot.hidden = open;
    const set = new Set(store.get('navOpen', [])); open ? set.add(sec.dataset.section) : set.delete(sec.dataset.section);
    store.set('navOpen', [...set]);
  }
  AV.setCollapsed = c => {
    app().classList.toggle('is-collapsed', c); store.set('collapsed', c);
    const b = $('#av-collapse');
    if (b) { b.setAttribute('aria-label', c ? 'Déplier la navigation' : 'Réduire la navigation'); b.dataset.tooltip = c ? 'Déplier la navigation' : 'Réduire la navigation'; b.innerHTML = AV.icon(c ? 'panel-left-open' : 'panel-left-close'); AV.icons(); }
  };
  AV.openNav = open => {
    const a = app(), scrim = $('#av-scrim');
    if (open) { scrim.hidden = false; requestAnimationFrame(() => a.classList.add('is-nav-open')); $('#av-menu-btn')?.setAttribute('aria-expanded', 'true'); setTimeout(() => $('#av-nav a, #av-nav button')?.focus(), 60); }
    else { a.classList.remove('is-nav-open'); $('#av-menu-btn')?.setAttribute('aria-expanded', 'false'); setTimeout(() => { if (!a.classList.contains('is-nav-open')) scrim.hidden = true; }, 240); }
  };
  function initSidebar() {
    AV.renderNav();
    AV.setCollapsed(store.get('collapsed', false));
    document.addEventListener('click', e => {
      const t = e.target.closest('.av-nav-section__toggle');
      if (t) {
        const sec = t.closest('.av-nav-section');
        if (app().classList.contains('is-collapsed') && !isMobile()) { AV.setCollapsed(false); toggleSection(sec, true); AV.hideTooltip(); }
        else toggleSection(sec);
      }
    });
    $('#av-collapse')?.addEventListener('click', () => isMobile() ? AV.openNav(false) : AV.setCollapsed(!app().classList.contains('is-collapsed')));
    $('#av-menu-btn')?.addEventListener('click', () => AV.openNav(true));
    $('#av-scrim')?.addEventListener('click', () => AV.openNav(false));
  }

  /* ---------- Dropdowns (délégation : fonctionne aussi pour le contenu dynamique) ---------- */
  let openDD = null;
  const trigOf = dd => $('[data-dropdown-trigger]', dd) || $('[data-dropdown-anchor]', dd);
  /* Le menu reste toujours entièrement dans le viewport : largeur et hauteur bornées, bascule haut/bas */
  function placeMenu(menu, trigger, align) {
    const M = 8, GAP = 6, vw = document.documentElement.clientWidth, vh = window.innerHeight;
    menu.style.maxWidth = (vw - M * 2) + 'px';
    menu.style.maxHeight = 'none';
    const r = trigger.getBoundingClientRect();
    const mw = menu.offsetWidth, natural = menu.scrollHeight;
    let left = align === 'start' ? r.left : r.right - mw;
    left = Math.max(M, Math.min(left, vw - mw - M));
    const below = vh - r.bottom - GAP - M, above = r.top - GAP - M;
    let top, maxH;
    if (natural <= below || below >= above) { maxH = Math.max(120, below); top = r.bottom + GAP; }
    else { maxH = above; top = r.top - GAP - Math.min(natural, above); }
    menu.style.maxHeight = maxH + 'px';
    menu.style.left = left + 'px'; menu.style.top = Math.max(M, top) + 'px';
  }
  AV.placeOpen = () => { if (openDD) placeMenu($('[data-dropdown-menu]', openDD), trigOf(openDD), openDD.dataset.align || 'end'); };
  AV.closeDropdown = (focusTrigger) => {
    if (!openDD) return;
    const dd = openDD; openDD = null;
    dd.classList.remove('is-open');
    const trig = trigOf(dd), menu = $('[data-dropdown-menu]', dd);
    trig?.setAttribute('aria-expanded', 'false'); if (menu) menu.hidden = true;
    if (focusTrigger) trig?.focus();
  };
  AV.openDropdown = (dd, viaKeyboard) => {
    if (openDD && openDD !== dd) AV.closeDropdown();
    const trig = trigOf(dd), menu = $('[data-dropdown-menu]', dd);
    menu.hidden = false; dd.classList.add('is-open'); trig.setAttribute('aria-expanded', 'true'); openDD = dd;
    placeMenu(menu, trig, dd.dataset.align || 'end');
    dd.dispatchEvent(new CustomEvent('av:dropdown-open', { bubbles: true }));
    if (viaKeyboard) setTimeout(() => menuItems(menu)[0]?.focus(), 0);
  };
  const menuItems = menu => $$('[role^="menuitem"]:not([aria-disabled="true"]):not([hidden]), .av-menu-focusable', menu).filter(el => el.offsetParent !== null);
  function initDropdowns() {
    document.addEventListener('click', e => {
      const trig = e.target.closest('[data-dropdown-trigger]');
      if (trig) {
        e.preventDefault(); e.stopPropagation();
        const dd = trig.closest('[data-dropdown]');
        dd === openDD ? AV.closeDropdown() : AV.openDropdown(dd, e.detail === 0);
        return;
      }
      if (openDD) {
        if (e.target.closest('[data-dropdown-anchor]') && e.target.closest('[data-dropdown]') === openDD) return;
        const inMenu = e.target.closest('[data-dropdown-menu]');
        if (!inMenu || e.target.closest('[role^="menuitem"]:not([data-keep-open]), [data-dd-close]')) AV.closeDropdown();
      }
    });
    document.addEventListener('keydown', e => {
      if (!openDD) return;
      const menu = $('[data-dropdown-menu]', openDD);
      if (e.key === 'Escape') { e.preventDefault(); AV.closeDropdown(true); return; }
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        const items = menuItems(menu); if (!items.length) return;
        e.preventDefault();
        const i = items.indexOf(document.activeElement);
        const n = e.key === 'ArrowDown' ? (i + 1) % items.length : (i - 1 + items.length) % items.length;
        items[n].focus();
      }
      if (e.key === 'Tab' && !menu.contains(document.activeElement)) AV.closeDropdown();
    });
    window.addEventListener('resize', () => AV.placeOpen());
    window.addEventListener('scroll', e => { if (openDD && !$('[data-dropdown-menu]', openDD).contains(e.target) && e.target !== document.activeElement) AV.closeDropdown(); }, true);
  }

  /* ---------- Modales & drawer (<dialog> natif : focus trap + Échap) ---------- */
  AV.modal = {
    open(id) { const d = document.getElementById(id); if (!d) return; AV.closeDropdown(); d.showModal(); d.dispatchEvent(new CustomEvent('av:open')); },
    close(id) { const d = typeof id === 'string' ? document.getElementById(id) : id; d?.close(); }
  };
  function initModals() {
    document.addEventListener('click', e => {
      const o = e.target.closest('[data-modal-open]'); if (o) { e.preventDefault(); AV.modal.open(o.dataset.modalOpen); return; }
      const c = e.target.closest('[data-modal-close]'); if (c) { AV.modal.close(c.closest('dialog')); return; }
      if (e.target.tagName === 'DIALOG' && (e.target.classList.contains('av-modal') || e.target.classList.contains('av-drawer'))) {
        const r = e.target.getBoundingClientRect();
        if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) e.target.close();
      }
    });
  }
  AV.drawer = {
    open({ eyebrow = '', title = '', badge = '', body = '', footer = '' }) {
      const d = $('#av-drawer');
      $('[data-drawer-eyebrow]', d).textContent = eyebrow;
      $('[data-drawer-title]', d).innerHTML = AV.esc(title) + (badge || '');
      $('[data-drawer-body]', d).innerHTML = body;
      const f = $('[data-drawer-footer]', d); f.innerHTML = footer; f.hidden = !footer;
      AV.icons(); AV.applyPerms(d);
      if (!d.open) d.showModal();
      $('[data-drawer-body]', d).scrollTop = 0;
      return d;
    },
    close() { $('#av-drawer')?.close(); }
  };

  /* ConfirmationModal : AV.confirm({...}).then(ok => …) */
  AV.confirm = ({ title, message, confirmLabel = 'Confirmer', tone = 'primary', icon = 'circle-help', note }) => new Promise(resolve => {
    const d = $('#av-confirm');
    $('[data-confirm-title]', d).textContent = title;
    $('[data-confirm-message]', d).textContent = message || '';
    const ic = $('[data-confirm-icon]', d);
    ic.className = 'av-modal__icon' + (tone === 'danger' ? ' av-modal__icon--danger' : tone === 'warning' ? ' av-modal__icon--warning' : '');
    ic.innerHTML = AV.icon(icon);
    const n = $('[data-confirm-note]', d); n.hidden = !note; n.innerHTML = note ? AV.icon('scroll-text', 'av-icon--sm') + '<span>' + AV.esc(note) + '</span>' : '';
    const ok = $('[data-confirm-ok]', d);
    ok.className = 'av-btn ' + (tone === 'danger' ? 'av-btn--danger' : 'av-btn--primary'); ok.textContent = confirmLabel;
    AV.icons();
    let result = false;
    const onOk = () => { result = true; d.close(); };
    ok.addEventListener('click', onOk, { once: true });
    d.addEventListener('close', () => { ok.removeEventListener('click', onOk); resolve(result); }, { once: true });
    d.showModal(); setTimeout(() => $('[data-modal-close]', d)?.focus(), 30);
  });

  /* ---------- Toast ---------- */
  AV.toast = ({ type = 'info', title, message = '', duration = 4200 }) => {
    const region = $('#av-toasts'); if (!region) return;
    const icons = { success: 'circle-check', error: 'circle-x', warning: 'triangle-alert', info: 'info' };
    const el = document.createElement('div');
    el.className = 'av-toast av-toast--' + type; el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = `<span class="av-toast__icon">${AV.icon(icons[type])}</span><div class="av-toast__body"><div class="av-toast__title">${AV.esc(title)}</div>${message ? `<div class="av-toast__msg">${AV.esc(message)}</div>` : ''}</div><button type="button" class="av-toast__close" aria-label="Fermer la notification">${AV.icon('x', 'av-icon--sm')}</button>`;
    region.appendChild(el); AV.icons();
    while (region.children.length > 4) region.firstElementChild.remove();
    const close = () => { el.classList.add('is-leaving'); setTimeout(() => el.remove(), 200); };
    el.querySelector('.av-toast__close').addEventListener('click', close);
    if (duration) setTimeout(close, duration);
  };

  /* ---------- Tooltip (un seul élément flottant, non tronqué par les overflow) ---------- */
  let tipTarget = null;
  AV.hideTooltip = () => { $('#av-tooltip')?.classList.remove('is-visible'); tipTarget = null; };
  function showTip(el) {
    const txt = el.dataset.tooltip; if (!txt) return;
    if (el.dataset.tooltipWhen === 'collapsed' && (!app().classList.contains('is-collapsed') || isMobile())) return;
    const tip = $('#av-tooltip'); tip.textContent = txt; tipTarget = el;
    const r = el.getBoundingClientRect(); const side = el.dataset.tooltipWhen === 'collapsed' ? 'right' : 'top';
    tip.classList.add('is-visible');
    const tw = tip.offsetWidth, th = tip.offsetHeight;
    let x, y;
    if (side === 'right') { x = r.right + 8; y = r.top + r.height / 2 - th / 2; }
    else { x = r.left + r.width / 2 - tw / 2; y = r.top - th - 8; if (y < 8) y = r.bottom + 8; }
    tip.style.left = Math.max(8, Math.min(x, window.innerWidth - tw - 8)) + 'px'; tip.style.top = y + 'px';
  }
  function initTooltips() {
    document.addEventListener('mouseover', e => { const el = e.target.closest('[data-tooltip]'); if (el && el !== tipTarget) showTip(el); else if (!el && tipTarget) AV.hideTooltip(); });
    document.addEventListener('focusin', e => { const el = e.target.closest('[data-tooltip]'); if (el && el.matches(':focus-visible')) showTip(el); });
    document.addEventListener('focusout', AV.hideTooltip);
    window.addEventListener('scroll', AV.hideTooltip, true);
  }

  /* ---------- Copie ---------- */
  AV.copy = async (text, label = 'Copié dans le presse-papiers') => {
    try { await navigator.clipboard.writeText(text); }
    catch (e) { const t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); } catch (_) {} t.remove(); }
    AV.toast({ type: 'success', title: label, message: text.length > 60 ? text.slice(0, 57) + '…' : text, duration: 2600 });
  };

  /* ---------- Bouton à état : loading → success/error (libellé conservé) ---------- */
  AV.withLoading = async (btn, fn) => {
    if (!btn || btn.classList.contains('is-loading')) return;
    btn.classList.add('is-loading'); btn.setAttribute('aria-busy', 'true'); btn.disabled = true;
    btn.insertAdjacentHTML('afterbegin', AV.spinner());
    try { return await fn(); }
    finally { btn.classList.remove('is-loading'); btn.removeAttribute('aria-busy'); btn.disabled = false; btn.querySelector('.av-spinner')?.remove(); }
  };

  /* ---------- Liens vers des pages à venir ---------- */
  function initSoonLinks() {
    document.addEventListener('click', e => {
      const a = e.target.closest('[data-soon]'); if (!a) return;
      e.preventDefault();
      if (isMobile() && a.closest('.av-sidebar')) AV.openNav(false);
      AV.toast({ type: 'info', title: '« ' + a.dataset.soon + ' »', message: 'Cette page sera construite lors des prochaines étapes, avec le même design system.' });
    });
    document.addEventListener('click', e => { const c = e.target.closest('[data-copy]'); if (c) { e.preventDefault(); AV.copy(c.dataset.copy, c.dataset.copyLabel); } });
  }

  /* ---------- Widgets à états ----------
     <section data-widget="nom" data-widget-perm="perm"> … <div data-widget-body></div></section>
     AV.widgets.register('nom', { render(body), skeleton: 'rows'|'chart'|'list', empty: {icon,title,text} }) */
  AV.widgets = {
    reg: {}, demo: 'ready',
    register(name, def) { this.reg[name] = def; },
    body(name) { const c = $(`[data-widget="${name}"]`); return c && ($('[data-widget-body]', c) || c); },
    skeleton(type) {
      const bar = (w, h = 12) => `<span class="av-skeleton" style="width:${w};height:${h}px"></span>`;
      if (type === 'kpis') return Array.from({ length: 6 }, () => `<div class="av-stat-card" aria-hidden="true">${bar('60%')}${bar('70%', 26)}${bar('45%')}</div>`).join('');
      if (type === 'chart') return `<div class="av-skeleton-stack" aria-hidden="true"><div class="av-skeleton-row">${bar('22%', 30)}${bar('22%', 30)}${bar('22%', 30)}</div>${bar('100%', 220)}</div>`;
      return `<div class="av-skeleton-stack" aria-hidden="true">${Array.from({ length: 5 }, () => `<div class="av-skeleton-row"><span class="av-skeleton" style="width:30px;height:30px;border-radius:8px;flex:none"></span><div style="flex:1;display:grid;gap:6px">${bar('70%')}${bar('40%', 10)}</div>${bar('60px', 20)}</div>`).join('')}</div>`;
    },
    state(kind, opts = {}) {
      if (kind === 'empty') return `<div class="av-state"><span class="av-state__icon">${AV.icon(opts.icon || 'inbox')}</span><p class="av-state__title">${AV.esc(opts.title || 'Rien à afficher pour cette période')}</p><p class="av-state__text">${AV.esc(opts.text || 'Les données apparaîtront ici dès la première activité.')}</p>${opts.action || ''}</div>`;
      if (kind === 'error') return `<div class="av-state av-state--error" role="alert"><span class="av-state__icon">${AV.icon('cloud-off')}</span><p class="av-state__title">Ces données n’ont pas pu être chargées</p><p class="av-state__text">Le service n’a pas répondu à temps. Les autres blocs restent à jour.</p><button type="button" class="av-state__code" data-copy="${opts.code}" data-copy-label="Code technique copié" aria-label="Copier le code technique ${opts.code}">${opts.code}${AV.icon('copy', 'av-icon--xs')}</button><div class="av-state__actions"><button type="button" class="av-btn av-btn--secondary av-btn--sm" data-widget-retry="${opts.name}">${AV.icon('refresh-cw', 'av-icon--sm')}Réessayer</button></div></div>`;
      if (kind === 'restricted') return `<div class="av-state av-state--restricted"><span class="av-state__icon">${AV.icon('lock')}</span><p class="av-state__title">Accès restreint</p><p class="av-state__text">Votre rôle (${AV.esc(D.roles[AV.role].label)}) ne permet pas de consulter ces données. Demandez l’accès à un Super Admin.</p></div>`;
      return '';
    },
    async render(name, forced) {
      const def = this.reg[name], card = $(`[data-widget="${name}"]`); if (!def || !card) return;
      const body = this.body(name);
      const st = forced || this.demo;
      card.setAttribute('aria-busy', String(st === 'loading'));
      card.dataset.state = st;
      const perm = card.dataset.widgetPerm;
      if (perm && !AV.can(perm)) { body.innerHTML = this.state('restricted'); card.dataset.state = 'restricted'; }
      else if (st === 'loading') body.innerHTML = this.skeleton(def.skeleton);
      else if (st === 'empty') body.innerHTML = def.renderEmpty ? def.renderEmpty() : this.state('empty', def.empty);
      else if (st === 'error') body.innerHTML = this.state('error', { name, code: 'ERR_' + name.toUpperCase().replace(/-/g, '_') + '_TIMEOUT · req_7f3a9c' });
      else def.render(body);
      card.querySelectorAll('[data-widget-ready-only]').forEach(el => el.hidden = st !== 'ready' || card.dataset.state === 'restricted');
      AV.icons(); AV.applyPerms(card);
    },
    renderAll(forced) { Object.keys(this.reg).forEach(n => this.render(n, forced)); },
    async reload(names = Object.keys(this.reg), ms = 650) {
      names.forEach(n => this.render(n, 'loading'));
      await AV.wait(ms);
      names.forEach(n => this.render(n));
    },
    setDemo(state) {
      this.demo = state;
      $$('[data-set-demo]').forEach(el => el.setAttribute('aria-checked', String(el.dataset.setDemo === state)));
      this.renderAll();
    }
  };
  document.addEventListener('click', e => {
    const r = e.target.closest('[data-widget-retry]'); if (!r) return;
    const name = r.dataset.widgetRetry;
    AV.widgets.render(name, 'loading');
    setTimeout(() => AV.widgets.render(name, 'ready'), 700);
  });

  /* ---------- Recherche globale ---------- */
  function initSearch() {
    const input = $('#av-search'), dd = $('#av-search-dd'); if (!input || !dd) return;
    const panel = $('[data-dropdown-menu]', dd);
    const index = [
      ...[...new Set(D.transactions.map(t => t.user))].map(u => ({ group: 'Utilisateurs', icon: 'user', label: u, sub: 'Utilisateur' })),
      ...D.transactions.slice(0, 12).map(t => ({ group: 'Transactions', icon: 'arrow-left-right', label: t.id, sub: AV.can('finance.sensitive') || AV.can('transactions.view') ? t.user : '' , tx: t.id })),
      ...D.tickets.map(t => ({ group: 'Tickets', icon: 'ticket', label: t.id + ' · ' + t.subject, sub: t.user })),
      ...D.problems.map(p => ({ group: 'Problèmes', icon: 'bug', label: p.id + ' · ' + p.title, sub: p.user, problem: p.id })),
      ...D.nav.flatMap(s => s.items.map(i => ({ group: 'Pages', icon: s.icon, label: i.label, sub: s.label, perm: s.perm })))
    ];
    let active = -1, results = [];
    const hl = (txt, q) => { const i = txt.toLowerCase().indexOf(q.toLowerCase()); return i < 0 ? AV.esc(txt) : AV.esc(txt.slice(0, i)) + '<mark>' + AV.esc(txt.slice(i, i + q.length)) + '</mark>' + AV.esc(txt.slice(i + q.length)); };
    const norm = s => s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    function render() {
      const q = input.value.trim();
      if (!q) { panel.innerHTML = `<div class="av-search-group">Raccourcis</div><div class="av-search-result">${AV.icon('keyboard', 'av-icon--sm')}Ouvrir la recherche<span class="av-search-result__sub"><span class="av-kbd">Ctrl</span> <span class="av-kbd">K</span></span></div><div class="av-search-result">${AV.icon('info', 'av-icon--sm')}Recherchez un nom, un ID de transaction (TX-…), un ticket (TCK-…) ou une page.</div>`; results = []; AV.icons(); return; }
      results = index.filter(r => AV.can(r.perm) && norm(r.label + ' ' + r.sub).includes(norm(q))).slice(0, 9);
      if (!results.length) { panel.innerHTML = `<div class="av-state" style="min-height:0;padding:24px 16px"><span class="av-state__icon">${AV.icon('search-x')}</span><p class="av-state__title">Aucun résultat pour « ${AV.esc(q)} »</p><p class="av-state__text">Vérifiez l’orthographe ou cherchez par ID (TX-000821, TCK-1042).</p></div>`; AV.icons(); return; }
      let g = '', html = '';
      results.forEach((r, i) => { if (r.group !== g) { g = r.group; html += `<div class="av-search-group">${g}</div>`; } html += `<a href="#" class="av-search-result${i === active ? ' is-active' : ''}" data-search-i="${i}" role="option">${AV.icon(r.icon, 'av-icon--sm')}<span>${hl(r.label, q)}</span><span class="av-search-result__sub">${AV.esc(r.sub)}</span></a>`; });
      panel.innerHTML = html; AV.icons();
    }
    function choose(i) {
      const r = results[i]; if (!r) return;
      AV.closeDropdown(); input.blur();
      if (r.tx && AV.openTransaction) AV.openTransaction(r.tx);
      else if (r.problem && AV.openProblem) AV.openProblem(r.problem);
      else AV.toast({ type: 'info', title: r.label, message: 'La fiche détaillée sera disponible avec la page « ' + r.group + ' ».' });
    }
    const open = () => { if (openDD !== dd) { AV.openDropdown(dd); } render(); AV.placeOpen(); };
    input.addEventListener('focus', open);
    input.addEventListener('input', () => { active = -1; open(); });
    input.addEventListener('keydown', e => {
      if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, results.length - 1); render(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); render(); }
      else if (e.key === 'Enter') { e.preventDefault(); choose(active < 0 ? 0 : active); }
      else if (e.key === 'Escape') { AV.closeDropdown(); input.blur(); }
      else if (e.key === 'Tab') AV.closeDropdown();
    });
    panel.addEventListener('mousedown', e => e.preventDefault());
    panel.addEventListener('click', e => { const a = e.target.closest('[data-search-i]'); if (a) { e.preventDefault(); choose(+a.dataset.searchI); } });
    document.addEventListener('keydown', e => {
      if ((e.key === 'k' && (e.metaKey || e.ctrlKey)) || (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName))) {
        e.preventDefault(); input.focus();
      }
    });
  }

  /* ---------- Notifications admin (topbar) ---------- */
  function initInbox() {
    const list = $('#av-inbox-list'); if (!list) return;
    const render = () => {
      const unread = D.inbox.filter(i => i.unread).length;
      const badge = $('#av-inbox-count');
      badge.textContent = unread; badge.hidden = !unread;
      $('#av-inbox-btn').setAttribute('aria-label', `Notifications, ${unread} non lue${unread > 1 ? 's' : ''}`);
      list.innerHTML = D.inbox.map((n, i) => `<a href="#" class="av-notif av-notif--${n.tone}${n.unread ? ' is-unread' : ''}" role="menuitem" data-inbox="${i}"><span class="av-notif__icon">${AV.icon(n.icon, 'av-icon--sm')}</span><span class="av-notif__text">${AV.esc(n.text)}<span class="av-notif__time" style="display:block">${n.time}</span></span></a>`).join('');
      AV.icons();
    };
    render();
    list.addEventListener('click', e => {
      const a = e.target.closest('[data-inbox]'); if (!a) return; e.preventDefault();
      const item = D.inbox[+a.dataset.inbox]; item.unread = false; render();
      if (item.problem && AV.openProblem) AV.openProblem(item.problem);
    });
    $('#av-inbox-readall')?.addEventListener('click', () => { D.inbox.forEach(n => n.unread = false); render(); AV.toast({ type: 'success', title: 'Toutes les notifications sont lues' }); });
  }

  /* ---------- Thème clair / sombre (persisté ; appliqué dès le <head> pour éviter le flash) ---------- */
  AV.theme = () => document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
  AV.setTheme = t => {
    document.documentElement.dataset.theme = t; store.set('theme', t);
    $$('[data-set-theme]').forEach(el => el.setAttribute('aria-checked', String(el.dataset.setTheme === t)));
    document.dispatchEvent(new CustomEvent('av:themechange', { detail: { theme: t } }));
  };

  /* ---------- Init ---------- */
  function initChrome() {
    $$('[data-set-theme]').forEach(el => el.setAttribute('aria-checked', String(el.dataset.setTheme === AV.theme())));
    const r = D.roles[AV.role].label;
    $$('[data-role-label]').forEach(el => el.textContent = r);
    $$('[data-set-role]').forEach(el => el.setAttribute('aria-checked', String(el.dataset.setRole === AV.role)));
    document.addEventListener('click', e => {
      const th = e.target.closest('[data-set-theme]'); if (th) { AV.setTheme(th.dataset.setTheme); return; }
      const ro = e.target.closest('[data-set-role]'); if (ro) { AV.setRole(ro.dataset.setRole); return; }
      const de = e.target.closest('[data-set-demo]'); if (de) { AV.widgets.setDemo(de.dataset.setDemo); return; }
      if (e.target.closest('[data-logout]')) {
        AV.confirm({ title: 'Se déconnecter ?', message: 'Vous devrez saisir à nouveau vos identifiants et votre code 2FA.', confirmLabel: 'Se déconnecter', icon: 'log-out' })
          .then(ok => ok && AV.toast({ type: 'info', title: 'Déconnexion simulée', message: 'En production : POST /logout puis redirection vers la connexion.' }));
      }
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && app().classList.contains('is-nav-open')) AV.openNav(false); });
  }

  AV.init = () => {
    initSidebar(); initDropdowns(); initModals(); initTooltips(); initSoonLinks(); initChrome(); initSearch(); initInbox();
    AV.applyPerms(); AV.icons();
  };
})();
