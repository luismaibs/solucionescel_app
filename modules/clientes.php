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
    <title>Clientes | SOLUCIONESCEL</title>
    <?php include '../includes/head_meta.php'; ?>
<?php endif; ?>
    <style data-module-css="clientes">
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
        .table-custom {
            --bs-table-bg: transparent;
            --bs-table-color: #e2e8f0;
            margin-bottom: 0;
        }
        .table-custom thead th {
            background: rgba(255,255,255,0.03);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            font-weight: 600;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            padding: 1rem 0.75rem;
            white-space: nowrap;
        }
        .table-custom tbody tr {
            border-bottom: 1px solid rgba(255,255,255,0.04);
            transition: all 0.15s ease;
        }
        .table-custom tbody tr:hover {
            background: rgba(255,255,255,0.03);
        }
        .table-custom td {
            padding: 0.875rem 0.75rem;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        .btn-action {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-action:hover {
            transform: scale(1.1);
        }
        .avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
            margin-right: 10px;
        }
        .search-bar {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            color: white;
            padding: 0.6rem 1rem 0.6rem 2.5rem;
            font-size: 0.85rem;
        }
        .search-bar:focus {
            background: rgba(255,255,255,0.06);
            border-color: rgba(59,130,246,0.5);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
            color: white;
        }
        .offcanvas-custom {
            width: 420px !important;
            max-width: 90vw;
            background: #0f172a !important;
            border-left: 1px solid rgba(255,255,255,0.06);
        }
        .cliente-link {
            color: #60a5fa;
            cursor: pointer;
            text-decoration: none;
            transition: color 0.15s;
        }
        .cliente-link:hover {
            color: #93c5fd;
            text-decoration: underline;
        }
        @media (max-width: 991.98px) {
            .app-table-wrap { display: none; }
            .app-mobile-cards-wrap { display: block !important; }
        }
        @media (min-width: 992px) {
            .app-table-wrap { display: block; }
            .app-mobile-cards-wrap { display: none !important; }
        }
        .app-mobile-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 1rem;
            margin: 0.5rem 1rem;
            transition: all 0.15s;
        }
        .app-mobile-card:active {
            transform: scale(0.98);
        }
        .app-mobile-card-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.6rem;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 600;
        }
    </style>
<?php if (!$isFragment): ?>
</head>

<body>
    <?php include '../includes/header.php'; ?>
<?php endif; ?>

    <div class="container-xl main-content-push with-subheader" style="max-width: 1440px;">

        <!-- Subheader -->
        <div class="module-subheader">
            <div class="module-subheader-kpis">
                <span class="module-subheader-title">Clientes</span>
                <span class="module-kpi-chip">
                    <i class="bi bi-people-fill kpi-icon text-primary"></i>
                    <span class="kpi-value" id="kpiTotalClientes">—</span>
                    <span class="kpi-label">Total</span>
                </span>
            </div>
            <div class="module-subheader-actions">
                <button class="btn btn-primary" onclick="abrirCrearCliente()">
                    <i class="bi bi-person-plus-fill"></i> Nuevo Cliente
                </button>
            </div>
        </div>

        <!-- Búsqueda -->
        <div class="d-flex justify-content-between align-items-center mb-4 gap-3">
            <div class="position-relative flex-grow-1" style="max-width: 400px;">
                <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                <input type="text" id="searchClientes" class="form-control search-bar"
                       placeholder="Buscar por nombre, teléfono o correo...">
            </div>
        </div>

        <!-- Tabla -->
        <div class="glass-card">
            <div class="app-table-wrap">
                <div class="table-responsive" style="min-height: 300px;">
                    <table class="table table-custom mb-0" id="clientesTable">
                        <thead>
                            <tr>
                                <th class="ps-4">Cliente</th>
                                <th>Teléfono</th>
                                <th>Correo</th>
                                <th>Equipos</th>
                                <th>Registro</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="clientesTableBody">
                            <tr><td colspan="6" class="text-center py-5 text-muted">Cargando clientes...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="clientesCardsContainer" class="app-mobile-cards-wrap" style="min-height: 200px; padding: 0 0 1rem;"></div>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center px-4 py-3 border-top border-white border-opacity-10 gap-2">
                <small class="text-muted" id="paginationInfo" style="font-size: 0.8rem;">Cargando...</small>
                <div class="d-flex gap-2 ms-auto">
                    <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3"
                            id="btnPrevPage" onclick="changePage(-1)" disabled>
                        <i class="bi bi-chevron-left me-1"></i> Anterior
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3"
                            id="btnNextPage" onclick="changePage(1)" disabled>
                        Siguiente <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Confirmación Eliminar -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg" style="background-color: #1e293b; border-radius: 20px;">
                <div class="modal-body text-center p-4 text-white">
                    <div class="mb-3 bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center"
                         style="width: 64px; height: 64px;">
                        <i class="bi bi-trash3 fs-3 text-danger"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Eliminar Cliente</h5>
                    <p class="text-muted small mb-4" id="eliminarMsg">¿Estás seguro?</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger rounded-pill px-4" id="btnConfirmDelete">Eliminar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Offcanvas: Crear Cliente -->
    <div class="offcanvas offcanvas-end offcanvas-custom text-white" tabindex="-1" id="offcanvasCrear"
         data-bs-scroll="true" data-bs-backdrop="false">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title fw-bold"><i class="bi bi-person-plus-fill text-primary me-2"></i>Nuevo Cliente</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <form id="formCrear" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label text-muted small ps-2">Nombre</label>
                    <input type="text" name="nombre" class="form-control" placeholder="Nombre" required
                           maxlength="200" oninput="this.value = this.value.toLocaleUpperCase('es-MX')">
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small ps-2">Apellido</label>
                    <input type="text" name="apellido" class="form-control" placeholder="Apellido" required
                           maxlength="200" oninput="this.value = this.value.toLocaleUpperCase('es-MX')">
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small ps-2">Teléfono</label>
                    <input type="tel" name="telefono" class="form-control" placeholder="Teléfono (único)" required
                           maxlength="25">
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Correo <span class="text-muted">(opcional)</span></label>
                    <input type="email" name="correo" class="form-control" placeholder="correo@ejemplo.com">
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary rounded-pill py-3 fw-bold shadow-lg">
                        <i class="bi bi-person-plus-fill me-2"></i>Registrar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Offcanvas: Editar Cliente -->
    <div class="offcanvas offcanvas-end offcanvas-custom text-white" tabindex="-1" id="offcanvasEditar"
         data-bs-scroll="true" data-bs-backdrop="false">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title fw-bold"><i class="bi bi-pencil text-info me-2"></i>Editar Cliente</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <form id="formEditar" autocomplete="off">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-3">
                    <label class="form-label text-muted small ps-2">Nombre</label>
                    <input type="text" name="nombre" id="edit_nombre" class="form-control" required
                           maxlength="200" oninput="this.value = this.value.toLocaleUpperCase('es-MX')">
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small ps-2">Apellido</label>
                    <input type="text" name="apellido" id="edit_apellido" class="form-control" required
                           maxlength="200" oninput="this.value = this.value.toLocaleUpperCase('es-MX')">
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small ps-2">Teléfono</label>
                    <input type="tel" name="telefono" id="edit_telefono" class="form-control" required maxlength="25">
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Correo <span class="text-muted">(opcional)</span></label>
                    <input type="email" name="correo" id="edit_correo" class="form-control">
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary rounded-pill py-3 fw-bold shadow-lg">
                        <i class="bi bi-save me-2"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function () {
        'use strict';

        const API_BASE = window.APP_API_BASE || '../api/';
        const BASE_PATH = window.APP_BASE_PATH || '../';
        let currentPage = 1;
        const perPage = 30;
        let totalItems = 0;
        let searchTimer = null;
        let deleteId = null;
        let modalEliminar = null;

        var escapeHtml = window.escapeHtml;
        var fmtDate = window.fmtDate;

        function getInitials(nombre, apellido) {
            const n = (nombre || '').trim();
            const a = (apellido || '').trim();
            return ((n[0] || '') + (a[0] || '')).toUpperCase();
        }

        async function loadClientes(page, search) {
            page = page || 1;
            search = search || '';
            const tbody = document.getElementById('clientesTableBody');
            const cards = document.getElementById('clientesCardsContainer');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">Cargando...</td></tr>';
            if (cards) cards.innerHTML = '<div class="text-center py-5 text-muted">Cargando...</div>';

            try {
                const url = API_BASE + 'api_clientes_list.php?page=' + page + '&per_page=' + perPage + '&search=' + encodeURIComponent(search);
                const resp = await fetch(url);
                const data = await resp.json();
                if (!data.ok) throw new Error(data.message);

                currentPage = data.page;
                totalItems = data.total;

                const kpiEl = document.getElementById('kpiTotalClientes');
                if (kpiEl) kpiEl.textContent = data.total;

                renderTable(data.items);
                renderCards(data.items);
                updatePagination();
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-danger">Error al cargar clientes</td></tr>';
                if (cards) cards.innerHTML = '<div class="text-center py-5 text-danger">Error al cargar</div>';
            }
        }

        function renderTable(items) {
            const tbody = document.getElementById('clientesTableBody');
            if (!items.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">Sin clientes registrados</td></tr>';
                return;
            }
            tbody.innerHTML = items.map(c => {
                const initials = getInitials(c.nombre, c.apellido);
                const nombreCompleto = escapeHtml(c.nombre + ' ' + c.apellido);
                return `<tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle flex-shrink-0">${escapeHtml(initials)}</div>
                            <div>
                                <a href="${BASE_PATH}modules/cliente_360?id=${c.id}" class="cliente-link fw-bold">${nombreCompleto}</a>
                            </div>
                        </div>
                    </td>
                    <td><i class="bi bi-whatsapp me-1 text-success small"></i>${escapeHtml(c.telefono)}</td>
                    <td class="text-muted">${escapeHtml(c.correo || '—')}</td>
                    <td>
                        <span class="badge bg-primary bg-opacity-25 text-primary rounded-pill">${c.total_equipos || 0} equipos</span>
                        ${(c.equipos_activos > 0) ? '<span class="badge bg-warning bg-opacity-25 text-warning rounded-pill ms-1">' + c.equipos_activos + ' activo(s)</span>' : ''}
                    </td>
                    <td class="text-muted small">${fmtDate(c.fecha_registro)}</td>
                    <td class="pe-4 text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="${BASE_PATH}modules/cliente_360?id=${c.id}" class="btn-action bg-primary bg-opacity-10 text-primary border-0" title="Vista 360°"><i class="bi bi-eye"></i></a>
                            <button class="btn-action bg-info bg-opacity-10 text-info border-0"
                                data-action="edit"
                                data-id="${c.id}"
                                data-nombre="${escapeHtml(c.nombre)}"
                                data-apellido="${escapeHtml(c.apellido)}"
                                data-telefono="${escapeHtml(c.telefono)}"
                                data-correo="${escapeHtml(c.correo || '')}"
                                title="Editar"><i class="bi bi-pencil"></i></button>
                            <button class="btn-action bg-danger bg-opacity-10 text-danger border-0"
                                data-action="delete"
                                data-id="${c.id}"
                                data-nombre="${escapeHtml(c.nombre + ' ' + c.apellido)}"
                                title="Eliminar"><i class="bi bi-trash3"></i></button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
        }

        function renderCards(items) {
            const container = document.getElementById('clientesCardsContainer');
            if (!container) return;
            if (!items.length) {
                container.innerHTML = '<div class="text-center py-5 text-muted">Sin clientes</div>';
                return;
            }
            container.innerHTML = items.map(c => {
                const initials = getInitials(c.nombre, c.apellido);
                return `<a href="${BASE_PATH}modules/cliente_360?id=${c.id}" class="app-mobile-card d-flex align-items-center gap-3 text-decoration-none">
                    <div class="avatar-circle">${escapeHtml(initials)}</div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-bold text-white text-truncate">${escapeHtml(c.nombre + ' ' + c.apellido)}</div>
                        <div class="small text-muted"><i class="bi bi-whatsapp me-1 text-success"></i>${escapeHtml(c.telefono)}</div>
                        <div class="d-flex gap-2 mt-1">
                            <span class="app-mobile-card-badge" style="background:rgba(59,130,246,0.15);color:#60a5fa">${c.total_equipos || 0} equipos</span>
                        </div>
                    </div>
                </a>`;
            }).join('');
        }

        function updatePagination() {
            const info = document.getElementById('paginationInfo');
            const btnPrev = document.getElementById('btnPrevPage');
            const btnNext = document.getElementById('btnNextPage');
            const totalPages = Math.ceil(totalItems / perPage) || 1;
            if (totalItems === 0) {
                info.textContent = 'Sin clientes';
                btnPrev.disabled = true;
                btnNext.disabled = true;
                return;
            }
            const start = (currentPage - 1) * perPage + 1;
            const end = Math.min(currentPage * perPage, totalItems);
            info.textContent = `Mostrando ${start}-${end} de ${totalItems} clientes`;
            btnPrev.disabled = currentPage <= 1;
            btnNext.disabled = currentPage >= totalPages;
        }

        function changePage(delta) {
            const totalPages = Math.ceil(totalItems / perPage) || 1;
            let target = currentPage + delta;
            if (target < 1) target = 1;
            if (target > totalPages) target = totalPages;
            if (target === currentPage) return;
            loadClientes(target, document.getElementById('searchClientes').value.trim());
        }

        function abrirCrearCliente() {
            document.getElementById('formCrear').reset();
            const el = document.getElementById('offcanvasCrear');
            new bootstrap.Offcanvas(el).show();
        }

        function abrirEditar(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_nombre').value = data.nombre || '';
            document.getElementById('edit_apellido').value = data.apellido || '';
            document.getElementById('edit_telefono').value = data.telefono || '';
            document.getElementById('edit_correo').value = data.correo || '';
            const el = document.getElementById('offcanvasEditar');
            new bootstrap.Offcanvas(el).show();
        }

        function confirmarEliminar(id, nombre) {
            deleteId = id;
            document.getElementById('eliminarMsg').textContent = '¿Eliminar al cliente "' + nombre + '"? Esta acción no se puede deshacer.';
            if (modalEliminar) modalEliminar.show();
        }

        async function submitForm(form, action) {
            const fd = new FormData(form);
            fd.append('action', action);

            try {
                const resp = await fetch(API_BASE + 'api_clientes', { method: 'POST', body: fd });
                const data = await resp.json();
                if (!data.ok) {
                    if (window.SCToast) window.SCToast.show(data.message || 'Error', 'error');
                    return false;
                }
                if (window.SCToast) window.SCToast.show(data.message || 'Operación exitosa', 'success');
                return true;
            } catch (err) {
                if (window.SCToast) window.SCToast.show('Error de conexión', 'error');
                return false;
            }
        }

        onModuleReady(function () {
            const modalEl = document.getElementById('modalEliminar');
            if (modalEl) modalEliminar = new bootstrap.Modal(modalEl);

            document.getElementById('formCrear').addEventListener('submit', async function (e) {
                e.preventDefault();
                const ok = await submitForm(this, 'create');
                if (ok) {
                    bootstrap.Offcanvas.getInstance(document.getElementById('offcanvasCrear')).hide();
                    loadClientes(1);
                }
            });

            document.getElementById('formEditar').addEventListener('submit', async function (e) {
                e.preventDefault();
                const ok = await submitForm(this, 'edit');
                if (ok) {
                    bootstrap.Offcanvas.getInstance(document.getElementById('offcanvasEditar')).hide();
                    loadClientes(currentPage, document.getElementById('searchClientes').value.trim());
                }
            });

            document.getElementById('btnConfirmDelete').addEventListener('click', async function () {
                if (!deleteId) return;
                const fd = new FormData();
                fd.append('action', 'delete');
                fd.append('id', deleteId);
                try {
                    const resp = await fetch(API_BASE + 'api_clientes', { method: 'POST', body: fd });
                    const data = await resp.json();
                    if (data.ok) {
                        if (window.SCToast) window.SCToast.show('Cliente eliminado', 'success');
                        loadClientes(currentPage, document.getElementById('searchClientes').value.trim());
                    } else {
                        if (window.SCToast) window.SCToast.show(data.message || 'Error', 'error');
                    }
                } catch (err) {
                    if (window.SCToast) window.SCToast.show('Error de conexión', 'error');
                }
                deleteId = null;
                if (modalEliminar) modalEliminar.hide();
            });

            document.getElementById('clientesTableBody').addEventListener('click', function (e) {
                const btn = e.target.closest('button[data-action]');
                if (!btn) return;
                if (btn.dataset.action === 'edit') {
                    abrirEditar({
                        id: btn.dataset.id,
                        nombre: btn.dataset.nombre,
                        apellido: btn.dataset.apellido,
                        telefono: btn.dataset.telefono,
                        correo: btn.dataset.correo
                    });
                } else if (btn.dataset.action === 'delete') {
                    confirmarEliminar(parseInt(btn.dataset.id), btn.dataset.nombre);
                }
            });

            document.getElementById('searchClientes').addEventListener('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () {
                    loadClientes(1, document.getElementById('searchClientes').value.trim());
                }, 300);
            });

            loadClientes(1);
        });

        window.changePage = changePage;
        window.abrirCrearCliente = abrirCrearCliente;
    })();
    </script>

<?php if (!$isFragment): ?>
    <?php include '../includes/pwa_script.php'; ?>
</main>
</body>
</html>
<?php endif; ?>
