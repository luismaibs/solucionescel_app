<?php
include '../config/auth.php';
requireLogin();
include_once '../config/db.php';
include_once '../includes/fragment_helper.php';

$panelError = null;
try {
    $reparacionRepo = new ReparacionRepository($supabase);
    $timelineService = class_exists('EventoTimelineService') ? new EventoTimelineService($supabase) : null;
    $webhook_n8n = getenv('N8N_WEBHOOK_NOTIFICAR') ?: '';
    $mensajes = new MensajesService($supabase, $webhook_n8n, $timelineService);

    // 1. KPIs via RPC (agregada en BD, sin cargar todas las filas)
    $kpis = $reparacionRepo->findKpis();
    $total_activos = $kpis['activos'] ?? 0;
    $total_listos = $kpis['listos'] ?? 0;
    $total_taller = $kpis['taller'] ?? 0;
    $total_viejos = $kpis['viejos'] ?? 0;

    // 2. Plantillas de mensajes
    $plantillas = $mensajes->listarPlantillas();

    // 3. Marcas y modelos (selectores inteligentes)
    $rawMM = $reparacionRepo->findDistinctMarcasModelos();
    $marcasMap = ReparacionDashboardService::construirMarcasMap($rawMM);
    $jsonMarcas = json_encode($marcasMap);

    try {
        $equiposMarcas = $reparacionRepo->getEquiposMarcas();
    } catch (Throwable $e) {
        $equiposMarcas = [];
    }
    $jsonEquiposMarcas = json_encode($equiposMarcas);

    // 4. Usuarios para select "Ingresado por"
    $usuariosParaIngreso = $reparacionRepo->findUsuariosParaIngreso();
    $usuarioLogueado = getCurrentUsername();

    // 5. Folio sugerido
    $folioSiguiente = $reparacionRepo->getProximoFolio();

    // 6. Estados para pipeline
    $estadosPipeline = $reparacionRepo->getEstadosSistema();
    $jsonEstadosPipeline = json_encode($estadosPipeline);
} catch (Throwable $e) {
    $panelError = $e;
    error_log('Panel modules/panel.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

if ($panelError !== null) {
    header('Content-Type: text/html; charset=utf-8');
    $msg = $panelError->getMessage();
    $file = $panelError->getFile();
    $line = $panelError->getLine();
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error</title></head><body style="font-family:sans-serif;max-width:600px;margin:2rem auto;padding:1rem;background:#fef2f2;color:#991b1b;">';
    echo '<h1>Error al cargar el panel</h1><p>' . htmlspecialchars($msg) . '</p>';
    echo '<p><small>Archivo: ' . htmlspecialchars($file) . ' — Línea: ' . (int)$line . '</small></p>';
    echo '<p><a href="../login">Ir al login</a></p></body></html>';
    exit;
}
?>

<?php if (!$isFragment): ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SOLUCIONESCEL | Equipos</title>
    <?php include '../includes/head_meta.php'; ?>
    <link rel="stylesheet" href="../assets/css/panel.css" data-module-css="panel">
</head>

<body>

    <!-- NAVBAR UNIFICADO -->
    <?php include '../includes/header.php'; ?>
<?php else: ?>
    <link rel="stylesheet" href="<?= $fragment_asset_base ?>assets/css/panel.css" data-module-css="panel">
<?php endif; ?>

    <!-- Feedback Toasts -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1050;">
        <div id="liveToast" class="toast align-items-center text-bg-success border-0 rounded-4 shadow-lg" role="alert">
            <div class="d-flex">
                <div class="toast-body px-4 py-3 fw-medium"><i class="bi bi-check-circle-fill me-2"></i> <span id="toastMsg">Acción completada</span></div>
                <button type="button" class="btn-close btn-close-white me-3 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
        <div id="errorToast" class="toast align-items-center text-bg-danger border-0 rounded-4 shadow-lg" role="alert">
            <div class="d-flex">
                <div class="toast-body px-4 py-3 fw-medium"><i class="bi bi-exclamation-octagon-fill me-2"></i> <span id="errorMsg">Error</span></div>
                <button type="button" class="btn-close btn-close-white me-3 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <!-- Subheader: Panel Taller -->
    <div class="module-subheader" id="subheaderEquipos">
        <div class="module-subheader-kpis">
            <span class="module-subheader-title">Equipos</span>
            <span class="module-kpi-chip">
                <i class="bi bi-tools kpi-icon text-primary"></i>
                <span class="kpi-value"><?= $total_taller ?></span>
                <span class="kpi-label">En Taller</span>
            </span>
            <span class="module-kpi-chip">
                <i class="bi bi-check-lg kpi-icon text-success"></i>
                <span class="kpi-value"><?= $total_listos ?></span>
                <span class="kpi-label">Listos</span>
            </span>
            <span class="module-kpi-chip" <?= $total_viejos > 0 ? 'style="border-color: rgba(239,68,68,0.3);"' : '' ?>>
                <i class="bi bi-hourglass-bottom kpi-icon text-danger"></i>
                <span class="kpi-value <?= $total_viejos > 0 ? 'text-danger' : '' ?>"><?= $total_viejos ?></span>
                <span class="kpi-label">+90 días</span>
            </span>
        </div>
        <div class="module-subheader-actions">
            <?php if (function_exists('isAdmin') && isAdmin()): ?>
            <button class="btn btn-outline-light" onclick="toggleNotificacionesSection()">
                <i class="bi bi-bell-fill"></i> Notificaciones
            </button>
            <button class="btn btn-outline-light" onclick="toggleEstadosSection()">
                <i class="bi bi-diagram-3"></i> Estados
            </button>
            <?php endif; ?>
            <button class="btn btn-primary" onclick="abrirOffcanvasCrear()">
                <i class="bi bi-plus-circle-fill"></i> Nuevo Ingreso
            </button>
        </div>
    </div>

    <div id="panelEquiposContent" class="container-xl main-content-push with-subheader" style="max-width: 1440px;">

        <!-- Filtros (solo visibles en modo tabla) -->
        <div id="filtrosEstadosContainer" class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center justify-content-between mb-4 gap-3">
            <div class="d-flex gap-2 overflow-x-auto hide-scrollbar pb-1 pb-lg-0 w-100" id="filtrosChips">
                <span class="filter-chip active flex-shrink-0" onclick="filterTable('all', this)">Todos <span class="opacity-50 ms-1"><?= $total_activos ?></span></span>
                <span class="filter-chip flex-shrink-0" onclick="filterTable('en_taller', this)">Laboratorio</span>
                <span class="filter-chip flex-shrink-0" onclick="filterTable('listo', this)">Listos</span>
                <span class="filter-chip flex-shrink-0" onclick="filterTable('no_quedo', this)">No Quedó</span>
                <span class="filter-chip flex-shrink-0" onclick="filterTable('entregado', this)">Entregados</span>
                <span class="filter-chip flex-shrink-0" onclick="filterTable('inactivo', this)">Inactivos</span>
                <span class="filter-chip flex-shrink-0 text-danger border-danger" onclick="filterTable('old', this)"><i class="bi bi-exclamation-circle-fill me-1"></i>Vencidos</span>
            </div>
            <div class="d-flex align-items-center gap-3 flex-shrink-0">
                <div class="view-switch" role="group">
                    <input type="radio" class="view-switch-input" name="viewToggle" id="viewTabla" value="tabla" autocomplete="off">
                    <input type="radio" class="view-switch-input" name="viewToggle" id="viewPipeline" value="pipeline" autocomplete="off">
                    <div class="view-switch-track">
                        <label class="view-switch-option" for="viewTabla" title="Vista Tabla"><i class="bi bi-table"></i></label>
                        <label class="view-switch-option" for="viewPipeline" title="Vista Pipeline"><i class="bi bi-kanban"></i></label>
                    </div>
                </div>
                <a href="mes_azul" class="btn mes-azul-trigger view-switch-size" title="Rastreo Mes Azul" aria-label="Rastreo Mes Azul">
                    <i class="bi bi-hourglass-split"></i>
                </a>
                <div class="position-relative" style="max-width: 260px;">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="searchInput" class="form-control search-bar" placeholder="Buscar...">
                </div>
            </div>
        </div>

        <!-- Vista Tabla -->
        <div id="viewTablaContainer" class="glass-card table-container">
            <div class="app-table-wrap">
                <div class="table-responsive" style="min-height: 400px; padding-bottom: 80px;">
                    <table class="table table-custom mb-0" id="repairsTable">
                        <thead>
                            <tr>
                                <th class="ps-4">Folio</th>
                                <th>Cliente</th>
                                <th>Dispositivo</th>
                                <th>Estado</th>
                                <th>Sub Estados</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="repairsTableBody">
                            <!-- Cargado via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="repairsCardsContainer" class="app-mobile-cards-wrap" style="min-height: 300px; padding: 0 0 1rem;"></div>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center px-4 py-3 border-top border-white border-opacity-10 gap-2">
                <small class="text-muted" id="repPaginationInfo" style="font-size: 0.8rem;">Cargando reparaciones...</small>
                <div class="d-flex gap-2 ms-auto">
                    <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3" id="btnRepPrevPage" onclick="changeRepPage(-1)" disabled>
                        <i class="bi bi-chevron-left me-1"></i> Anterior
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3" id="btnRepNextPage" onclick="changeRepPage(1)" disabled>
                        Siguiente <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Vista Pipeline (Kanban) -->
        <div id="viewPipelineContainer" class="pipeline-container glass-card" style="display: none;">
            <div class="pipeline-toolbar">
                <div class="pipeline-scroll-track" id="pipelineScrollTrack">
                    <div class="pipeline-scroll-thumb" id="pipelineScrollThumb"></div>
                </div>
                <button type="button" class="pipeline-lock-btn" id="pipelineLockDrag" title="Bloquear arrastre de tarjetas" aria-pressed="false">
                    <i class="bi bi-unlock-fill" id="pipelineLockIcon" aria-hidden="true"></i>
                </button>
            </div>
            <div class="pipeline-board" id="pipelineBoard">
                <?php foreach ($estadosPipeline as $est): ?>
                <div class="pipeline-column" data-estado="<?= htmlspecialchars($est['slug']) ?>">
                    <div class="pipeline-column-header" style="border-left-color: <?= htmlspecialchars($est['color']) ?>;">
                        <span class="pipeline-column-title"><?= htmlspecialchars($est['label']) ?></span>
                        <span class="pipeline-column-count" data-count="<?= htmlspecialchars($est['slug']) ?>">0</span>
                    </div>
                    <div class="pipeline-column-cards" data-drop-zone="<?= htmlspecialchars($est['slug']) ?>">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Subheader: Configuración de Estados (oculto por defecto) -->
    <div class="module-subheader" id="subheaderEstados" style="display: none;">
        <div class="module-subheader-kpis">
            <span class="module-subheader-title" style="cursor:pointer;" onclick="toggleEstadosSection()">← Equipos</span>
            <span class="fw-semibold text-primary" style="font-size:0.95rem; padding-right:0.75rem; border-right:1px solid var(--glass-border);">
                <i class="bi bi-diagram-3 me-1"></i> Estados
            </span>
            <span class="module-kpi-chip">
                <span class="kpi-value" id="kpiTotalEstados">—</span>
                <span class="kpi-label">configurados</span>
            </span>
        </div>
        <div class="module-subheader-actions">
            <button class="btn btn-primary" onclick="openCreateModal()">
                <i class="bi bi-plus-circle-fill"></i> Nuevo Estado
            </button>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         SECCIÓN ESTADOS (oculta por defecto)
         ═══════════════════════════════════════════════════════ -->
    <div id="panelEstadosSection" class="container-xl main-content-push with-subheader" style="max-width: 1100px; display: none;">

        <!-- Primer Ingreso -->
        <div class="mb-5">
            <h5 class="text-primary fw-bold mb-2 d-flex align-items-center gap-2">
                <i class="bi bi-box-arrow-in-right"></i> Primer Ingreso
            </h5>
            <div class="glass-card p-0" style="overflow: hidden;">
                <div class="table-responsive">
                    <table class="table table-custom mb-0" id="tablaPrimerIngreso">
                        <thead>
                            <tr>
                                <th style="width: 40px;"></th>
                                <th style="width: 50px;">Color</th>
                                <th>Estado</th>
                                <th>Slug / n8n Endpoint</th>
                                <th>Plantilla</th>
                                <th style="width: 140px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyPrimerIngreso">
                            <tr><td colspan="6" class="text-center py-4 text-muted">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Re Ingreso -->
        <div class="mb-5">
            <h5 class="text-warning fw-bold mb-2 d-flex align-items-center gap-2">
                <i class="bi bi-arrow-repeat"></i> Re Ingreso
            </h5>
            <div class="glass-card p-0" style="overflow: hidden;">
                <div class="table-responsive">
                    <table class="table table-custom mb-0" id="tablaReIngreso">
                        <thead>
                            <tr>
                                <th style="width: 40px;"></th>
                                <th style="width: 50px;">Color</th>
                                <th>Estado</th>
                                <th>Slug / n8n Endpoint</th>
                                <th>Plantilla</th>
                                <th style="width: 140px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyReIngreso">
                            <tr><td colspan="6" class="text-center py-4 text-muted">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Subheader: Configuración de Notificaciones (oculto por defecto) -->
    <div class="module-subheader" id="subheaderNotificaciones" style="display: none;">
        <div class="module-subheader-kpis">
            <span class="module-subheader-title" style="cursor:pointer;" onclick="toggleNotificacionesSection()">← Equipos</span>
            <span class="fw-semibold text-warning" style="font-size:0.95rem; padding-right:0.75rem; border-right:1px solid var(--glass-border);">
                <i class="bi bi-bell-fill me-1"></i> Notificaciones
            </span>
            <span class="module-kpi-chip">
                <span class="kpi-value" id="kpiTotalNotificaciones">—</span>
                <span class="kpi-label">configuradas</span>
            </span>
        </div>
        <div class="module-subheader-actions">
            <button class="btn btn-outline-light" onclick="openCreateGrupoModal()">
                <i class="bi bi-collection"></i> Nuevo Grupo
            </button>
            <button class="btn btn-primary" onclick="openCreateNotifModal()">
                <i class="bi bi-plus-circle-fill"></i> Nueva Notificación
            </button>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         SECCIÓN NOTIFICACIONES (oculta por defecto)
         ═══════════════════════════════════════════════════════ -->
    <div id="panelNotificacionesSection" class="container-xl main-content-push with-subheader" style="max-width: 1100px; display: none;">

        <!-- Grupos -->
        <div class="mb-4" id="gruposNotifSection">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="fw-semibold text-muted small text-uppercase" style="letter-spacing: 0.05em;">Grupos</span>
            </div>
            <div id="gruposChipsContainer" class="d-flex flex-wrap gap-2">
                <span class="text-muted small">Cargando grupos...</span>
            </div>
        </div>

        <div class="mb-5">
            <div class="glass-card p-0" style="overflow: hidden;">
                <div class="table-responsive">
                    <table class="table table-custom mb-0" id="tablaNotificaciones">
                        <thead>
                            <tr>
                                <th style="width: 40px;">Icono</th>
                                <th>Título</th>
                                <th>Slug / n8n Endpoint</th>
                                <th style="width: 90px;">Tipo</th>
                                <th>Grupo</th>
                                <th>Plantilla</th>
                                <th style="width: 100px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyNotificaciones">
                            <tr><td colspan="7" class="text-center py-4 text-muted">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- ═══════════════════════════════════════════════════════
         OFFCANVAS DE ESTADOS
         ═══════════════════════════════════════════════════════ -->
    <!-- Offcanvas: Crear/Editar Estado -->
    <div class="offcanvas offcanvas-end offcanvas-custom text-white" tabindex="-1" id="offcanvasEstado" data-bs-scroll="true" data-bs-backdrop="false">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title fw-bold" id="modalEstadoTitle">Nuevo Estado</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <form id="formEstado" autocomplete="off">
                <input type="hidden" name="id" id="ef_id">
                <input type="hidden" name="parent_id" id="ef_parent_id">
                <div class="mb-4" id="ef_tipo_wrap">
                    <label class="form-label text-muted small ps-2">Tipo <span class="text-danger">*</span></label>
                    <select id="ef_tipo" class="form-select bg-dark text-white border-secondary" onchange="onTipoChange()">
                        <option value="primer_ingreso">Primer Ingreso</option>
                        <option value="re_ingreso">Re Ingreso</option>
                    </select>
                    <small class="text-muted" id="ef_tipo_help"></small>
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Nombre <span class="text-danger">*</span></label>
                    <input type="text" id="ef_nombre" class="form-control bg-dark text-white border-secondary" required maxlength="100" autocomplete="off" oninput="this.value = this.value.trimStart()">
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Descripción</label>
                    <textarea id="ef_descripcion" class="form-control bg-dark text-white border-secondary" rows="2" maxlength="300" autocomplete="off"></textarea>
                </div>
                <div class="mb-4" id="ef_color_wrap">
                    <label class="form-label text-muted small ps-2">Color</label>
                    <div class="d-flex align-items-center gap-3">
                        <input type="color" id="ef_color" class="form-control form-control-color border-0" style="width: 48px; height: 38px; padding: 2px; border-radius: 8px; cursor: pointer;" value="#3b82f6">
                        <span class="font-monospace small" id="ef_color_hex">#3b82f6</span>
                    </div>
                </div>
                <div class="mb-4" id="ef_reingreso_wrap" style="display: none;">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="ef_habilitar_reingreso">
                        <label class="form-check-label small" for="ef_habilitar_reingreso">
                            <i class="bi bi-arrow-repeat me-1"></i> Habilitar Reingreso
                        </label>
                    </div>
                    <small class="text-muted d-block mt-1">Permite que equipos en este estado puedan reactivarse bajo flujo de reingreso.</small>
                </div>
                <div class="mb-4" id="ef_seleccionable_wrap">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="ef_seleccionable" name="seleccionable" value="1" checked>
                        <label class="form-check-label small" for="ef_seleccionable">
                            <i class="bi bi-hand-index me-1"></i> Estado seleccionable directamente
                        </label>
                    </div>
                    <small class="text-muted d-block mt-1" id="ef_seleccionable_help">
                        Si está <strong class="text-success">activo</strong>: al elegir este estado se notifica a n8n de inmediato.<br>
                        Si está <strong class="text-warning">inactivo</strong>: el usuario <strong>debe elegir un sub-estado</strong> obligatoriamente.
                    </small>
                </div>
                <div class="mb-4" id="ef_slug_wrap" style="display: none;">
                    <label class="form-label text-muted small ps-2">Slug (no editable)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark text-info border-secondary font-monospace small" id="ef_slug_display" style="font-size: 0.8rem;">—</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copiarSlug()" title="Copiar slug"><i class="bi bi-copy"></i></button>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Plantilla vinculada</label>
                    <select id="ef_plantilla_id" class="form-select bg-dark text-white border-secondary">
                        <option value="">Crear automáticamente al guardar</option>
                    </select>
                    <small class="text-muted">Si no seleccionas ninguna, se creará una plantilla automática en "Automatizaciones de Estados".</small>
                </div>
                <div id="ef_error" class="alert alert-danger d-none py-2 mb-0" style="font-size: 0.85rem;"></div>
                <div class="d-grid mt-5">
                    <button type="submit" class="btn btn-primary rounded-pill py-3 fw-bold shadow-lg" id="ef_guardar_btn">
                        <i class="bi bi-check-lg me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Confirmar Eliminación -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="background-color: #1e293b; border-radius: 20px; color: #e2e8f0;">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                        <i class="bi bi-exclamation-triangle-fill text-danger fs-3"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Peligro: Eliminar Estado</h5>
                    <p class="text-muted small mb-3">Está a punto de eliminar este estado o subestado. Esta acción desconectará las funciones del módulo de plantillas y podría romper flujos activos en n8n que dependan del endpoint asignado.</p>
                    <div id="del_info" class="p-3 rounded-3 mb-4 text-start" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                        <div class="small"><span class="text-muted">Nombre:</span> <strong id="del_nombre">—</strong></div>
                        <div class="small mt-1"><span class="text-muted">Slug:</span> <code class="text-info" id="del_slug">—</code></div>
                        <div class="small mt-1"><span class="text-muted">Subestados:</span> <span id="del_subestados">0</span></div>
                    </div>
                    <p class="text-muted small mb-4">¿Desea continuar?</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger rounded-pill px-4 fw-bold" id="btnConfirmarEliminar">
                            <i class="bi bi-trash-fill me-1"></i> Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Confirmación -->
    <div class="modal fade" id="modalConfirmacion" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg" style="background-color: #1e293b; border-radius: 20px;">
                <div class="modal-body text-center p-4 text-white">
                    <div class="mb-3 bg-white bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                        <i class="bi bi-question-lg fs-3"></i>
                    </div>
                    <h5 class="fw-bold mb-2" id="confirmTitle">Confirmar</h5>
                    <p class="text-muted small mb-4" id="confirmMessage">...</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">No</button>
                        <button type="button" class="btn btn-primary rounded-pill px-4" id="btnConfirmAction">Sí</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         MODALES DE NOTIFICACIONES
         ═══════════════════════════════════════════════════════ -->
    <!-- Offcanvas: Crear/Editar Notificación -->
    <div class="offcanvas offcanvas-end offcanvas-custom text-white" tabindex="-1" id="offcanvasNotificacion" data-bs-scroll="true" data-bs-backdrop="false">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title fw-bold" id="modalNotifTitle">Nueva Notificación</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <form id="formNotificacion" autocomplete="off">
                <input type="hidden" name="id" id="nf_id">
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Título <span class="text-danger">*</span></label>
                    <input type="text" id="nf_titulo" name="titulo" class="form-control bg-dark text-white border-secondary" required maxlength="200" autocomplete="off" oninput="this.value = this.value.trimStart()">
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Mensaje</label>
                    <textarea id="nf_mensaje" name="mensaje" class="form-control bg-dark text-white border-secondary" rows="3" maxlength="500" autocomplete="off"></textarea>
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Tipo <span class="text-danger">*</span></label>
                    <select id="nf_tipo" name="tipo" class="form-select bg-dark text-white border-secondary">
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                        <option value="success">Success</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Icono (Bootstrap Icons)</label>
                    <select id="nf_icono" name="icono" class="form-select bg-dark text-white border-secondary">
                        <option value="bell-fill">🔔 Bell</option>
                        <option value="info-circle-fill">ℹ️ Info</option>
                        <option value="exclamation-triangle-fill">⚠️ Warning</option>
                        <option value="exclamation-octagon-fill">🚫 Error</option>
                        <option value="check-circle-fill">✅ Success</option>
                        <option value="megaphone-fill">📢 Megaphone</option>
                        <option value="chat-dots-fill">💬 Chat</option>
                        <option value="envelope-fill">✉️ Envelope</option>
                    </select>
                </div>
                <div class="mb-4" id="nf_slug_wrap" style="display: none;">
                    <label class="form-label text-muted small ps-2">Slug (no editable)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark text-info border-secondary font-monospace small" id="nf_slug_display" style="font-size: 0.8rem;">—</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copiarSlugNotif()" title="Copiar slug"><i class="bi bi-copy"></i></button>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Plantilla vinculada</label>
                    <select id="nf_plantilla_id" name="plantilla_id" class="form-select bg-dark text-white border-secondary">
                        <option value="">Crear automáticamente al guardar</option>
                    </select>
                    <small class="text-muted">Si no seleccionas ninguna, se creará una plantilla automática en "Automatizaciones de Notificaciones".</small>
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Grupo</label>
                    <select id="nf_grupo_id" name="grupo_id" class="form-select bg-dark text-white border-secondary">
                        <option value="">Sin grupo</option>
                    </select>
                </div>
                <div id="nf_error" class="alert alert-danger d-none py-2 mb-0" style="font-size: 0.85rem;"></div>
                <div class="d-grid mt-5">
                    <button type="submit" class="btn btn-primary rounded-pill py-3 fw-bold shadow-lg" id="nf_guardar_btn">
                        <i class="bi bi-check-lg me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Confirmar Eliminación Notificación -->
    <!-- Modal: Crear / Editar Grupo de Notificaciones -->
    <div class="modal fade" id="modalGrupoNotif" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="background-color: #1e293b; border-radius: 20px; color: #e2e8f0;">
                <div class="modal-header border-bottom border-secondary border-opacity-25">
                    <h5 class="modal-title fw-bold" id="modalGrupoTitle"><i class="bi bi-collection me-2 text-info"></i>Nuevo Grupo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="formGrupoNotif" autocomplete="off">
                        <input type="hidden" id="gn_id">
                        <div class="mb-3">
                            <label class="form-label text-muted small ps-1">Nombre del Grupo <span class="text-danger">*</span></label>
                            <input type="text" id="gn_nombre" class="form-control bg-dark text-white border-secondary" required maxlength="100" placeholder="Ej: Avisos Cliente, Operativo…">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small ps-1">Orden <span class="text-muted fw-normal">(menor = primero)</span></label>
                            <input type="number" id="gn_orden" class="form-control bg-dark text-white border-secondary" value="0" min="0" max="999">
                        </div>
                        <div id="gn_error" class="alert alert-danger d-none py-2 mb-3" style="font-size: 0.85rem;"></div>
                        <div class="d-flex gap-2 justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-danger rounded-pill px-4 d-none" id="gn_eliminar_btn" onclick="deleteGrupoFromModal()">
                                <i class="bi bi-trash me-1"></i> Eliminar
                            </button>
                            <div class="d-flex gap-2 ms-auto">
                                <button type="button" class="btn btn-outline-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" id="gn_guardar_btn">
                                    <i class="bi bi-check-lg me-1"></i> Guardar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEliminarNotif" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="background-color: #1e293b; border-radius: 20px; color: #e2e8f0;">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                        <i class="bi bi-exclamation-triangle-fill text-danger fs-3"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Eliminar Notificación</h5>
                    <p class="text-muted small mb-3">Está a punto de eliminar esta notificación. Esta acción podría romper flujos activos en n8n que dependan del endpoint asignado.</p>
                    <div id="del_notif_info" class="p-3 rounded-3 mb-4 text-start" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                        <div class="small"><span class="text-muted">Título:</span> <strong id="del_notif_nombre">—</strong></div>
                        <div class="small mt-1"><span class="text-muted">Slug:</span> <code class="text-info" id="del_notif_slug">—</code></div>
                    </div>
                    <p class="text-muted small mb-4">¿Desea continuar?</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger rounded-pill px-4 fw-bold" id="btnConfirmarEliminarNotif">
                            <i class="bi bi-trash-fill me-1"></i> Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Seleccionar Tipo de Listo (ahora dinamico para cualquier padre con hijos) -->
    <div class="modal fade" id="modalSeleccionarTipoListo" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-ios">
            <div class="modal-content modal-ios-content">
                <div class="modal-body modal-ios-body">
                    <p class="modal-ios-title" id="modalHijoTitle">Seleccionar</p>
                    <p class="modal-ios-subtitle" id="modalHijoSubtitle">Elige una opción</p>
                    <div class="modal-ios-actions" id="modalHijoActions">
                    </div>
                    <button type="button" class="modal-ios-cancel" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo Cliente (se abre al dar "Crear nuevo cliente" desde el buscador del offcanvas Crear) -->
    <div class="modal fade" id="modalNuevoCliente" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="background-color: #1e293b; border-radius: 20px; color: #e2e8f0;">
                <div class="modal-header border-bottom border-secondary border-opacity-25">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill text-primary me-2"></i>Nuevo Cliente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="formNuevoCliente" autocomplete="off">
                        <div class="mb-3">
                            <label class="form-label text-muted small ps-1">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="nc_nombre" class="form-control bg-dark text-white border-secondary" required maxlength="200" autocomplete="off" oninput="this.value = this.value.toLocaleUpperCase('es-MX')">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small ps-1">Apellido <span class="text-danger">*</span></label>
                            <input type="text" id="nc_apellido" class="form-control bg-dark text-white border-secondary" required maxlength="200" autocomplete="off" oninput="this.value = this.value.toLocaleUpperCase('es-MX')">
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-muted small ps-1">WhatsApp / Teléfono <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select id="nc_lada" class="form-select bg-dark text-white border-secondary" style="max-width: 90px;" autocomplete="off">
                                    <option value="52" selected>MX</option>
                                    <option value="+1">US</option>
                                </select>
                                <input type="tel" id="nc_telefono" class="form-control bg-dark text-white border-secondary" placeholder="10 dígitos" required maxlength="10" minlength="10" autocomplete="off" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                            </div>
                        </div>
                        <div id="nc_error" class="alert alert-danger d-none py-2 mb-3" style="font-size: 0.85rem;"></div>
                        <div class="d-flex gap-2 justify-content-end">
                            <button type="button" class="btn btn-outline-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" id="nc_guardar_btn">
                                <i class="bi bi-check-lg me-1"></i> Guardar y Vincular
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- OFFCANVAS: Historial -->
    <div class="offcanvas offcanvas-end offcanvas-custom text-white" tabindex="-1" id="offcanvasHistorial" data-bs-scroll="true" data-bs-backdrop="false">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title fw-bold"><i class="bi bi-clock-history text-warning me-2"></i>Historial <span id="hist_folio" class="text-info fs-6 font-monospace"></span></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div id="loaderHistorial" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted small mt-3">Sincronizando...</p>
            </div>
            <div id="timelineContainer"></div>
        </div>
    </div>

    <!-- OFFCANVAS: Configuración -->
    <div class="offcanvas offcanvas-end offcanvas-custom text-white" tabindex="-1" id="offcanvasConfig" data-bs-scroll="true" data-bs-backdrop="false" style="width: 550px;">
        <div class="offcanvas-header border-bottom border-secondary border-opacity-25">
            <div>
                <h5 class="offcanvas-title fw-bold">Plantillas de Mensajes</h5>
                <p class="m-0 text-muted small">Personaliza lo que n8n enviará al cliente</p>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0">
            <form method="POST" action="../api/api_reparaciones">
                <input type="hidden" name="action" value="save_templates">
                <div class="p-3">
                    <?php if (empty($plantillas)): ?>
                        <div class="text-center p-4 text-muted">
                            <i class="bi bi-database-exclamation fs-1"></i>
                            <p class="mt-2">No se encontraron plantillas. <br>Asegúrate de ejecutar el SQL de instalación.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($plantillas as $p): ?>
                        <div class="template-card">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold text-primary m-0"><?= htmlspecialchars($p['nombre']) ?></h6>
                                <span class="badge bg-secondary bg-opacity-25 text-secondary font-monospace" style="font-size: 0.7rem;"><?= htmlspecialchars($p['clave']) ?></span>
                            </div>
                            <div class="mb-2 d-flex flex-wrap gap-2">
                                <?php $standardVars = ['{{cliente}}', '{{modelo}}', '{{folio}}', '{{falla}}', '{{fecha}}']; foreach ($standardVars as $v): ?>
                                    <span class="var-badge" onclick="insertTemplateVar(this, 'tpl_<?= $p['id'] ?>')"><?= $v ?></span>
                                <?php endforeach; ?>
                            </div>
                            <textarea name="plantillas[<?= $p['id'] ?>]" id="tpl_<?= $p['id'] ?>" class="form-control bg-dark text-light border-secondary" rows="3" style="font-size: 0.9rem;"><?= htmlspecialchars($p['plantilla']) ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="p-3 border-top border-secondary border-opacity-25 sticky-bottom" style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(10px);">
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary rounded-pill py-2 fw-bold shadow-lg">
                            <i class="bi bi-save me-2"></i>Guardar Cambios
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- OFFCANVAS: Crear -->
    <div class="offcanvas offcanvas-end offcanvas-custom text-white" tabindex="-1" id="offcanvasCrear" data-bs-scroll="true" data-bs-backdrop="false">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title fw-bold">Nuevo Ingreso</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="../api/api_reparaciones" id="formCrear" autocomplete="off">
                <input type="hidden" name="action" value="create">
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Fecha de Ingreso</label>
                    <input type="date" name="fecha_ingreso" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Ingresado por</label>
                    <select name="ingresado_por" class="form-select bg-dark text-white border-secondary" required>
                        <option value="">Selecciona usuario...</option>
                        <?php foreach ($usuariosParaIngreso as $u): ?>
                            <option value="<?= htmlspecialchars($u['username']) ?>" <?= ($u['username'] === $usuarioLogueado) ? ' selected' : '' ?>>
                                <?= htmlspecialchars($u['nombre_completo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4 p-2 rounded-3" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59,130,246,0.2);">
                    <label class="form-label text-primary small ps-1 fw-bold mb-0">Folio</label>
                    <p class="text-muted small mb-0 mt-1">
                        Folio: <span class="font-monospace text-info fw-bold"><?= htmlspecialchars($folioSiguiente ?? 'SC-0001') ?></span>
                    </p>
                </div>

                <h6 class="text-primary mb-3 mt-4 border-bottom border-secondary pb-2 border-opacity-25">Datos del Cliente</h6>
                <input type="hidden" name="cliente_id" id="create_cliente_id" value="">

                <div class="mb-3" id="create_cliente_search_wrap">
                    <label class="form-label text-muted small ps-2">Cliente</label>
                    <div class="position-relative">
                        <input type="text" id="create_cliente_search" class="form-control" placeholder="NOMBRE O TELÉFONO..." autocomplete="off" oninput="this.value = this.value.toLocaleUpperCase('es-MX')">
                        <div id="create_cliente_results" class="d-none" style="position:absolute;top:100%;left:0;right:0;z-index:1055;background:#1e293b;border:1px solid rgba(255,255,255,0.1);border-radius:12px;max-height:220px;overflow-y:auto;margin-top:4px;box-shadow:0 8px 24px rgba(0,0,0,0.4);"></div>
                    </div>
                </div>

                <div id="create_cliente_selected" class="d-none mb-3 p-3 rounded-3" style="background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.25);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-primary rounded-pill mb-1">Cliente vinculado</span>
                            <div class="fw-bold text-white" id="create_cliente_selected_name"></div>
                            <div class="small text-muted" id="create_cliente_selected_phone"></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="desvincularCliente()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <input type="hidden" name="nombre" id="create_nombre">
                <div class="mb-4" id="create_telefono_wrap">
                    <label class="form-label text-muted small ps-2">WhatsApp / Teléfono</label>
                    <div class="input-group">
                        <select name="lada" id="create_lada" class="form-select bg-dark text-white border-secondary" style="max-width: 90px;" required autocomplete="off">
                            <option value="52" selected>MX</option>
                            <option value="+1">US</option>
                        </select>
                        <input type="tel" name="telefono" id="create_telefono" class="form-control" placeholder="10 dígitos" required maxlength="10" minlength="10" autocomplete="off" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                    </div>
                </div>

                <h6 class="text-primary mb-3 mt-4 border-bottom border-secondary pb-2 border-opacity-25">Datos del Equipo</h6>

                <!-- MARCA -->
                <div class="mb-3">
                    <label class="form-label text-muted small ps-2">Marca</label>
                    <div id="create_marca_select_container">
                        <select id="create_marca_select" class="form-select" autocomplete="off" onchange="handleMarcaChange(this, 'create')">
                            <option value="" disabled selected>Selecciona Marca...</option>
                            <option value="__NEW__" class="fw-bold text-info">+ Agregar nueva...</option>
                        </select>
                    </div>
                    <div id="create_marca_input_container" class="input-group d-none">
                        <input type="text" id="create_marca_input" class="form-control text-info border-end-0" placeholder="Escribe marca..." autocomplete="off">
                        <span class="input-group-text input-group-text-custom" onclick="resetToSelect('create', 'marca')"><i class="bi bi-x-lg"></i></span>
                    </div>
                    <input type="hidden" name="marca" id="create_marca_final">
                    <input type="hidden" name="equipo_marca_id" id="create_equipo_marca_id" value="">
                </div>

                <!-- MODELO -->
                <div class="mb-3">
                    <label class="form-label text-muted small ps-2">Modelo</label>
                    <div id="create_modelo_select_container">
                        <select id="create_modelo_select" class="form-select" autocomplete="off" onchange="handleModeloChange(this, 'create')">
                            <option value="" disabled selected>Primero selecciona marca...</option>
                            <option value="__NEW__" class="fw-bold text-info">+ Agregar nuevo...</option>
                        </select>
                    </div>
                    <div id="create_modelo_input_container" class="input-group d-none">
                        <input type="text" id="create_modelo_input" class="form-control text-info border-end-0" placeholder="Escribe modelo..." autocomplete="off">
                        <span class="input-group-text input-group-text-custom" onclick="resetToSelect('create', 'modelo')"><i class="bi bi-x-lg"></i></span>
                    </div>
                    <input type="hidden" name="modelo" id="create_modelo_final">
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Falla Reportada</label>
                    <textarea name="falla" class="form-control" style="height: 100px" placeholder="Describe el problema..." required autocomplete="off"></textarea>
                </div>

                <div class="d-grid mt-5">
                    <button type="submit" class="btn btn-primary rounded-pill py-3 fw-bold shadow-lg">Registrar Ingreso</button>
                </div>
            </form>
        </div>
    </div>

    <!-- OFFCANVAS: Editar -->
    <div class="offcanvas offcanvas-end offcanvas-custom text-white" tabindex="-1" id="offcanvasEditar" data-bs-scroll="true" data-bs-backdrop="false">
        <div class="offcanvas-header pt-3 pb-2 border-bottom-0">
            <h5 class="offcanvas-title fw-bold">Editar Registro</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body pt-0">
            <form method="POST" action="../api/api_reparaciones" id="formEditar" autocomplete="off">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id_interno" id="edit_id">

                <div class="mb-2">
                    <label class="form-label text-muted small ps-2 mb-1">Ingresado por</label>
                    <input type="text" id="edit_ingresado_por" class="form-control form-control-sm-custom bg-dark text-muted" readonly disabled>
                </div>
                <div class="p-2 mb-3 rounded-3" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59,130,246,0.2);">
                    <label class="form-label text-primary small ps-1 fw-bold mb-0">FOLIO (no editable)</label>
                    <input type="text" id="edit_folio" readonly class="form-control form-control-sm-custom fw-bold border-0 bg-transparent text-white">
                </div>

                <!-- EDICIÓN (estructura duplicada intencionalmente para claridad del DOM 'edit' vs 'create') -->
                <div class="mb-2"><label class="form-label text-muted small ps-2 mb-1">Cliente</label><input type="text" name="nombre" id="edit_nombre" class="form-control form-control-sm-custom" required autocomplete="off" maxlength="200" oninput="this.value = this.value.toLocaleUpperCase('es-MX')"></div>
                <div class="mb-2"><label class="form-label text-muted small ps-2 mb-1">Contacto</label><div class="input-group"><select name="lada" id="edit_lada" class="form-select form-select-sm bg-dark text-white border-secondary" style="max-width: 90px;" autocomplete="off"><option value="52">MX</option><option value="+1">US</option></select><input type="text" name="telefono" id="edit_telefono" class="form-control form-control-sm-custom" required maxlength="10" minlength="10" autocomplete="off" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)"></div></div>

                <!-- EDITAR MARCA -->
                <div class="mb-2"><label class="form-label text-muted small ps-2 mb-1">Marca</label><div id="edit_marca_select_container"><select id="edit_marca_select" class="form-select form-select-sm" autocomplete="off" onchange="handleMarcaChange(this, 'edit')"><option value="__NEW__" class="fw-bold text-info">+ Agregar nueva...</option></select></div><div id="edit_marca_input_container" class="input-group d-none"><input type="text" id="edit_marca_input" class="form-control form-control-sm text-info border-end-0" autocomplete="off"><span class="input-group-text input-group-text-custom p-1" onclick="resetToSelect('edit', 'marca')"><i class="bi bi-x-lg"></i></span></div><input type="hidden" name="marca" id="edit_marca_final"><input type="hidden" name="equipo_marca_id" id="edit_equipo_marca_id" value=""></div>

                <!-- EDITAR MODELO -->
                <div class="mb-2"><label class="form-label text-muted small ps-2 mb-1">Modelo</label><div id="edit_modelo_select_container"><select id="edit_modelo_select" class="form-select form-select-sm" autocomplete="off" onchange="handleModeloChange(this, 'edit')"><option value="__NEW__" class="fw-bold text-info">+ Agregar nuevo...</option></select></div><div id="edit_modelo_input_container" class="input-group d-none"><input type="text" id="edit_modelo_input" class="form-control form-control-sm text-info border-end-0" autocomplete="off"><span class="input-group-text input-group-text-custom p-1" onclick="resetToSelect('edit', 'modelo')"><i class="bi bi-x-lg"></i></span></div><input type="hidden" name="modelo" id="edit_modelo_final"></div>

                <div class="mb-3"><label class="form-label text-muted small ps-2 mb-1">Falla</label><textarea name="falla" id="edit_falla" class="form-control form-control-sm-custom" rows="3" required autocomplete="off"></textarea></div>

                <div class="d-grid mt-3"><button type="submit" class="btn btn-primary rounded-pill py-2 fw-bold">Guardar Cambios</button></div>
            </form>
        </div>
    </div>

    <!-- OFFCANVAS: Mensaje -->
    <div class="offcanvas offcanvas-end offcanvas-custom text-white" tabindex="-1" id="offcanvasMensaje" data-bs-scroll="true" data-bs-backdrop="false">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title fw-bold"><i class="bi bi-chat-left-text text-info me-2"></i>Enviar Mensaje</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div class="p-4 mb-4 rounded-4" style="background: linear-gradient(135deg, rgba(30,41,59,0.8), rgba(15,23,42,0.9)); border: 1px solid rgba(255,255,255,0.05);">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-secondary small text-uppercase fw-bold ls-1">Destinatario</span>
                    <span class="badge bg-primary bg-opacity-25 text-primary rounded-pill font-monospace" id="disp_folio">#000</span>
                </div>
                <div class="fw-bold text-white fs-5 mb-1" id="disp_nombre">Cliente</div>
                <div class="d-flex gap-3 text-muted small">
                    <span><i class="bi bi-whatsapp me-1 text-success"></i> <span id="disp_telefono">000</span></span>
                    <span><i class="bi bi-phone me-1"></i> <span id="disp_modelo">Modelo</span></span>
                </div>
            </div>

            <form method="POST" action="../api/api_reparaciones">
                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="id_interno" id="msg_id"><input type="hidden" name="folio" id="msg_folio"><input type="hidden" name="nombre" id="msg_nombre"><input type="hidden" name="telefono" id="msg_telefono"><input type="hidden" name="modelo" id="msg_modelo"><input type="hidden" name="fecha_ingreso" id="msg_fecha">

                <div class="mb-3">
                    <label class="form-label text-muted small ps-2">Variables disponibles</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="filter-chip py-1 px-3 small" onclick="insertTag('{{cliente}}')">Cliente</span>
                        <span class="filter-chip py-1 px-3 small" onclick="insertTag('{{folio}}')">Folio</span>
                        <span class="filter-chip py-1 px-3 small" onclick="insertTag('{{modelo}}')">Modelo</span>
                        <span class="filter-chip py-1 px-3 small" onclick="insertTag('{{falla}}')">Falla</span>
                        <span class="filter-chip py-1 px-3 small" onclick="insertTag('{{fecha}}')">Fecha</span>
                    </div>
                </div>

                <div class="mb-4">
                    <textarea name="mensaje_texto" id="msg_texto" class="form-control" style="height: 180px; font-size: 1rem; line-height: 1.5;" placeholder="Escribe tu mensaje aquí..." required></textarea>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary rounded-pill py-3 fw-bold shadow-lg"><i class="bi bi-send-fill me-2"></i>Enviar WhatsApp</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CORE SCRIPTS -->
    <script>window.PANEL_DATA = { datosMarcas: <?= $jsonMarcas ?: '{}' ?>, equiposMarcas: <?= $jsonEquiposMarcas ?: '[]' ?> };</script>
    <script>window.PIPELINE_ESTADOS = <?= $jsonEstadosPipeline ?: '[]' ?>;</script>
    <script>window.PIPELINE_PUEDE_CAMBIAR_ESTADO = true;</script>
    <?php $v = date('YmdHi'); ?>
    <script defer src="../assets/js/panel-utils.js?v=<?= $v ?>"></script>
    <script defer src="../assets/js/panel-offcanvas.js?v=<?= $v ?>"></script>
    <script defer src="../assets/js/panel-reparaciones.js?v=<?= $v ?>"></script>
    <script defer src="../assets/js/panel.js?v=<?= $v ?>"></script>

    <!-- Supabase Realtime -->
    <script>
    window.REALTIME_CONFIG = {
        url: <?= json_encode($supabase_url_for_js) ?>,
        anonKey: <?= json_encode($supabase_anon_key_for_js) ?>,
        tenantId: <?= json_encode($tenant_id_for_js) ?>,
        tables: ['reparaciones']
    };
    window.APP_DEBUG = <?= json_encode((getenv('APP_DEBUG') ?: 'false') === 'true') ?>;
    </script>
    <script defer src="../assets/js/realtime.js"></script>

<?php if (!$isFragment): ?>
    <?php include '../includes/pwa_script.php'; ?>
<?php endif; ?>

    <?php if (function_exists('isAdmin') && isAdmin()): ?>
    <link rel="stylesheet" href="../assets/css/estados.css" data-module-css="panel">
    <link rel="stylesheet" href="../assets/css/notificaciones.css" data-module-css="panel">
    <script defer src="../assets/js/estados.js?v=<?= $v ?>"></script>
    <script defer src="../assets/js/notificaciones.js?v=<?= $v ?>"></script>
    <script>
    function toggleView(showId, hideIds) {
        hideIds.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
        var show = document.getElementById(showId);
        if (show) show.style.display = '';
    }

    function toggleEstadosSection() {
        var equipos = document.getElementById('panelEquiposContent');
        var estados = document.getElementById('panelEstadosSection');
        var subEquipos = document.getElementById('subheaderEquipos');
        var subEstados = document.getElementById('subheaderEstados');
        var subNotifs = document.getElementById('subheaderNotificaciones');
        if (!equipos || !estados) return;
        var showingEstados = estados.style.display !== 'none';
        if (showingEstados) {
            toggleView('panelEquiposContent', ['panelEstadosSection', 'panelNotificacionesSection']);
            if (subEquipos) subEquipos.style.display = '';
            if (subEstados) subEstados.style.display = 'none';
        } else {
            toggleView('panelEstadosSection', ['panelEquiposContent', 'panelNotificacionesSection']);
            if (subEquipos) subEquipos.style.display = 'none';
            if (subEstados) subEstados.style.display = '';
            if (subNotifs) subNotifs.style.display = 'none';
            if (typeof loadTree === 'function') loadTree();
            if (typeof loadTemplates === 'function') loadTemplates();
        }
    }

    function toggleNotificacionesSection() {
        var notifs = document.getElementById('panelNotificacionesSection');
        var subEquipos = document.getElementById('subheaderEquipos');
        var subNotifs = document.getElementById('subheaderNotificaciones');
        var subEstados = document.getElementById('subheaderEstados');
        if (!notifs) return;
        var showingNotifs = notifs.style.display !== 'none';
        if (showingNotifs) {
            toggleView('panelEquiposContent', ['panelEstadosSection', 'panelNotificacionesSection']);
            if (subEquipos) subEquipos.style.display = '';
            if (subNotifs) subNotifs.style.display = 'none';
        } else {
            toggleView('panelNotificacionesSection', ['panelEquiposContent', 'panelEstadosSection']);
            if (subEquipos) subEquipos.style.display = 'none';
            if (subEstados) subEstados.style.display = 'none';
            if (subNotifs) subNotifs.style.display = '';
            if (typeof loadGrupos === 'function') loadGrupos();
            if (typeof loadNotificaciones === 'function') loadNotificaciones();
        }
    }
    </script>
    <?php endif; ?>
<?php if (!$isFragment): ?>
</main>
</body>
</html>
<?php endif; ?>
