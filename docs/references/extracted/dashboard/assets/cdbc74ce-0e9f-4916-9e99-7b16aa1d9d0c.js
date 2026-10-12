/* ==========================================================================
   Données FICTIVES de démonstration — aucune donnée réelle.
   En production : remplacées par les données injectées par les contrôleurs Laravel
   (ex. @json($kpis)) ou par des appels API.
   ========================================================================== */
window.AV_DATA = (function () {
  'use strict';

  // Générateur pseudo-aléatoire déterministe (données stables à chaque chargement)
  function rng(seed) {
    return function () {
      seed |= 0; seed = seed + 0x6D2B79F5 | 0;
      let t = Math.imul(seed ^ seed >>> 15, 1 | seed);
      t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t;
      return ((t ^ t >>> 14) >>> 0) / 4294967296;
    };
  }

  const NOW = new Date(2026, 8, 29, 14, 32, 0); // 29 sept. 2026, 14:32 (horloge de démo)

  /* ---------- Navigation (→ config/admin-nav.php) ---------- */
  const nav = [
    { id: 'overview', label: "Vue d'ensemble", icon: 'layout-dashboard', flat: true, items: [
      { label: 'Dashboard', href: 'index.html', icon: 'layout-dashboard', active: true } ] },
    { id: 'users', label: 'Utilisateurs', icon: 'users', perm: 'users', items: [
      { label: 'Utilisateurs' }, { label: 'Groupes' }, { label: 'Invitations' },
      { label: 'Demandes de visibilité', count: 9 }, { label: 'Appareils' }, { label: 'Historique / activité' } ] },
    { id: 'subs', label: 'Abonnements', icon: 'package', perm: 'subscriptions', items: [
      { label: 'Abonnements' }, { label: 'Packs Camtel' }, { label: 'Activations Camtel', count: 42, tone: 'danger' } ] },
    { id: 'finance', label: 'Finance', icon: 'wallet', perm: 'finance', items: [
      { label: 'Transactions' }, { label: 'Paiements', count: 12, tone: 'warning' },
      { label: 'Remboursements', count: 4, tone: 'warning' }, { label: 'Réconciliation' } ] },
    { id: 'companies', label: 'Entreprises', icon: 'building-2', perm: 'companies', items: [
      { label: 'Entreprises' }, { label: 'Modération', count: 6 }, { label: 'Plans entreprise' }, { label: 'Catégories' } ] },
    { id: 'comms', label: 'Communication', icon: 'megaphone', perm: 'marketing', items: [
      { label: 'Campagnes' }, { label: 'Notifications' }, { label: 'Appareils & Push' }, { label: 'Modèles de messages' } ] },
    { id: 'support', label: 'Support', icon: 'life-buoy', perm: 'support', items: [
      { label: 'Support' }, { label: 'Tickets', count: 124 }, { label: 'Problèmes signalés', count: 3, tone: 'danger' }, { label: 'FAQ' } ] },
    { id: 'analytics', label: 'Analytics', icon: 'chart-column', perm: 'analytics', items: [
      { label: 'Vue générale' }, { label: 'Utilisateurs' }, { label: 'Abonnements' },
      { label: 'Finance', perm: 'finance' }, { label: 'Entreprises' }, { label: 'Notifications' } ] },
    { id: 'system', label: 'Système & développeurs', icon: 'code-xml', perm: 'system', items: [
      { label: 'Health Check' }, { label: 'API' }, { label: 'Documentation API' },
      { label: 'Clés & identifiants', perm: 'secrets' }, { label: 'Intégrations' }, { label: 'Webhooks' }, { label: 'Jobs / tâches système' } ] },
    { id: 'admin', label: 'Administration', icon: 'shield', perm: 'admin', items: [
      { label: 'Administrateurs' }, { label: 'Rôles & permissions' }, { label: "Journal d'activité" },
      { label: 'Documentation projet' }, { label: 'Paramètres' } ] }
  ];

  /* ---------- Rôles & permissions (→ policies / gates Laravel) ---------- */
  const roles = {
    super_admin: { label: 'Super Admin', perms: ['*'] },
    support:     { label: 'Support',     perms: ['users', 'subscriptions', 'support', 'companies', 'transactions.view'] },
    finance:     { label: 'Finance',     perms: ['finance', 'finance.sensitive', 'transactions.view', 'subscriptions', 'users', 'analytics'] },
    marketing:   { label: 'Marketing',   perms: ['marketing', 'companies', 'analytics', 'users'] }
  };

  const admin = { name: 'Estelle Fouda', initials: 'EF', email: 'e.fouda@allovaho.demo' };

  /* ---------- Périodes ---------- */
  const periods = [
    { id: '24h', label: 'Dernières 24 h', points: 24, unit: 'hour', mult: 0.034 },
    { id: 'today', label: "Aujourd'hui", points: 15, unit: 'hour', mult: 0.022 },
    { id: 'yesterday', label: 'Hier', points: 24, unit: 'hour', mult: 0.035 },
    { id: '7d', label: '7 derniers jours', points: 7, unit: 'day', mult: 0.24 },
    { id: '30d', label: '30 derniers jours', points: 30, unit: 'day', mult: 1 },
    { id: 'month', label: 'Ce mois', points: 29, unit: 'day', mult: 0.96 },
    { id: 'prev_month', label: 'Mois précédent', points: 31, unit: 'day', mult: 1.03 }
  ];

  /* ---------- KPI ---------- */
  function kpis(period) {
    const p = periods.find(x => x.id === period.id) || period;
    const r = rng(p.points * 97 + Math.round(p.mult * 1000));
    const jitter = (base, amp) => +(base + (r() - .5) * amp).toFixed(1);
    const spark = (n, trend) => Array.from({ length: 14 }, (_, i) => 50 + i * trend + (r() - .5) * 18);
    return [
      { id: 'users', label: 'Utilisateurs', icon: 'users', value: 24821, delta: jitter(12.4, 3), good: 'up', context: 'vs période préc.', spark: spark(14, 2.2), link: 'Utilisateurs' },
      { id: 'subs', label: 'Abonnements actifs', icon: 'badge-check', value: 8421, delta: jitter(8.7, 2.5), good: 'up', context: 'vs période préc.', spark: spark(14, 1.6), link: 'Abonnements' },
      { id: 'revenue', label: "Chiffre d'affaires", icon: 'banknote', value: Math.round(18450000 * p.mult / 500) * 500, unit: 'FCFA', delta: jitter(14.2, 4), good: 'up', context: 'vs période préc.', spark: spark(14, 2.8), perm: 'finance.sensitive', link: 'Transactions' },
      { id: 'tx', label: 'Transactions', icon: 'arrow-left-right', value: Math.max(1, Math.round(12842 * p.mult)), delta: jitter(6.1, 3), good: 'up', context: 'vs période préc.', spark: spark(14, 1.1), link: 'Transactions' },
      { id: 'tickets', label: 'Tickets ouverts', icon: 'ticket', value: 124, delta: -8.3, good: 'down', context: '12 urgents', spark: spark(14, -1.4), link: 'Tickets' },
      { id: 'devices', label: 'Appareils enregistrés', icon: 'smartphone', value: 12842, delta: jitter(6.4, 2), good: 'up', context: 'vs période préc.', spark: spark(14, 1.3), link: 'Appareils' }
    ];
  }

  /* ---------- Série graphique ---------- */
  function activity(period) {
    const p = periods.find(x => x.id === period.id) || period;
    const r = rng(p.points * 31 + 7);
    const labels = [];
    const users = [], subs = [], pays = [];
    for (let i = 0; i < p.points; i++) {
      let d;
      if (p.unit === 'hour') {
        d = new Date(NOW); d.setMinutes(0);
        const offset = p.id === 'yesterday' ? 24 + 14 : p.points - 1;
        d.setHours(NOW.getHours() - (offset - i));
        labels.push(d.getHours().toString().padStart(2, '0') + 'h');
      } else {
        d = new Date(NOW); d.setDate(NOW.getDate() - (p.points - 1 - i));
        labels.push(d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' }).replace('.', ''));
      }
      const hourShape = p.unit === 'hour' ? (0.35 + Math.sin(((d.getHours() - 6) / 24) * Math.PI * 2 - Math.PI / 2) * -0.45 + .55) : 1;
      const weekday = p.unit === 'day' ? ([0, 6].includes(d.getDay()) ? .82 : 1) : 1;
      const growth = 1 + i / p.points * .18;
      const base = p.unit === 'hour' ? 34 : 820;
      const u = Math.max(0, Math.round(base * hourShape * weekday * growth * (0.86 + r() * .28)));
      const s = Math.round(u * (0.36 + r() * .08));
      const py = Math.round(s * (0.8 + r() * .1));
      users.push(u); subs.push(s); pays.push(py);
    }
    return {
      labels,
      series: [
        { key: 'users', name: 'Nouveaux utilisateurs', color: 'var(--av-chart-1)', values: users },
        { key: 'subs', name: 'Abonnements', color: 'var(--av-chart-2)', values: subs },
        { key: 'pays', name: 'Paiements réussis', color: 'var(--av-chart-3)', values: pays }
      ]
    };
  }

  /* ---------- Funnel ---------- */
  function funnel(period) {
    const m = period.mult;
    const base = [24821, 18430, 11842, 9820, 8421];
    const labels = ['Utilisateurs inscrits', 'Ont consulté les offres', 'Abonnements commencés', 'Paiements effectués', 'Activations réussies'];
    const deltas = [12.4, 9.1, 7.6, 6.2, 8.7];
    const scale = m >= 0.9 ? 1 : Math.max(m * 1.2, 0.03);
    return labels.map((l, i) => ({ label: l, value: Math.round(base[i] * scale), delta: deltas[i] }));
  }

  /* ---------- Camtel ---------- */
  const camtel = {
    pending: 42, lt12: 21, h12to24: 14, gt24: 7, activated: 8421, rejected: 124, avgDelay: '6 h 40',
    oldest: [
      { id: 'ACT-4821', pack: 'Pack Liberté 15 Go', phone: '+237 6•• •• •• 12', user: 'Hervé Kamga', age: 31 },
      { id: 'ACT-4817', pack: 'Pack Pro Voix 60 min', phone: '+237 6•• •• •• 47', user: 'Ruth Abena', age: 28 },
      { id: 'ACT-4809', pack: 'Pack Nuit 5 Go', phone: '+237 6•• •• •• 90', user: 'Samuel Nji', age: 26 }
    ]
  };

  /* ---------- Transactions ---------- */
  const people = [
    ['Jean Dupont', 'Yaoundé'], ['Paul Martin', 'Douala'], ['Marie Ngono', 'Yaoundé'], ['Sophie Mbarga', 'Bafoussam'],
    ['Alain Tchoupo', 'Douala'], ['Brigitte Essomba', 'Yaoundé'], ['Samuel Nji', 'Bamenda'], ['Clarisse Atangana', 'Garoua'],
    ['Hervé Kamga', 'Douala'], ['Ruth Abena', 'Yaoundé'], ['Ibrahim Bello', 'Garoua'], ['Nadège Fotso', 'Bafoussam']
  ];
  const methods = [
    { id: 'mtn', label: 'MTN MoMo', mark: 'MTN', prefix: 'MTN', provider: 'MTN Mobile Money' },
    { id: 'om', label: 'Orange Money', mark: 'OM', prefix: 'OM', provider: 'Orange Money Cameroun' },
    { id: 'card', label: 'Carte bancaire', mark: 'CB', prefix: 'PSP', provider: 'Prestataire carte (démo)' }
  ];
  const packs = ['Pack Liberté 15 Go', 'Pack Pro Voix 60 min', 'Pack Nuit 5 Go', 'Pack Essentiel 2 Go', 'Pack Entreprise 40 Go'];
  const amounts = [2500, 5000, 5000, 10000, 15000, 5000, 25000, 2500, 10000];
  const statusPool = ['successful', 'successful', 'successful', 'successful', 'successful', 'successful', 'successful', 'pending', 'pending', 'failed', 'failed', 'refunded', 'cancelled'];

  const transactions = (function () {
    const r = rng(821);
    const list = [];
    let t = new Date(NOW); t.setMinutes(NOW.getMinutes() - 1);
    for (let i = 0; i < 26; i++) {
      const [name, city] = people[Math.floor(r() * people.length)];
      const m = methods[r() < .5 ? 0 : r() < .8 ? 1 : 2];
      const status = i === 1 ? 'pending' : i === 3 ? 'failed' : statusPool[Math.floor(r() * statusPool.length)];
      const date = new Date(t);
      t = new Date(t.getTime() - (3 + Math.floor(r() * 22)) * 60000);
      list.push({
        id: 'TX-' + String(821 - i).padStart(6, '0'),
        user: name, city,
        phone: '+237 6•• •• •• ' + String(10 + Math.floor(r() * 89)),
        amount: amounts[Math.floor(r() * amounts.length)],
        method: m,
        status,
        date,
        ref: m.prefix + '-' + String(84920381 - i * 7331),
        internalRef: 'AV-PAY-' + (2026092900 + 821 - i),
        pack: packs[Math.floor(r() * packs.length)],
        subscription: 'SUB-' + String(30200 + Math.floor(r() * 800)),
        app: r() < .75 ? 'Allo Vaho Android' : 'Allo Vaho iOS',
        requestId: 'req_' + Math.floor(r() * 1e12).toString(36) + Math.floor(r() * 1e6).toString(36),
        idempotency: 'idem_' + Math.floor(r() * 1e14).toString(36)
      });
    }
    return list;
  })();

  /* ---------- Tickets (recherche globale) ---------- */
  const tickets = [
    { id: 'TCK-1042', user: 'Marie Ngono', subject: 'Forfait payé mais non activé' },
    { id: 'TCK-1041', user: 'Paul Martin', subject: 'Double débit Orange Money' },
    { id: 'TCK-1039', user: 'Sophie Mbarga', subject: 'Entreprise introuvable dans l’annuaire' },
    { id: 'TCK-1037', user: 'Ibrahim Bello', subject: 'Changement de numéro de compte' }
  ];

  /* ---------- Problèmes signalés : contexte DANS l'application (pas de localisation physique) ---------- */
  const problems = [
    { id: 'BUG-00218', title: 'Impossible de souscrire au pack', message: 'Je n’arrive pas à souscrire au pack.', user: 'Jean Dupont', date: '29 sept. 2026 · 14:12', status: 'investigating', priority: 'high',
      ctx: { app: 'Allo Vaho Android', version: '1.4.2', screen: 'Packs Camtel', page: 'Détail du pack', step: 'Confirmation du paiement', prevAction: 'Clic sur « Souscrire »', lastAction: 'Validation du paiement', screenId: 'camtel.pack.checkout',
        trail: ['Accueil', 'Packs Camtel', 'Détail du pack', 'Paiement', 'Confirmation du paiement'] } },
    { id: 'BUG-00217', title: 'Paiement bloqué après validation', message: 'J’ai validé sur mon téléphone mais le paiement reste bloqué.', user: 'Paul Martin', date: '29 sept. 2026 · 13:58', status: 'open', priority: 'critical',
      ctx: { app: 'Allo Vaho iOS', version: '1.4.2', screen: 'Paiement', page: 'Moyen de paiement', step: 'Validation Orange Money', prevAction: 'Choix « Orange Money »', lastAction: 'Confirmation du code sur le téléphone', screenId: 'payment.provider.confirm',
        trail: ['Packs Camtel', 'Détail du pack', 'Paiement', 'Validation Orange Money'] } },
    { id: 'BUG-00216', title: 'Entreprise introuvable', message: 'La pharmacie que je cherche n’apparaît pas.', user: 'Sophie Mbarga', date: '29 sept. 2026 · 12:40', status: 'open', priority: 'medium',
      ctx: { app: 'Allo Vaho Android', version: '1.4.1', screen: 'Annuaire', page: 'Résultats de recherche', step: 'Recherche', prevAction: 'Recherche « pharmacie »', lastAction: 'Filtre « Catégorie : Santé »', screenId: 'directory.search.results',
        trail: ['Accueil', 'Annuaire', 'Résultats de recherche'] } },
    { id: 'BUG-00215', title: 'Écran blanc au lancement', message: 'L’application reste blanche après la mise à jour.', user: 'Samuel Nji', date: '29 sept. 2026 · 11:05', status: 'resolved', priority: 'high',
      ctx: { app: 'Allo Vaho Android', version: '1.3.9', screen: 'Démarrage', page: 'Écran de chargement', step: 'Initialisation', prevAction: 'Mise à jour de l’application', lastAction: 'Ouverture de l’application', screenId: 'app.splash',
        trail: ['Démarrage'] } }
  ];

  /* ---------- Services (santé) ---------- */
  const services = [
    { id: 'api', name: 'API', icon: 'server', latency: 84 },
    { id: 'db', name: 'Base de données', icon: 'database', latency: 12 },
    { id: 'cache', name: 'Cache', icon: 'zap', latency: 3 },
    { id: 'queue', name: 'Files d’attente', icon: 'layers', latency: 21 },
    { id: 'camtel', name: 'API Camtel', icon: 'radio-tower', latency: 412 },
    { id: 'payment', name: 'API Paiement', icon: 'credit-card', latency: 236 },
    { id: 'push', name: 'Notifications push', icon: 'bell-ring', latency: 148 },
    { id: 'storage', name: 'Stockage', icon: 'hard-drive', latency: 46 }
  ];

  /* ---------- Notifications admin (topbar) ---------- */
  const inbox = [
    { tone: 'danger', icon: 'timer-off', text: '7 activations Camtel ont dépassé le SLA de 24 h et ont été escaladées.', time: 'il y a 4 min', unread: true },
    { tone: 'danger', icon: 'bug', text: 'Nouveau problème critique : BUG-00217 · Paiement bloqué.', time: 'il y a 34 min', unread: true, problem: 'BUG-00217' },
    { tone: 'warning', icon: 'undo-2', text: '4 remboursements attendent votre validation.', time: 'il y a 1 h', unread: true },
    { tone: 'neutral', icon: 'file-down', text: 'Export « Transactions septembre » prêt au téléchargement.', time: 'hier, 18:02', unread: false }
  ];

  return { NOW, rng, nav, roles, admin, periods, kpis, activity, funnel, camtel, transactions, methods, tickets, problems, services, inbox };
})();
