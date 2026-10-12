/* ==========================================================================
   AVChart — graphique linéaire SVG sans dépendance (remplaçable par Chart.js /
   ApexCharts : même contrat { labels, series:[{key,name,color,values}] }).
   ========================================================================== */
(function () {
  'use strict';
  const NS = 'http://www.w3.org/2000/svg';
  const fmt = n => new Intl.NumberFormat('fr-FR').format(Math.round(n));
  const short = n => n >= 1e6 ? (n / 1e6).toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' M' : n >= 1e3 ? (n / 1e3).toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' k' : fmt(n);
  function niceMax(v) {
    if (v <= 0) return 4;
    const p = Math.pow(10, Math.floor(Math.log10(v))), n = v / p;
    return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 4 ? 4 : n <= 5 ? 5 : n <= 8 ? 8 : 10) * p;
  }

  function LineChart(el, opts) {
    this.el = el; this.opts = Object.assign({ height: 260 }, opts || {});
    this.hidden = new Set(); this.idx = -1;
    el.classList.add('av-chart'); el.tabIndex = 0; el.setAttribute('role', 'img');
    el.innerHTML = '<div class="av-chart__tip" aria-hidden="true"></div>';
    this.tip = el.firstChild;
    this._ro = new ResizeObserver(() => this.render()); this._ro.observe(el);
    el.addEventListener('mousemove', e => this._hover(e));
    el.addEventListener('mouseleave', () => this._setIdx(-1));
    el.addEventListener('blur', () => this._setIdx(-1));
    el.addEventListener('keydown', e => {
      if (!this.data) return; const n = this.data.labels.length;
      if (e.key === 'ArrowRight') { e.preventDefault(); this._setIdx(Math.min(n - 1, this.idx < 0 ? 0 : this.idx + 1)); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); this._setIdx(Math.max(0, this.idx < 0 ? n - 1 : this.idx - 1)); }
    });
  }
  LineChart.prototype.setData = function (data) { this.data = data; this.render(); };
  LineChart.prototype.toggle = function (key) {
    const visible = this.data.series.filter(s => !this.hidden.has(s.key));
    if (!this.hidden.has(key) && visible.length === 1) return false; // garder au moins une série
    this.hidden.has(key) ? this.hidden.delete(key) : this.hidden.add(key);
    this.render(); return true;
  };
  LineChart.prototype.destroy = function () { this._ro.disconnect(); this.el.innerHTML = ''; };

  LineChart.prototype.render = function () {
    const d = this.data; if (!d) return;
    const w = this.el.clientWidth; if (!w) return;
    const h = this.opts.height, mobile = w < 520;
    const pad = { t: 12, r: 8, b: 26, l: mobile ? 34 : 44 };
    const iw = w - pad.l - pad.r, ih = h - pad.t - pad.b, n = d.labels.length;
    const series = d.series.filter(s => !this.hidden.has(s.key));
    const max = niceMax(Math.max(1, ...series.flatMap(s => s.values)) * 1.08);
    const x = i => pad.l + (n === 1 ? iw / 2 : i * iw / (n - 1));
    const y = v => pad.t + ih - v / max * ih;
    this.geom = { x, y, pad, iw, ih, n, w };

    const svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('width', w); svg.setAttribute('height', h); svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
    let html = '<defs>' + series.map(s => `<linearGradient id="g-${s.key}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${s.color}" stop-opacity=".16"/><stop offset="1" stop-color="${s.color}" stop-opacity="0"/></linearGradient>`).join('') + '</defs>';
    for (let i = 0; i <= 4; i++) {
      const v = max * i / 4, yy = Math.round(y(v)) + .5;
      html += `<line x1="${pad.l}" x2="${w - pad.r}" y1="${yy}" y2="${yy}" stroke="var(--av-chart-grid)" ${i === 0 ? 'stroke="var(--av-border-strong)"' : ''}/>`;
      html += `<text x="${pad.l - 8}" y="${yy + 4}" text-anchor="end">${short(v)}</text>`;
    }
    const step = Math.max(1, Math.ceil(n / Math.floor(iw / (mobile ? 46 : 64))));
    d.labels.forEach((l, i) => { if (i % step === 0 || i === n - 1 && (n - 1) % step > step / 2) html += `<text x="${x(i)}" y="${h - 6}" text-anchor="${i === 0 ? 'start' : i === n - 1 ? 'end' : 'middle'}">${l}</text>`; });
    series.forEach((s, si) => {
      const pts = s.values.map((v, i) => `${x(i).toFixed(1)},${y(v).toFixed(1)}`);
      if (si === 0) html += `<path d="M${pts[0]} L${pts.join(' L')} L${x(n - 1)},${y(0)} L${x(0)},${y(0)} Z" fill="url(#g-${s.key})"/>`;
      html += `<polyline points="${pts.join(' ')}" fill="none" stroke="${s.color}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>`;
    });
    html += `<g class="av-chart__cursor" style="display:none"><line y1="${pad.t}" y2="${pad.t + ih}" stroke="var(--av-border-strong)" stroke-dasharray="3 3"/>${series.map(s => `<circle r="4" fill="var(--av-surface)" stroke="${s.color}" stroke-width="2" data-k="${s.key}"/>`).join('')}</g>`;
    html += `<rect class="av-chart__hit" x="${pad.l}" y="${pad.t}" width="${iw}" height="${ih}" fill="transparent"/>`;
    svg.innerHTML = html;
    const old = this.el.querySelector('svg'); old ? old.replaceWith(svg) : this.el.appendChild(svg);
    this.svg = svg; this.series = series;
    const tot = series.map(s => `${s.name} : ${fmt(s.values.reduce((a, b) => a + b, 0))}`).join(', ');
    this.el.setAttribute('aria-label', `Graphique d'activité, ${n} points. ${tot}. Flèches gauche/droite pour parcourir.`);
    if (this.idx >= 0) this._setIdx(Math.min(this.idx, n - 1));
  };
  LineChart.prototype._hover = function (e) {
    if (!this.geom) return; const r = this.el.getBoundingClientRect(), { pad, iw, n } = this.geom;
    const mx = e.clientX - r.left; if (mx < pad.l - 10 || mx > pad.l + iw + 10) return this._setIdx(-1);
    this._setIdx(Math.max(0, Math.min(n - 1, Math.round((mx - pad.l) / (iw / Math.max(1, n - 1))))));
  };
  LineChart.prototype._setIdx = function (i) {
    this.idx = i; const g = this.svg && this.svg.querySelector('.av-chart__cursor'); if (!g) return;
    if (i < 0) { g.style.display = 'none'; this.tip.classList.remove('is-visible'); return; }
    const { x, y, w } = this.geom, xx = x(i);
    g.style.display = ''; g.querySelector('line').setAttribute('x1', xx); g.querySelector('line').setAttribute('x2', xx);
    this.series.forEach(s => { const c = g.querySelector(`[data-k="${s.key}"]`); c.setAttribute('cx', xx); c.setAttribute('cy', y(s.values[i])); });
    this.tip.innerHTML = `<div class="av-chart__tip-title">${this.data.labels[i]}</div>` + this.series.map(s => `<div class="av-chart__tip-row"><i style="background:${s.color}"></i>${s.name}<b>${fmt(s.values[i])}</b></div>`).join('');
    this.tip.classList.add('is-visible');
    const tw = this.tip.offsetWidth; let left = xx + 14; if (left + tw > w) left = xx - tw - 14;
    this.tip.style.transform = `translate(${Math.max(0, left)}px, 8px)`;
  };

  function sparkline(values, color, w = 72, h = 28) {
    const min = Math.min(...values), max = Math.max(...values), rng = max - min || 1;
    const pts = values.map((v, i) => `${(i / (values.length - 1) * w).toFixed(1)},${(h - 2 - (v - min) / rng * (h - 4)).toFixed(1)}`);
    return `<svg viewBox="0 0 ${w} ${h}" width="${w}" height="${h}" aria-hidden="true"><polyline points="${pts.join(' ')}" fill="none" stroke="${color}" stroke-width="1.75" stroke-linejoin="round" stroke-linecap="round"/></svg>`;
  }

  window.AVChart = { LineChart, sparkline, short };
})();
