<?php
include '../config/auth.php';
requireLogin();
include '../config/db.php';
include_once '../includes/fragment_helper.php';
?>
<?php if (!$isFragment): ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Rastreo Mes Azul | SOLUCIONESCEL</title>
    <?php include '../includes/head_meta.php'; ?>
    <link rel="stylesheet" href="../assets/css/panel.css" data-module-css="mes_azul">
    <style data-module-css="mes_azul">
        body { background-color: var(--bg-app); font-family: 'Inter', sans-serif; color: var(--text-main); min-height: 100vh; background-image: radial-gradient(circle at top right, #1e293b 0%, #0f172a 40%); }
        .back-link { color: #94a3b8; text-decoration: none; transition: color 0.15s; font-size: 0.9rem; }
        .back-link:hover { color: #e2e8f0; }
        .mes-azul-lista { max-height: 320px; overflow-y: auto; }
        .mes-azul-lateral { max-height: calc(100vh - 220px); overflow-y: auto; }
        .mes-azul-item { border: 1px solid rgba(255,255,255,0.06); }
        .equipo-link-360 { text-decoration: none; color: inherit; display: block; transition: background 0.2s, border-color 0.2s; }
        .equipo-link-360:hover { color: inherit; background: rgba(59,130,246,0.08); border-color: rgba(59,130,246,0.25); }
    </style>
</head>

<body>
    <?php include '../includes/header.php'; ?>
<?php endif; ?>

<?php if ($isFragment): ?>
    <link rel="stylesheet" href="<?= $fragment_asset_base ?>assets/css/panel.css" data-module-css="mes_azul">
    <style data-module-css="mes_azul">
        body { background-color: var(--bg-app); font-family: 'Inter', sans-serif; color: var(--text-main); min-height: 100vh; background-image: radial-gradient(circle at top right, #1e293b 0%, #0f172a 40%); }
        .back-link { color: #94a3b8; text-decoration: none; transition: color 0.15s; font-size: 0.9rem; }
        .back-link:hover { color: #e2e8f0; }
        .mes-azul-lista { max-height: 320px; overflow-y: auto; }
        .mes-azul-lateral { max-height: calc(100vh - 220px); overflow-y: auto; }
        .mes-azul-item { border: 1px solid rgba(255,255,255,0.06); }
        .equipo-link-360 { text-decoration: none; color: inherit; display: block; transition: background 0.2s, border-color 0.2s; }
        .equipo-link-360:hover { color: inherit; background: rgba(59,130,246,0.08); border-color: rgba(59,130,246,0.25); }
    </style>
<?php endif; ?>

    <div class="container-xl main-content-push with-subheader" style="max-width: 1400px;">

        <!-- Subheader -->
        <div class="module-subheader">
            <div class="module-subheader-kpis">
                <span class="module-subheader-title"><i class="bi bi-hourglass-split text-info me-1"></i>Rastreo Mes Azul</span>
            </div>
            <div class="module-subheader-actions">
                <button class="btn btn-primary" type="button" id="btnMesAzulEjecutar">
                    <i class="bi bi-play-fill"></i> Ejecutar proceso ahora
                </button>
            </div>
        </div>

        <div class="row g-4">
            <!-- Lateral: Dispositivos con 90+ días (todos, hayan o no recibido avisos) -->
            <div class="col-lg-4">
                <div class="glass-card p-4 h-100">
                    <h6 class="text-white border-bottom border-secondary pb-2 mb-3">
                        <i class="bi bi-calendar-x me-2 text-danger"></i>Dispositivos con 90+ días
                    </h6>
                    <p class="text-muted small mb-3">Listos sin recoger (con o sin avisos Mes Azul enviados)</p>
                    <div id="mesAzulLista90" class="mes-azul-lateral mes-azul-lista">
                        <div class="text-muted small text-center py-3">Cargando...</div>
                    </div>
                </div>
            </div>

            <!-- Contenido principal: activos + historial -->
            <div class="col-lg-8">
                <div class="glass-card p-4 mb-4">
                    <h6 class="text-white border-bottom border-secondary pb-2 mb-3">Dispositivos en Mes Azul activo</h6>
                    <p class="text-muted small mb-3">Con aviso Inicio enviado; esperando 5 días para envío Final e inactivación</p>
                    <div id="mesAzulActivosLista" class="mes-azul-lista">
                        <div class="text-muted small text-center py-3">Cargando...</div>
                    </div>
                </div>
                <div class="glass-card p-4">
                    <h6 class="text-white border-bottom border-secondary pb-2 mb-3">Dispositivos inactivados por Mes Azul (historial)</h6>
                    <div id="mesAzulHistorialLista" class="mes-azul-lista">
                        <div class="text-muted small text-center py-3">Cargando...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1050;">
        <div id="liveToast" class="toast align-items-center text-bg-success border-0 rounded-4 shadow-lg" role="alert">
            <div class="d-flex">
                <div class="toast-body px-4 py-3 fw-medium"><i class="bi bi-check-circle-fill me-2"></i><span id="toastMsg">Listo</span></div>
                <button type="button" class="btn-close btn-close-white me-3 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
        <div id="errorToast" class="toast align-items-center text-bg-danger border-0 rounded-4 shadow-lg" role="alert">
            <div class="d-flex">
                <div class="toast-body px-4 py-3 fw-medium"><i class="bi bi-exclamation-octagon-fill me-2"></i><span id="errorMsg">Error</span></div>
                <button type="button" class="btn-close btn-close-white me-3 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script>
    (function () {
        'use strict';
        var API_BASE = window.APP_API_BASE || '../api/';
        var BASE_PATH = window.APP_BASE_PATH || '../';

        var escapeHtml = window.escapeHtml;

        function renderLista90(lista) {
            var cont = document.getElementById('mesAzulLista90');
            if (!cont) return;
            if (!lista || lista.length === 0) {
                cont.innerHTML = '<div class="text-muted small text-center py-3">Ningún dispositivo con 90+ días.</div>';
                return;
            }
            cont.innerHTML = lista.map(function (d) {
                var nombre = (d.folio_publico || '') + ' — ' + (d.cliente_nombre || '') + ' ' + ((d.equipo_marca || '') + ' ' + (d.equipo_modelo || '')).trim();
                var dias = typeof d.dias_transcurridos === 'number' ? d.dias_transcurridos : 90;
                var fechaListo = d.fecha_listo ? (new Date(d.fecha_listo)).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
                var estado = (d.mes_azul_estado || '').toLowerCase();
                var badge = '';
                if (estado === 'inactivado') badge = '<span class="badge bg-secondary bg-opacity-25 text-info border border-info border-opacity-25 rounded-pill px-2 py-0 ms-1" style="font-size: 0.65rem;">Inactivado</span>';
                else if (estado === 'esperando_final') badge = '<span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25 rounded-pill px-2 py-0 ms-1" style="font-size: 0.65rem;">Esperando final</span>';
                else if (d.mes_azul_inicio_enviado) badge = '<span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-25 rounded-pill px-2 py-0 ms-1" style="font-size: 0.65rem;">Inicio enviado</span>';
                else badge = '<span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-25 rounded-pill px-2 py-0 ms-1" style="font-size: 0.65rem;">Sin aviso</span>';
                return '<a href="equipo_360?id=' + d.id + '" class="d-flex align-items-center justify-content-between py-2 px-3 rounded-3 mb-2 mes-azul-item equipo-link-360" style="background: rgba(255,255,255,0.03);">' +
                    '<div class="min-w-0">' +
                        '<div class="fw-medium text-white text-truncate">' + escapeHtml(nombre) + '</div>' +
                        '<div class="small text-muted">' + fechaListo + ' · ' + dias + ' días</div>' +
                    '</div>' +
                    '<div class="flex-shrink-0 ms-2">' + badge + '</div>' +
                    '</a>';
            }).join('');
        }

        function renderActivos(lista) {
            var cont = document.getElementById('mesAzulActivosLista');
            if (!cont) return;
            if (!lista || lista.length === 0) {
                cont.innerHTML = '<div class="text-muted small text-center py-3">Ningún dispositivo en Mes Azul activo.</div>';
                return;
            }
            cont.innerHTML = lista.map(function (d) {
                var nombre = (d.folio_publico || '') + ' — ' + (d.cliente_nombre || '') + ' ' + ((d.equipo_marca || '') + ' ' + (d.equipo_modelo || '')).trim();
                var fechaEntrada = d.mes_azul_inicio_enviado ? (new Date(d.mes_azul_inicio_enviado)).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
                var webhookOk = d.webhook_inicio_ok;
                var diasRestantes = typeof d.dias_restantes === 'number' ? d.dias_restantes : 5;
                var iconoWebhook = webhookOk ? '<i class="bi bi-check-circle-fill text-success" title="Mes Azul Inicio enviado"></i>' : '<i class="bi bi-exclamation-triangle-fill text-warning" title="Pendiente o falló"></i>';
                return '<div class="d-flex align-items-center justify-content-between py-2 px-3 rounded-3 mb-2 mes-azul-item" style="background: rgba(255,255,255,0.05);">' +
                    '<div><div class="fw-medium text-white">' + escapeHtml(nombre) + '</div>' +
                    '<div class="small text-muted">Entró a Mes Azul: ' + fechaEntrada + ' ' + iconoWebhook + '</div></div>' +
                    '<div class="contador-mes-azul fw-bold text-info">' + diasRestantes + ' día' + (diasRestantes !== 1 ? 's' : '') + '</div>' +
                    '</div>';
            }).join('');
        }

        function renderHistorial(lista) {
            var cont = document.getElementById('mesAzulHistorialLista');
            if (!cont) return;
            if (!lista || lista.length === 0) {
                cont.innerHTML = '<div class="text-muted small text-center py-3">Sin historial de dispositivos inactivados por Mes Azul.</div>';
                return;
            }
            cont.innerHTML = lista.map(function (d) {
                var nombre = (d.folio_publico || '') + ' — ' + (d.cliente_nombre || '') + ' ' + ((d.equipo_marca || '') + ' ' + (d.equipo_modelo || '')).trim();
                var inicio = d.mes_azul_inicio_enviado ? (new Date(d.mes_azul_inicio_enviado)).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
                var finalFmt = d.mes_azul_final_enviado ? (new Date(d.mes_azul_final_enviado)).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
                var inact = d.mes_azul_fecha_inactivacion ? (new Date(d.mes_azul_fecha_inactivacion)).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
                var check = '<i class="bi bi-check-circle-fill text-success small"></i>';
                return '<div class="d-flex align-items-center justify-content-between py-2 px-3 rounded-3 mb-2" style="background: rgba(255,255,255,0.03);">' +
                    '<div><div class="fw-medium text-white">' + escapeHtml(nombre) + '</div>' +
                    '<div class="small text-muted">Inicio: ' + inicio + ' ' + check + ' | Final: ' + finalFmt + ' ' + check + ' | Inactivado: ' + inact + '</div></div>' +
                    '</div>';
            }).join('');
        }

        function loadAll() {
            var list90 = document.getElementById('mesAzulLista90');
            var activos = document.getElementById('mesAzulActivosLista');
            var historial = document.getElementById('mesAzulHistorialLista');
            if (list90) list90.innerHTML = '<div class="text-muted small text-center py-3">Cargando...</div>';
            if (activos) activos.innerHTML = '<div class="text-muted small text-center py-3">Cargando...</div>';
            if (historial) historial.innerHTML = '<div class="text-muted small text-center py-3">Cargando...</div>';

            fetch(API_BASE + 'api_mes_azul?action=90_dias')
                .then(function (r) { return r.json(); })
                .then(function (data) { if (list90) renderLista90(data.ok ? data.data : []); })
                .catch(function () { if (list90) list90.innerHTML = '<div class="text-danger small text-center py-3">Error al cargar.</div>'; });

            fetch(API_BASE + 'api_mes_azul?action=activos')
                .then(function (r) { return r.json(); })
                .then(function (data) { if (activos) renderActivos(data.ok ? data.data : []); })
                .catch(function () { if (activos) activos.innerHTML = '<div class="text-danger small text-center py-3">Error al cargar.</div>'; });

            fetch(API_BASE + 'api_mes_azul?action=historial')
                .then(function (r) { return r.json(); })
                .then(function (data) { if (historial) renderHistorial(data.ok ? data.data : []); })
                .catch(function () { if (historial) historial.innerHTML = '<div class="text-danger small text-center py-3">Error al cargar.</div>'; });
        }

        function showToast(msg, isError) {
            var el = isError ? document.getElementById('errorMsg') : document.getElementById('toastMsg');
            var toastEl = isError
                ? document.getElementById('errorToast')
                : document.getElementById('liveToast');
            if (el) el.textContent = msg;
            if (toastEl) {
                var inst = bootstrap.Toast.getInstance(toastEl) || new bootstrap.Toast(toastEl);
                inst.show();
            }
        }

        document.getElementById('btnMesAzulEjecutar').addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'ejecutar');
            fetch(API_BASE + 'api_mes_azul', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    btn.disabled = false;
                    if (data.ok) {
                        loadAll();
                        showToast('Proceso ejecutado. Inicio: ' + (data.inicio_enviados || 0) + ', Final: ' + (data.final_enviados || 0), false);
                    } else {
                        showToast(data.message || 'Error', true);
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    showToast('Error de conexión', true);
                });
        });

        document.addEventListener('DOMContentLoaded', function () {
            loadAll();
            var liveToast = document.getElementById('liveToast');
            var errorToast = document.getElementById('errorToast');
            if (liveToast) window.toastSuccess = new bootstrap.Toast(liveToast);
            if (errorToast) window.toastError = new bootstrap.Toast(errorToast);
        });
    })();
    </script>

<?php if (!$isFragment): ?>
    <?php include '../includes/pwa_script.php'; ?>
</main>
</body>
</html>
<?php endif; ?>
