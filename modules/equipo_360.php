<?php
include '../config/auth.php';
requireLogin();
include '../config/db.php';
include_once '../includes/fragment_helper.php';

$equipoId = (int) ($_GET['id'] ?? 0);
if ($equipoId <= 0) {
    header('Location: ../modules/panel');
    exit;
}
?>
<?php if (!$isFragment): ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Equipo 360° | SOLUCIONESCEL</title>
    <?php include '../includes/head_meta.php'; ?>
<?php endif; ?>
    <style data-module-css="equipo_360">
        /* Globales en app.css: body, .glass-card, .info-label/value, .timeline-*, .status-dot, .cliente-link, .badge-estado */
        .timeline-card--estado { border-left: 3px solid #f59e0b; background: rgba(245,158,11,0.06); }
        .timeline-card--entregado { border-left: 3px solid #22c55e; background: rgba(34,197,94,0.06); }
        .timeline-card--mensaje { border-left: 3px solid #10b981; background: rgba(16,185,129,0.06); }
        .timeline-card--edicion { border-left: 3px solid #a78bfa; background: rgba(167,139,250,0.06); }
        .timeline-card .timeline-usuario { font-size: 0.75rem; color: #94a3b8; margin-top: 0.35rem; }
        .timeline-card .timeline-usuario i { margin-right: 0.25rem; }
        .stat-mini { text-align: center; padding: 1rem; }
        .stat-mini h4 { font-size: 1.5rem; font-weight: 700; margin: 0; }
        .stat-mini small { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.03em; color: #94a3b8; font-weight: 600; }
    </style>
<?php if (!$isFragment): ?>
</head>

<body>
    <?php include '../includes/header.php'; ?>
<?php endif; ?>

    <div class="container-xl main-content-push" style="max-width: 1200px;">
        <div class="mb-4">
            <a href="../modules/panel" class="back-link"><i class="bi bi-arrow-left me-1"></i>Volver al panel</a>
        </div>

        <div id="loadingState" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted small mt-3">Cargando datos del equipo...</p>
        </div>

        <div id="mainContent" class="d-none">
            <!-- Header -->
            <div class="glass-card p-4 mb-4">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="d-flex align-items-center justify-content-center rounded-3" id="headerIcon"
                             style="width:56px;height:56px;background:rgba(59,130,246,0.15);">
                            <i class="bi bi-phone-fill fs-3 text-primary"></i>
                        </div>
                    </div>
                    <div class="col">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h3 class="fw-bold text-white m-0" id="headerFolio"></h3>
                            <span class="badge-estado" id="headerEstado"></span>
                        </div>
                        <div class="text-muted" id="headerModelo"></div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Columna izquierda: Info -->
                <div class="col-lg-4">
                    <!-- Datos del equipo -->
                    <div class="glass-card p-4 mb-4">
                        <h6 class="fw-bold text-white mb-3"><i class="bi bi-phone me-2 text-primary"></i>Datos del Equipo</h6>
                        <div class="mb-3"><div class="info-label">Marca / Modelo</div><div class="info-value" id="infoModelo">—</div></div>
                        <div class="mb-3"><div class="info-label">Folio</div><div class="info-value font-monospace text-info" id="infoFolio">—</div></div>
                        <div class="mb-3"><div class="info-label">Problema reportado</div><div class="info-value" id="infoFalla" style="white-space:pre-wrap">—</div></div>
                        <div class="mb-3"><div class="info-label">Fecha de ingreso</div><div class="info-value" id="infoFechaIngreso">—</div></div>
                        <div class="mb-3"><div class="info-label">Ingresado por</div><div class="info-value" id="infoIngresadoPor">—</div></div>
                        <div><div class="info-label">Costo final</div><div class="info-value" id="infoCosto">—</div></div>
                    </div>

                    <!-- Cliente asociado -->
                    <div class="glass-card p-4 mb-4">
                        <h6 class="fw-bold text-white mb-3"><i class="bi bi-person me-2 text-info"></i>Cliente Asociado</h6>
                        <div id="clienteInfo">
                            <div class="text-muted small">Cargando...</div>
                        </div>
                    </div>
                </div>

                <!-- Columna derecha: Timeline -->
                <div class="col-lg-8">
                    <div class="glass-card p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h6 class="fw-bold text-white m-0"><i class="bi bi-clock-history me-2 text-warning"></i>Timeline del Equipo</h6>
                            <small class="text-muted" id="timelineCount"></small>
                        </div>
                        <div id="timelineContainer">
                            <div class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                                <p class="small mt-2">Cargando timeline...</p>
                            </div>
                        </div>
                        <div id="timelinePagination" class="d-none text-center mt-3">
                            <button class="btn btn-sm btn-outline-light rounded-pill px-4" id="btnLoadMore" onclick="loadMoreTimeline()">
                                <i class="bi bi-arrow-down me-1"></i>Cargar más
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="errorState" class="d-none text-center py-5">
            <i class="bi bi-exclamation-triangle fs-1 text-danger"></i>
            <p class="text-muted mt-3">No se pudo cargar la información del equipo.</p>
            <div class="d-flex justify-content-center gap-3 mt-3 flex-wrap">
                <a href="../index" class="btn btn-outline-light rounded-pill px-4">Volver al panel</a>
                <a href="../logout?logout=true" class="btn btn-danger rounded-pill px-4">
                    Salir sesión
                </a>
            </div>
        </div>
    </div>

    <script>
    (function () {
        'use strict';

        const API_BASE = window.APP_API_BASE || '../api/';
        const BASE_PATH = window.APP_BASE_PATH || '../';
        const EQUIPO_ID = <?= $equipoId ?>;
        let timelinePage = 1;
        let timelineTotal = 0;
        let timelineLoaded = 0;

        var escapeHtml = window.escapeHtml;
        var fmtDate = window.fmtDate;
        var getEstadoColor = window.getEstadoColor;
        var getEstadoLabel = window.getEstadoLabel;

        async function loadEquipo() {
            try {
                var resp = await fetch(API_BASE + 'api_equipo_360?id=' + EQUIPO_ID);
                var data = await resp.json();
                if (!data.ok) throw new Error(data.message);

                document.getElementById('loadingState').classList.add('d-none');
                document.getElementById('mainContent').classList.remove('d-none');

                var eq = data.data.equipo;
                var cl = data.data.cliente;

                var color = getEstadoColor(eq.estado);
                var label = getEstadoLabel(eq.estado);

                document.getElementById('headerFolio').textContent = '#' + (eq.folio_publico || '');
                document.getElementById('headerModelo').textContent = eq.modelo_completo || '';
                document.getElementById('headerEstado').textContent = label;
                document.getElementById('headerEstado').style.cssText = 'background:' + color + '22;color:' + color + ';';

                document.getElementById('infoModelo').textContent = eq.modelo_completo || '—';
                document.getElementById('infoFolio').textContent = eq.folio_publico || '—';
                document.getElementById('infoFalla').textContent = eq.falla_reportada || '—';
                document.getElementById('infoFechaIngreso').textContent = fmtDate(eq.fecha_ingreso);
                document.getElementById('infoIngresadoPor').textContent = eq.ingresado_por || '—';
                document.getElementById('infoCosto').textContent = eq.costo_final ? '$' + parseFloat(eq.costo_final).toFixed(2) : 'No definido';

                if (cl) {
                    document.getElementById('clienteInfo').innerHTML =
                        '<div class="d-flex align-items-center gap-3 mb-3">' +
                            '<div class="d-flex align-items-center justify-content-center rounded-circle" style="width:42px;height:42px;background:linear-gradient(135deg,#3b82f6,#6366f1);font-size:0.75rem;font-weight:700;color:#fff;">' +
                                escapeHtml(((cl.nombre||'')[0]||'') + ((cl.apellido||'')[0]||'')) +
                            '</div>' +
                            '<div>' +
                                '<a href="' + BASE_PATH + 'modules/cliente_360.php?id=' + cl.id + '" class="cliente-link fw-bold">' + escapeHtml(cl.nombre + ' ' + cl.apellido) + '</a>' +
                                '<div class="small text-muted"><i class="bi bi-whatsapp me-1 text-success"></i>' + escapeHtml(cl.telefono) + '</div>' +
                                (cl.correo ? '<div class="small text-muted"><i class="bi bi-envelope me-1"></i>' + escapeHtml(cl.correo) + '</div>' : '') +
                            '</div>' +
                        '</div>';
                } else {
                    document.getElementById('clienteInfo').innerHTML = '<div class="text-muted small"><i class="bi bi-person-x me-1"></i>Sin cliente vinculado</div>';
                }

                // Cargar mensajes existentes como parte del timeline si no hay eventos aún
                var mensajes = data.data.mensajes || [];
                if (mensajes.length > 0) {
                    renderMensajesLegacy(mensajes);
                }

                loadTimeline();

            } catch (err) {
                document.getElementById('loadingState').classList.add('d-none');
                document.getElementById('errorState').classList.remove('d-none');
            }
        }

        function renderMensajesLegacy(mensajes) {
            // Se mostrarán como fallback al final del timeline si no hay eventos registrados
            window._mensajesLegacy = mensajes;
        }

        async function loadTimeline() {
            try {
                var resp = await fetch(API_BASE + 'api_equipo_360?id=' + EQUIPO_ID + '&timeline=1&page=' + timelinePage + '&per_page=30');
                var data = await resp.json();
                if (!data.ok) throw new Error(data.message);

                timelineTotal = data.total;
                var items = data.items || [];
                timelineLoaded += items.length;

                var container = document.getElementById('timelineContainer');

                if (timelinePage === 1 && items.length === 0) {
                    // Si no hay eventos, mostrar mensajes legacy
                    var legacy = window._mensajesLegacy || [];
                    if (legacy.length > 0) {
                        container.innerHTML = renderLegacyTimeline(legacy);
                    } else {
                        container.innerHTML = '<div class="text-center py-4 text-muted"><i class="bi bi-clock-history fs-1 d-block mb-2" style="opacity:0.2"></i>Sin actividad registrada</div>';
                    }
                    document.getElementById('timelineCount').textContent = legacy.length + ' evento(s)';
                    return;
                }

                if (timelinePage === 1) container.innerHTML = '';
                container.innerHTML += '<div class="timeline-wrap">' + items.map(renderTimelineItem).join('') + '</div>';
                document.getElementById('timelineCount').textContent = timelineTotal + ' evento(s)';

                if (timelineLoaded < timelineTotal) {
                    document.getElementById('timelinePagination').classList.remove('d-none');
                } else {
                    document.getElementById('timelinePagination').classList.add('d-none');
                    // Append legacy messages at the end if they exist
                    var legacy = window._mensajesLegacy || [];
                    if (legacy.length > 0) {
                        container.innerHTML += '<h6 class="text-muted small mt-4 mb-3 fw-bold"><i class="bi bi-chat-dots me-1"></i>Historial de mensajes</h6>';
                        container.innerHTML += renderLegacyTimeline(legacy);
                    }
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
                extraHtml = '<div class="mt-2 d-flex align-items-center gap-2 small flex-wrap">' +
                    '<span class="badge bg-secondary bg-opacity-25 rounded-pill">' + escapeHtml(getEstadoLabel(meta.estado_anterior)) + '</span>' +
                    '<i class="bi bi-arrow-right text-muted"></i>' +
                    '<span class="badge bg-primary bg-opacity-25 rounded-pill fw-bold">' + escapeHtml(getEstadoLabel(meta.estado_nuevo)) + '</span></div>';
            }

            var cardClass = 'timeline-card';
            if (item.tipo === 'cambio_estado' || item.tipo === 'garantia_reactivada') cardClass += ' timeline-card--estado';
            else if (item.tipo === 'equipo_entregado') cardClass += ' timeline-card--entregado';
            else if (item.tipo === 'mensaje_enviado') cardClass += ' timeline-card--mensaje';
            else if (item.tipo === 'equipo_editado') cardClass += ' timeline-card--edicion';
            else if (item.tipo === 'garantia_activada') cardClass += ' timeline-card--entregado';

            var usuarioHtml = (item.usuario) ? '<div class="timeline-usuario"><i class="bi bi-person-fill"></i>Por: ' + escapeHtml(item.usuario) + '</div>' : '';

            return '<div class="timeline-item">' +
                '<div class="timeline-dot" style="background:' + (item.bg || 'rgba(100,116,139,0.2)') + ';color:' + (item.color || '#94a3b8') + ';">' +
                    '<i class="bi ' + (item.icon || 'bi-circle') + '"></i>' +
                '</div>' +
                '<div class="' + cardClass + '">' +
                    '<div class="d-flex justify-content-between align-items-start mb-1">' +
                        '<span class="fw-bold text-white small">' + escapeHtml(item.titulo) + '</span>' +
                        '<small class="text-muted font-monospace" style="font-size:0.7rem">' + escapeHtml(item.fecha_fmt || '') + '</small>' +
                    '</div>' +
                    '<div class="d-flex align-items-center gap-2 mb-1">' +
                        '<span class="badge rounded-pill" style="background:' + (item.bg || '') + ';color:' + (item.color || '') + ';font-size:0.7rem">' + escapeHtml(item.label || '') + '</span>' +
                    '</div>' +
                    usuarioHtml +
                    (item.descripcion ? '<div class="text-muted small mt-1" style="white-space:pre-wrap">' + escapeHtml(item.descripcion) + '</div>' : '') +
                    extraHtml +
                '</div>' +
            '</div>';
        }

        function renderLegacyTimeline(mensajes) {
            return '<div class="timeline-wrap">' + mensajes.map(function (m) {
                var icon = 'bi-chat-left';
                var color = '#10b981';
                var bg = 'rgba(16,185,129,0.1)';
                if (m.estado_envio === 'fallido') { color = '#f43f5e'; bg = 'rgba(244,63,94,0.1)'; icon = 'bi-exclamation-triangle'; }
                else if (m.estado_envio === 'pendiente') { color = '#f59e0b'; bg = 'rgba(245,158,11,0.1)'; icon = 'bi-hourglass'; }
                else { icon = 'bi-whatsapp'; }

                var fecha = m.fecha_envio ? fmtDate(m.fecha_envio) : '';
                var contenido = m.contenido_mensaje ? '<div class="mt-2 p-2 rounded small" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.05);white-space:pre-wrap;color:#cbd5e1;">' + escapeHtml(m.contenido_mensaje) + '</div>' : '';

                return '<div class="timeline-item">' +
                    '<div class="timeline-dot" style="background:' + bg + ';color:' + color + ';"><i class="bi ' + icon + '"></i></div>' +
                    '<div class="timeline-card timeline-card--mensaje">' +
                        '<div class="d-flex justify-content-between align-items-start mb-1">' +
                            '<span class="fw-bold text-white small">' + escapeHtml((m.tipo_mensaje || '').replace(/_/g, ' ')) + '</span>' +
                            '<small class="text-muted font-monospace" style="font-size:0.7rem">' + escapeHtml(fecha) + '</small>' +
                        '</div>' +
                        '<div class="d-flex gap-2 mb-1">' +
                            '<span class="badge rounded-pill" style="background:' + bg + ';color:' + color + ';font-size:0.7rem">' + escapeHtml(m.estado_envio || '') + '</span>' +
                        '</div>' +
                        (m.enviado_por ? '<div class="timeline-usuario"><i class="bi bi-person-fill"></i>Por: ' + escapeHtml(m.enviado_por) + '</div>' : '') +
                        contenido +
                    '</div>' +
                '</div>';
            }).join('') + '</div>';
        }

        onModuleReady(function () {
            loadEquipo();
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
