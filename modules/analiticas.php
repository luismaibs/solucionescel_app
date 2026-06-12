<?php
include '../config/auth.php';
requireLogin();
requireAdmin();
include '../config/db.php';
include_once '../includes/fragment_helper.php';
$data = (new AnaliticasService(new AnaliticasRepository($supabase), new SoporteRepository($supabase)))->obtenerDatosPanel();

$topMarcasLabel  = json_encode(array_column($data['topMarcas'], 'equipo_marca'));
$topMarcasData   = json_encode(array_column($data['topMarcas'], 'total'));
$tendenciaLabels = json_encode($data['tendenciaLabels']);
$tendenciaData   = json_encode(array_column($data['tendenciaReparaciones'], 'total'));
$invCatLabels    = json_encode(array_column($data['catInventario'], 'categoria'));
$invCatData      = json_encode(array_column($data['catInventario'], 'total_stock'));
$soporteLabels   = json_encode(array_column($data['trendSoporte'], 'dia'));
$soporteData     = json_encode(array_column($data['trendSoporte'], 'total'));
?>
<?php if (!$isFragment): ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analíticas | SOLUCIONESCEL</title>
    <?php include '../includes/head_meta.php'; ?>
    <link rel="stylesheet" href="../assets/css/analiticas.css" data-module-css="analiticas">
</head>

<body>

    <!-- NAVBAR -->
    <!-- NAVBAR UNIFICADO -->
    <?php include '../includes/header.php'; ?>
<?php else: ?>
    <link rel="stylesheet" href="<?= $fragment_asset_base ?>assets/css/analiticas.css" data-module-css="analiticas">
<?php endif; ?>
    <!-- Chart.js (lazy: carga dinámica sin bloquear parsing) -->
    <script>
    (function(){
        if(window.Chart) return;
        var s=document.createElement('script');
        s.src='https://cdn.jsdelivr.net/npm/chart.js';
        document.head.appendChild(s);
    })();
    </script>

    <div class="container-xl main-content-push with-subheader pb-5" style="max-width: 1400px;">

        <!-- Subheader: título + KPI chips + fecha -->
        <div class="module-subheader">
            <div class="module-subheader-kpis">
                <span class="module-subheader-title">Tablero de Control</span>
                <span class="module-kpi-chip">
                    <i class="bi bi-phone kpi-icon text-primary"></i>
                    <span class="kpi-value"><?= number_format($data['totalActivos']) ?></span>
                    <span class="kpi-label">En Taller</span>
                </span>
                <span class="module-kpi-chip">
                    <i class="bi bi-lightning-charge-fill kpi-icon text-warning"></i>
                    <span class="kpi-value"><?= $data['tasaExito'] ?>%</span>
                    <span class="kpi-label">Efectividad</span>
                </span>
                <span class="module-kpi-chip">
                    <i class="bi bi-currency-dollar kpi-icon text-success"></i>
                    <span class="kpi-value">$<?= number_format($data['valorInventario'], 0) ?></span>
                    <span class="kpi-label">Inventario</span>
                </span>
                <span class="module-kpi-chip">
                    <i class="bi bi-headset kpi-icon text-info"></i>
                    <span class="kpi-value"><?= number_format($data['totalSoporte']) ?></span>
                    <span class="kpi-label">Soporte</span>
                </span>
            </div>
            <div class="module-subheader-actions">
                <a href="asistente_ia" class="btn btn-outline-light" style="border-color: rgba(255,255,255,0.2);">
                    <i class="bi bi-stars"></i> Asistente IA
                </a>
            </div>
        </div>

        <!-- ROW 2: Gráficos Principales -->
        <div class="row g-4 mb-4">
            <!-- Tendencia de Ingresos (Línea) -->
            <div class="col-lg-8">
                <div class="glass-card">
                    <h5 class="fw-bold mb-4">Tendencia de Ingresos (Semestral)</h5>
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="chartTrend"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Marcas (Dona) -->
            <div class="col-lg-4">
                <div class="glass-card">
                    <h5 class="fw-bold mb-4">Top Marcas Recibidas</h5>
                    <div class="chart-container" style="height: 220px; position: relative;">
                        <canvas id="chartMarcas"></canvas>
                        <div class="position-absolute top-50 start-50 translate-middle text-center pointer-events-none">
                            <div class="h4 fw-bold m-0 text-white"><?= $data['topMarcas'][0]['total'] ?? 0 ?></div>
                            <small class="text-muted text-uppercase" style="font-size: 0.6rem;">Líder</small>
                        </div>
                    </div>
                    <div class="mt-4 text-center">
                        <small class="text-muted">Marca dominante: <strong
                                class="text-primary"><?= $data['topMarcas'][0]['equipo_marca'] ?? 'N/A' ?></strong></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- ROW 3: Chart Soporte -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="glass-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold m-0">Interacciones de Soporte (7 Días)</h5>
                        <a href="soporte" class="btn btn-sm btn-outline-info rounded-pill px-3">Ir a Soporte</a>
                    </div>
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="chartSoporte"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ROW 4: Detalles Específicos -->
        <div class="row g-4">
            <!-- Top Modelos (Lista) -->
            <div class="col-md-4">
                <div class="glass-card h-100">
                    <h5 class="fw-bold mb-4">🏆 Modelos Más Frecuentes</h5>
                    <div class="d-flex flex-column gap-1">
                        <?php foreach ($data['topModelos'] as $idx => $mod):
                            $percent = ($data['totalHist'] > 0) ? round(($mod['total'] / $data['totalHist']) * 100) : 0;
                            ?>
                            <div class="rank-item">
                                <div class="d-flex align-items-center">
                                    <span class="rank-number"><?= $idx + 1 ?></span>
                                    <div>
                                        <div class="fw-bold text-white"><?= htmlspecialchars($mod['equipo_modelo']) ?>
                                        </div>
                                        <div class="small text-muted"><?= htmlspecialchars($mod['equipo_marca']) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold fs-5 text-white"><?= $mod['total'] ?></div>
                                    <small class="text-muted"><?= $percent ?>%</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Inventario Categorías (Barras Horizontal) -->
            <div class="col-md-4">
                <div class="glass-card h-100">
                    <h5 class="fw-bold mb-3">📦 Inventario por Categoría</h5>
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="chartInvCat"></canvas>
                    </div>
                </div>
            </div>

            <!-- Subcategorías (Refacciones) -->
            <div class="col-md-4">
                <div class="glass-card h-100">
                    <h5 class="fw-bold mb-4">🔧 Refacciones (Stock)</h5>
                    <div class="row g-2">
                        <?php foreach ($data['subCatInventario'] as $sub):
                            if (empty($sub['subcategoria']))
                                continue;
                            ?>
                            <div class="col-6">
                                <div class="p-3 rounded-3 h-100"
                                    style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                                    <div class="text-muted small text-uppercase text-truncate mb-1">
                                        <?= htmlspecialchars($sub['subcategoria']) ?>
                                    </div>
                                    <div class="fw-bold fs-4 text-info"><?= $sub['total'] ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // CONFIGURACIÓN GLOBAL CHART.JS
    (function () {
        'use strict';

        function initCharts() {
            if (!window.Chart) {
                setTimeout(initCharts, 50);
                return;
            }
            if (!document.getElementById('chartTrend')) return;

        Chart.defaults.color = '#94a3b8';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
        Chart.defaults.font.family = "'Inter', sans-serif";

        // DATOS DESDE PHP
        const marcasLabels = <?= $topMarcasLabel ?>;
        const marcasData = <?= $topMarcasData ?>;

        const trendLabels = <?= $tendenciaLabels ?>;
        const trendData = <?= $tendenciaData ?>;

        const invCatLabels = <?= $invCatLabels ?>;
        const invCatData = <?= $invCatData ?>;

        const soporteLabels = <?= $soporteLabels ?>;
        const soporteData = <?= $soporteData ?>;

        // 1. GRÁFICO DE TENDENCIA (LINE AREA)
        const ctxTrend = document.getElementById('chartTrend').getContext('2d');
        const gradientTrend = ctxTrend.createLinearGradient(0, 0, 0, 400);
        gradientTrend.addColorStop(0, 'rgba(59, 130, 246, 0.5)');
        gradientTrend.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

        new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Equipos Ingresados',
                    data: trendData,
                    borderColor: '#3b82f6',
                    backgroundColor: gradientTrend,
                    borderWidth: 3,
                    pointBackgroundColor: '#1e293b',
                    pointBorderColor: '#3b82f6',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#cbd5e1',
                        borderColor: 'rgba(255,255,255,0.1)',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            label: function (context) {
                                return context.parsed.y + ' Equipos';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [4, 4] }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });

        // 2. GRÁFICO MARCAS (DOUGHNUT)
        const ctxMarcas = document.getElementById('chartMarcas').getContext('2d');
        new Chart(ctxMarcas, {
            type: 'doughnut',
            data: {
                labels: marcasLabels,
                datasets: [{
                    data: marcasData,
                    backgroundColor: [
                        '#3b82f6',
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#8b5cf6',
                        '#64748b'
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { boxWidth: 12, padding: 15, font: { size: 11 } }
                    }
                }
            }
        });

        // 3. GRÁFICO INVENTARIO (BAR HORIZONTAL)
        const ctxInv = document.getElementById('chartInvCat').getContext('2d');
        new Chart(ctxInv, {
            type: 'bar',
            data: {
                labels: invCatLabels,
                datasets: [{
                    label: 'Stock',
                    data: invCatData,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderRadius: 4,
                    barThickness: 20
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
                    y: { grid: { display: false } }
                }
            }
        });

        // 4. GRÁFICO SOPORTE (BAR REDUCIDO)
        const ctxSupport = document.getElementById('chartSoporte').getContext('2d');
        new Chart(ctxSupport, {
            type: 'bar',
            data: {
                labels: soporteLabels,
                datasets: [{
                    label: 'Solicitudes',
                    data: soporteData,
                    backgroundColor: 'rgba(6, 182, 212, 0.7)', // Cyan 500
                    borderRadius: 6,
                    barThickness: 30
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#cbd5e1',
                        borderColor: 'rgba(255,255,255,0.1)',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [4, 4] }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
        }

        onModuleReady(initCharts);
    })();
    </script>
<?php if (!$isFragment): ?>
</main>
</body>

</html>
<?php endif; ?>
