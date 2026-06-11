<?php
include '../config/auth.php';
requireLogin();
requireAdmin();
include '../config/db.php';
include_once '../includes/fragment_helper.php';
?>
<?php if (!$isFragment): ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Plantillas WhatsApp | SOLUCIONESCEL</title>
    <?php include '../includes/head_meta.php'; ?>
<?php endif; ?>
    <style data-module-css="plantillas">
        :root {
            --wp-green: #dcf8c6;
            --wp-green-dark: #005c4b;
            --wp-bg: #e5ddd5;
        }
        [data-bs-theme="dark"] .wp-bg { background: #0b1419; }
        [data-bs-theme="dark"] .wp-bubble { background: #005c4b; }
        [data-bs-theme="dark"] .wp-bubble-time { color: rgba(255,255,255,0.45); }
        [data-bs-theme="light"] .wp-bg { background: #efeae2; }
        [data-bs-theme="light"] .wp-bubble { background: #d9fdd3; }
        [data-bs-theme="light"] .wp-bubble-time { color: rgba(0,0,0,0.45); }

        body {
            background-color: var(--bg-app);
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            min-height: 100vh;
            background-image: radial-gradient(circle at top right, #1e293b 0%, #0f172a 40%);
        }
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .form-control {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
            color: var(--text-main);
            font-size: 0.9rem;
            padding: 0.7rem 1rem;
        }
        .form-control:focus {
            background: rgba(255,255,255,0.06);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
            color: var(--text-main);
        }
        .form-control::placeholder { color: var(--text-muted); opacity: 0.6; }
        .form-select {
            background-color: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
            color: var(--text-main);
            font-size: 0.85rem;
            padding: 0.55rem 2.2rem 0.55rem 1rem;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        }
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.9rem;
            border-radius: 50px;
            font-size: 0.78rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            color: var(--text-muted);
            white-space: nowrap;
            user-select: none;
        }
        .filter-chip.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }
        .filter-chip:hover:not(.active) {
            background: rgba(255,255,255,0.08);
            color: var(--text-main);
        }

        .template-list-item {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }
        .template-list-item:hover { background: rgba(255,255,255,0.03); }
        .template-list-item.active {
            background: rgba(59,130,246,0.1);
            border-left: 3px solid var(--primary);
        }
        .template-list-item-title {
            font-size: 0.88rem;
            font-weight: 500;
            color: var(--text-main);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .template-list-item-meta {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 0.15rem;
        }

        .wp-preview-container {
            background: var(--wp-bg, #e5ddd5);
            border-radius: 12px;
            padding: 1.5rem;
            min-height: 320px;
            position: relative;
            overflow-y: auto;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60' viewBox='0 0 60 60'%3E%3Cpath d='M30 4 L56 30 L30 56 L4 30 Z' fill='none' stroke='rgba(0,0,0,0.03)' stroke-width='0.5'/%3E%3C/svg%3E");
        }
        .wp-bubble {
            background: var(--wp-bubble, #d9fdd3);
            border-radius: 8px 8px 8px 2px;
            padding: 0.7rem 0.9rem;
            max-width: 90%;
            display: inline-block;
            word-break: break-word;
            box-shadow: 0 1px 1px rgba(0,0,0,0.1);
            font-size: 0.88rem;
            line-height: 1.5;
            color: #111b21;
        }
        [data-bs-theme="dark"] .wp-bubble { color: #e9edef; }
        .wp-bubble-time {
            font-size: 0.68rem;
            text-align: right;
            margin-top: 0.3rem;
            color: var(--wp-bubble-time, rgba(0,0,0,0.45));
        }

        .format-chip {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.7rem;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 500;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            color: var(--text-muted);
        }
        .format-chip code {
            color: var(--primary);
            background: transparent;
            font-size: 0.7rem;
        }

        .btn-action-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 1rem;
            background: transparent;
            color: var(--text-muted);
        }
        .btn-action-icon:hover { background: rgba(255,255,255,0.08); color: var(--text-main); }
        .btn-action-icon.text-danger:hover { background: rgba(239,68,68,0.12); color: #f87171; }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: var(--text-muted);
            text-align: center;
            min-height: 200px;
        }
        .empty-state i { font-size: 2.5rem; opacity: 0.25; margin-bottom: 0.75rem; }

        @media (max-width: 991.98px) {
            .desktop-layout { display: none !important; }
            .mobile-layout { display: block !important; }
        }
        @media (min-width: 992px) {
            .desktop-layout { display: flex !important; }
            .mobile-layout { display: none !important; }
        }

        .folder-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: var(--radius-md);
            padding: 0.75rem 1rem;
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .folder-card:hover { background: rgba(255,255,255,0.04); }
        .folder-card.active { border-color: var(--primary); background: rgba(59,130,246,0.08); }
        .folder-card .folder-count {
            font-size: 0.7rem;
            color: var(--text-muted);
            background: rgba(255,255,255,0.06);
            padding: 0.1rem 0.5rem;
            border-radius: 50px;
        }

        .modal-glass .modal-content {
            background: #0f172a;
            border: 1px solid rgba(255,255,255,0.08);
            color: var(--text-main);
        }
        #carpetaModal .modal-content { background: #0f172a; border: 1px solid rgba(255,255,255,0.08); color: var(--text-main); }
    </style>
<?php if (!$isFragment): ?>
</head>

<body>
    <?php include '../includes/header.php'; ?>
<?php endif; ?>

    <div class="container-xl main-content-push with-subheader" style="max-width: 1600px;">

        <!-- Subheader -->
        <div class="module-subheader">
            <div class="module-subheader-kpis">
                <span class="module-subheader-title"><i class="bi bi-chat-dots-fill text-primary me-1"></i>Plantillas WhatsApp</span>
                <span class="module-kpi-chip">
                    <i class="bi bi-chat-dots-fill kpi-icon text-primary"></i>
                    <span class="kpi-value" id="kpiTotal">0</span>
                    <span class="kpi-label">Plantillas</span>
                </span>
                <span class="module-kpi-chip">
                    <i class="bi bi-folder2 kpi-icon text-warning"></i>
                    <span class="kpi-value" id="kpiCarpetas">0</span>
                    <span class="kpi-label">Carpetas</span>
                </span>
            </div>
            <div class="module-subheader-actions">
                <button class="btn btn-sm btn-outline-light me-2" onclick="openCarpetaModal()" title="Nueva Carpeta">
                    <i class="bi bi-folder-plus me-1"></i> Carpeta
                </button>
                <button class="btn btn-sm btn-primary" onclick="createNewTemplate()" title="Nueva Plantilla">
                    <i class="bi bi-plus-lg me-1"></i> Plantilla
                </button>
            </div>
        </div>

        <!-- ─── MOBILE LAYOUT ─── -->
        <div class="mobile-layout" style="display:none;">
            <!-- Folder chips -->
            <div class="d-flex gap-2 flex-wrap align-items-center mb-3" id="mobileFolderChips">
                <span class="filter-chip active" data-carpeta="" onclick="filterByCarpeta(null, this)">Todas</span>
            </div>
            <!-- Templates as cards -->
            <div id="mobileTemplateCards" class="d-flex flex-column gap-2">
                <div class="empty-state"><i class="bi bi-files"></i><p class="mb-2">Sin plantillas</p><small>Crea tu primera plantilla</small></div>
            </div>
            <!-- Floating action button -->
            <button class="btn btn-primary rounded-circle position-fixed shadow-lg"
                style="width:52px;height:52px;bottom:80px;right:20px;z-index:1050;"
                onclick="openEditorMobile()" title="Nueva Plantilla">
                <i class="bi bi-plus-lg fs-5"></i>
            </button>
        </div>

        <!-- ─── DESKTOP LAYOUT ─── -->
        <div class="row g-3 desktop-layout" style="display:flex;">
            <!-- Left panel: Folders + Template list -->
            <div class="col-lg-4 col-xl-3">
                <div class="glass-card d-flex flex-column" style="height: calc(100vh - 200px); min-height: 500px;">
                    <!-- Search -->
                    <div class="p-3 border-bottom border-white border-opacity-10">
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                            <input type="text" id="searchInput" class="form-control ps-5"
                                placeholder="Buscar plantillas..." oninput="filterTemplates()">
                        </div>
                    </div>
                    <!-- Folder filter chips -->
                    <div class="p-2 border-bottom border-white border-opacity-10">
                        <div class="d-flex gap-1 flex-wrap" id="folderChips">
                            <span class="filter-chip active" data-carpeta="" onclick="filterByCarpeta(null, this)">Todas</span>
                        </div>
                    </div>
                    <!-- Template list -->
                    <div class="flex-grow-1 overflow-auto" id="templateList">
                        <div class="empty-state"><i class="bi bi-files"></i><p class="mb-2">Sin plantillas</p><small>Crea tu primera plantilla</small></div>
                    </div>
                </div>
            </div>

            <!-- Right panel: Editor + Preview -->
            <div class="col-lg-8 col-xl-9">
                <div class="glass-card" style="height: calc(100vh - 200px); min-height: 500px;">
                    <div id="emptyState" class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                        <i class="bi bi-file-text" style="font-size:4rem;opacity:0.2;"></i>
                        <p class="mt-3 mb-1 fs-5">Selecciona o crea una plantilla</p>
                        <small>Gestiona tus mensajes predefinidos para WhatsApp</small>
                    </div>

                    <div id="editorArea" class="d-none h-100">
                        <div class="row h-100 g-0">
                            <!-- Editor -->
                            <div class="col-lg-7 d-flex flex-column border-end border-white border-opacity-10">
                                <div class="p-3 border-bottom border-white border-opacity-10">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="mb-0 fw-semibold"><i class="bi bi-pencil-square text-primary me-2"></i>Editor</h6>
                                        <div class="d-flex gap-1">
                                            <button class="btn-action-icon text-danger" onclick="deleteTemplate()" title="Eliminar">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Date info bar -->
                                    <div id="dateInfoBar" class="d-none d-flex align-items-center gap-3 small text-muted py-1">
                                        <span><i class="bi bi-calendar-plus text-primary me-1"></i>Creada: <strong id="dateCreated" class="text-body">—</strong></span>
                                        <span class="opacity-50">|</span>
                                        <span><i class="bi bi-clock text-warning me-1"></i>Modificada: <strong id="dateUpdated" class="text-body">—</strong></span>
                                    </div>
                                    <div id="estadosVinculadosInfo" class="small py-1" style="font-size:0.75rem;"></div>
                                </div>
                                <div class="p-3 flex-grow-1 d-flex flex-column overflow-auto">
                                    <input type="hidden" id="templateId">
                                    <label class="form-label fw-semibold small text-muted text-uppercase mb-1">Carpeta</label>
                                    <select class="form-select mb-3" id="inputCarpeta">
                                        <option value="">Sin carpeta</option>
                                    </select>
                                    <label class="form-label fw-semibold small text-muted text-uppercase mb-1">Titulo</label>
                                    <input type="text" id="inputTitle" class="form-control mb-3" placeholder="Ej: Saludo de bienvenida">
                                    <label class="form-label fw-semibold small text-muted text-uppercase mb-1">Contenido</label>
                                    <textarea id="inputContent" class="form-control flex-grow-1" style="min-height:200px;resize:none;"
                                        placeholder="Escribe tu mensaje...&#10;&#10;Formatos: *negrita*  _cursiva_  ~tachado~  ```mono```"></textarea>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <span class="small text-muted" id="charCount">0 caracteres</span>
                                        <button class="btn btn-primary btn-sm" onclick="saveTemplate()">
                                            <i class="bi bi-check-lg me-1"></i> Guardar
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!-- Preview panel -->
                            <div class="col-lg-5 d-flex flex-column">
                                <div class="p-3 border-bottom border-white border-opacity-10">
                                    <h6 class="mb-0 fw-semibold"><i class="bi bi-eye text-success me-2"></i>Vista Previa</h6>
                                    <!-- Format guide -->
                                    <div class="d-flex gap-2 flex-wrap mt-2">
                                        <span class="format-chip"><code>*texto*</code> <b>negrita</b></span>
                                        <span class="format-chip"><code>_texto_</code> <i>cursiva</i></span>
                                        <span class="format-chip"><code>~texto~</code> <s>tachado</s></span>
                                        <span class="format-chip"><code>```txt```</code> <span style="font-family:monospace;font-size:0.65rem;">mono</span></span>
                                    </div>
                                </div>
                                <div class="p-3 flex-grow-1 d-flex flex-column overflow-auto">
                                    <div class="wp-preview-container flex-grow-1">
                                        <div class="wp-bubble">
                                            <div id="previewContent" style="min-height:20px;"></div>
                                            <div class="wp-bubble-time">
                                                <span id="currentTime">12:00</span>
                                                <i class="bi bi-check2-all ms-1" style="font-size:0.7rem;opacity:0.6;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Nueva/Editar Carpeta -->
    <div class="modal fade" id="carpetaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="background:#0f172a;border:1px solid rgba(255,255,255,0.08);color:var(--text-main);">
                <div class="modal-header border-bottom border-white border-opacity-10">
                    <h6 class="modal-title fw-semibold" id="carpetaModalTitle"><i class="bi bi-folder-plus me-2 text-warning"></i>Nueva Carpeta</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="carpetaEditId">
                    <label class="form-label fw-semibold small text-muted text-uppercase">Nombre</label>
                    <input type="text" id="carpetaNombre" class="form-control" placeholder="Ej: Ventas, Soporte...">
                </div>
                <div class="modal-footer border-top border-white border-opacity-10">
                    <button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-primary" onclick="saveCarpeta()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Editor Offcanvas -->
    <div class="offcanvas offcanvas-bottom h-100" tabindex="-1" id="mobileEditor" style="background:#0f172a;color:var(--text-main);">
        <div class="offcanvas-header border-bottom border-white border-opacity-10">
            <h6 class="offcanvas-title fw-semibold"><i class="bi bi-pencil-square text-primary me-2"></i>Editar Plantilla</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column">
            <input type="hidden" id="mTemplateId">
            <label class="form-label fw-semibold small text-muted text-uppercase mb-1">Carpeta</label>
            <select class="form-select mb-3" id="mInputCarpeta">
                <option value="">Sin carpeta</option>
            </select>
            <label class="form-label fw-semibold small text-muted text-uppercase mb-1">Titulo</label>
            <input type="text" id="mInputTitle" class="form-control mb-3" placeholder="Ej: Saludo de bienvenida">
            <label class="form-label fw-semibold small text-muted text-uppercase mb-1">Contenido</label>
            <textarea id="mInputContent" class="form-control flex-grow-1" style="min-height:180px;resize:none;"
                placeholder="Escribe tu mensaje..."></textarea>
            <span class="small text-muted mt-1 mb-2" id="mCharCount">0 caracteres</span>
            <!-- Format guide -->
            <div class="d-flex gap-2 flex-wrap mb-3">
                <span class="format-chip"><code>*texto*</code> <b>negrita</b></span>
                <span class="format-chip"><code>_texto_</code> <i>cursiva</i></span>
                <span class="format-chip"><code>~texto~</code> <s>tachado</s></span>
                <span class="format-chip"><code>```txt```</code> <span style="font-family:monospace;">mono</span></span>
            </div>
            <!-- Preview -->
            <label class="form-label fw-semibold small text-muted text-uppercase mb-1">Vista Previa</label>
            <div class="wp-preview-container mb-3" style="min-height:140px;">
                <div class="wp-bubble">
                    <div id="mPreviewContent" style="min-height:20px;"></div>
                    <div class="wp-bubble-time">
                        <span id="mCurrentTime">12:00</span>
                        <i class="bi bi-check2-all ms-1" style="font-size:0.7rem;opacity:0.6;"></i>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-auto">
                <button class="btn btn-outline-danger flex-shrink-0" onclick="deleteTemplateMobile()">
                    <i class="bi bi-trash3"></i>
                </button>
                <button class="btn btn-primary flex-grow-1" onclick="saveTemplateMobile()">
                    <i class="bi bi-check-lg me-1"></i> Guardar
                </button>
            </div>
        </div>
    </div>

    <script>
    const API = (window.APP_API_BASE || '../api/') + 'api_plantillas';
    const API_ESTADOS = (window.APP_API_BASE || '../api/') + 'api_estados';
    const escapeHtml = window.escapeHtml || (s => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; });
    const fmtDate = window.fmtDate || (s => s ? new Date(s).toLocaleDateString('es-MX', {day:'numeric',month:'short',year:'numeric'}) : '—');
    let templates = [], carpetas = [], currentId = null, currentCarpetaId = null;
    let estadosVinculados = {}; // plantilla_id -> [{slug, nombre, color}]

    // ─── INIT ───
    function init() {
        document.getElementById('currentTime').textContent = new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
        document.getElementById('mCurrentTime').textContent = new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
        loadCarpetas().then(() => Promise.all([loadTemplates(), loadEstadosVinculados()]));
    }

    async function loadEstadosVinculados() {
        try {
            const r = await fetch(API_ESTADOS + '?action=tree');
            const d = await r.json();
            if (!d.ok) return;
            estadosVinculados = {};
            function walk(items) {
                (items || []).forEach(function (p) {
                    if (p.plantilla_id) {
                        if (!estadosVinculados[p.plantilla_id]) estadosVinculados[p.plantilla_id] = [];
                        estadosVinculados[p.plantilla_id].push({ slug: p.slug, nombre: p.nombre, color: p.color });
                    }
                    if (p.hijos) walk(p.hijos);
                });
            }
            walk(d.primer_ingreso);
            walk(d.re_ingreso);
            renderList();
            renderMobileCards();
        } catch (e) {}
    }

    function getEstadosBadgeHtml(plantillaId) {
        var estados = estadosVinculados[plantillaId];
        if (!estados || !estados.length) return '';
        return '<div class="d-flex flex-wrap gap-1 mt-1">' + estados.map(function (e) {
            return '<span class="badge rounded-pill" style="background:' + (e.color || '#94a3b8') + '22;color:' + (e.color || '#94a3b8') + ';font-size:0.65rem;border:1px solid ' + (e.color || '#94a3b8') + '44;">' + escapeHtml(e.nombre) + '</span>';
        }).join('') + '</div>';
    }

    function getEstadosVinculadosText(plantillaId) {
        var estados = estadosVinculados[plantillaId];
        if (!estados || !estados.length) return '';
        return estados.map(function (e) { return '<span class="badge rounded-pill px-2 py-1 me-1 mb-1" style="background:' + (e.color || '#94a3b8') + '22;color:' + (e.color || '#94a3b8') + ';font-size:0.7rem;border:1px solid ' + (e.color || '#94a3b8') + '44;"><i class="bi bi-link-45deg me-1"></i>' + escapeHtml(e.nombre) + '</span>'; }).join(' ');
    }

    // ─── CARPETAS ───
    async function loadCarpetas() {
        const r = await fetch(API + '?action=carpetas');
        const d = await r.json();
        carpetas = d.ok ? (d.data || []) : [];
        renderFolderChips();
        renderCarpetaSelects();
        document.getElementById('kpiCarpetas').textContent = carpetas.length;
    }

    function renderFolderChips() {
        const allChip = '<span class="filter-chip ' + (currentCarpetaId === null ? 'active' : '') + '" data-carpeta="" onclick="filterByCarpeta(null, this)">Todas</span>';
        const chips = carpetas.map(c =>
            '<span class="filter-chip ' + (currentCarpetaId === c.id ? 'active' : '') + '" data-carpeta="' + c.id + '" onclick="filterByCarpeta(' + c.id + ', this)">' +
            '<i class="bi bi-folder2 me-1"></i>' + escapeHtml(c.nombre) +
            '</span>'
        ).join('');
        document.getElementById('folderChips').innerHTML = allChip + chips;
        document.getElementById('mobileFolderChips').innerHTML = allChip + chips;
    }

    function renderCarpetaSelects() {
        const opts = '<option value="">Sin carpeta</option>' +
            carpetas.map(c => '<option value="' + c.id + '">' + escapeHtml(c.nombre) + '</option>').join('');
        document.getElementById('inputCarpeta').innerHTML = opts;
        document.getElementById('mInputCarpeta').innerHTML = opts;
    }

    function filterByCarpeta(id, el) {
        currentCarpetaId = id;
        document.querySelectorAll('#folderChips .filter-chip, #mobileFolderChips .filter-chip').forEach(c => c.classList.remove('active'));
        if (el) el.classList.add('active');
        loadTemplates();
    }

    async function saveCarpeta() {
        const id = document.getElementById('carpetaEditId').value || null;
        const nombre = document.getElementById('carpetaNombre').value.trim();
        if (!nombre) { showToast('El nombre es obligatorio', 'warning'); return; }
        const r = await fetch(API + '?action=save_carpeta', {
            method: 'POST',
            body: JSON.stringify({id, nombre})
        });
        const d = await r.json();
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('carpetaModal')).hide();
            showToast(id ? 'Carpeta actualizada' : 'Carpeta creada', 'success');
            loadCarpetas().then(() => { renderList(); loadTemplates(); });
        } else {
            showToast(d.error || 'Error al guardar carpeta', 'error');
        }
    }

    function openCarpetaModal(id = null) {
        document.getElementById('carpetaEditId').value = id || '';
        document.getElementById('carpetaNombre').value = '';
        document.getElementById('carpetaModalTitle').innerHTML = id
            ? '<i class="bi bi-pencil-square me-2 text-warning"></i>Editar Carpeta'
            : '<i class="bi bi-folder-plus me-2 text-warning"></i>Nueva Carpeta';
        if (id) {
            const c = carpetas.find(x => x.id == id);
            if (c) document.getElementById('carpetaNombre').value = c.nombre;
        }
        new bootstrap.Modal(document.getElementById('carpetaModal')).show();
    }

    // ─── TEMPLATES ───
    async function loadTemplates() {
        let url = API + '?action=list';
        if (currentCarpetaId) url += '&carpeta_id=' + currentCarpetaId;
        const r = await fetch(url);
        const d = await r.json();
        templates = d.ok ? (d.data || []) : [];
        document.getElementById('kpiTotal').textContent = templates.length;
        renderList();
        renderMobileCards();
    }

    function renderList() {
        const q = (document.getElementById('searchInput').value || '').toLowerCase();
        const filtered = templates.filter(t => t.title.toLowerCase().includes(q));
        const listEl = document.getElementById('templateList');

        if (!filtered.length) {
            listEl.innerHTML = '<div class="empty-state"><i class="bi bi-files"></i><p class="mb-2">Sin resultados</p></div>';
            return;
        }
        listEl.innerHTML = filtered.map(t => {
            const activeClass = currentId == t.id ? ' active' : '';
            const carpetaName = t.carpeta ? escapeHtml(t.carpeta.nombre) : '';
            const dateStr = t.created_at ? fmtDate(t.created_at) : '';
            const estadoBadges = getEstadosVinculadosText(t.id);
            return `<div class="template-list-item${activeClass}" onclick="selectTemplate(${t.id})">
                <div class="flex-grow-1" style="min-width:0;">
                    <div class="template-list-item-title">${escapeHtml(t.title || 'Sin titulo')}</div>
                    <div class="template-list-item-meta">
                        ${carpetaName ? '<i class="bi bi-folder2 me-1"></i>' + carpetaName + ' · ' : ''}${dateStr}
                    </div>
                    ${estadoBadges ? '<div class="mt-1">' + estadoBadges + '</div>' : ''}
                </div>
                <i class="bi bi-chevron-right text-muted small flex-shrink-0"></i>
            </div>`;
        }).join('');
    }

    function renderMobileCards() {
        const container = document.getElementById('mobileTemplateCards');
        if (!templates.length) {
            container.innerHTML = '<div class="empty-state"><i class="bi bi-files"></i><p class="mb-2">Sin plantillas</p><small>Crea tu primera plantilla</small></div>';
            return;
        }
        container.innerHTML = templates.map(t => {
            const carpetaName = t.carpeta ? escapeHtml(t.carpeta.nombre) : '';
            const dateStr = t.created_at ? fmtDate(t.created_at) : '';
            const estadoBadges = getEstadosVinculadosText(t.id);
            return `<div class="app-mobile-card" onclick="openEditorMobile(${t.id})" style="cursor:pointer;">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1" style="min-width:0;">
                        <div class="fw-semibold text-truncate" style="font-size:0.9rem;">${escapeHtml(t.title || 'Sin titulo')}</div>
                        <div class="d-flex align-items-center gap-2 mt-1" style="font-size:0.72rem;color:var(--text-muted);">
                            ${carpetaName ? '<span class="app-mobile-card-badge" style="background:rgba(59,130,246,0.15);color:#60a5fa;"><i class="bi bi-folder2 me-1"></i>' + carpetaName + '</span>' : ''}
                            <span>${dateStr}</span>
                        </div>
                        ${estadoBadges ? '<div class="mt-1">' + estadoBadges + '</div>' : ''}
                    </div>
                    <i class="bi bi-chevron-right text-muted flex-shrink-0 ms-2"></i>
                </div>
            </div>`;
        }).join('');
    }

    function filterTemplates() { renderList(); }

    function selectTemplate(id) {
        currentId = id;
        const t = templates.find(x => x.id == id);
        if (!t) return;
        document.getElementById('templateId').value = t.id;
        document.getElementById('inputTitle').value = t.title || '';
        document.getElementById('inputContent').value = t.content || '';
        document.getElementById('inputCarpeta').value = t.carpeta_id || '';
        document.getElementById('dateCreated').textContent = t.created_at ? new Date(t.created_at).toLocaleString('es-MX') : '—';
        document.getElementById('dateUpdated').textContent = t.updated_at ? new Date(t.updated_at).toLocaleString('es-MX') : '—';
        var estadosHtml = getEstadosVinculadosText(t.id);
        var estadoInfoEl = document.getElementById('estadosVinculadosInfo');
        if (estadoInfoEl) {
            estadoInfoEl.innerHTML = estadosHtml || '<span class="text-muted small">No vinculada a ningún estado</span>';
        }
        document.getElementById('dateInfoBar').classList.remove('d-none');
        document.getElementById('emptyState').classList.add('d-none');
        document.getElementById('editorArea').classList.remove('d-none');
        updatePreview();
        renderList();
    }

    function createNewTemplate() {
        currentId = null;
        document.getElementById('templateId').value = '';
        document.getElementById('inputTitle').value = '';
        document.getElementById('inputContent').value = '';
        document.getElementById('inputCarpeta').value = currentCarpetaId || '';
        document.getElementById('dateInfoBar').classList.add('d-none');
        document.getElementById('emptyState').classList.add('d-none');
        document.getElementById('editorArea').classList.remove('d-none');
        updatePreview();
        renderList();
        document.getElementById('inputTitle').focus();
    }

    function formatWhatsApp(text) {
        text = escapeHtml(text);
        text = text.replace(/\*(.+?)\*/g, '<b>$1</b>');
        text = text.replace(/_(.+?)_/g, '<i>$1</i>');
        text = text.replace(/~(.+?)~/g, '<s>$1</s>');
        text = text.replace(/```(.+?)```/g, '<span style="font-family:monospace;background:rgba(0,0,0,0.1);border-radius:4px;padding:0 3px;">$1</span>');
        return text.replace(/\n/g, '<br>');
    }

    function updatePreview() {
        const raw = document.getElementById('inputContent').value || '';
        document.getElementById('previewContent').innerHTML = formatWhatsApp(raw);
        document.getElementById('charCount').textContent = raw.length + ' caracteres';
    }

    async function saveTemplate() {
        const title = document.getElementById('inputTitle').value.trim();
        if (!title) { showToast('El titulo es obligatorio', 'warning'); return; }
        const body = JSON.stringify({
            id: document.getElementById('templateId').value || null,
            title,
            content: document.getElementById('inputContent').value,
            carpeta_id: document.getElementById('inputCarpeta').value || null
        });
        const r = await fetch(API + '?action=save', {method:'POST', body});
        const d = await r.json();
        if (!d.ok) { showToast(d.error || 'Error al guardar', 'error'); return; }
        showToast(d.record ? 'Plantilla guardada' : 'Plantilla guardada', 'success');
        loadCarpetas().then(() => {
            loadTemplates().then(() => {
                if (d.id && d.record) {
                    currentId = d.id;
                    document.getElementById('templateId').value = d.id;
                    document.getElementById('dateInfoBar').classList.remove('d-none');
                    document.getElementById('dateCreated').textContent = d.record.created_at ? new Date(d.record.created_at).toLocaleString('es-MX') : '—';
                    document.getElementById('dateUpdated').textContent = d.record.updated_at ? new Date(d.record.updated_at).toLocaleString('es-MX') : '—';
                    renderList();
                }
            });
        });
    }

    async function deleteTemplate() {
        if (!currentId) return;
        if (!confirm('Eliminar esta plantilla?')) return;
        await fetch(API + '?action=delete', {method:'POST', body:JSON.stringify({id:currentId})});
        showToast('Plantilla eliminada', 'success');
        currentId = null;
        document.getElementById('templateId').value = '';
        document.getElementById('inputTitle').value = '';
        document.getElementById('inputContent').value = '';
        document.getElementById('dateInfoBar').classList.add('d-none');
        document.getElementById('editorArea').classList.add('d-none');
        document.getElementById('emptyState').classList.remove('d-none');
        loadTemplates();
    }

    // ─── MOBILE ───
    function openEditorMobile(id = null) {
        if (id) {
            const t = templates.find(x => x.id == id);
            if (!t) return;
            document.getElementById('mTemplateId').value = t.id;
            document.getElementById('mInputTitle').value = t.title || '';
            document.getElementById('mInputContent').value = t.content || '';
            document.getElementById('mInputCarpeta').value = t.carpeta_id || '';
        } else {
            document.getElementById('mTemplateId').value = '';
            document.getElementById('mInputTitle').value = '';
            document.getElementById('mInputContent').value = '';
            document.getElementById('mInputCarpeta').value = currentCarpetaId || '';
        }
        updateMobilePreview();
        new bootstrap.Offcanvas(document.getElementById('mobileEditor')).show();
    }

    function updateMobilePreview() {
        const raw = document.getElementById('mInputContent').value || '';
        document.getElementById('mPreviewContent').innerHTML = formatWhatsApp(raw);
        document.getElementById('mCharCount').textContent = raw.length + ' caracteres';
    }

    async function saveTemplateMobile() {
        const title = document.getElementById('mInputTitle').value.trim();
        if (!title) { showToast('El titulo es obligatorio', 'warning'); return; }
        const body = JSON.stringify({
            id: document.getElementById('mTemplateId').value || null,
            title,
            content: document.getElementById('mInputContent').value,
            carpeta_id: document.getElementById('mInputCarpeta').value || null
        });
        const r = await fetch(API + '?action=save', {method:'POST', body});
        const d = await r.json();
        if (!d.ok) { showToast(d.error || 'Error al guardar', 'error'); return; }
        showToast('Plantilla guardada', 'success');
        bootstrap.Offcanvas.getInstance(document.getElementById('mobileEditor')).hide();
        loadCarpetas().then(() => loadTemplates());
    }

    async function deleteTemplateMobile() {
        const id = document.getElementById('mTemplateId').value;
        if (!id) return;
        if (!confirm('Eliminar esta plantilla?')) return;
        await fetch(API + '?action=delete', {method:'POST', body:JSON.stringify({id})});
        showToast('Plantilla eliminada', 'success');
        bootstrap.Offcanvas.getInstance(document.getElementById('mobileEditor')).hide();
        loadTemplates();
    }

    // ─── HELPERS ───
    function showToast(msg, type) {
        if (window.SCToast) { window.SCToast.show(msg, type); }
        else { alert(msg); }
    }

    // ─── EVENT LISTENERS ───
    document.getElementById('inputContent').addEventListener('input', updatePreview);
    document.getElementById('mInputContent').addEventListener('input', updateMobilePreview);

    init();
    </script>
<?php if (!$isFragment): ?>
    <?php include '../includes/pwa_script.php'; ?>
</main>
</body>
</html>
<?php endif; ?>
