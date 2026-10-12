/* ==========================================================================
   Page : Dashboard — rendu des widgets et interactions propres à la page
   → resources/views/admin/dashboard.blade.php + resources/js/admin/pages/dashboard.js
   ========================================================================== */
(function () {
  'use strict';
  const AV = window.AV, D = window.AV_DATA, $ = AV.$, $$ = AV.$$, f = AV.fmt, I = AV.icon, esc = AV.esc;
  const lock = I('lock', 'av-icon--xs');

  const state = {
    period: D.periods[0],
    chartHidden: new Set(),
    health: { last: Date.now() - 30000, checking: false, statuses: {} }
  };
  let chart = null;

  /* ---------------- KPI ---------------- */
  AV.widgets.register('kpis', {
    skeleton: 'kpis',
    render(body) {
      body.innerHTML = D.kpis(state.period).map(k => {
        const restricted = k.perm && !AV.can(k.perm);
        const dir = k.delta > 0 ? 'up' : k.delta < 0 ? 'down' : 'flat';
        const tone = dir === 'flat' ? 'neutral' : dir === k.good ? 'good' : 'bad';
        const deltaTxt = k.deltaAbs ? f.signed(k.delta, false) : f.signed(k.delta);
        const big = k.value >= 1e6;
        const value = restricted ? '•••••' : big ? (k.value / 1e6).toLocaleString('fr-FR', { maximumFractionDigits: 2 }) : f.num(k.value);
        const unit = restricted ? '' : (big ? ' M ' : '') + (k.unit || '');
        const color = tone === 'bad' ? 'var(--av-danger-dot)' : 'var(--av-blue)';
        return `<a href="#" class="av-stat-card${k.tone ? ' av-stat-card--' + k.tone : ''}${restricted ? ' av-stat-card--restricted' : ''}" data-soon="${esc(k.link)}"${big && !restricted ? ` data-tooltip="${f.money(k.value)}"` : ''}>
          <div class="av-stat-card__head"><span class="av-stat-card__icon">${I(k.icon)}</span><span class="av-stat-card__label">${esc(k.label)}</span></div>
          <div class="av-stat-card__value">${value}${unit.trim() ? `<span class="av-stat-card__unit">${unit.trim()}</span>` : ''}</div>
          <div class="av-stat-card__foot">${restricted
            ? `<span class="av-restricted-inline">${lock}Réservé au rôle Finance</span>`
            : `<span class="av-trend av-trend--${tone}"><span class="av-sr-only">Évolution </span>${I(dir === 'up' ? 'trending-up' : dir === 'down' ? 'trending-down' : 'minus', 'av-icon--xs')}${deltaTxt}</span><span>${esc(k.context)}</span>`}</div>
          ${restricted ? '' : `<div class="av-stat-card__spark">${AVChart.sparkline(k.spark, color)}</div>`}
        </a>`;
      }).join('');
    }
  });

  /* ---------------- Activité (graphique) ---------------- */
  AV.widgets.register('activity', {
    skeleton: 'chart',
    empty: { icon: 'chart-line', title: 'Aucune activité sur cette période', text: 'Choisissez une période plus large pour afficher la courbe.' },
    render(body) {
      const data = D.activity(state.period);
      body.innerHTML = `<div class="av-chart-summary">${data.series.map(s => `<div class="av-chart-summary__item${state.chartHidden.has(s.key) ? ' is-off' : ''}" data-sum="${s.key}"><div class="av-chart-summary__label"><i style="background:${s.color}"></i>${esc(s.name)}</div><div class="av-chart-summary__value">${f.num(s.values.reduce((a, b) => a + b, 0))}</div></div>`).join('')}</div><div data-chart></div>`;
      if (chart) chart.destroy();
      chart = new AVChart.LineChart($('[data-chart]', body), { height: 240 });
      state.chartHidden.forEach(k => chart.hidden.add(k));
      chart.setData(data);
    }
  });
  function initLegend() {
    $$('[data-series]').forEach(b => b.addEventListener('click', () => {
      if (!chart) return;
      const k = b.dataset.series;
      if (!chart.toggle(k)) { AV.toast({ type: 'warning', title: 'Au moins une série doit rester visible' }); return; }
      const off = chart.hidden.has(k);
      off ? state.chartHidden.add(k) : state.chartHidden.delete(k);
      b.setAttribute('aria-pressed', String(!off));
      $(`[data-sum="${k}"]`)?.classList.toggle('is-off', off);
    }));
  }

  /* ---------------- Santé technique ---------------- */
  AV.widgets.register('health', {
    skeleton: 'rows',
    render(body) {
      const h = state.health;
      body.innerHTML = `<div class="av-health-summary${h.checking ? ' is-checking' : ''}" role="status">
          ${h.checking ? AV.spinner() : I('shield-check')}
          <div><div class="av-health-summary__title">${h.checking ? 'Vérification des services en cours…' : 'Tous les services sont opérationnels'}</div>
          <div class="av-health-summary__sub">Disponibilité 30 j : 99,98 % · aucun incident ouvert</div></div>
        </div>
        <ul class="av-health-list">${D.services.map(s => `<li class="av-health-row" data-service="${s.id}">${I(s.icon)}<span class="av-health-row__name">${esc(s.name)}</span><span class="av-health-row__latency">${h.statuses[s.id] === 'checking' ? '—' : s.latency + ' ms'}</span>${AV.status(h.statuses[s.id] || 'operational')}</li>`).join('')}</ul>`;
    }
  });
  async function runHealthCheck(btn) {
    const h = state.health; if (h.checking) return;
    await AV.withLoading(btn, async () => {
      h.checking = true; D.services.forEach(s => h.statuses[s.id] = 'checking');
      AV.widgets.render('health');
      await AV.wait(700);
      for (const s of D.services) {
        await AV.wait(140);
        s.latency = Math.max(2, Math.round(s.latency * (0.8 + Math.random() * 0.4)));
        h.statuses[s.id] = 'operational';
        const row = $(`[data-service="${s.id}"]`);
        if (row) { row.querySelector('.av-health-row__latency').textContent = s.latency + ' ms'; row.querySelector('.av-badge').outerHTML = AV.status('operational'); }
      }
      h.checking = false; h.last = Date.now();
      AV.widgets.render('health'); tickLastCheck();
      AV.toast({ type: 'success', title: '8 / 8 services opérationnels', message: 'Contrôle simulé terminé · résultats de démonstration.' });
    });
  }
  function tickLastCheck() { $$('[data-last-check]').forEach(el => el.textContent = f.ago(Date.now() - state.health.last)); }

  /* ---------------- Funnel ---------------- */
  AV.widgets.register('funnel', {
    skeleton: 'rows',
    render(body) {
      const st = D.funnel(state.period), top = st[0].value;
      let worst = { i: 1, rate: 100 };
      st.forEach((s, i) => { if (i) { const r = s.value / st[i - 1].value * 100; if (r < worst.rate) worst = { i, rate: r }; } });
      const global = st[st.length - 1].value / top * 100;
      body.innerHTML = `<div class="av-metric-row" style="margin-bottom:16px">
          <div class="av-metric"><div class="av-metric__label">Conversion globale</div><div class="av-metric__value">${f.pct(global)}<small>inscrit → activé</small></div></div>
          <div class="av-metric av-metric--warning"><div class="av-metric__label">Plus forte perte</div><div class="av-metric__value">−${f.pct(100 - worst.rate)}<small>étape ${worst.i} → ${worst.i + 1}</small></div></div>
        </div>
        <div class="av-funnel">${st.map((s, i) => {
          const pct = s.value / top * 100;
          const drop = i ? `<div class="av-funnel__drop">${I('corner-down-right', 'av-icon--xs')}<span><b>${f.pct(s.value / st[i - 1].value * 100)}</b> passent à l’étape suivante</span><span class="is-loss">−${f.num(st[i - 1].value - s.value)}</span></div>` : '';
          return drop + `<div class="av-funnel__step">
            <div class="av-funnel__label"><span class="av-funnel__index">${i + 1}</span><span>${esc(s.label)}</span></div>
            <div class="av-funnel__nums"><span class="av-trend av-trend--good">${f.signed(s.delta)}</span><span class="av-funnel__value">${f.num(s.value)}</span><span class="av-funnel__pct">${f.pct(pct, 0)}</span></div>
            <div class="av-funnel__track" role="img" aria-label="${esc(s.label)} : ${f.pct(pct, 0)} des inscrits"><div class="av-funnel__bar" style="width:${pct.toFixed(1)}%"></div></div>
          </div>`;
        }).join('')}</div>`;
    }
  });

  /* ---------------- Camtel SLA ---------------- */
  AV.widgets.register('sla', {
    skeleton: 'rows',
    empty: { icon: 'check-check', title: 'Aucune activation en attente', text: 'Toutes les demandes ont été traitées par Camtel.' },
    render(body) {
      const c = D.camtel;
      body.innerHTML = `<div class="av-sla-head">
          <div><div class="av-sla-head__value">${c.pending}</div><div class="av-sla-head__label">activations en attente chez Camtel</div></div>
          <span class="av-badge av-badge--danger av-badge--lg" style="margin-left:auto"><span class="av-badge__dot"></span>${c.gt24} hors SLA</span>
        </div>
        <div class="av-stacked" role="img" aria-label="${c.lt12} dans les délais, ${c.h12to24} en alerte, ${c.gt24} escaladées">
          <span style="flex-grow:${c.lt12};background:var(--av-success-dot)"></span><span style="flex-grow:${c.h12to24};background:var(--av-warning-dot)"></span><span style="flex-grow:${c.gt24};background:var(--av-danger-dot)"></span>
        </div>
        <div class="av-sla-buckets">
          <div class="av-sla-bucket"><span class="av-badge av-badge--success av-sla">&lt; 12 h</span><span class="av-sla-bucket__value">${c.lt12}</span><span class="av-sla-bucket__rule">Dans les délais</span></div>
          <div class="av-sla-bucket av-sla-bucket--warn"><span class="av-badge av-badge--warning av-sla">12–24 h</span><span class="av-sla-bucket__value">${c.h12to24}</span><span class="av-sla-bucket__rule">Alerte envoyée à 12 h</span></div>
          <div class="av-sla-bucket av-sla-bucket--breach"><span class="av-badge av-badge--danger av-sla">&gt; 24 h</span><span class="av-sla-bucket__value">${c.gt24}</span><span class="av-sla-bucket__rule">Escaladées à 24 h</span></div>
        </div>
        <div class="av-alert av-alert--danger" style="margin-top:12px">
          <span class="av-alert__icon">${I('siren')}</span>
          <div class="av-alert__body"><div class="av-alert__title">${c.gt24} activations dépassent actuellement le SLA</div><div>Escalade transmise à l’équipe Camtel.</div></div>
          <a href="#" class="av-btn av-btn--danger av-btn--sm" data-soon="Activations Camtel">Voir les activations</a>
        </div>
        <div class="av-sla-oldest">
          <div class="av-section-title">Plus anciennes en attente</div>
          ${c.oldest.map(o => `<div class="av-sla-oldest__row"><div class="av-sla-oldest__main"><div><span class="av-cell-id">${o.id}</span> · ${esc(o.pack)}</div><div class="av-sla-oldest__sub">${esc(o.user)} · <span class="av-mono">${o.phone}</span></div></div><span class="av-badge av-badge--danger av-sla">${o.age} h</span></div>`).join('')}
        </div>
        <div class="av-sla-totals">
          <div><b>${f.num(c.activated)}</b>Activées</div><div><b>${f.num(c.rejected)}</b>Rejetées</div><div><b>${c.avgDelay}</b>Délai moyen</div>
        </div>`;
    }
  });

  /* ================= Drawers de détail (ouverts depuis la recherche globale et les notifications) ================= */
  const canFin = () => AV.can('finance.sensitive');
  const methodCell = m => `<span class="av-method"><span class="av-method__mark" aria-hidden="true">${m.mark}</span>${m.label}</span>`;
  const kv = rows => `<dl class="av-kv">${rows.filter(Boolean).map(([k, v]) => `<dt>${k}</dt><dd>${v}</dd>`).join('')}</dl>`;
  const copyBtn = (v, label = 'Copié') => `<button type="button" class="av-icon-btn av-icon-btn--sm" data-copy="${esc(v)}" data-copy-label="${label}" aria-label="Copier ${esc(v)}" data-tooltip="Copier">${I('copy', 'av-icon--xs')}</button>`;
  AV.jsonViewer = (obj, title = 'application/json') => {
    const raw = JSON.stringify(obj, null, 2);
    const hl = esc(raw).replace(/(&quot;(?:[^&]|&(?!quot;))*?&quot;)(\s*:)?|\b(true|false)\b|\bnull\b|-?\b\d+(?:\.\d+)?\b/g, (m, str, colon, bool) => {
      if (str) return colon ? `<span class="j-key">${str}</span>${colon}` : `<span class="j-str">${str}</span>`;
      if (bool) return `<span class="j-bool">${m}</span>`;
      if (m === 'null') return `<span class="j-null">${m}</span>`;
      return `<span class="j-num">${m}</span>`;
    });
    return `<div class="av-json"><div class="av-json__bar">${I('braces', 'av-icon--xs')}<span>${esc(title)}</span><button type="button" class="av-btn av-btn--sm" data-copy="${esc(raw)}" data-copy-label="JSON copié">${I('copy', 'av-icon--xs')}Copier</button></div><pre><code>${hl}</code></pre></div>`;
  };
  AV.openTransaction = id => {
    const t = D.transactions.find(x => x.id === id); if (!t) return;
    const fin = canFin();
    const webhook = t.status === 'pending' ? 'pending' : 'received';
    const hist = [
      ['Paiement initié depuis l’application', 'info', 'circle-play', 0],
      ['Demande envoyée à ' + t.method.provider, 'neutral', 'send', 1],
      t.status === 'pending' ? ['En attente de validation par l’utilisateur (USSD)', 'warning', 'hourglass', 2] :
      t.status === 'failed' ? ['Refusé par le fournisseur · solde insuffisant', 'danger', 'circle-x', 2] :
      t.status === 'cancelled' ? ['Annulé par l’utilisateur', 'neutral', 'ban', 2] : ['Webhook de confirmation reçu', 'success', 'webhook', 2],
      t.status === 'successful' ? ['Abonnement transmis à Camtel pour activation', 'success', 'radio-tower', 3] : null,
      t.status === 'refunded' ? ['Remboursement validé par Finance', 'info', 'undo-2', 60] : null
    ].filter(Boolean);
    const fmtT = m => f.time(new Date(t.date.getTime() + m * 60000)) + ':' + String(12 + m * 7 % 47).padStart(2, '0');
    const payload = { provider: t.method.provider, status: t.status.toUpperCase(), financialTransactionId: t.ref.split('-')[1], externalId: t.internalRef, amount: String(t.amount), currency: 'XAF', payer: { partyIdType: 'MSISDN', partyId: '2376••••••' + t.phone.slice(-2) }, reason: t.status === 'failed' ? 'INSUFFICIENT_FUNDS' : null, _demo: true };
    AV.drawer.open({
      eyebrow: 'Transaction · données fictives', title: t.id, badge: AV.status(t.status),
      body: `<div class="av-metric-row">
          <div class="av-metric"><div class="av-metric__label">Montant</div><div class="av-metric__value">${fin ? f.num(t.amount) + '<small>FCFA</small>' : '<span class="av-masked">' + lock + '••••</span>'}</div></div>
          <div class="av-metric"><div class="av-metric__label">Méthode</div><div class="av-metric__value" style="font-size:15px">${methodCell(t.method)}</div></div>
          <div class="av-metric"><div class="av-metric__label">Webhook</div><div class="av-metric__value" style="font-size:15px">${AV.status(webhook, webhook === 'pending' ? 'En attente' : 'Reçu')}</div></div>
        </div>
        <section><h3 class="av-section-title">Détails</h3>${kv([
          ['Transaction ID', `<span class="av-mono">${t.id}</span>${copyBtn(t.id, 'ID copié')}`],
          ['Référence interne', `<span class="av-mono">${t.internalRef}</span>`],
          ['Référence fournisseur', fin ? `<span class="av-mono">${t.ref}</span>${copyBtn(t.ref, 'Référence copiée')}` : `<span class="av-masked">${lock}${t.method.prefix}-••••${t.ref.slice(-2)}</span>`],
          ['Utilisateur', `<a href="#" data-soon="Utilisateur 360° · ${esc(t.user)}">${esc(t.user)}</a><span class="av-muted">· ${t.city} · <span class="av-mono">${t.phone}</span></span>`],
          ['Abonnement', `<span class="av-mono">${t.subscription}</span> · ${esc(t.pack)}`],
          ['Montant · devise', fin ? `${f.money(t.amount)} · XAF` : `<span class="av-masked">${lock}Restreint</span>`],
          ['Fournisseur', esc(t.method.provider)],
          ['Créée le', f.dateTime(t.date)],
          ['Application', `${esc(t.app)} · v1.4.2`],
          ['Request ID', `<span class="av-mono">${t.requestId}</span>${copyBtn(t.requestId, 'Request ID copié')}`],
          ['Idempotency key', `<span class="av-mono av-masked">idem_••••••${t.idempotency.slice(-4)}</span><span class="av-badge av-badge--outline" style="height:20px;font-size:11px">Secret masqué</span>`]
        ])}</section>
        <section><h3 class="av-section-title">Historique</h3><ol class="av-timeline av-timeline--compact">${hist.map(([txt, tone, icon, m]) => `<li class="av-timeline__item"><span class="av-timeline__icon${tone !== 'neutral' ? ' av-timeline__icon--' + tone : ''}">${I(icon)}</span><div class="av-timeline__content"><div class="av-timeline__title">${txt}</div><div class="av-timeline__meta av-num">${fmtT(m)}</div></div></li>`).join('')}</ol></section>
        <section><h3 class="av-section-title">Réponse fournisseur</h3>${fin ? AV.jsonViewer(payload, 'POST /webhooks/payments · 200') : `<div class="av-restricted-box"><span class="av-restricted-box__icon">${I('lock')}</span><div><div class="av-restricted-box__title">Informations restreintes</div><div class="av-restricted-box__text">Les réponses techniques des fournisseurs de paiement sont réservées aux rôles Finance et Super Admin.</div></div></div>`}</section>`,
      footer: `<button type="button" class="av-btn av-btn--ghost" data-copy="${t.id}" data-copy-label="ID copié">${I('copy', 'av-icon--sm')}Copier l’ID</button>
        <button type="button" class="av-btn av-btn--secondary" data-soon="Utilisateur 360° · ${esc(t.user)}">${I('user', 'av-icon--sm')}Voir l’utilisateur</button>
        ${t.status === 'successful' ? `<button type="button" class="av-btn av-btn--danger-soft" data-tx-refund="${t.id}" data-perm="finance" data-perm-mode="disable">${I('undo-2', 'av-icon--sm')}Rembourser…</button>` : ''}`
    });
  };
  async function refund(id) {
    const t = D.transactions.find(x => x.id === id);
    const ok = await AV.confirm({ title: `Rembourser ${id} ?`, message: `${f.money(t.amount)} seront renvoyés à ${t.user} via ${t.method.label}. Une seconde validation Finance sera requise.`, confirmLabel: 'Demander le remboursement', tone: 'danger', icon: 'undo-2', note: 'Cette action sera enregistrée dans le journal d’activité.' });
    if (ok) AV.toast({ type: 'success', title: 'Demande de remboursement créée', message: `${id} · en attente de validation (simulation).` });
  }

  /* AppContext : où se trouvait l'utilisateur dans l'application au moment du signalement */
  AV.appContext = c => `<div class="av-app-context">
      <div class="av-app-context__head">${I('app-window', 'av-icon--sm')}Contexte dans l’application<span class="av-badge av-badge--outline">${esc(c.app)} · v${esc(c.version)}</span></div>
      <div class="av-app-context__body">
        <div><div class="av-section-title" style="margin-bottom:6px">Parcours avant le signalement</div>
          <div class="av-app-trail">${c.trail.map((s, i) => (i ? I('chevron-right', 'av-icon--xs') : '') + `<span class="av-app-trail__step${i === c.trail.length - 1 ? ' is-current' : ''}"${i === c.trail.length - 1 ? ' aria-current="step"' : ''}>${esc(s)}</span>`).join('')}</div></div>
        ${kv([
          ['Écran', esc(c.screen)],
          ['Page', esc(c.page)],
          ['Étape', esc(c.step)],
          ['Action précédente', esc(c.prevAction)],
          ['Dernière action', esc(c.lastAction)],
          ['Application', esc(c.app)],
          ['Version', esc(c.version)],
          ['Identifiant écran', `<span class="av-mono">${esc(c.screenId)}</span>${copyBtn(c.screenId, 'Identifiant copié')}`]
        ])}
      </div>
    </div>`;
  AV.openProblem = id => {
    const p = D.problems.find(x => x.id === id); if (!p) return;
    AV.drawer.open({
      eyebrow: 'Problème signalé · ' + p.id, title: p.title, badge: AV.status(p.status),
      body: `<div class="av-quote">${I('message-square-quote', 'av-icon--sm')}<span>« ${esc(p.message)} »</span></div>
        ${kv([
          ['Utilisateur', `<a href="#" data-soon="Utilisateur 360° · ${esc(p.user)}">${esc(p.user)}</a>`],
          ['Date', esc(p.date)],
          ['Priorité', AV.priority(p.priority)],
          ['Statut', AV.status(p.status)]
        ])}
        <section><h3 class="av-section-title">Contexte</h3>${AV.appContext({ ...p.ctx })}</section>`,
      footer: `<button type="button" class="av-btn av-btn--secondary" data-soon="Problèmes signalés">${I('external-link', 'av-icon--sm')}Ouvrir la fiche</button><button type="button" class="av-btn av-btn--primary" data-problem-ticket="${p.id}">${I('ticket-plus', 'av-icon--sm')}Créer un ticket</button>`
    });
  };
  function initDrawerActions() {
    $('#av-drawer').addEventListener('click', async e => {
      const tk = e.target.closest('[data-problem-ticket]');
      if (tk) { await AV.withLoading(tk, () => AV.wait(700)); AV.drawer.close(); AV.toast({ type: 'success', title: 'Ticket TCK-1043 créé', message: `Lié à ${tk.dataset.problemTicket} · contexte de l’application joint (simulation).` }); return; }
      const rf = e.target.closest('[data-tx-refund]'); if (rf) refund(rf.dataset.txRefund);
    });
  }

  /* ================= Période, export, en-tête ================= */
  function setPeriod(p) {
    state.period = p;
    $$('[data-period-label]').forEach(el => el.textContent = p.label);
    $$('[data-period]').forEach(el => el.setAttribute('aria-checked', String(el.dataset.period === p.id)));
    $('#av-activity-sub').textContent = p.label + (p.unit === 'hour' ? ' · par heure' : ' · par jour');
    AV.widgets.reload(['kpis', 'activity', 'funnel']);
  }
  function initPeriod() {
    const menu = $('#av-period-menu');
    menu.innerHTML = D.periods.map(p => `<button type="button" class="av-menu-item" role="menuitemradio" aria-checked="${p.id === state.period.id}" data-period="${p.id}">${p.label}</button>`).join('') +
      `<div class="av-menu-sep" role="separator"></div><button type="button" class="av-menu-item" role="menuitem" data-modal-open="av-modal-period">${I('calendar-range')}Période personnalisée…</button>`;
    menu.addEventListener('click', e => { const b = e.target.closest('[data-period]'); if (b) setPeriod(D.periods.find(p => p.id === b.dataset.period)); });
    const form = $('#av-period-form'), from = $('#av-date-from'), to = $('#av-date-to'), err = $('#av-date-error');
    form.addEventListener('submit', e => {
      e.preventDefault();
      const a = new Date(from.value), b = new Date(to.value);
      let msg = '';
      if (!from.value || !to.value) msg = 'Choisissez une date de début et une date de fin.';
      else if (b < a) msg = 'La date de fin doit être après la date de début.';
      else if ((b - a) / 864e5 > 366) msg = 'La période ne peut pas dépasser 12 mois.';
      err.hidden = !msg; $('span', err).textContent = msg;
      [from, to].forEach(i => i.setAttribute('aria-invalid', String(!!msg)));
      if (msg) return;
      const days = Math.round((b - a) / 864e5) + 1;
      const o = { day: 'numeric', month: 'short' };
      setPeriod({ id: 'custom', label: `${a.toLocaleDateString('fr-FR', o)} → ${b.toLocaleDateString('fr-FR', o)}`, points: Math.max(2, Math.min(days, 60)), unit: 'day', mult: days / 30 });
      AV.modal.close('av-modal-period');
    });
  }
  function initExport() {
    const form = $('#av-export-form');
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const fmt = new FormData(form).get('format');
      const sections = new FormData(form).getAll('sections');
      if (!sections.length) { $('#av-export-error').hidden = false; return; }
      $('#av-export-error').hidden = true;
      await AV.withLoading($('#av-export-submit'), () => AV.wait(1100));
      const name = `allovaho_dashboard_${new Date().toISOString().slice(0, 10)}.${fmt}`;
      if (fmt === 'csv') {
        const fin = canFin();
        const rows = [['id', 'utilisateur', 'montant_fcfa', 'methode', 'statut', 'date', 'reference'], ...D.transactions.map(t => [t.id, t.user, fin ? t.amount : 'masque', t.method.label, t.status, t.date.toISOString(), fin ? t.ref : 'masque'])];
        const blob = new Blob(['\ufeff' + rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(';')).join('\n')], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click(); a.remove();
      }
      AV.modal.close('av-modal-export');
      AV.toast({ type: 'success', title: 'Export prêt', message: fmt === 'csv' ? `${name} téléchargé (données fictives).` : `${name} · génération simulée pour la démo.` });
    });
  }
  function initHeader() {
    const upd = () => { $('#av-updated-time').textContent = f.time(new Date()); };
    upd();
    $('#av-refresh').addEventListener('click', async e => { await AV.withLoading(e.currentTarget, () => AV.widgets.reload(undefined, 700)); upd(); });
    $('#av-critical-close')?.addEventListener('click', () => { $('#av-critical').hidden = true; });
    $('#av-critical-problem')?.addEventListener('click', e => { e.preventDefault(); AV.openProblem('BUG-00217'); });
    $('#av-health-run').addEventListener('click', e => runHealthCheck(e.currentTarget));
    setInterval(tickLastCheck, 5000); tickLastCheck();
  }

  /* ================= Init ================= */
  AV.init();
  initLegend(); initDrawerActions(); initPeriod(); initExport(); initHeader();
  $('#av-activity-sub').textContent = state.period.label + ' · par heure';
  AV.widgets.reload(undefined, 450);
  document.addEventListener('av:rolechange', () => AV.widgets.renderAll());
})();
