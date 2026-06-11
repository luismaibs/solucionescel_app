<?php
include '../config/auth.php';
requireLogin();
include '../config/db.php';
include_once '../includes/fragment_helper.php';

$totalConvActivas = 0;
$totalReactivadasHoy = 0;
$totalHistoricoPausas = 0;
$soporteError = null;

try {
    $soporteRepo = new SoporteRepository($supabase);
    $soporteService = new SoporteService($soporteRepo);
    $resumen = $soporteService->obtenerResumenPanel();
    $totalConvActivas = $resumen['totalConvActivas'];
    $totalReactivadasHoy = $resumen['totalReactivadasHoy'];
    $totalHistoricoPausas = $resumen['totalHistoricoPausas'];
} catch (Throwable $e) {
    $soporteError = $e->getMessage();
    error_log('soporte.php: ' . $e->getMessage());
}

?>
<?php if (!$isFragment): ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soporte Humano | SOLUCIONESCEL</title>
    <?php include '../includes/head_meta.php'; ?>
    <link rel="stylesheet" href="../assets/css/soporte.css" data-module-css="soporte">
</head>

<body>

    <?php include '../includes/header.php'; ?>
<?php else: ?>
    <link rel="stylesheet" href="<?= $fragment_asset_base ?>assets/css/soporte.css" data-module-css="soporte">
<?php endif; ?>

    <div class="container-xl main-content-push with-subheader pb-5" style="max-width: 1400px;">

        <!-- Subheader: título + KPI chips -->
        <div class="module-subheader">
            <div class="module-subheader-kpis">
                <span class="module-subheader-title">Soporte Humano</span>
                <span class="module-kpi-chip">
                    <i class="bi bi-chat-dots-fill kpi-icon text-warning"></i>
                    <span class="kpi-value"><?= number_format($totalConvActivas) ?></span>
                    <span class="kpi-label">Activas</span>
                </span>
                <span class="module-kpi-chip">
                    <i class="bi bi-check2-circle kpi-icon text-success"></i>
                    <span class="kpi-value"><?= number_format($totalReactivadasHoy) ?></span>
                    <span class="kpi-label">Reactivadas hoy</span>
                </span>
                <span class="module-kpi-chip">
                    <i class="bi bi-clock-history kpi-icon text-secondary"></i>
                    <span class="kpi-value"><?= number_format($totalHistoricoPausas) ?></span>
                    <span class="kpi-label">Histórico pausas</span>
                </span>
            </div>
            <div class="module-subheader-actions"></div>
        </div>

        <!-- Filtros -->
        <div class="filters-row-wrap d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center justify-content-between mb-4 gap-3">
            <div class="d-flex gap-2 overflow-x-auto hide-scrollbar pb-1 pb-lg-0 w-100">
                <span class="filter-chip active flex-shrink-0" onclick="filterConversations('all', this)">Todos</span>
                <span class="filter-chip flex-shrink-0" onclick="filterConversations('pausado', this)">Pendientes</span>
                <span class="filter-chip flex-shrink-0" onclick="filterConversations('activo', this)">Reactivadas</span>
            </div>
            <div class="position-relative flex-shrink-0 w-100" style="max-width: 300px;">
                <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                <input type="text" id="searchInput" class="form-control search-bar" placeholder="Buscar..."
                    onkeyup="filterConversations()">
            </div>
        </div>

        <!-- LISTA CONVERSACIONES -->
        <div class="glass-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold m-0">Listado de Conversaciones</h5>
            </div>

            <div class="d-none d-md-flex row px-3 py-2 text-muted small fw-bold text-uppercase border-bottom border-white border-opacity-10 mb-2">
                <div class="col-md-3">Cliente</div>
                <div class="col-md-4">Contexto</div>
                <div class="col-md-2 text-center">Estado del Bot</div>
                <div class="col-md-1 text-center">Tiempo</div>
                <div class="col-md-2 text-end">Acciones</div>
            </div>

            <div id="conversacionesContainer">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="text-muted mt-3">Cargando conversaciones...</p>
                </div>
            </div>
        </div>

    </div>

    <!-- Modal Confirmación Reactivar -->
    <div class="modal fade" id="reactivarModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-glass border border-secondary border-opacity-25 shadow-lg">
                <div class="modal-header modal-glass-header border-bottom border-secondary border-opacity-10">
                    <h5 class="modal-title fw-bold">Confirmar Reactivación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body modal-glass-body p-4 text-center">
                    <div class="mb-3">
                        <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-circle text-primary"
                            style="width: 60px; height: 60px;">
                            <i class="bi bi-robot fs-1"></i>
                        </div>
                    </div>
                    <h5 class="mb-3">¿Has terminado de atender al cliente?</h5>
                    <p class="text-muted mb-0">Al reactivar el bot, este volverá a tomar el control y contestará
                        automáticamente los mensajes nuevos.</p>
                </div>
                <div class="modal-footer modal-glass-footer border-top border-secondary border-opacity-10 p-3 bg-black bg-opacity-10">
                    <button type="button" class="btn btn-outline-light border-0" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary px-4 fw-bold" id="btnConfirmReactivar">
                        <i class="bi bi-check-lg me-1"></i> Sí, Reactivar
                    </button>
                </div>
            </div>
        </div>
    </div>


    <script>
        (function () {
            let allConversations = [];
            let currentFilter = 'all';
            let pendingReactivation = null;
            let pollIntervalId = null;
            const reactivarModal = new bootstrap.Modal(document.getElementById('reactivarModal'));

            const esc = (typeof window.escapeHtml === 'function')
                ? (s) => window.escapeHtml(String(s ?? ''))
                : (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

            async function cargarConversaciones() {
                const container = document.getElementById('conversacionesContainer');

                if (allConversations.length === 0) {
                    container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div><p class="text-muted mt-3">Cargando conversaciones...</p></div>';
                }

                try {
                    const response = await fetch('../api/api_analiticas?action=obtener_conversaciones');
                    const data = await response.json();

                    if (data.ok) {
                        allConversations = data.conversaciones;
                        renderFiltered();
                    } else {
                        container.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ' + (data.message || data.error || 'Error') + '</div>';
                    }
                } catch (error) {
                    container.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Error al cargar conversaciones. Verifica tu conexión.</div>';
                    console.error('Error:', error);
                }
            }

            function renderFiltered() {
                const searchText = document.getElementById('searchInput').value.toLowerCase();

                const filtered = allConversations.filter(function (conv) {
                    if (currentFilter !== 'all' && conv.estado !== currentFilter) return false;
                    if (searchText) {
                        const searchStr = (conv.nombre_cliente + ' ' + conv.telefono + ' ' + conv.mensaje).toLowerCase();
                        if (searchStr.indexOf(searchText) === -1) return false;
                    }
                    return true;
                });

                renderConversaciones(filtered);
            }

            function renderConversaciones(conversations) {
                const container = document.getElementById('conversacionesContainer');

                if (conversations.length === 0) {
                    container.innerHTML = '<div class="text-center py-5"><i class="bi bi-chat-left-dots fs-1 text-muted opacity-50"></i><p class="text-muted mt-3">No se encontraron conversaciones con los filtros actuales</p></div>';
                    return;
                }

                container.innerHTML = conversations.map(function (conv) {
                    const whatsappLabel = conv.estado === 'pausado' ? 'Responder' : 'Ir a Chat';
                    const whatsappClass = conv.estado === 'pausado' ? 'btn-wa' : 'btn-outline-secondary border-0 text-white bg-white bg-opacity-10';
                    const pulseDot = conv.estado === 'pausado' ? '<span class="pulse-dot flex-shrink-0"></span>' : '';
                    const estadoIcon = conv.estado === 'pausado' ? 'bi-pause-circle-fill' : 'bi-play-circle-fill';
                    const estadoLabel = conv.estado === 'pausado' ? 'Pausado' : 'Activo';

                    return ''
                        + '<div class="conv-item">'
                        + '  <div class="row align-items-center">'
                        + '    <div class="col-12 col-md-3 mb-2 mb-md-0">'
                        + '      <div class="d-flex align-items-center gap-3">' + pulseDot
                        + '        <div style="min-width: 0;">'
                        + '          <div class="fw-bold text-white text-truncate" title="' + esc(conv.nombre_cliente) + '">' + esc(conv.nombre_cliente) + '</div>'
                        + '          <div class="text-muted font-monospace small"><i class="bi bi-phone me-1"></i>' + esc(conv.telefono) + '</div>'
                        + '        </div>'
                        + '      </div>'
                        + '    </div>'
                        + '    <div class="col-12 col-md-4 mb-2 mb-md-0">'
                        + '      <small class="text-muted d-block d-md-none text-uppercase" style="font-size: 0.65rem;">Contexto</small>'
                        + '      <div class="text-light text-truncate" title="' + esc(conv.mensaje) + '" style="font-size: 0.85rem;">'
                        + '        <i class="bi bi-chat-quote-fill me-1 text-secondary"></i> "' + esc(conv.mensaje) + '"'
                        + '      </div>'
                        + '    </div>'
                        + '    <div class="col-6 col-md-2 mb-2 mb-md-0 text-center">'
                        + '      <small class="text-muted d-block d-md-none text-uppercase" style="font-size: 0.65rem;">Estado</small>'
                        + '      <span class="conv-estado ' + conv.estado + ' px-2 py-1 w-100 justify-content-center">'
                        + '        <i class="bi ' + estadoIcon + '"></i>'
                        + '        <span class="d-none d-lg-inline ms-1">' + estadoLabel + '</span>'
                        + '      </span>'
                        + '    </div>'
                        + '    <div class="col-6 col-md-1 mb-2 mb-md-0 text-center">'
                        + '      <small class="text-muted d-block d-md-none text-uppercase" style="font-size: 0.65rem;">Tiempo</small>'
                        + '      <small class="text-muted fw-bold" style="font-size: 0.75rem;">' + esc(conv.tiempo_transcurrido) + '</small>'
                        + '    </div>'
                        + '    <div class="col-12 col-md-2 text-end">'
                        + '      <div class="d-flex gap-2 justify-content-end align-items-center">'
                        + '        <a href="https://wa.me/' + esc(conv.telefono) + '" target="_blank" class="' + whatsappClass + ' px-3 py-1 text-decoration-none d-inline-flex align-items-center justify-content-center" style="height: 32px;" title="' + whatsappLabel + '">'
                        + '          <i class="bi bi-whatsapp"></i> <span class="d-none d-xl-inline ms-2" style="font-size: 0.8rem;">' + whatsappLabel + '</span>'
                        + '        </a>'
                        + (conv.estado === 'pausado'
                            ? '<button class="btn btn-primary px-3 py-1 d-inline-flex align-items-center justify-content-center" style="background-color: #3b82f6; border: none; height: 32px; font-size: 0.85rem; border-radius: 8px;" onclick="Soporte.reactivarBot(\'' + esc(conv.remote_jid) + '\', ' + conv.id + ')">Activar</button>'
                            : '')
                        + '      </div>'
                        + '    </div>'
                        + '  </div>'
                        + '</div>';
                }).join('');
            }

            function filterConversations(filterType, el) {
                if (filterType && (filterType === 'all' || filterType === 'pausado' || filterType === 'activo')) {
                    currentFilter = filterType;
                }
                if (el) {
                    document.querySelectorAll('.filter-chip').forEach(function (chip) { chip.classList.remove('active'); });
                    el.classList.add('active');
                }
                renderFiltered();
            }

            function reactivarBot(remoteJid, convId) {
                pendingReactivation = { remoteJid: remoteJid, convId: convId };
                reactivarModal.show();
            }

            async function processReactivation(remoteJid, convId) {
                reactivarModal.hide();
                if (window.SCToast) window.SCToast.show('Reactivando bot...', 'info');

                try {
                    const response = await fetch('../api/api_analiticas', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'reactivar_bot', remote_jid: remoteJid, conv_id: convId })
                    });
                    const data = await response.json();

                    if (data.ok) {
                        cargarConversaciones();
                        if (window.SCToast) window.SCToast.show('Bot reactivado correctamente', 'success');
                    } else {
                        if (window.SCToast) window.SCToast.show('Error: ' + (data.error || 'Error desconocido'), 'error');
                        else alert('Error: ' + data.error);
                    }
                } catch (error) {
                    if (window.SCToast) window.SCToast.show('Error al reactivar el bot', 'error');
                    else alert('Error al reactivar el bot');
                    console.error('Error:', error);
                }
            }

            function startPolling() {
                pollIntervalId = setInterval(cargarConversaciones, 30000);
            }

            function stopPolling() {
                if (pollIntervalId) {
                    clearInterval(pollIntervalId);
                    pollIntervalId = null;
                }
            }

            // API pública
            window.Soporte = {
                reactivarBot: reactivarBot,
                filterConversations: filterConversations
            };

            document.addEventListener('DOMContentLoaded', function () {
                cargarConversaciones();
                startPolling();

                document.getElementById('btnConfirmReactivar').addEventListener('click', function () {
                    if (pendingReactivation) {
                        processReactivation(pendingReactivation.remoteJid, pendingReactivation.convId);
                    }
                });
            });

            document.addEventListener('visibilitychange', function () {
                if (document.hidden) stopPolling();
                else { cargarConversaciones(); startPolling(); }
            });

            window.addEventListener('beforeunload', stopPolling);
        })();
    </script>
<?php if (!$isFragment): ?>
</main>
</body>
</html>
<?php endif; ?>
