<?php

$current_page = 'prediction-analytics';
$page_title   = 'Prediction & Analytics — Barangay EQUIAID';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous" referrerpolicy="no-referrer">

    <link rel="stylesheet" href="../styles/admin_sidebar.css">
    <link rel="stylesheet" href="../styles/admin_dashboard.css">
    <link rel="stylesheet" href="../styles/street_monitoring.css">
    <link rel="stylesheet" href="../styles/prediction_analytics.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>

<div class="admin-shell">
    <?php include 'sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="dashboard-container">

            <!-- ── PAGE HEADER ─────────────────────────── -->
            <div class="dash-header">
                <div class="dash-header-left">
                    <div class="dash-breadcrumb">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>Prediction & Analytics</span>
                    </div>
                    <h1 class="dash-title">Prediction & Analytics</h1>
                    <p class="dash-desc">
                        AI-powered welfare need forecasting, street-level risk scoring, and
                        model training dashboard for Barangay Bagong Silang.
                    </p>
                </div>
                <div class="dash-header-right">
                    <div class="dash-meta">
                        <span class="dash-meta-label">Last run</span>
                        <span class="dash-meta-value" id="paLastRun">—</span>
                    </div>
                    <button class="btn-sm-primary" id="paRunPredictBtn">
                        <i class="fa-solid fa-bolt"></i> Run Prediction
                    </button>
                </div>
            </div>

            <!-- ══════════════════════════════════════════
                 SECTION 1 — PREDICTION SUMMARY
            ══════════════════════════════════════════ -->
            <div class="pa-section-label">
                <i class="fa-solid fa-circle-dot"></i> Prediction Summary
            </div>

            <!-- Big welfare-need gauge row -->
            <div class="pa-summary-strip" id="paSummaryStrip">
                <!-- Gauge card -->
                <div class="pa-gauge-card dash-card">
                    <div class="card-label">Welfare Need Index</div>
                    <div class="pa-gauge-wrap">
                        <canvas id="paGaugeChart" width="220" height="130"></canvas>
                        <div class="pa-gauge-center">
                            <span class="pa-gauge-pct sk-inline" id="paGaugePct">—</span>
                            <span class="pa-gauge-label">need welfare</span>
                        </div>
                    </div>
                    <p class="pa-gauge-desc" id="paGaugeDesc">
                        Loading prediction data…
                    </p>
                </div>

                <!-- KPI mini-strip -->
                <div class="pa-kpi-strip">
                    <div class="kpi-card pa-kpi-sm">
                        <div class="kpi-icon-wrap" style="background:#b91c1c;">
                            <i class="fa-solid fa-circle-exclamation" style="color:#fff;font-size:14px;"></i>
                        </div>
                        <div class="kpi-body">
                            <span class="kpi-label">Streets Needing Welfare</span>
                            <span class="kpi-number sk-inline" id="paKpiWelfare" style="color:#b91c1c;">—</span>
                            <span class="kpi-sub" id="paKpiWelfareSub">of total streets</span>
                        </div>
                    </div>
                    <div class="kpi-card pa-kpi-sm">
                        <div class="kpi-icon-wrap" style="background:#d97706;">
                            <i class="fa-solid fa-triangle-exclamation" style="color:#fff;font-size:14px;"></i>
                        </div>
                        <div class="kpi-body">
                            <span class="kpi-label">Moderate Welfare Need</span>
                            <span class="kpi-number sk-inline" id="paKpiModerate" style="color:#d97706;">—</span>
                            <span class="kpi-sub">streets flagged moderate</span>
                        </div>
                    </div>
                    <div class="kpi-card pa-kpi-sm">
                        <div class="kpi-icon-wrap" style="background:#16a34a;">
                            <i class="fa-solid fa-circle-check" style="color:#fff;font-size:14px;"></i>
                        </div>
                        <div class="kpi-body">
                            <span class="kpi-label">Low / No Welfare Need</span>
                            <span class="kpi-number sk-inline" id="paKpiSafe" style="color:#16a34a;">—</span>
                            <span class="kpi-sub">streets not flagged</span>
                        </div>
                    </div>
                    <div class="kpi-card pa-kpi-sm">
                        <div class="kpi-icon-wrap dark">
                            <i class="fa-solid fa-road"></i>
                        </div>
                        <div class="kpi-body">
                            <span class="kpi-label">Avg Vulnerability Score</span>
                            <span class="kpi-number sk-inline" id="paKpiAvgScore">—</span>
                            <span class="kpi-sub">mean score / 100</span>
                        </div>
                    </div>
                    <div class="kpi-card pa-kpi-sm">
                        <div class="kpi-icon-wrap" style="background:#0284c7;">
                            <i class="fa-solid fa-people-group" style="color:#fff;font-size:14px;"></i>
                        </div>
                        <div class="kpi-body">
                            <span class="kpi-label">Affected Persons Est.</span>
                            <span class="kpi-number sk-inline" id="paKpiPersons" style="color:#0284c7;">—</span>
                            <span class="kpi-sub">in welfare-need streets</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prediction alert banner -->
            <div class="pa-alert-banner" id="paAlertBanner" style="display:none;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span id="paAlertMsg"></span>
            </div>

            <!-- ══════════════════════════════════════════
                 SECTION 2 — DATA VISUALIZATION
            ══════════════════════════════════════════ -->
            <div class="pa-section-label" style="margin-top:28px;">
                <i class="fa-solid fa-chart-bar"></i> Data Visualization
            </div>

            <div class="pa-charts-row">
                <!-- Bar chart: vulnerability score per street -->
                <div class="dash-card pa-chart-card">
                    <div class="card-head">
                        <div>
                            <div class="card-label">Per-Street Scores</div>
                            <h2 class="card-title">Vulnerability Score by Street</h2>
                        </div>
                        <div class="pa-chart-legend" id="paBarLegend"></div>
                    </div>
                    <div class="pa-chart-wrap">
                        <canvas id="paBarChart"></canvas>
                    </div>
                </div>

                <!-- Doughnut chart: welfare breakdown -->
                <div class="dash-card pa-chart-card pa-chart-card--sm">
                    <div class="card-head">
                        <div>
                            <div class="card-label">Welfare Classification</div>
                            <h2 class="card-title">Welfare Distribution</h2>
                        </div>
                    </div>
                    <div class="pa-chart-wrap pa-doughnut-wrap">
                        <canvas id="paDoughnutChart"></canvas>
                    </div>
                    <div class="pa-doughnut-legend" id="paDoughnutLegend"></div>
                </div>
            </div>

            <!-- Zone comparison + trend line -->
            <div class="pa-charts-row" style="margin-top:0;">
                <!-- Zone bar -->
                <div class="dash-card pa-chart-card">
                    <div class="card-head">
                        <div>
                            <div class="card-label">Zone Breakdown</div>
                            <h2 class="card-title">Risk & Welfare by Zone</h2>
                        </div>
                    </div>
                    <div class="pa-chart-wrap">
                        <canvas id="paZoneChart"></canvas>
                    </div>
                </div>

                <!-- Score trend line (from analytics_snapshots) -->
                <div class="dash-card pa-chart-card pa-chart-card--sm">
                    <div class="card-head">
                        <div>
                            <div class="card-label">Historical Snapshots</div>
                            <h2 class="card-title">Welfare Need Trend</h2>
                        </div>
                    </div>
                    <div class="pa-chart-wrap">
                        <canvas id="paTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Per-street breakdown table -->
            <div class="dash-card">
                <div class="card-head">
                    <div>
                        <div class="card-label">Predicted Results</div>
                        <h2 class="card-title">Street-Level Prediction Breakdown</h2>
                    </div>
                    <button class="btn-dash-outline" id="paExportBtn">
                        <i class="fa-solid fa-file-export"></i> Export CSV
                    </button>
                </div>
                <div class="table-wrap" style="margin-top:16px;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Street Name</th>
                                <th>Zone</th>
                                <th>Vuln. Score</th>
                                <th>Risk Level</th>
                                <th>Welfare Need</th>
                                <th>Flood Freq.</th>
                                <th>Poverty Rate</th>
                                <th>Priority</th>
                                <th>Last Predicted</th>
                            </tr>
                        </thead>
                        <tbody id="paTableTbody">
                            <tr><td colspan="10" class="tbl-loading"><span class="sk-inline">Loading…</span></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer" id="paTableFooter" style="display:none;">
                    <span class="table-info" id="paTableInfo"></span>
                    <div class="pagination" id="paTablePagination"></div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════
                 SECTION 3 — TRAIN MODEL
            ══════════════════════════════════════════ -->
            <div class="pa-section-label" style="margin-top:28px;">
                <i class="fa-solid fa-microchip"></i> Model Training Panel
                <span class="pa-section-badge">Admin Only</span>
            </div>

            <div class="pa-train-layout">

                <!-- Upload + Config Panel -->
                <div class="dash-card pa-train-config">
                    <div class="card-head">
                        <div>
                            <div class="card-label">Training Configuration</div>
                            <h2 class="card-title">Train Welfare Classifier</h2>
                        </div>
                        <span class="pa-train-status-pill" id="paTrainStatusPill">Idle</span>
                    </div>

                    <!-- Dataset upload -->
                    <div class="pa-upload-zone" id="paUploadZone">
                        <input type="file" id="paDatasetFile" accept=".csv,.xlsx,.json" style="display:none;">
                        <div class="pa-upload-icon">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                        </div>
                        <div class="pa-upload-label">
                            <strong>Upload Training Dataset</strong>
                            <span>CSV, XLSX or JSON — drag & drop or click</span>
                        </div>
                        <div class="pa-upload-filename" id="paUploadFilename" style="display:none;"></div>
                    </div>

                    <!-- Hyperparams -->
                    <div class="pa-param-grid">
                        <div class="pa-param-row">
                            <label class="pa-param-label" for="paEpochs">Epochs</label>
                            <input type="number" class="sm-input pa-param-input" id="paEpochs" value="20" min="1" max="200">
                        </div>
                        <div class="pa-param-row">
                            <label class="pa-param-label" for="paLearningRate">Learning Rate</label>
                            <input type="number" class="sm-input pa-param-input" id="paLearningRate" value="0.001" min="0.0001" max="0.1" step="0.0001">
                        </div>
                        <div class="pa-param-row">
                            <label class="pa-param-label" for="paBatchSize">Batch Size</label>
                            <select class="sm-select pa-param-input" id="paBatchSize">
                                <option value="16">16</option>
                                <option value="32" selected>32</option>
                                <option value="64">64</option>
                            </select>
                        </div>
                        <div class="pa-param-row">
                            <label class="pa-param-label" for="paModelType">Model Architecture</label>
                            <select class="sm-select pa-param-input" id="paModelType">
                                <option value="logistic">Logistic Regression</option>
                                <option value="rf" selected>Random Forest</option>
                                <option value="gradient_boost">Gradient Boosting</option>
                                <option value="mlp">Neural Net (MLP)</option>
                            </select>
                        </div>
                        <div class="pa-param-row">
                            <label class="pa-param-label" for="paValidSplit">Validation Split</label>
                            <select class="sm-select pa-param-input" id="paValidSplit">
                                <option value="0.1">10%</option>
                                <option value="0.2" selected>20%</option>
                                <option value="0.3">30%</option>
                            </select>
                        </div>
                        <div class="pa-param-row">
                            <label class="pa-param-label" for="paDataSource">Data Source</label>
                            <select class="sm-select pa-param-input" id="paDataSource">
                                <option value="db">Current Database</option>
                                <option value="upload">Uploaded File</option>
                            </select>
                        </div>
                    </div>

                    <div class="pa-train-actions">
                        <button class="btn-sm-primary" id="paStartTrainBtn">
                            <i class="fa-solid fa-play"></i> Start Training
                        </button>
                        <button class="btn-dash-outline" id="paStopTrainBtn" disabled>
                            <i class="fa-solid fa-stop"></i> Stop
                        </button>
                        <button class="btn-dash-outline" id="paClearLogsBtn">
                            <i class="fa-solid fa-eraser"></i> Clear Logs
                        </button>
                    </div>
                </div>

                <!-- Training progress + live logs -->
                <div class="dash-card pa-train-monitor">
                    <div class="card-head">
                        <div>
                            <div class="card-label">Live Training Monitor</div>
                            <h2 class="card-title">Training Progress</h2>
                        </div>
                        <div class="pa-epoch-display">
                            Epoch <span id="paCurrentEpoch">0</span> / <span id="paTotalEpochs">—</span>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div class="pa-progress-wrap">
                        <div class="pa-progress-track">
                            <div class="pa-progress-fill" id="paProgressFill" style="width:0%;"></div>
                        </div>
                        <span class="pa-progress-pct" id="paProgressPct">0%</span>
                    </div>

                    <!-- Live metrics row -->
                    <div class="pa-metrics-row">
                        <div class="pa-metric-box">
                            <span class="pa-metric-label">Train Loss</span>
                            <span class="pa-metric-val" id="paMetricLoss">—</span>
                        </div>
                        <div class="pa-metric-box">
                            <span class="pa-metric-label">Train Accuracy</span>
                            <span class="pa-metric-val" id="paMetricAcc">—</span>
                        </div>
                        <div class="pa-metric-box">
                            <span class="pa-metric-label">Val Loss</span>
                            <span class="pa-metric-val" id="paMetricValLoss">—</span>
                        </div>
                        <div class="pa-metric-box">
                            <span class="pa-metric-label">Val Accuracy</span>
                            <span class="pa-metric-val" id="paMetricValAcc">—</span>
                        </div>
                        <div class="pa-metric-box">
                            <span class="pa-metric-label">Elapsed</span>
                            <span class="pa-metric-val" id="paMetricTime">—</span>
                        </div>
                        <div class="pa-metric-box">
                            <span class="pa-metric-label">Status</span>
                            <span class="pa-metric-val pa-metric-status" id="paMetricStatus">Idle</span>
                        </div>
                    </div>

                    <!-- Loss + Accuracy mini-charts -->
                    <div class="pa-minichart-row">
                        <div class="pa-minichart-wrap">
                            <span class="pa-minichart-label">Loss Curve</span>
                            <canvas id="paLossChart" height="80"></canvas>
                        </div>
                        <div class="pa-minichart-wrap">
                            <span class="pa-minichart-label">Accuracy Curve</span>
                            <canvas id="paAccChart" height="80"></canvas>
                        </div>
                    </div>

                    <!-- Terminal-style logs -->
                    <div class="pa-terminal" id="paTerminal">
                        <div class="pa-terminal-header">
                            <span><i class="fa-solid fa-terminal"></i> Training Output</span>
                            <span class="pa-terminal-live-dot" id="paTerminalDot"></span>
                        </div>
                        <div class="pa-terminal-body" id="paTerminalBody">
                            <span class="pa-log pa-log-info">EQUIAID Model Trainer v1.0 — ready.</span><br>
                            <span class="pa-log pa-log-info">Select configuration and press Start Training.</span>
                        </div>
                    </div>
                </div>

            </div><!-- /.pa-train-layout -->

        </div><!-- /.dashboard-container -->
    </main>
</div><!-- /.admin-shell -->

<script src="../js/prediction_analytics.js"></script>
</body>
</html>