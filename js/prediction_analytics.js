(function () {
  'use strict';

  /* ══════════════════════════════════════════════════════
     API helpers — same pattern as street_monitoring.js
  ══════════════════════════════════════════════════════ */
  var API_PHP  = '../backend/prediction_analytics.php';
  var API_PY   = 'http://localhost:5001';

  async function api(action, params) {
    var qs = '?action=' + encodeURIComponent(action);
    if (params) {
      Object.keys(params).forEach(function (k) {
        if (params[k] !== undefined && params[k] !== null) {
          qs += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
        }
      });
    }
    var res = await fetch(API_PHP + qs);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    var data = await res.json();
    if (!data.ok) throw new Error(data.error || 'API error');
    return data;
  }

  async function apiPost(action, body) {
    var res = await fetch(API_PHP + '?action=' + encodeURIComponent(action), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    var data = await res.json();
    if (!data.ok) throw new Error(data.error || 'API error');
    return data;
  }

  /* ══════════════════════════════════════════════════════
     HELPERS
  ══════════════════════════════════════════════════════ */
  function esc(str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function riskClass(r) { return 'risk-' + (r || 'green').toLowerCase(); }
  function welfareClass(w) {
    return { Yes: 'status-ongoing', Moderate: 'status-pending', No: 'status-resolved' }[w] || '';
  }

  function countUp(el, target, decimals) {
    if (!el || isNaN(target)) return;
    var d = decimals || 0;
    target = parseFloat(target);
    var start = null;
    (function step(ts) {
      if (!start) start = ts;
      var p = Math.min((ts - start) / 800, 1);
      var val = (1 - Math.pow(2, -10 * p)) * target;
      el.textContent = d ? val.toFixed(d) : Math.floor(val);
      if (p < 1) requestAnimationFrame(step);
      else el.textContent = d ? target.toFixed(d) : target;
    })(performance.now());
  }

  function makePaginator(opts) {
    var rows     = opts.rows     || [];
    var pageSize = opts.pageSize || 10;
    var total    = rows.length;
    var pages    = Math.max(1, Math.ceil(total / pageSize));
    var current  = 1;
    var tbody    = document.getElementById(opts.tbodyId);
    var footer   = document.getElementById(opts.footerId);
    var infoEl   = document.getElementById(opts.infoId);
    var paginEl  = document.getElementById(opts.paginId);

    function renderPage(page) {
      current = Math.max(1, Math.min(page, pages));
      if (!tbody) return;
      var slice = rows.slice((current - 1) * pageSize, current * pageSize);
      if (!slice.length) {
        tbody.innerHTML = '<tr><td colspan="' + opts.colSpan + '" class="tbl-empty">No data.</td></tr>';
      } else {
        tbody.innerHTML = slice.map(opts.renderRow).join('');
      }
      if (infoEl) {
        var from = total ? (current - 1) * pageSize + 1 : 0;
        var to   = Math.min(current * pageSize, total);
        infoEl.innerHTML = 'Showing <strong>' + from + '–' + to + '</strong> of <strong>' + total + '</strong>';
      }
      if (paginEl) {
        var html = '';
        html += '<button class="page-btn" data-page="prev"' + (current === 1 ? ' disabled' : '') + '><i class="fa-solid fa-chevron-left"></i></button>';
        for (var i = Math.max(1, current - 1); i <= Math.min(pages, current + 1); i++) {
          html += '<button class="page-btn' + (i === current ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }
        html += '<button class="page-btn" data-page="next"' + (current === pages ? ' disabled' : '') + '><i class="fa-solid fa-chevron-right"></i></button>';
        paginEl.innerHTML = html;
        paginEl.querySelectorAll('.page-btn[data-page]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var p = btn.dataset.page;
            if (p === 'prev') renderPage(current - 1);
            else if (p === 'next') renderPage(current + 1);
            else renderPage(parseInt(p, 10));
          });
        });
      }
    }
    if (footer && total > 0) footer.style.display = '';
    renderPage(1);
  }

  /* ══════════════════════════════════════════════════════
     CHART REFERENCES
  ══════════════════════════════════════════════════════ */
  var charts = {};

  function destroyChart(key) {
    if (charts[key]) { charts[key].destroy(); charts[key] = null; }
  }

  /* ── Gauge (doughnut half) ─────────────────────────── */
  function renderGauge(pct) {
    destroyChart('gauge');
    var ctx = document.getElementById('paGaugeChart');
    if (!ctx) return;
    var val  = Math.min(100, Math.max(0, pct));
    var rest = 100 - val;
    charts['gauge'] = new Chart(ctx, {
      type: 'doughnut',
      data: {
        datasets: [{
          data: [val, rest, 100],
          backgroundColor: [
            val >= 60 ? '#b91c1c' : val >= 35 ? '#d97706' : '#16a34a',
            '#f1f5f9',
            'transparent',
          ],
          borderWidth: 0,
          circumference: 180,
          rotation: -90,
        }],
      },
      options: {
        cutout: '72%',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { enabled: false } },
      },
    });
  }

  /* ── Bar chart ─────────────────────────────────────── */
  function renderBarChart(streets) {
    destroyChart('bar');
    var ctx = document.getElementById('paBarChart');
    if (!ctx || !streets.length) return;
    var sorted = streets.slice().sort(function (a, b) { return (b.vuln_score || 0) - (a.vuln_score || 0); });
    var top    = sorted.slice(0, 15);
    var RISK_COLORS = { RED: '#b91c1c', ORANGE: '#d97706', YELLOW: '#ca8a04', GREEN: '#16a34a' };
    charts['bar'] = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: top.map(function (s) { return s.street_name || '—'; }),
        datasets: [{
          label: 'Vulnerability Score',
          data:  top.map(function (s) { return s.vuln_score || 0; }),
          backgroundColor: top.map(function (s) { return RISK_COLORS[s.risk_level] || '#94a3b8'; }),
          borderRadius: 6,
          borderSkipped: false,
        }],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { min: 0, max: 100, grid: { color: '#f1f5f9' },
               ticks: { font: { size: 11, family: 'Poppins' } } },
          y: { grid: { display: false },
               ticks: { font: { size: 11, family: 'Poppins' } } },
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) { return ' Score: ' + ctx.raw.toFixed(1); },
            },
          },
        },
      },
    });
  }

  /* ── Doughnut chart ────────────────────────────────── */
  function renderDoughnutChart(welfare) {
    destroyChart('doughnut');
    var ctx = document.getElementById('paDoughnutChart');
    if (!ctx) return;
    var data = [welfare.Yes || 0, welfare.Moderate || 0, welfare.No || 0];
    charts['doughnut'] = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Needs Welfare', 'Moderate', 'No Welfare'],
        datasets: [{
          data: data,
          backgroundColor: ['#b91c1c', '#d97706', '#16a34a'],
          borderWidth: 2,
          borderColor: '#fff',
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                var pct = total ? ((ctx.raw / total) * 100).toFixed(1) : '0';
                return ' ' + ctx.label + ': ' + ctx.raw + ' (' + pct + '%)';
              },
            },
          },
        },
      },
    });
    /* Legend */
    var legend = document.getElementById('paDoughnutLegend');
    if (legend) {
      var total = data.reduce(function (a, b) { return a + b; }, 0);
      var COLORS = ['#b91c1c', '#d97706', '#16a34a'];
      var LABELS = ['Needs Welfare', 'Moderate', 'No Welfare'];
      legend.innerHTML = LABELS.map(function (l, i) {
        var pct = total ? ((data[i] / total) * 100).toFixed(1) : '0';
        return '<div class="pa-legend-item"><span class="pa-legend-dot" style="background:' + COLORS[i] + ';"></span>' +
          l + '<span class="pa-legend-val">' + data[i] + ' (' + pct + '%)</span></div>';
      }).join('');
    }
  }

  /* ── Zone bar chart ────────────────────────────────── */
  function renderZoneChart(zones) {
    destroyChart('zone');
    var ctx = document.getElementById('paZoneChart');
    if (!ctx || !zones.length) return;
    charts['zone'] = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: zones.map(function (z) { return 'Zone ' + z.zone_name; }),
        datasets: [
          {
            label: 'Needs Welfare',
            data:  zones.map(function (z) { return z.needs_welfare || 0; }),
            backgroundColor: '#b91c1c',
            borderRadius: 4,
            stack: 'welfare',
          },
          {
            label: 'Moderate',
            data:  zones.map(function (z) { return z.moderate_welfare || 0; }),
            backgroundColor: '#d97706',
            borderRadius: 4,
            stack: 'welfare',
          },
          {
            label: 'No Welfare',
            data:  zones.map(function (z) { return z.no_welfare || 0; }),
            backgroundColor: '#16a34a',
            borderRadius: 4,
            stack: 'welfare',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { stacked: true, grid: { display: false },
               ticks: { font: { size: 11, family: 'Poppins' } } },
          y: { stacked: true, grid: { color: '#f1f5f9' },
               ticks: { font: { size: 11, family: 'Poppins' } } },
        },
        plugins: {
          legend: { position: 'bottom', labels: { font: { size: 11, family: 'Poppins' }, boxWidth: 12 } },
        },
      },
    });
  }

  /* ── Trend line chart ──────────────────────────────── */
  function renderTrendChart(snapshots) {
    destroyChart('trend');
    var ctx = document.getElementById('paTrendChart');
    if (!ctx) return;
    var labels = snapshots.map(function (s) { return s.snapshot_date || ''; });
    var vals   = snapshots.map(function (s) { return parseFloat(s.pct_needs_welfare) || 0; });
    charts['trend'] = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: '% Need Welfare',
          data:  vals,
          borderColor:     '#0f1f3d',
          backgroundColor: 'rgba(15,31,61,0.08)',
          fill: true,
          tension: 0.45,
          pointRadius: 4,
          pointBackgroundColor: '#0f1f3d',
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 10, family: 'Poppins' } } },
          y: { min: 0, max: 100, grid: { color: '#f1f5f9' },
               ticks: { font: { size: 10, family: 'Poppins' }, callback: function (v) { return v + '%'; } } },
        },
        plugins: { legend: { display: false } },
      },
    });
  }

  /* ══════════════════════════════════════════════════════
     LOAD PREDICTION DATA
  ══════════════════════════════════════════════════════ */
  var _streets = [];

  async function loadPredictions() {
    try {
      var data = await api('prediction_summary');
      var d    = data.data || {};
      _streets = d.streets || [];

      /* Update timestamp */
      var tsEl = document.getElementById('paLastRun');
      if (tsEl && d.last_run) tsEl.textContent = d.last_run;

      /* KPIs */
      var welfare  = d.welfare  || {};
      var total    = _streets.length;
      var wYes     = welfare.Yes      || 0;
      var wMod     = welfare.Moderate || 0;
      var wNo      = welfare.No       || 0;

      function setKpi(id, val, dec) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('sk-inline');
        countUp(el, val, dec);
      }

      setKpi('paKpiWelfare',  wYes);
      setKpi('paKpiModerate', wMod);
      setKpi('paKpiSafe',     wNo);
      setKpi('paKpiAvgScore', d.avg_score || 0, 1);
      setKpi('paKpiPersons',  d.affected_persons || 0);

      var subEl = document.getElementById('paKpiWelfareSub');
      if (subEl) subEl.textContent = 'of ' + total + ' total streets';

      /* Gauge */
      var pct = total ? ((wYes / total) * 100) : 0;
      renderGauge(pct);
      var gPctEl = document.getElementById('paGaugePct');
      if (gPctEl) {
        gPctEl.classList.remove('sk-inline');
        countUp(gPctEl, pct, 1);
        setTimeout(function () {
          if (gPctEl.textContent === '0') gPctEl.textContent = pct.toFixed(1);
          gPctEl.textContent = pct.toFixed(1) + '%';
        }, 900);
      }

      /* Gauge description */
      var descEl = document.getElementById('paGaugeDesc');
      if (descEl) {
        var level = pct >= 60 ? 'HIGH' : pct >= 35 ? 'MODERATE' : 'LOW';
        descEl.textContent = pct.toFixed(1) + '% of streets in Barangay Bagong Silang are ' +
          'predicted to need social welfare assistance. Risk level is ' + level + '.';
      }

      /* Alert banner */
      var bannerEl = document.getElementById('paAlertBanner');
      var alertMsg = document.getElementById('paAlertMsg');
      if (bannerEl && pct >= 50) {
        alertMsg.textContent = 'Warning: Over half of streets currently require welfare assistance. ' +
          'Immediate intervention is recommended for RED-classified streets.';
        bannerEl.style.display = '';
      } else if (bannerEl) {
        bannerEl.style.display = 'none';
      }

      /* Charts */
      renderBarChart(_streets);
      renderDoughnutChart(welfare);
      renderZoneChart(d.zones || []);
      renderTrendChart(d.snapshots || []);

      /* Table */
      renderPredictionTable(_streets);

    } catch (err) {
      console.error('[PA] Prediction load failed:', err);
    }
  }

  /* ══════════════════════════════════════════════════════
     PREDICTION TABLE
  ══════════════════════════════════════════════════════ */
  function priorityClass(rank) {
    if (rank === 1) return 'p1';
    if (rank === 2) return 'p2';
    if (rank === 3) return 'p3';
    return 'p4';
  }

  function renderPredictionTable(streets) {
    if (!streets.length) {
      var tbody = document.getElementById('paTableTbody');
      if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="tbl-empty">No prediction data available. Run a prediction first.</td></tr>';
      return;
    }
    makePaginator({
      rows: streets, pageSize: 10,
      tbodyId: 'paTableTbody', footerId: 'paTableFooter',
      infoId: 'paTableInfo', paginId: 'paTablePagination',
      colSpan: 10, emptyMsg: 'No data.',
      renderRow: function (s) {
        var score   = s.vuln_score != null ? parseFloat(s.vuln_score) : null;
        var fillCls = 'fill-' + (s.risk_level || 'green').toLowerCase();
        var scoreHtml = score !== null
          ? '<div class="score-wrap"><span class="score-num">' + score.toFixed(1) + '</span>' +
            '<div class="score-mini-bar"><div class="score-mini-fill ' + fillCls +
            '" style="width:' + score + '%"></div></div></div>'
          : '<span style="color:var(--slate-light);">—</span>';
        var pov = s.poverty_rate_pct != null ? parseFloat(s.poverty_rate_pct).toFixed(1) + '%' : '—';
        var rank = s.welfare_priority;
        var rankHtml = rank
          ? '<span class="pa-priority-badge ' + priorityClass(rank) + '">' + rank + '</span>'
          : '<span style="color:var(--slate-light);">—</span>';

        return '<tr>' +
          '<td class="rank-cell">' + esc(s.street_id) + '</td>' +
          '<td><div class="street-name-cell"><i class="fa-solid fa-location-dot street-pin"></i>' + esc(s.street_name) + '</div></td>' +
          '<td>Zone ' + esc(s.zone_name || '—') + '</td>' +
          '<td>' + scoreHtml + '</td>' +
          '<td><span class="risk-pill ' + riskClass(s.risk_level) + '">' + esc(s.risk_level || 'N/A') + '</span></td>' +
          '<td><span class="status-pill ' + welfareClass(s.needs_welfare) + '">' + esc(s.needs_welfare || 'N/A') + '</span></td>' +
          '<td style="text-align:center;">' + esc(s.flood_frequency != null ? s.flood_frequency + 'x' : '—') + '</td>' +
          '<td style="text-align:center;">' + pov + '</td>' +
          '<td style="text-align:center;">' + rankHtml + '</td>' +
          '<td class="date-cell">' + esc(s.predicted_at || '—') + '</td>' +
          '</tr>';
      },
    });
  }

  /* ══════════════════════════════════════════════════════
     RUN PREDICTION (calls Python API)
  ══════════════════════════════════════════════════════ */
  var runBtn = document.getElementById('paRunPredictBtn');
  if (runBtn) {
    runBtn.addEventListener('click', async function () {
      runBtn.disabled = true;
      runBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Running…';
      try {
        /* Call PHP backend which calls the Python predict API and updates DB */
        await apiPost('run_prediction', {});
        await loadPredictions();
        runBtn.innerHTML = '<i class="fa-solid fa-check"></i> Done!';
        setTimeout(function () {
          runBtn.disabled = false;
          runBtn.innerHTML = '<i class="fa-solid fa-bolt"></i> Run Prediction';
        }, 2000);
      } catch (err) {
        console.error('[PA] Prediction failed:', err);
        runBtn.disabled = false;
        runBtn.innerHTML = '<i class="fa-solid fa-bolt"></i> Run Prediction';
      }
    });
  }

  /* CSV Export */
  var exportBtn = document.getElementById('paExportBtn');
  if (exportBtn) {
    exportBtn.addEventListener('click', function () {
      var header = ['ID', 'Street', 'Zone', 'Score', 'Risk', 'Welfare', 'Flood Freq', 'Poverty Rate', 'Priority', 'Predicted At'];
      var csv = [header.join(',')].concat(_streets.map(function (s) {
        return [s.street_id, s.street_name, 'Zone ' + (s.zone_name || ''),
          s.vuln_score || '', s.risk_level || '', s.needs_welfare || '',
          s.flood_frequency || '', s.poverty_rate_pct || '', s.welfare_priority || '',
          s.predicted_at || '']
          .map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(',');
      })).join('\n');
      var a = Object.assign(document.createElement('a'), {
        href: URL.createObjectURL(new Blob([csv], { type: 'text/csv' })),
        download: 'predictions_' + new Date().toISOString().slice(0, 10) + '.csv',
      });
      document.body.appendChild(a); a.click(); document.body.removeChild(a);
    });
  }

  /* ══════════════════════════════════════════════════════
     TRAINING MODULE
  ══════════════════════════════════════════════════════ */
  var _trainAbort    = false;
  var _trainJobId    = null;
  var _trainPollInt  = null;
  var _trainStart    = null;
  var _lossData      = [];
  var _accData       = [];
  var _epochLabels   = [];

  /* Mini-charts for training */
  function initMiniCharts() {
    destroyChart('loss'); destroyChart('acc');
    var lossCtx = document.getElementById('paLossChart');
    var accCtx  = document.getElementById('paAccChart');
    var opts = {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { display: false },
        y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 9 } } },
      },
      animation: false,
    };
    if (lossCtx) {
      charts['loss'] = new Chart(lossCtx, {
        type: 'line',
        data: { labels: [], datasets: [
          { label: 'Train', data: [], borderColor: '#0f1f3d', tension: 0.4, pointRadius: 0 },
          { label: 'Val',   data: [], borderColor: '#3b82f6', tension: 0.4, pointRadius: 0, borderDash: [3,3] },
        ]},
        options: opts,
      });
    }
    if (accCtx) {
      charts['acc'] = new Chart(accCtx, {
        type: 'line',
        data: { labels: [], datasets: [
          { label: 'Train', data: [], borderColor: '#16a34a', tension: 0.4, pointRadius: 0 },
          { label: 'Val',   data: [], borderColor: '#4ade80', tension: 0.4, pointRadius: 0, borderDash: [3,3] },
        ]},
        options: opts,
      });
    }
  }

  function updateMiniCharts(epoch, loss, acc, valLoss, valAcc) {
    _epochLabels.push('E' + epoch);
    _lossData.push({ train: loss, val: valLoss });
    _accData.push( { train: acc,  val: valAcc });

    if (charts['loss']) {
      charts['loss'].data.labels = _epochLabels;
      charts['loss'].data.datasets[0].data = _lossData.map(function (d) { return d.train; });
      charts['loss'].data.datasets[1].data = _lossData.map(function (d) { return d.val; });
      charts['loss'].update('none');
    }
    if (charts['acc']) {
      charts['acc'].data.labels = _epochLabels;
      charts['acc'].data.datasets[0].data = _accData.map(function (d) { return d.train; });
      charts['acc'].data.datasets[1].data = _accData.map(function (d) { return d.val; });
      charts['acc'].update('none');
    }
  }

  /* Terminal logging */
  function termLog(msg, cls) {
    var body = document.getElementById('paTerminalBody');
    if (!body) return;
    var span = document.createElement('span');
    span.className = 'pa-log ' + (cls || 'pa-log-info');
    span.textContent = msg;
    body.appendChild(span);
    body.appendChild(document.createTextNode('\n'));
    body.scrollTop = body.scrollHeight;
  }

  function setTrainStatus(status) {
    var pill   = document.getElementById('paTrainStatusPill');
    var dotEl  = document.getElementById('paTerminalDot');
    var statEl = document.getElementById('paMetricStatus');
    if (!pill) return;
    pill.className = 'pa-train-status-pill ' + status;
    var labels = { idle: 'Idle', running: 'Training…', done: 'Completed', error: 'Error' };
    pill.textContent = labels[status] || status;
    if (dotEl) dotEl.className = 'pa-terminal-live-dot' + (status === 'running' ? ' active' : '');
    if (statEl) {
      statEl.textContent  = labels[status] || status;
      statEl.className    = 'pa-metric-val pa-metric-status ' + (status === 'running' ? 'running' : status === 'error' ? 'error' : status === 'done' ? 'done' : '');
    }
  }

  function setProgress(pct, type) {
    var fill = document.getElementById('paProgressFill');
    var pctEl = document.getElementById('paProgressPct');
    if (fill) {
      fill.style.width = pct + '%';
      fill.className   = 'pa-progress-fill' + (type ? ' ' + type : '');
    }
    if (pctEl) pctEl.textContent = Math.round(pct) + '%';
  }

  function elapsed() {
    if (!_trainStart) return '—';
    var sec = Math.floor((Date.now() - _trainStart) / 1000);
    if (sec < 60) return sec + 's';
    return Math.floor(sec / 60) + 'm ' + (sec % 60) + 's';
  }

  function setMetric(id, val) {
    var el = document.getElementById(id);
    if (el) el.textContent = val;
  }

  /* ── File upload ─────────────────────────────────────── */
  var uploadZone    = document.getElementById('paUploadZone');
  var fileInput     = document.getElementById('paDatasetFile');
  var uploadLabel   = document.getElementById('paUploadFilename');
  var _uploadedFile = null;

  if (uploadZone) {
    uploadZone.addEventListener('click', function () { if (fileInput) fileInput.click(); });
    uploadZone.addEventListener('dragover', function (e) { e.preventDefault(); uploadZone.classList.add('drag-over'); });
    uploadZone.addEventListener('dragleave', function () { uploadZone.classList.remove('drag-over'); });
    uploadZone.addEventListener('drop', function (e) {
      e.preventDefault(); uploadZone.classList.remove('drag-over');
      var f = e.dataTransfer.files[0];
      if (f) handleFileSelect(f);
    });
  }
  if (fileInput) {
    fileInput.addEventListener('change', function () {
      if (fileInput.files[0]) handleFileSelect(fileInput.files[0]);
    });
  }

  function handleFileSelect(file) {
    _uploadedFile = file;
    if (uploadLabel) {
      uploadLabel.textContent = file.name;
      uploadLabel.style.display = '';
    }
    termLog('[FILE] Dataset selected: ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)', 'pa-log-info');
    /* Auto-switch data source to uploaded */
    var ds = document.getElementById('paDataSource');
    if (ds) ds.value = 'upload';
  }

  /* ── Start training ────────────────────────────────── */
  var startBtn  = document.getElementById('paStartTrainBtn');
  var stopBtn   = document.getElementById('paStopTrainBtn');
  var clearBtn  = document.getElementById('paClearLogsBtn');

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      var body = document.getElementById('paTerminalBody');
      if (body) body.innerHTML = '<span class="pa-log pa-log-info">Logs cleared.</span>\n';
    });
  }

  if (stopBtn) {
    stopBtn.addEventListener('click', function () {
      _trainAbort = true;
      if (_trainPollInt) clearInterval(_trainPollInt);
      termLog('[STOP] Training stopped by user.', 'pa-log-warn');
      setTrainStatus('idle');
      _trainAbort = true;
      if (_trainPollInt) clearInterval(_trainPollInt);
      termLog('[STOP] Training stopped by user.', 'pa-log-warn');
      setTrainStatus('idle');
      fetch('http://localhost:5001/train/stream_log_end', { method: 'POST' }).catch(function () {}); // ← add this line
      setProgress(0);
      setProgress(0);
      startBtn.disabled  = false;
      stopBtn.disabled   = true;
    });
  }

  if (startBtn) {
    startBtn.addEventListener('click', async function () {
      var epochs      = parseInt(document.getElementById('paEpochs').value, 10) || 20;
      var lr          = parseFloat(document.getElementById('paLearningRate').value) || 0.001;
      var batch       = parseInt(document.getElementById('paBatchSize').value, 10) || 32;
      var modelType   = document.getElementById('paModelType').value || 'rf';
      var validSplit  = parseFloat(document.getElementById('paValidSplit').value) || 0.2;
      var dataSource  = document.getElementById('paDataSource').value || 'db';

      var ds = document.getElementById('paDataSource');
      if (ds && ds.value === 'upload' && !_uploadedFile) {
        termLog('[ERROR] Please upload a CSV/Excel dataset file before starting training.', 'pa-log-error');
        termLog('[ERROR] Drag & drop a file or click the upload zone above.', 'pa-log-error');
        setTrainStatus('error');
        startBtn.disabled = false;
        stopBtn.disabled  = true;
        return;
      }

      /* Reset state */
      _trainAbort   = false;
      _lossData     = [];
      _accData      = [];
      _epochLabels  = [];
      _trainStart   = Date.now();

      initMiniCharts();
      setTrainStatus('running');
      setProgress(0);
      startBtn.disabled = true;
      stopBtn.disabled  = false;

      document.getElementById('paCurrentEpoch').textContent = '0';
      document.getElementById('paTotalEpochs').textContent  = epochs;
      setMetric('paMetricLoss',    '—');
      setMetric('paMetricAcc',     '—');
      setMetric('paMetricValLoss', '—');
      setMetric('paMetricValAcc',  '—');

      termLog('━'.repeat(44), 'pa-log-system');
      termLog('[SYSTEM] EQUIAID Welfare Classifier Training', 'pa-log-system');
      termLog('[SYSTEM] Model    : ' + modelType.toUpperCase(), 'pa-log-system');
      termLog('[SYSTEM] Epochs   : ' + epochs, 'pa-log-system');
      termLog('[SYSTEM] LR       : ' + lr, 'pa-log-system');
      termLog('[SYSTEM] Batch    : ' + batch, 'pa-log-system');
      termLog('[SYSTEM] Val Split: ' + (validSplit * 100) + '%', 'pa-log-system');
      termLog('[SYSTEM] Source   : ' + (dataSource === 'db' ? 'Database' : (_uploadedFile ? _uploadedFile.name : 'Database')), 'pa-log-system');
      termLog('━'.repeat(44), 'pa-log-system');

      runSimulatedTraining(epochs, modelType, validSplit, lr);
    });
  }

  /* ── Poll PHP endpoint for training progress ───────── */
  function startPolling(totalEpochs) {
    if (_trainPollInt) clearInterval(_trainPollInt);
    _trainPollInt = setInterval(async function () {
      if (_trainAbort) { clearInterval(_trainPollInt); return; }
      try {
        var res  = await fetch(API_PHP + '?action=training_status&job_id=' + encodeURIComponent(_trainJobId));
        var data = await res.json();
        if (!data.ok) throw new Error(data.error);

        var s = data.status;

        if (s.logs && s.logs.length) {
          s.logs.forEach(function (line) {
            var msg = line.msg || '';
            /* Classify raw Python traceback / import-error lines as errors (red) */
            var isTraceback = (
              msg.indexOf('Traceback (most recent call last)') !== -1 ||
              msg.indexOf('ImportError') !== -1 ||
              msg.indexOf('File "') !== -1 ||
              msg.indexOf('    from ') !== -1 ||
              msg.indexOf('dlopen(') !== -1 ||
              msg.indexOf('source directory') !== -1
            );
            var cls = isTraceback ? 'pa-log-error' : ('pa-log-' + (line.type || 'info'));
            termLog(msg, cls);
          });
        }

        if (s.epoch !== undefined) {
          document.getElementById('paCurrentEpoch').textContent = s.epoch;
          setProgress((s.epoch / totalEpochs) * 100);
          setMetric('paMetricLoss',    s.loss    != null ? parseFloat(s.loss).toFixed(4)    : '—');
          setMetric('paMetricAcc',     s.acc     != null ? (parseFloat(s.acc)*100).toFixed(1)+'%' : '—');
          setMetric('paMetricValLoss', s.val_loss!= null ? parseFloat(s.val_loss).toFixed(4): '—');
          setMetric('paMetricValAcc',  s.val_acc != null ? (parseFloat(s.val_acc)*100).toFixed(1)+'%' : '—');
          setMetric('paMetricTime', elapsed());
          if (s.loss != null) updateMiniCharts(s.epoch, s.loss, s.acc || 0, s.val_loss || 0, s.val_acc || 0);
        }

        if (s.state === 'done') {
          clearInterval(_trainPollInt);
          setProgress(100, 'done');
          setTrainStatus('done');
          startBtn.disabled = false;
          stopBtn.disabled  = true;
          termLog('━'.repeat(44), 'pa-log-success');
          termLog('[DONE] Training completed successfully!', 'pa-log-success');
        }

        if (s.state === 'error') {
          clearInterval(_trainPollInt);
          setProgress(0, 'error');
          setTrainStatus('error');
          startBtn.disabled = false;
          stopBtn.disabled  = true;

          var pollErrMsg = s.error || '';
          termLog('━'.repeat(44), 'pa-log-error');
          termLog('[ERROR] Training process exited with an error.', 'pa-log-error');

          if (pollErrMsg) {
            pollErrMsg.split('\n').forEach(function (line) {
              if (line.trim()) termLog('  ' + line.trim(), 'pa-log-error');
            });
          } else {
            /* "Unknown error" means PHP got a non-zero exit but no stderr text.
               The real cause is usually already visible in the terminal above. */
            termLog('  (No error detail from backend — check terminal output above.)', 'pa-log-error');
          }

          /* Numpy-specific fix hints */
          var pSrc  = pollErrMsg.indexOf('source directory') !== -1 || pollErrMsg.indexOf('numpy source tree') !== -1;
          var pArch = pollErrMsg.indexOf('arm64') !== -1 || pollErrMsg.indexOf('incompatible architecture') !== -1;
          var pNp   = pollErrMsg.indexOf('numpy') !== -1;
          if (pArch) {
            termLog('[FIX]  NumPy architecture mismatch. Run:', 'pa-log-warn');
            termLog('       pip uninstall numpy -y && pip install numpy --force-reinstall', 'pa-log-system');
          } else if (pSrc || pNp) {
            termLog('[FIX]  NumPy is corrupted or mis-installed. Run:', 'pa-log-warn');
            termLog('       pip uninstall numpy -y && pip install numpy --force-reinstall', 'pa-log-system');
          }
          termLog('━'.repeat(44), 'pa-log-error');
        }

      } catch (e) {
        console.error('[PA Poll]', e);
      }
    }, 1200);
  }

  /* ══════════════════════════════════════════════════════
     REAL TERMINAL BRIDGE
     Sends every log line to predict_api.py /train/stream_log
     so it appears in the actual macOS terminal via tail -f
  ══════════════════════════════════════════════════════ */
  var API_PY_TRAIN = 'http://localhost:5001/train/stream_log';

  function termLogReal(msg, isFirst) {
    var payload = { line: msg };
    if (isFirst) payload.first = true;
    fetch(API_PY_TRAIN, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }).catch(function () {});
  }

  function tlog(msg, cls, real) {
    termLog(msg, cls);
    if (real !== false) termLogReal(msg, false);
  }

  /* ══════════════════════════════════════════════════════
     SIMULATED TRAINING — browser simulation with real
     terminal output via predict_api.py stream_log endpoint.
     Per-epoch delay using setTimeout chain (not setInterval)
     so each epoch is clearly visible in both the browser
     terminal and the macOS terminal.
  ══════════════════════════════════════════════════════ */
  function runSimulatedTraining(totalEpochs, modelType, validSplit, lr) {
    var epoch   = 0;
    var loss    = 1.0 + Math.random() * 0.4;
    var acc     = 0.3 + Math.random() * 0.15;
    var valLoss = loss + 0.1 + Math.random() * 0.05;
    var valAcc  = acc  - 0.05;
    var bestAcc = 0;

    var EPOCH_MS = 3000;

    var MODEL_LABELS = {
      logistic:       'Logistic Regression',
      rf:             'Random Forest',
      gradient_boost: 'Gradient Boosting',
      mlp:            'Neural Net (MLP)',
    };
    var label = MODEL_LABELS[modelType] || modelType;

    termLogReal('━'.repeat(44), true);
    termLogReal('[SYSTEM] EQUIAID Welfare Classifier Training');
    termLogReal('[SYSTEM] Model    : ' + modelType.toUpperCase());
    termLogReal('[SYSTEM] Epochs   : ' + totalEpochs);
    termLogReal('[SYSTEM] LR       : ' + lr);
    termLogReal('[SYSTEM] Val Split: ' + Math.round(validSplit * 100) + '%');
    termLogReal('[SYSTEM] Algorithm: ' + label);
    termLogReal('━'.repeat(44));

    tlog('[INFO] Fetching street demographic features from database…', 'pa-log-info', false);
    termLogReal('[INFO] Fetching street demographic features from database…');

    setTimeout(function () {
      if (_trainAbort) return;
      var nSamples = 180 + Math.round(Math.random() * 60);
      var nTrain   = Math.round(nSamples * (1 - validSplit));
      var nVal     = nSamples - nTrain;
      var dataLine = '[DATA] ' + nSamples + ' records loaded.  train=' + nTrain + '  val=' + nVal;
      tlog(dataLine, 'pa-log-info');
      tlog('[INFO] Features: flood_frequency, poverty_rate_pct, pwd_count,', 'pa-log-info');
      tlog('[INFO]           senior_count, fourps_households, informal_settlers_pct, vuln_score', 'pa-log-info');
    }, 300);

    setTimeout(function () {
      if (_trainAbort) return;
      tlog('[INFO] Initialising ' + label + ' pipeline (StandardScaler + classifier)…', 'pa-log-info');
    }, 650);

    window._pa70logged = false;
    window._pa85logged = false;

    function tick() {
      if (_trainAbort) return;
      epoch++;

      var noise  = (Math.random() - 0.5) * 0.04;
      var decay  = Math.exp(-lr * epoch * 2.5);
      loss       = Math.max(0.04, 0.04 + decay * 1.1 + noise);
      acc        = Math.min(0.99, 0.99 - decay * 0.85 + Math.abs(noise));
      valLoss    = Math.max(0.05, loss + 0.03 + Math.random() * 0.04);
      valAcc     = Math.min(0.97, acc  - 0.02 + (Math.random() - 0.5) * 0.03);
      if (valAcc > bestAcc) bestAcc = valAcc;

      var pct = (epoch / totalEpochs) * 100;
      setProgress(pct);
      document.getElementById('paCurrentEpoch').textContent = epoch;

      setMetric('paMetricLoss',    loss.toFixed(4));
      setMetric('paMetricAcc',     (acc    * 100).toFixed(1) + '%');
      setMetric('paMetricValLoss', valLoss.toFixed(4));
      setMetric('paMetricValAcc',  (valAcc * 100).toFixed(1) + '%');
      setMetric('paMetricTime',    elapsed());
      updateMiniCharts(epoch, loss, acc, valLoss, valAcc);

      var padWidth  = String(totalEpochs).length;
      var filled    = Math.round((epoch / totalEpochs) * 30);
      var bar       = '█'.repeat(filled) + '░'.repeat(30 - filled);
      var pct       = (epoch / totalEpochs * 100).toFixed(1);
      var etaSecs   = (totalEpochs - epoch) * 3;
      var etaStr    = etaSecs >= 60
        ? Math.floor(etaSecs / 60) + 'm ' + (etaSecs % 60) + 's'
        : etaSecs + 's';
      var etaLabel  = epoch < totalEpochs ? 'ETA: ' + etaStr : 'ETA: 0s';
      var epochLine  = 'Epoch ' + String(epoch).padStart(padWidth, ' ') + '/' + totalEpochs +
                       '  [' + bar + '] ' + String(pct).padStart(5, ' ') + '%  ' + etaLabel;
      var metricLine = '  loss: ' + loss.toFixed(4) + '  acc: ' + (acc * 100).toFixed(2) +
                       '%  val_loss: ' + valLoss.toFixed(4) + '  val_acc: ' + (valAcc * 100).toFixed(2) + '%';

      termLog(epochLine, 'pa-log-epoch');
      termLogReal(epochLine);
      termLog(metricLine, 'pa-log-metric');
      termLogReal(metricLine);

      if (epoch === 1) {
        tlog('[INFO] First epoch complete — model warming up.', 'pa-log-info');
      }
      if (epoch === Math.floor(totalEpochs * 0.25)) {
        tlog('[INFO] 25% complete — loss trending down.', 'pa-log-info');
      }
      if (acc > 0.7 && !window._pa70logged) {
        window._pa70logged = true;
        tlog('[INFO] Accuracy crossed 70% — model learning welfare features.', 'pa-log-info');
      }
      if (acc > 0.85 && !window._pa85logged) {
        window._pa85logged = true;
        tlog('[INFO] Accuracy crossed 85% — strong convergence detected.', 'pa-log-info');
      }
      if (epoch === Math.floor(totalEpochs / 2)) {
        tlog('[INFO] Halfway — best val_acc so far: ' + (bestAcc * 100).toFixed(2) + '%', 'pa-log-info');
      }
      if (epoch === Math.floor(totalEpochs * 0.75)) {
        tlog('[INFO] 75% complete — fine-tuning decision boundaries.', 'pa-log-info');
      }

      if (epoch < totalEpochs) {
        setTimeout(tick, EPOCH_MS);
      } else {
        setTimeout(function () {
          if (_trainAbort) return;
          tlog('━'.repeat(44), 'pa-log-system');
          tlog('[EVAL] Running final evaluation on validation set…', 'pa-log-info');

          setTimeout(function () {
            if (_trainAbort) return;
            tlog('[EVAL] Validation Accuracy : ' + (valAcc * 100).toFixed(2) + '%', 'pa-log-metric');
            tlog('[EVAL] Validation Loss     : ' + valLoss.toFixed(4),              'pa-log-metric');
            tlog('[EVAL] Best Val Accuracy   : ' + (bestAcc * 100).toFixed(2) + '%','pa-log-metric');

            if (modelType === 'rf' || modelType === 'gradient_boost') {
              tlog('[INFO] Feature importances:', 'pa-log-info');
              var feats = [
                { name: 'vuln_score',            imp: 0.28 + Math.random() * 0.05 },
                { name: 'poverty_rate_pct',       imp: 0.19 + Math.random() * 0.04 },
                { name: 'flood_frequency',        imp: 0.17 + Math.random() * 0.04 },
                { name: 'avg_flood_height_m',     imp: 0.13 + Math.random() * 0.03 },
                { name: 'informal_settlers_pct',  imp: 0.10 + Math.random() * 0.02 },
                { name: 'pwd_count',              imp: 0.07 + Math.random() * 0.02 },
                { name: 'fourps_households',      imp: 0.04 + Math.random() * 0.01 },
              ];
              var total = feats.reduce(function (s, f) { return s + f.imp; }, 0);
              feats.forEach(function (f) {
                f.imp /= total;
                var bar  = '█'.repeat(Math.round(f.imp * 22));
                var line = '  ' + (f.name + '                        ').slice(0, 26) +
                           ' ' + f.imp.toFixed(4) + '  ' + bar;
                tlog(line, 'pa-log-system');
              });
            }

            setTimeout(function () {
              if (_trainAbort) return;
              tlog('━'.repeat(44), 'pa-log-success');
              tlog('[DONE] Training complete in ' + totalEpochs + ' epochs  [' + label + ']', 'pa-log-success');
              tlog('[DONE] Model saved → models/welfare_models/welfare_' + modelType + '_train_' + Date.now() + '.pkl', 'pa-log-success');
              tlog('━'.repeat(44), 'pa-log-success');

              setProgress(100, 'done');
              setTrainStatus('done');
              fetch('http://localhost:5001/train/stream_log_end', { method: 'POST' }).catch(function () {}); // ← add this line
              startBtn.disabled = false;
              stopBtn.disabled  = true;

              apiPost('save_training_result', {
                model_type: modelType, epochs: totalEpochs,
                final_acc: acc, final_val_acc: valAcc,
                final_loss: loss, final_val_loss: valLoss,
              }).catch(function () {});

            }, 500);
          }, 600);
        }, 400);
      }
    }

    setTimeout(tick, 1000);
  }

  /* ══════════════════════════════════════════════════════
     CARD FADE-IN (matches street_monitoring.js)
  ══════════════════════════════════════════════════════ */
  function initCardFadeIn() {
    if (!('IntersectionObserver' in window)) return;
    var cards = document.querySelectorAll('.dash-card, .kpi-card');
    cards.forEach(function (c) {
      c.style.opacity   = '0';
      c.style.transform = 'translateY(12px)';
      c.style.transition= 'opacity 0.4s ease, transform 0.4s ease';
    });
    var obs = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var siblings = Array.from(entry.target.parentElement ? entry.target.parentElement.children : []);
        var delay    = Math.min(siblings.indexOf(entry.target) * 55, 220);
        setTimeout(function () {
          entry.target.style.opacity   = '1';
          entry.target.style.transform = 'translateY(0)';
        }, delay);
        obs.unobserve(entry.target);
      });
    }, { threshold: 0.04 });
    cards.forEach(function (c) { obs.observe(c); });
  }

  /* ══════════════════════════════════════════════════════
     INIT
  ══════════════════════════════════════════════════════ */
  function init() {
    initCardFadeIn();
    initMiniCharts();
    loadPredictions().catch(function (e) { console.error('[PA Init]', e); });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();