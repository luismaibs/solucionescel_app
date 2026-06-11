<?php
include '../config/auth.php';
requireLogin();
include '../config/db.php';
include_once '../includes/fragment_helper.php';

$clienteId = (int) ($_GET['id'] ?? 0);
if ($clienteId <= 0) {
    header('Location: clientes');
    exit;
}
?>
<?php if (!$isFragment): ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cliente 360° | SOLUCIONESCEL</title>
    <?php include '../includes/head_meta.php'; ?>
<?php endif; ?>
    <style data-module-css="cliente_360">
        body { background-color: var(--bg-app); font-family: 'Inter', sans-serif; color: var(--text-main); min-height: 100vh; background-image: radial-gradient(circle at top right, #1e293b 0%, #0f172a 40%); }
        .glass-card { background: var(--glass-bg); backdrop-filter: blur(12px); border: 1px solid var(--glass-border); border-radius: var(--radius-xl); box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        .info-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; margin-bottom: 0.25rem; }
        .info-value { font-size: 0.95rem; color: #e2e8f0; font-weight: 500; }
        .timeline-wrap { position: relative; padding-left: 2rem; }
        .timeline-wrap::before { content: ''; position: absolute; left: 12px; top: 0; bottom: 0; width: 2px; background: rgba(255,255,255,0.06); }
        .timeline-item { position: relative; padding-bottom: 1.5rem; }
        .timeline-item:last-child { padding-bottom: 0; }
        .timeline-dot { position: absolute; left: -2rem; top: 2px; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; z-index: 1; }
        .timeline-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 1rem; transition: all 0.15s; }
        .timeline-card:hover { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.1); }
        .stat-mini { text-align: center; padding: 1rem; border-radius: 14px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); }
        .stat-mini h4 { font-size: 1.5rem; font-weight: 700; margin: 0; }
        .stat-mini small { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.03em; color: #94a3b8; font-weight: 600; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .back-link { color: #94a3b8; text-decoration: none; transition: color 0.15s; font-size: 0.9rem; }
        .back-link:hover { color: #e2e8f0; }
        .equipo-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 14px; padding: 1rem; transition: all 0.2s; cursor: pointer; text-decoration: none; display: block; color: inherit; }
        .equipo-card:hover { background: rgba(59,130,246,0.08); border-color: rgba(59,130,246,0.2); transform: translateY(-2px); color: inherit; }
        .badge-estado { padding: 0.3rem 0.65rem; border-radius: 50px; font-size: 0.72rem; font-weight: 600; }
        .avatar-big { width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #6366f1); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 700; color: white; flex-shrink: 0; }
    </style>
<?php if (!$isFragment): ?>
</head>

<body>
    <?php include '../includes/header.php'; ?>
<?php endif; ?>

    <div class="container-xl main-content-push" style="max-width: 1200px;">
        <div class="mb-4">
            <a href="clientes" class="back-link"><i class="bi bi-arrow-left me-1"></i>Volver a Clientes</a>
        </div>

        <div id="loadingState" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted small mt-3">Cargando datos del cliente...</p>
        </div>

        <div id="mainContent" class="d-none">
            <!-- Header del cliente -->
            <div class="glass-card p-4 mb-4">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="avatar-big" id="headerAvatar"></div>
                    </div>
                    <div class="col">
                        <h3 class="fw-bold text-white m-0" id="headerNombre"></h3>
                        <div class="d-flex flex-wrap gap-3 mt-2 text-muted small">
                            <span><i class="bi bi-whatsapp me-1 text-success"></i><span id="headerTelefono"></span></span>
                            <span id="headerCorreoWrap" class="d-none"><i class="bi bi-envelope me-1"></i><span id="headerCorreo"></span></span>
                            <span><i class="bi bi-calendar3 me-1"></i>Cliente desde <span id="headerFechaRegistro"></span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPIs -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="stat-mini">
                        <h4 class="text-primary" id="kpiTotal">0</h4>
                        <small>Equipos Total</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-mini">
                        <h4 class="text-success" id="kpiCompletadas">0</h4>
                        <small>Completadas</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-mini">
                        <h4 class="text-warning" id="kpiEnProceso">0</h4>
                        <small>En Proceso</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-mini">
                        <h4 class="text-info" id="kpiGarantia">0</h4>
                        <small>Con Garantía</small>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Columna izquierda: Equipos del cliente -->
                <div class="col-lg-4">
                    <div class="glass-card p-4">
                        <h6 class="fw-bold text-white mb-3"><i class="bi bi-phone me-2 text-primary"></i>Equipos del Cliente</h6>
                        <div id="equiposContainer">
                            <div class="text-muted small text-center py-3">Cargando...</div>
                        </div>
                    </div>
                </div>

                <!-- Columna derecha: Timeline completo -->
                <div class="col-lg-8">
                    <div class="glass-card p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h6 class="fw-bold text-white m-0"><i class="bi bi-clock-history me-2 text-warning"></i>Timeline Completo</h6>
                            <small class="text-muted" id="timelineCount"></small>
                        </div>
                        <div id="timelineContainer">
                            <div class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                                <p class="small mt-2">Cargando timeline...</p>
                            </div>
                        </div>
                        <div id="timelinePagination" class="d-none text-center mt-3">
                            <button class="btn btn-sm btn-outline-light rounded-pill px-4" onclick="loadMoreTimeline()">
                                <i class="bi bi-arrow-down me-1"></i>Cargar más
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="errorState" class="d-none text-center py-5">
            <i class="bi bi-exclamation-triangle fs-1 text-danger"></i>
            <p class="text-muted mt-3">No se pudo cargar la información del cliente.</p>
            <a href="clientes" class="btn btn-outline-light rounded-pill px-4">Volver a Clientes</a>
        </div>
    </div>

    <script>
    (function () {
        'use strict';

        const API_BASE = window.APP_API_BASE || '../api/';
        const BASE_PATH = window.APP_BASE_PATH || '../';
        const CLIENTE_ID = <?= $clienteId ?>;
        let timelinePage = 1;
        let timelineTotal = 0;
        let timelineLoaded = 0;

        var escapeHtml = window.escapeHtml;
        var fmtDate = window.fmtDate;
        function fmtDateTime(d) { return window.fmtDate ? window.fmtDate(d, 'datetime') : (d ? new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + new Date(d).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) : '—'); }
        var getEstadoColor = window.getEstadoColor;
        var getEstadoLabel = window.getEstadoLabel;

        async function loadCliente() {
            try {
                var resp = await fetch(API_BASE + 'api_cliente_360.php?id=' + CLIENTE_ID);
                var data = await resp.json();
                if (!data.ok) throw new Error(data.message);

                document.getElementById('loadingState').classList.add('d-none');
                document.getElementById('mainContent').classList.remove('d-none');

                var cl = data.data.cliente;
                var stats = data.data.estadisticas;
                var equipos = data.data.equipos;

                var initials = ((cl.nombre || '')[0] || '') + ((cl.apellido || '')[0] || '');
                document.getElementById('headerAvatar').textContent = initials.toUpperCase();
                document.getElementById('headerNombre').textContent = (cl.nombre || '') + ' ' + (cl.apellido || '');
                document.getElementById('headerTelefono').textContent = cl.telefono || '';
                document.getElementById('headerFechaRegistro').textContent = fmtDate(cl.fecha_registro);

                if (cl.correo) {
                    document.getElementById('headerCorreo').textContent = cl.correo;
                    document.getElementById('headerCorreoWrap').classList.remove('d-none');
                }

                document.getElementById('kpiTotal').textContent = stats.total_equipos || 0;
                document.getElementById('kpiCompletadas').textContent = stats.completadas || 0;
                document.getElementById('kpiEnProceso').textContent = stats.en_proceso || 0;
                document.getElementById('kpiGarantia').textContent = stats.con_garantia || 0;

                renderEquipos(equipos);
                loadTimeline();

            } catch (err) {
                document.getElementById('loadingState').classList.add('d-none');
                document.getElementById('errorState').classList.remove('d-none');
            }
        }

        function renderEquipos(equipos) {
            var container = document.getElementById('equiposContainer');
            if (!equipos || equipos.length === 0) {
                container.innerHTML = '<div class="text-center py-3 text-muted"><i class="bi bi-phone fs-3 d-block mb-2" style="opacity:0.2"></i>Sin equipos registrados</div>';
                return;
            }

            container.innerHTML = equipos.map(function (eq) {
                var modelo = eq.modelo_completo || ((eq.equipo_marca || '') + ' ' + (eq.equipo_modelo || '')).trim();
                var color = getEstadoColor(eq.estado);
                var label = getEstadoLabel(eq.estado);
                return '<a href="equipo_360?id=' + eq.id + '" class="equipo-card mb-2">' +
                    '<div class="d-flex justify-content-between align-items-start">' +
                        '<div class="min-w-0">' +
                            '<div class="fw-bold text-info font-monospace small">#' + escapeHtml(eq.folio_publico || '') + '</div>' +
                            '<div class="fw-medium text-white text-truncate">' + escapeHtml(modelo) + '</div>' +
                            '<div class="text-muted small text-truncate" style="max-width:220px">' + escapeHtml(eq.falla_reportada || '') + '</div>' +
                        '</div>' +
                        '<div class="flex-shrink-0 ms-2">' +
                            '<span class="badge-estado" style="background:' + color + '22;color:' + color + '">' + escapeHtml(label) + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="text-muted small mt-1"><i class="bi bi-calendar3 me-1"></i>' + fmtDate(eq.fecha_ingreso) + '</div>' +
                '</a>';
            }).join('');
        }

        async function loadTimeline() {
            try {
                var resp = await fetch(API_BASE + 'api_cliente_360.php?id=' + CLIENTE_ID + '&timeline=1&page=' + timelinePage + '&per_page=30');
                var data = await resp.json();
                if (!data.ok) throw new Error(data.message);

                timelineTotal = data.total;
                var items = data.items || [];
                timelineLoaded += items.length;

                var container = document.getElementById('timelineContainer');

                if (timelinePage === 1 && items.length === 0) {
                    container.innerHTML = '<div class="text-center py-4 text-muted"><i class="bi bi-clock-history fs-1 d-block mb-2" style="opacity:0.2"></i>Sin actividad registrada</div>';
                    document.getElementById('timelineCount').textContent = '0 eventos';
                    return;
                }

                if (timelinePage === 1) container.innerHTML = '';
                container.insertAdjacentHTML('beforeend', '<div class="timeline-wrap">' + items.map(renderTimelineItem).join('') + '</div>');
                document.getElementById('timelineCount').textContent = timelineTotal + ' evento(s)';

                if (timelineLoaded < timelineTotal) {
                    document.getElementById('timelinePagination').classList.remove('d-none');
                } else {
                    document.getElementById('timelinePagination').classList.add('d-none');
                }

            } catch (err) {
                document.getElementById('timelineContainer').innerHTML = '<div class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Error al cargar timeline</div>';
            }
        }

        function loadMoreTimeline() {
            timelinePage++;
            loadTimeline();
        }

        function renderTimelineItem(item) {
            var meta = item.metadata || {};
            var extraHtml = '';

            if (item.tipo === 'cambio_estado' && meta.estado_anterior && meta.estado_nuevo) {
                extraHtml = '<div class="mt-2 d-flex align-items-center gap-2 small">' +
                    '<span class="badge bg-secondary bg-opacity-25 rounded-pill">' + escapeHtml(getEstadoLabel(meta.estado_anterior)) + '</span>' +
                    '<i class="bi bi-arrow-right text-muted"></i>' +
                    '<span class="badge bg-primary bg-opacity-25 rounded-pill">' + escapeHtml(getEstadoLabel(meta.estado_nuevo)) + '</span></div>';
            }

            var folioLink = '';
            if (item.folio_publico) {
                folioLink = ' <a href="equipo_360?id=' + (item.reparacion_id || '') + '" class="text-info text-decoration-none small font-monospace">#' + escapeHtml(item.folio_publico) + '</a>';
            }

            return '<div class="timeline-item">' +
                '<div class="timeline-dot" style="background:' + (item.bg || 'rgba(100,116,139,0.2)') + ';color:' + (item.color || '#94a3b8') + ';">' +
                    '<i class="bi ' + (item.icon || 'bi-circle') + '"></i>' +
                '</div>' +
                '<div class="timeline-card">' +
                    '<div class="d-flex justify-content-between align-items-start mb-1">' +
                        '<div><span class="fw-bold text-white small">' + escapeHtml(item.titulo) + '</span>' + folioLink + '</div>' +
                        '<small class="text-muted font-monospace flex-shrink-0 ms-2" style="font-size:0.7rem">' + escapeHtml(item.fecha_fmt || '') + '</small>' +
                    '</div>' +
                    '<div class="d-flex align-items-center gap-2 mb-1">' +
                        '<span class="badge rounded-pill" style="background:' + (item.bg || '') + ';color:' + (item.color || '') + ';font-size:0.7rem">' + escapeHtml(item.label || '') + '</span>' +
                        (item.usuario ? '<span class="text-muted" style="font-size:0.7rem"><i class="bi bi-person me-1"></i>' + escapeHtml(item.usuario) + '</span>' : '') +
                        (item.equipo_marca ? '<span class="text-muted" style="font-size:0.7rem"><i class="bi bi-phone me-1"></i>' + escapeHtml((item.equipo_marca || '') + ' ' + (item.equipo_modelo || '')) + '</span>' : '') +
                    '</div>' +
                    (item.descripcion ? '<div class="text-muted small mt-1" style="white-space:pre-wrap">' + escapeHtml(item.descripcion) + '</div>' : '') +
                    extraHtml +
                '</div>' +
            '</div>';
        }

        document.addEventListener('DOMContentLoaded', function () {
            loadCliente();
        });

        window.loadMoreTimeline = loadMoreTimeline;
    })();
    </script>

<?php if (!$isFragment): ?>
    <?php include '../includes/pwa_script.php'; ?>
</main>
</body>
</html>
<?php endif; ?>
