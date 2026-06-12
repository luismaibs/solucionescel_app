<?php
include '../config/auth.php'; // Protección de sesión
requireLogin();

include '../config/db.php';
include_once '../includes/fragment_helper.php';

// ==========================================
// SIN LÓGICA BACKEND LEGACY
// Todo se maneja vía AJAX con los endpoints:
//   - api/inventario/categoria          (listar + delete)
//   - api/inventario/kpis               (KPIs por categoría)
//   - api/inventario/servicios_generales (crear servicio)
//   - api/inventario/crear_bateria       (crear batería)
//   - api/inventario/crear_accesorio     (crear accesorio)
//   - api/inventario/crear_pantalla      (crear pantalla)
// ==========================================
?>

<?php if (!$isFragment): ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Inventario | SOLUCIONESCEL</title>
    <?php include '../includes/head_meta.php'; ?>
    <link rel="stylesheet" href="../assets/css/inventario.css" data-module-css="inventario">

</head>

<body>

    <!-- NAVBAR -->
    <?php include '../includes/header.php'; ?>
<?php else: ?>
    <link rel="stylesheet" href="<?= $fragment_asset_base ?>assets/css/inventario.css" data-module-css="inventario">
<?php endif; ?>

    <!-- Feedback Toasts -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1050;">
        <div id="liveToast" class="toast align-items-center text-bg-success border-0 rounded-4 shadow-lg" role="alert">
            <div class="d-flex">
                <div class="toast-body px-4 py-3 fw-medium"><i class="bi bi-check-circle-fill me-2"></i> <span
                        id="toastMsg">Operación exitosa</span></div><button type="button"
                    class="btn-close btn-close-white me-3 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
        <div id="errorToast" class="toast align-items-center text-bg-danger border-0 rounded-4 shadow-lg" role="alert">
            <div class="d-flex">
                <div class="toast-body px-4 py-3 fw-medium"><i class="bi bi-exclamation-octagon-fill me-2"></i> <span
                        id="errorMsg">Error</span></div><button type="button"
                    class="btn-close btn-close-white me-3 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <!-- Modal Confirmación -->
    <div class="modal fade" id="modalConfirmacion" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg" style="background-color: #1e293b; border-radius: 20px;">
                <div class="modal-body text-center p-4 text-white">
                    <div class="mb-3 bg-white bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center"
                        style="width: 64px; height: 64px;">
                        <i class="bi bi-question-lg fs-3"></i>
                    </div>
                    <h5 class="fw-bold mb-2" id="confirmTitle">Confirmar</h5>
                    <p class="text-muted small mb-4" id="confirmMessage">...</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light rounded-pill px-4"
                            data-bs-dismiss="modal">No</button>
                        <button type="button" class="btn btn-primary rounded-pill px-4"
                            id="btnConfirmAction">Sí</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-xl main-content-push with-subheader" style="max-width: 1440px;">

        <!-- Subheader: título + KPI chips + Nuevo Producto (izquierda) + acciones (derecha) -->
        <div class="module-subheader">
            <div class="module-subheader-kpis">
                <span class="module-subheader-title">Inventario</span>
                <div id="kpiCards" style="display: contents;">
                    <span class="module-kpi-chip">
                        <i class="bi bi-hourglass-split kpi-icon text-secondary"></i>
                        <span class="kpi-value">—</span>
                        <span class="kpi-label">CARGANDO</span>
                    </span>
                </div>
            </div>
            <div class="module-subheader-actions">
                <button class="btn btn-outline-light" onclick="abrirModalImportar()"
                    style="border-color: rgba(255,255,255,0.2);">
                    <i class="bi bi-upload"></i> Importar
                </button>
                <?php if (isAdmin()): ?>
                <button class="btn btn-outline-warning" onclick="reindexarInventario()"
                    style="border-color: rgba(245,158,11,0.3);">
                    <i class="bi bi-cpu"></i> Reindexar IA
                </button>
                <?php endif; ?>
                <button class="btn btn-primary" onclick="abrirOffcanvasCrear()">
                    <i class="bi bi-plus-circle-fill"></i> Nuevo Producto
                </button>
            </div>
        </div>

        <!-- Filtros de Categoría (navegación principal) + Búsqueda -->
        <div
            class="filters-row-wrap d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center justify-content-between mb-4 gap-3">

            <!-- Filtros — 4 categorías (ordenados alfabéticamente) -->
            <div class="d-flex gap-2 overflow-x-auto hide-scrollbar pb-1 pb-lg-0 w-100">
                <span class="filter-chip active flex-shrink-0" data-cat="accesorios" onclick="cambiarCategoria('accesorios', this)">
                    <i class="bi bi-headphones me-1"></i> Accesorios
                </span>
                <span class="filter-chip flex-shrink-0" data-cat="baterias" onclick="cambiarCategoria('baterias', this)">
                    <i class="bi bi-battery-charging me-1"></i> Baterías
                </span>
                <span class="filter-chip flex-shrink-0" data-cat="pantallas" onclick="cambiarCategoria('pantallas', this)">
                    <i class="bi bi-phone me-1"></i> Pantallas
                </span>
                <span class="filter-chip flex-shrink-0" data-cat="servicios" onclick="cambiarCategoria('servicios', this)">
                    <i class="bi bi-gear-wide-connected me-1"></i> Servicios Generales
                </span>
            </div>

            <!-- Buscador -->
            <div class="position-relative flex-shrink-0 w-100" style="max-width: 300px;">
                <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                <input type="text" id="searchInput" class="form-control search-bar" placeholder="Buscar...">
            </div>
        </div>

        <!-- Tabla dinámica por categoría -->
        <div class="glass-card table-container">
            <div class="app-table-wrap">
                <div class="table-responsive">
                    <table class="table table-custom mb-0" id="invTable">
                        <thead id="invTableHead">
                            <!-- Se genera dinámicamente según categoría -->
                        </thead>
                        <tbody id="invTableBody">
                            <!-- Filas cargadas dinámicamente vía AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="invCardsContainer" class="app-mobile-cards-wrap" style="min-height: 200px; padding: 0 0 1rem;"></div>

            <!-- Paginación -->
            <div
                class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center px-4 py-3 border-top border-white border-opacity-10 gap-2">
                <small class="text-muted" id="invPaginationInfo" style="font-size: 0.8rem;">
                    Cargando inventario...
                </small>
                <div class="d-flex gap-2 ms-auto">
                    <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3"
                        id="btnPrevPage" onclick="changeInvPage(-1)" disabled>
                        <i class="bi bi-chevron-left me-1"></i> Anterior
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3"
                        id="btnNextPage" onclick="changeInvPage(1)" disabled>
                        Siguiente <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Selección de Categoría -->
    <div class="modal fade" id="modalSeleccionCategoria" tabindex="-1" aria-labelledby="modalSeleccionCategoriaLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-cat-selector">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalSeleccionCategoriaLabel">Selecciona la categoría del producto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3 pb-4">
                    <p class="text-muted mb-4" style="font-size: 0.9rem;">Elige la categoría para abrir el formulario correspondiente.</p>
                    <div class="row g-3">
                        <!-- Accesorios -->
                        <div class="col-6 col-md-3">
                            <button type="button" class="cat-selector-card w-100" onclick="abrirFormAccesorios()">
                                <div class="cat-selector-icon" style="background: rgba(168, 85, 247, 0.15); color: #c084fc;">
                                    <i class="bi bi-headphones"></i>
                                </div>
                                <span class="cat-selector-label">Accesorios</span>
                            </button>
                        </div>
                        <!-- Baterías -->
                        <div class="col-6 col-md-3">
                            <button type="button" class="cat-selector-card w-100" onclick="abrirFormBaterias()">
                                <div class="cat-selector-icon" style="background: rgba(34, 197, 94, 0.15); color: #4ade80;">
                                    <i class="bi bi-battery-charging"></i>
                                </div>
                                <span class="cat-selector-label">Baterías</span>
                            </button>
                        </div>
                        <!-- Pantallas -->
                        <div class="col-6 col-md-3">
                            <button type="button" class="cat-selector-card w-100" onclick="abrirFormPantallas()">
                                <div class="cat-selector-icon" style="background: rgba(6, 182, 212, 0.15); color: #22d3ee;">
                                    <i class="bi bi-phone"></i>
                                </div>
                                <span class="cat-selector-label">Pantallas</span>
                            </button>
                        </div>
                        <!-- Servicios Generales -->
                        <div class="col-6 col-md-3">
                            <button type="button" class="cat-selector-card w-100" onclick="abrirFormServiciosGenerales()">
                                <div class="cat-selector-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
                                    <i class="bi bi-gear-wide-connected"></i>
                                </div>
                                <span class="cat-selector-label">Servicios Generales</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Importar inventario (3 pasos) -->
    <div class="modal fade" id="modalImportarInventario" tabindex="-1" aria-labelledby="modalImportarLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content modal-cat-selector">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalImportarLabel">Importar inventario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body pt-3 pb-4">
                    <!-- Indicador de pasos -->
                    <div class="d-flex justify-content-between mb-4" style="max-width: 320px; margin: 0 auto;">
                        <div class="d-flex flex-column align-items-center">
                            <span class="rounded-circle d-inline-flex align-items-center justify-content-center import-step-dot active" id="importStepDot1" style="width:32px;height:32px;background:var(--primary);color:#fff;">1</span>
                            <small class="text-muted mt-1">Categoría</small>
                        </div>
                        <div class="align-self-center flex-grow-1" style="height:2px;background:rgba(255,255,255,0.1); margin:0 8px; margin-bottom: 20px;"></div>
                        <div class="d-flex flex-column align-items-center">
                            <span class="rounded-circle d-inline-flex align-items-center justify-content-center import-step-dot" id="importStepDot2" style="width:32px;height:32px;background:rgba(255,255,255,0.1);color:#94a3b8;">2</span>
                            <small class="text-muted mt-1">Plantilla</small>
                        </div>
                        <div class="align-self-center flex-grow-1" style="height:2px;background:rgba(255,255,255,0.1); margin:0 8px; margin-bottom: 20px;"></div>
                        <div class="d-flex flex-column align-items-center">
                            <span class="rounded-circle d-inline-flex align-items-center justify-content-center import-step-dot" id="importStepDot3" style="width:32px;height:32px;background:rgba(255,255,255,0.1);color:#94a3b8;">3</span>
                            <small class="text-muted mt-1">Archivo</small>
                        </div>
                    </div>

                    <!-- PASO 1: Selección de categoría -->
                    <div id="importPaso1" class="import-paso">
                        <p class="text-muted mb-3">Selecciona la categoría a la que deseas importar datos.</p>
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <button type="button" class="cat-selector-card w-100" data-import-cat="accesorios" onclick="seleccionarCategoriaImportar('accesorios', this)">
                                    <div class="cat-selector-icon" style="background: rgba(168, 85, 247, 0.15); color: #c084fc;"><i class="bi bi-headphones"></i></div>
                                    <span class="cat-selector-label">Accesorios</span>
                                </button>
                            </div>
                            <div class="col-6 col-md-3">
                                <button type="button" class="cat-selector-card w-100" data-import-cat="baterias" onclick="seleccionarCategoriaImportar('baterias', this)">
                                    <div class="cat-selector-icon" style="background: rgba(34, 197, 94, 0.15); color: #4ade80;"><i class="bi bi-battery-charging"></i></div>
                                    <span class="cat-selector-label">Baterías</span>
                                </button>
                            </div>
                            <div class="col-6 col-md-3">
                                <button type="button" class="cat-selector-card w-100" data-import-cat="pantallas" onclick="seleccionarCategoriaImportar('pantallas', this)">
                                    <div class="cat-selector-icon" style="background: rgba(6, 182, 212, 0.15); color: #22d3ee;"><i class="bi bi-phone"></i></div>
                                    <span class="cat-selector-label">Pantallas</span>
                                </button>
                            </div>
                            <div class="col-6 col-md-3">
                                <button type="button" class="cat-selector-card w-100" data-import-cat="servicios" onclick="seleccionarCategoriaImportar('servicios', this)">
                                    <div class="cat-selector-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;"><i class="bi bi-gear-wide-connected"></i></div>
                                    <span class="cat-selector-label">Servicios</span>
                                </button>
                            </div>
                        </div>
                        <div class="mt-3 text-end">
                            <button type="button" class="btn btn-primary" id="btnImportPaso1Siguiente" disabled onclick="importarIrPaso(2)">Siguiente</button>
                        </div>
                    </div>

                    <!-- PASO 2: Descarga de plantilla -->
                    <div id="importPaso2" class="import-paso d-none">
                        <p class="text-muted mb-3">Descarga la plantilla Excel con las columnas correctas y un ejemplo. Luego complétala con tus datos.</p>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="button" class="btn btn-outline-success d-inline-flex align-items-center gap-2" id="btnDescargarPlantilla" onclick="descargarPlantillaImportar()">
                                <i class="bi bi-file-earmark-excel"></i> Descargar Excel de prueba
                            </button>
                            <span class="text-muted small" id="importCategoriaNombre"></span>
                        </div>
                        <div class="mt-3 d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-light" onclick="importarIrPaso(1)">Atrás</button>
                            <button type="button" class="btn btn-primary" onclick="importarIrPaso(3)">Siguiente</button>
                        </div>
                    </div>

                    <!-- PASO 3: Adjuntar archivo e importar -->
                    <div id="importPaso3" class="import-paso d-none">
                        <p class="text-muted mb-3">Adjunta un archivo .xlsx o .csv con las columnas de la plantilla.</p>
                        <div class="mb-3">
                            <input type="file" id="importFileInput" class="form-control" accept=".xlsx,.xls,.csv" style="max-width: 100%;">
                            <small class="text-muted">Formatos aceptados: Excel (.xlsx, .xls) o CSV (.csv)</small>
                        </div>
                        <div id="importValidacionAlert" class="alert d-none mb-3"></div>
                        <div id="importFilasInvalidas" class="d-none mb-3">
                            <h6 class="text-warning mb-2">Filas con datos incompletos (campos obligatorios):</h6>
                            <ul id="importFilasInvalidasLista" class="small text-muted mb-0"></ul>
                            <p class="small text-muted mt-2">Corrige el archivo o confirma para importar solo las filas válidas.</p>
                        </div>
                        <div id="importProgress" class="d-none mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted">Progreso de importación</small>
                                <small class="fw-bold" id="importProgressPercent" style="color: var(--primary);">0%</small>
                            </div>
                            <div class="progress" style="height: 8px; background: rgba(255,255,255,0.08);">
                                <div id="importProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%; background: var(--primary);"></div>
                            </div>
                            <small id="importProgressDetail" class="text-muted mt-1" style="font-size: 0.75rem;">Procesando 0 de 0 registros...</small>
                        </div>
                        <div id="importResultado" class="d-none mb-3"></div>
                        <div class="mt-3 d-flex justify-content-between flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-light" onclick="importarIrPaso(2)">Atrás</button>
                            <button type="button" class="btn btn-primary" id="btnImportarEnviar" disabled onclick="enviarImportacion()">
                                <i class="bi bi-upload me-1"></i> Importar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- OFFCANVAS: Formulario Servicios Generales -->
    <div class="offcanvas offcanvas-end offcanvas-custom text-white" tabindex="-1" id="offcanvasServicioGeneral"
        data-bs-scroll="true" data-bs-backdrop="false">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title fw-bold">Nuevo Servicio General</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <form id="formServicioGeneral" novalidate>

                <!-- 1. Subcategoría -->
                <div class="form-section">
                    <div class="mb-0">
                        <label class="form-label">Subcategoría</label>
                        <select name="subcategoria" id="sgSubcategoria" class="form-select" required>
                            <option value="" disabled selected>Selecciona...</option>
                            <option value="desbloqueo">Desbloqueo</option>
                            <option value="liberaciones">Liberaciones</option>
                            <option value="servicios">Servicios</option>
                            <option value="reparaciones">Reparaciones</option>
                            <option value="software">Software</option>
                        </select>
                    </div>
                </div>

                <!-- 2. Acciones a Realizar -->
                <div class="form-section">
                    <label class="form-label">Acciones a Realizar</label>
                    <div class="input-group mb-2">
                        <input type="text" id="sgAccionInput" class="form-control"
                            placeholder="Escribe una acción..." style="border-right: none; border-radius: 12px 0 0 12px;">
                        <button type="button" class="btn btn-outline-primary" id="btnAddAccion"
                            style="border-radius: 0 12px 12px 0; border-left: none;">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <div id="accionesContainer" class="d-flex flex-wrap gap-2"></div>
                    <input type="hidden" name="acciones" id="accionesHidden">
                </div>

                <!-- 3. Gama (Selector visual) -->
                <div class="form-section">
                    <label class="form-label">Gama</label>
                    <div class="gama-grid">
                        <button type="button" class="gama-option" data-value="baja">Baja</button>
                        <button type="button" class="gama-option" data-value="media">Media</button>
                        <button type="button" class="gama-option" data-value="alta">Alta</button>
                        <button type="button" class="gama-option" data-value="premium">Premium</button>
                        <button type="button" class="gama-option" data-value="s.premium">S.Premium</button>
                        <button type="button" class="gama-option" data-value="todas las gamas">Todas</button>
                    </div>
                    <input type="hidden" name="gama" id="gamaHidden" required>
                </div>

                <!-- 4. Sistema Operativo (Checkboxes) -->
                <div class="form-section">
                    <label class="form-label">Sistema Operativo</label>
                    <div class="os-check-grid">
                        <label class="os-check-item">
                            <input type="checkbox" name="sistemas_operativos[]" value="Android">
                            <span class="os-check-label"><i class="bi bi-android2 me-1"></i>Android</span>
                        </label>
                        <label class="os-check-item">
                            <input type="checkbox" name="sistemas_operativos[]" value="iPhone OS">
                            <span class="os-check-label"><i class="bi bi-apple me-1"></i>iPhone OS</span>
                        </label>
                        <label class="os-check-item">
                            <input type="checkbox" name="sistemas_operativos[]" value="Windows">
                            <span class="os-check-label"><i class="bi bi-windows me-1"></i>Windows</span>
                        </label>
                        <label class="os-check-item">
                            <input type="checkbox" name="sistemas_operativos[]" value="macOS">
                            <span class="os-check-label"><i class="bi bi-laptop me-1"></i>macOS</span>
                        </label>
                        <label class="os-check-item">
                            <input type="checkbox" name="sistemas_operativos[]" value="iPadOS">
                            <span class="os-check-label"><i class="bi bi-tablet me-1"></i>iPadOS</span>
                        </label>
                        <label class="os-check-item">
                            <input type="checkbox" name="sistemas_operativos[]" value="Otros">
                            <span class="os-check-label"><i class="bi bi-three-dots me-1"></i>Otros</span>
                        </label>
                    </div>
                </div>

                <!-- 5. Garantía (Toggle) -->
                <div class="form-section">
                    <div class="d-flex align-items-center justify-content-between">
                        <label class="form-label mb-0">Garantía</label>
                        <div class="form-check form-switch form-switch-lg">
                            <input class="form-check-input custom-switch" type="checkbox" role="switch"
                                id="garantiaSwitch">
                            <label class="form-check-label ms-2" id="garantiaLabel"
                                style="font-size: 0.85rem; color: rgba(248,250,252,0.6);">NO</label>
                        </div>
                    </div>
                    <input type="hidden" name="garantia" id="garantiaHidden" value="NO">
                </div>

                <!-- 6. Tiempo de Entrega -->
                <div class="form-section">
                    <div class="mb-0">
                        <label class="form-label">Tiempo de Entrega</label>
                        <input type="text" name="tiempo_entrega" id="sgTiempoEntrega" class="form-control"
                            placeholder="Ej: 24 horas, 2-3 días, inmediato, etc.">
                    </div>
                </div>

                <!-- 7. Precio -->
                <div class="form-section">
                    <div class="mb-0">
                        <label class="form-label">Precio</label>
                        <div class="input-group">
                            <span class="input-group-text" style="background: rgba(255,255,255,0.04); border: 1.5px solid rgba(255,255,255,0.08); color: #4ade80; font-weight: 600; border-radius: 12px 0 0 12px;">$</span>
                            <input type="number" step="0.01" min="0" name="precio" id="sgPrecio"
                                class="form-control" placeholder="0.00" required
                                style="border-radius: 0 12px 12px 0;">
                        </div>
                    </div>
                </div>

                <!-- 8. Nota -->
                <div class="form-section" style="border-bottom: none;">
                    <div class="mb-0">
                        <label class="form-label">Nota</label>
                        <textarea name="nota" id="sgNota" class="form-control" rows="3"
                            placeholder="Notas o comentarios adicionales sobre el servicio..."></textarea>
                    </div>
                </div>

                <!-- Feedback de errores -->
                <div id="sgFeedback" class="d-none mb-3" style="font-size: 0.85rem;"></div>

                <!-- Botones -->
                <div class="d-grid gap-2 mt-2">
                    <button type="submit" class="btn btn-primary py-3" id="btnGuardarServicio">
                        <i class="bi bi-check-circle me-2"></i>Guardar Servicio
                    </button>
                    <button type="button" class="btn btn-outline-light py-2" data-bs-dismiss="offcanvas"
                        style="border-radius: 12px; border-color: rgba(255,255,255,0.1);">
                        Cancelar
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- OFFCANVAS: Formulario BATERÍAS -->
    <div class="offcanvas offcanvas-end offcanvas-custom text-white" tabindex="-1" id="offcanvasBateria"
        data-bs-scroll="true" data-bs-backdrop="false">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title fw-bold">Nueva Batería</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <form id="formBateria" novalidate>
                <!-- 1. Marca -->
                <div class="form-section">
                    <label class="form-label">Marca</label>
                    <input type="text" name="marca" id="batMarca" class="form-control" placeholder="Ej: Samsung, Apple..." required>
                </div>
                <!-- 1b. Código, Precio, Stock -->
                <div class="form-section row g-2">
                    <div class="col-4">
                        <label class="form-label">Código</label>
                        <input type="text" name="codigo" id="batCodigo" class="form-control" placeholder="Ej: BAT-001">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Precio</label>
                        <input type="number" name="precio" id="batPrecio" class="form-control" placeholder="0" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Stock</label>
                        <input type="number" name="stock" id="batStock" class="form-control" placeholder="0" min="0" value="0">
                    </div>
                </div>
                <!-- 2. Calidad (multi) -->
                <div class="form-section">
                    <label class="form-label">Calidad</label>
                    <div class="os-check-grid" style="grid-template-columns: repeat(3, 1fr);">
                        <label class="os-check-item">
                            <input type="checkbox" name="bat_calidad[]" value="Genérico">
                            <span class="os-check-label">Genérico</span>
                        </label>
                        <label class="os-check-item">
                            <input type="checkbox" name="bat_calidad[]" value="Larga duración">
                            <span class="os-check-label">Larga duración</span>
                        </label>
                        <label class="os-check-item">
                            <input type="checkbox" name="bat_calidad[]" value="Original">
                            <span class="os-check-label">Original</span>
                        </label>
                    </div>
                </div>
                <!-- 3. Tipo (multi) -->
                <div class="form-section">
                    <label class="form-label">Tipo</label>
                    <div class="os-check-grid" style="grid-template-columns: repeat(2, 1fr);">
                        <label class="os-check-item">
                            <input type="checkbox" name="bat_tipo[]" value="Interna">
                            <span class="os-check-label"><i class="bi bi-cpu me-1"></i>Interna</span>
                        </label>
                        <label class="os-check-item">
                            <input type="checkbox" name="bat_tipo[]" value="Externa">
                            <span class="os-check-label"><i class="bi bi-battery-full me-1"></i>Externa</span>
                        </label>
                    </div>
                </div>
                <!-- 4. Modelo de Batería -->
                <div class="form-section">
                    <label class="form-label">Modelo de Batería</label>
                    <input type="text" name="modelo_bateria" id="batModelo" class="form-control" placeholder="Ej: BLP927, A2479..." required>
                </div>
                <!-- 5. Tiempo (multi) -->
                <div class="form-section">
                    <label class="form-label">Tiempo de Entrega</label>
                    <div class="os-check-grid" style="grid-template-columns: 1fr;">
                        <label class="os-check-item">
                            <input type="checkbox" name="bat_tiempo[]" value="Instalación inmediata 4hrs">
                            <span class="os-check-label"><i class="bi bi-lightning-charge me-1"></i>Instalación inmediata (4 horas)</span>
                        </label>
                        <label class="os-check-item">
                            <input type="checkbox" name="bat_tiempo[]" value="2-3 días full">
                            <span class="os-check-label"><i class="bi bi-clock me-1"></i>2-3 días (Full)</span>
                        </label>
                        <label class="os-check-item">
                            <input type="checkbox" name="bat_tiempo[]" value="3-5 días estándar">
                            <span class="os-check-label"><i class="bi bi-calendar3 me-1"></i>3-5 días (Estándar)</span>
                        </label>
                        <label class="os-check-item">
                            <input type="checkbox" name="bat_tiempo[]" value="Envío internacional 20-30 días">
                            <span class="os-check-label"><i class="bi bi-globe me-1"></i>Envío internacional (20-30 días)</span>
                        </label>
                    </div>
                </div>
                <!-- 6. Notas -->
                <div class="form-section" style="border-bottom: none;">
                    <label class="form-label">Notas</label>
                    <textarea name="notas" id="batNotas" class="form-control" rows="3" placeholder="Notas adicionales..."></textarea>
                </div>
                <div id="batFeedback" class="d-none mb-3" style="font-size: 0.85rem;"></div>
                <div class="d-grid gap-2 mt-2">
                    <button type="submit" class="btn btn-primary py-3" id="btnGuardarBateria">
                        <i class="bi bi-check-circle me-2"></i>Guardar Batería
                    </button>
                    <button type="button" class="btn btn-outline-light py-2" data-bs-dismiss="offcanvas"
                        style="border-radius: 12px; border-color: rgba(255,255,255,0.1);">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- OFFCANVAS: Formulario ACCESORIOS -->
    <div class="offcanvas offcanvas-end offcanvas-custom text-white" tabindex="-1" id="offcanvasAccesorio"
        data-bs-scroll="true" data-bs-backdrop="false">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title fw-bold">Nuevo Accesorio</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <form id="formAccesorio" novalidate>
                <!-- 1. Subcategoría dinámica -->
                <div class="form-section">
                    <label class="form-label">Subcategoría</label>
                    <div class="d-flex gap-2">
                        <select name="subcategoria_id" id="accSubcategoria" class="form-select flex-grow-1" required>
                            <option value="" disabled selected>Cargando...</option>
                        </select>
                        <button type="button" class="btn btn-outline-primary flex-shrink-0 btn-add-catalog"
                            onclick="abrirModalCatalogo('acc_subcategoria', 'subcategoría')" title="Agregar nueva">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>
                <!-- 2. Marca dinámica -->
                <div class="form-section">
                    <label class="form-label">Marca</label>
                    <div class="d-flex gap-2">
                        <select name="marca_id" id="accMarca" class="form-select flex-grow-1" required>
                            <option value="" disabled selected>Cargando...</option>
                        </select>
                        <button type="button" class="btn btn-outline-primary flex-shrink-0 btn-add-catalog"
                            onclick="abrirModalCatalogo('acc_marca', 'marca')" title="Agregar nueva">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>
                <!-- 3. Código -->
                <div class="form-section">
                    <label class="form-label">Código</label>
                    <input type="text" name="codigo" id="accCodigo" class="form-control" placeholder="Ej: ACC-001" required>
                </div>
                <!-- 4. Nombre del Producto -->
                <div class="form-section">
                    <label class="form-label">Nombre del Producto</label>
                    <input type="text" name="nombre_producto" id="accNombre" class="form-control" placeholder="Ej: Funda Silicon iPhone 15" required>
                </div>
                <!-- 5. Stock + 6. Precio -->
                <div class="form-section">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Stock</label>
                            <div class="input-group">
                                <span class="input-group-text input-prefix">#</span>
                                <input type="number" min="0" name="stock" id="accStock" class="form-control" value="1" required style="border-radius: 0 12px 12px 0;">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Precio</label>
                            <div class="input-group">
                                <span class="input-group-text input-prefix" style="color: #4ade80;">$</span>
                                <input type="number" step="0.01" min="0" name="precio" id="accPrecio" class="form-control" placeholder="0.00" required style="border-radius: 0 12px 12px 0;">
                            </div>
                        </div>
                    </div>
                </div>
                <!-- 7. Color dinámico -->
                <div class="form-section" style="border-bottom: none;">
                    <label class="form-label">Color</label>
                    <div class="d-flex gap-2">
                        <select name="color_id" id="accColor" class="form-select flex-grow-1" required>
                            <option value="" disabled selected>Cargando...</option>
                        </select>
                        <button type="button" class="btn btn-outline-primary flex-shrink-0 btn-add-catalog"
                            onclick="abrirModalCatalogo('acc_color', 'color')" title="Agregar nuevo">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>
                <div id="accFeedback" class="d-none mb-3" style="font-size: 0.85rem;"></div>
                <div class="d-grid gap-2 mt-2">
                    <button type="submit" class="btn btn-primary py-3" id="btnGuardarAccesorio">
                        <i class="bi bi-check-circle me-2"></i>Guardar Producto
                    </button>
                    <button type="button" class="btn btn-outline-light py-2" data-bs-dismiss="offcanvas"
                        style="border-radius: 12px; border-color: rgba(255,255,255,0.1);">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- OFFCANVAS: Formulario PANTALLAS -->
    <div class="offcanvas offcanvas-end offcanvas-custom text-white" tabindex="-1" id="offcanvasPantalla"
        data-bs-scroll="true" data-bs-backdrop="false">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title fw-bold">Nueva Pantalla</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <form id="formPantalla" novalidate>
                <!-- 1. Modelo dinámico -->
                <div class="form-section">
                    <label class="form-label">Modelo</label>
                    <div class="d-flex gap-2">
                        <select name="modelo_id" id="panModelo" class="form-select flex-grow-1" required>
                            <option value="" disabled selected>Cargando...</option>
                        </select>
                        <button type="button" class="btn btn-outline-primary flex-shrink-0 btn-add-catalog"
                            onclick="abrirModalCatalogo('pan_modelo', 'modelo')" title="Agregar nuevo">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>
                <!-- 2. Modelo Técnico dinámico -->
                <div class="form-section">
                    <label class="form-label">Modelo Técnico</label>
                    <div class="d-flex gap-2">
                        <select name="modelo_tecnico_id" id="panModeloTecnico" class="form-select flex-grow-1" required>
                            <option value="" disabled selected>Cargando...</option>
                        </select>
                        <button type="button" class="btn btn-outline-primary flex-shrink-0 btn-add-catalog"
                            onclick="abrirModalCatalogo('pan_modelo_tecnico', 'modelo técnico')" title="Agregar nuevo">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>
                <!-- 3. Calidad (selección única - radio visual) -->
                <div class="form-section">
                    <label class="form-label">Calidad</label>
                    <div class="gama-grid" style="grid-template-columns: repeat(3, 1fr);">
                        <button type="button" class="gama-option pan-calidad-opt" data-value="Original">Original</button>
                        <button type="button" class="gama-option pan-calidad-opt" data-value="Intermedio">Intermedio</button>
                        <button type="button" class="gama-option pan-calidad-opt" data-value="Genérico">Genérico</button>
                    </div>
                    <input type="hidden" name="calidad" id="panCalidadHidden" required>
                </div>
                <!-- 4. Precio -->
                <div class="form-section">
                    <label class="form-label">Precio</label>
                    <div class="input-group">
                        <span class="input-group-text input-prefix" style="color: #4ade80;">$</span>
                        <input type="number" step="0.01" min="0" name="precio" id="panPrecio" class="form-control" placeholder="0.00" required style="border-radius: 0 12px 12px 0;">
                    </div>
                </div>
                <!-- 5. Tiempo (selección única - radio visual) -->
                <div class="form-section">
                    <label class="form-label">Tiempo de Entrega</label>
                    <div class="gama-grid" style="grid-template-columns: 1fr;">
                        <button type="button" class="gama-option pan-tiempo-opt" data-value="Instalación inmediata 4hrs">
                            <i class="bi bi-lightning-charge me-1"></i>Instalación inmediata (4 horas)
                        </button>
                        <button type="button" class="gama-option pan-tiempo-opt" data-value="2-3 días full">
                            <i class="bi bi-clock me-1"></i>2-3 días (Full)
                        </button>
                        <button type="button" class="gama-option pan-tiempo-opt" data-value="3-5 días estándar">
                            <i class="bi bi-calendar3 me-1"></i>3-5 días (Estándar)
                        </button>
                        <button type="button" class="gama-option pan-tiempo-opt" data-value="Envío internacional 20-30 días">
                            <i class="bi bi-globe me-1"></i>Envío internacional (20-30 días)
                        </button>
                    </div>
                    <input type="hidden" name="tiempo" id="panTiempoHidden" required>
                </div>
                <!-- 6. Nota -->
                <div class="form-section" style="border-bottom: none;">
                    <label class="form-label">Nota</label>
                    <textarea name="nota" id="panNota" class="form-control" rows="3" placeholder="Notas adicionales..."></textarea>
                </div>
                <div id="panFeedback" class="d-none mb-3" style="font-size: 0.85rem;"></div>
                <div class="d-grid gap-2 mt-2">
                    <button type="submit" class="btn btn-primary py-3" id="btnGuardarPantalla">
                        <i class="bi bi-check-circle me-2"></i>Guardar Pantalla
                    </button>
                    <button type="button" class="btn btn-outline-light py-2" data-bs-dismiss="offcanvas"
                        style="border-radius: 12px; border-color: rgba(255,255,255,0.1);">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL REUTILIZABLE: Agregar nuevo valor a catálogo        -->
    <div class="modal fade" id="modalAgregarCatalogo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content modal-cat-selector" style="border-radius: var(--radius-lg);">
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title fw-bold" id="modalCatalogoTitle">Agregar nuevo</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3 pb-4">
                    <input type="text" id="catalogoNombreInput" class="form-control mb-3" placeholder="Nombre...">
                    <div id="catalogoFeedback" class="d-none mb-3" style="font-size: 0.85rem;"></div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary py-2" id="btnGuardarCatalogo">
                            <i class="bi bi-check-circle me-1"></i>Guardar
                        </button>
                        <button type="button" class="btn btn-outline-light py-2" data-bs-dismiss="modal"
                            style="border-radius: 12px; border-color: rgba(255,255,255,0.1); font-size: 0.85rem;">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SheetJS (lazy: carga dinámica sin bloquear parsing) -->
    <script>
    (function(){
        if(window.XLSX) return;
        var s=document.createElement('script');
        s.src='https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js';
        document.head.appendChild(s);
    })();
    </script>

    <!-- Supabase Realtime — tunel global unico -->
    <script>
    window.REALTIME_CONFIG = {
        url: <?= json_encode($supabase_url_for_js) ?>,
        anonKey: <?= json_encode($supabase_anon_key_for_js) ?>,
        tenantId: <?= json_encode($tenant_id_for_js) ?>,
        tables: ['reparaciones', 'inv_accesorios', 'inv_baterias', 'inv_pantallas', 'inv_servicios_generales']
    };
    window.APP_DEBUG = <?= json_encode((getenv('APP_DEBUG') ?: 'false') === 'true') ?>;
    </script>
    <?php $v = defined('APP_VERSION') ? APP_VERSION : date('Ymd'); ?>
    <script defer src="../assets/js/realtime.js?v=<?= $v ?>"></script>
    <script defer src="../assets/js/inventario.js?v=<?= $v ?>"></script>
<?php if (!$isFragment): ?>
</main>
</body>

</html>
<?php endif; ?>