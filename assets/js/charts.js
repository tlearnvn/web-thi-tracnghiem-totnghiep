/* Biểu đồ dùng chung (Chart.js) – tự đổi màu theo giao diện sáng / tối */
(function (w, d) {
  'use strict';
  if (!w.Chart) return;
  var charts = [];
  function css(v) { return getComputedStyle(d.documentElement).getPropertyValue(v).trim(); }
  function theme() {
    var dark = d.documentElement.getAttribute('data-theme') === 'dark';
    Chart.defaults.color = dark ? '#aab4c5' : '#64748b';
    Chart.defaults.borderColor = dark ? 'rgba(148,163,184,.14)' : 'rgba(15,23,42,.08)';
    Chart.defaults.font.family = "'Be Vietnam Pro', system-ui, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.plugins.tooltip.backgroundColor = dark ? '#1e293b' : '#0f172a';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
  }
  theme();
  var P = function () { return css('--primary') || '#2563eb'; };
  var PALETTE = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#65a30d', '#ea580c', '#4f46e5'];
  var SCORE = ['#dc2626', '#f97316', '#f59e0b', '#2563eb', '#16a34a'];
  function alpha(hex, a) {
    hex = hex.replace('#', '');
    if (hex.length === 3) hex = hex.split('').map(function (c) { return c + c; }).join('');
    var n = parseInt(hex, 16);
    return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + a + ')';
  }
  function make(el, cfg) {
    if (typeof el === 'string') el = d.getElementById(el);
    if (!el) return null;
    var c = new Chart(el, cfg);
    charts.push({ c: c, el: el, cfg: cfg });
    return c;
  }
  var fmt = function (v) { return (w.TN && TN.fmtNum) ? TN.fmtNum(v, 2) : v; };

  w.TNChart = {
    palette: PALETTE,
    alpha: alpha,
    /** Phổ điểm: labels = mốc điểm, data = số học sinh */
    histogram: function (el, labels, data, opt) {
      opt = opt || {};
      var colors = labels.map(function (l) {
        var v = parseFloat(String(l).replace(',', '.'));
        var max = opt.max || 10;
        var r = v / max;
        return r >= 0.8 ? SCORE[4] : r >= 0.65 ? SCORE[3] : r >= 0.5 ? SCORE[2] : r >= 0.35 ? SCORE[1] : SCORE[0];
      });
      return make(el, {
        type: 'bar',
        data: { labels: labels, datasets: [{ label: opt.label || 'Số học sinh', data: data, backgroundColor: colors.map(function (c) { return alpha(c, .85); }), borderRadius: 5, maxBarThickness: 38 }] },
        options: {
          responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { title: function (it) { return 'Điểm ' + it[0].label; }, label: function (it) { return ' ' + it.raw + ' học sinh'; } } } },
          scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false }, title: { display: !!opt.xTitle, text: opt.xTitle } } }
        }
      });
    },
    bar: function (el, labels, datasets, opt) {
      opt = opt || {};
      return make(el, {
        type: 'bar',
        data: { labels: labels, datasets: datasets.map(function (ds, i) { var c = ds.color || PALETTE[i % PALETTE.length]; return Object.assign({ backgroundColor: alpha(c, .82), borderRadius: 6, maxBarThickness: 44 }, ds); }) },
        options: {
          indexAxis: opt.horizontal ? 'y' : 'x', responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: datasets.length > 1 }, tooltip: { callbacks: { label: function (it) { return ' ' + it.dataset.label + ': ' + fmt(it.raw) + (opt.suffix || ''); } } } },
          scales: { y: { beginAtZero: true, max: opt.max, stacked: !!opt.stacked, ticks: { callback: function (v) { return fmt(v) + (opt.suffix || ''); } } }, x: { stacked: !!opt.stacked, grid: { display: false } } }
        }
      });
    },
    line: function (el, labels, datasets, opt) {
      opt = opt || {};
      return make(el, {
        type: 'line',
        data: { labels: labels, datasets: datasets.map(function (ds, i) { var c = ds.color || PALETTE[i % PALETTE.length]; return Object.assign({ borderColor: c, backgroundColor: alpha(c, .12), fill: true, tension: .35, pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: c }, ds); }) },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: datasets.length > 1 } }, scales: { y: { beginAtZero: true, max: opt.max } } }
      });
    },
    doughnut: function (el, labels, data, colors, opt) {
      opt = opt || {};
      return make(el, {
        type: 'doughnut',
        data: { labels: labels, datasets: [{ data: data, backgroundColor: colors || PALETTE, borderWidth: 2, borderColor: css('--surface') || '#fff', hoverOffset: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: { legend: { position: opt.legend || 'bottom' }, tooltip: { callbacks: { label: function (it) { var t = it.dataset.data.reduce(function (a, b) { return a + b; }, 0); return ' ' + it.label + ': ' + it.raw + (t ? ' (' + fmt(it.raw * 100 / t) + '%)' : ''); } } } } }
      });
    },
    scoreColors: SCORE
  };

  d.addEventListener('tn:theme', function () {
    theme();
    charts.forEach(function (x) {
      if (x.cfg.type === 'doughnut') x.c.data.datasets[0].borderColor = css('--surface');
      x.c.update();
    });
  });
})(window, document);
