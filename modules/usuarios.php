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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios | SOLUCIONESCEL</title>
    <?php include '../includes/head_meta.php'; ?>
<?php endif; ?>
    <style data-module-css="usuarios">
        /* body global en app.css */
        .navbar-custom {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            height: 70px;
        }

        /* Mobile Menu Styles matching Index/Soporte */
        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: rgba(15, 23, 42, 0.98);
                backdrop-filter: blur(30px);
                padding: 1.5rem;
                margin-top: 1rem;
                border-radius: 16px;
                border: 1px solid rgba(255, 255, 255, 0.08);
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
            }

            .navbar-nav {
                gap: 0.5rem;
            }

            .nav-link {
                border-radius: 8px;
                padding: 0.75rem 1rem !important;
                transition: all 0.2s ease;
            }

            .nav-link:hover,
            .nav-link.active {
                background: rgba(59, 130, 246, 0.15);
            }
        }

        /* Móvil: filas compactas en Logs y Auditoría */
        @media (max-width: 991.98px) {
            #pills-logs .table-custom th,
            #pills-logs .table-custom td,
            #pills-audit .table-custom th,
            #pills-audit .table-custom td {
                padding: 0.5rem 0.5rem;
                font-size: 0.8rem;
            }
            #pills-logs .table-responsive,
            #pills-audit .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }

        /* .glass-card global en app.css */
        .stat-card {
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 0.8rem;
        }

        .table-custom th {
            background: rgba(15, 23, 42, 0.9);
            color: var(--text-muted);
            font-size: 0.7rem;
            letter-spacing: 0.08em;
            padding: 1.2rem 1rem;
            border-bottom: 1px solid var(--glass-border);
            text-transform: uppercase;
            font-weight: 600;
        }

        .table-custom td {
            background: transparent;
            padding: 1.2rem 1rem;
            color: var(--text-main);
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            vertical-align: middle;
            transition: background 0.2s;
        }

        .table-custom tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        /* Form Controls - igual que Equipos e Inventario */
        .form-label {
            font-size: 0.8125rem;
            font-weight: 500;
            color: rgba(248, 250, 252, 0.7);
            margin-bottom: 0.5rem;
            letter-spacing: 0.01em;
            text-transform: uppercase;
        }

        .form-control,
        .form-select {
            background: rgba(255, 255, 255, 0.04);
            border: 1.5px solid rgba(255, 255, 255, 0.08);
            color: #f8fafc;
            border-radius: 12px;
            padding: 0.875rem 1.125rem;
            font-size: 0.9375rem;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .form-control::placeholder {
            color: rgba(148, 163, 184, 0.5);
        }

        .form-control:hover,
        .form-select:hover {
            border-color: rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.06);
        }

        .form-control:focus,
        .form-select:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(59, 130, 246, 0.5);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.08), inset 0 1px 2px rgba(0, 0, 0, 0.1);
            color: #ffffff;
            outline: none;
        }

        /* Opciones del select siempre visibles (fondo oscuro, texto claro) */
        .form-select option {
            background-color: #1e293b;
            color: #f8fafc;
            padding: 0.5rem;
        }

        .form-select option:hover {
            background-color: #334155;
        }

        .form-select option:checked {
            background-color: #334155;
            color: #f8fafc;
        }

        /* Offcanvas styles centralizados en app.css */

        /* Filter Chips matching Index - sin blur */
        .filters-row-wrap {
            backdrop-filter: none;
            background: transparent;
        }
        .filter-chip {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: none;
            border: 1px solid var(--glass-border);
            color: #94a3b8;
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .filter-chip:hover {
            color: #ffffff;
            border-color: rgba(59, 130, 246, 0.35);
            background: rgba(37, 99, 235, 0.15);
        }
        .filter-chip.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .btn-new-ingreso {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: 1px solid rgba(59, 130, 246, 0.4);
            border-radius: var(--radius-lg);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            color: white;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }

        .btn-new-ingreso:hover {
            background: rgba(37, 99, 235, 0.9);
            border-color: rgba(59, 130, 246, 0.5);
            color: white;
        }

        /* Botón submit offcanvas - sin blur, hover como sidebar */
        .offcanvas .btn-primary,
        .btn-primary.rounded-pill {
            background: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.4);
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: -0.01em;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .offcanvas .btn-primary:hover,
        .btn-primary.rounded-pill:hover {
            background: rgba(37, 99, 235, 0.9);
            border-color: rgba(59, 130, 246, 0.5);
            color: #ffffff;
        }

        .offcanvas .btn-primary:active,
        .btn-primary.rounded-pill:active {
            background: #1d4ed8;
        }
    </style>
<?php if (!$isFragment): ?>
</head>

<body>

    <!-- NAVBAR -->
    <!-- NAVBAR UNIFICADO -->
    <?php include '../includes/header.php'; ?>
<?php endif; ?>

    <div class="container-xl main-content-push with-subheader pb-5" style="max-width: 1440px;">

        <!-- Subheader: título + KPI chips + Nuevo Usuario -->
        <div class="module-subheader">
            <div class="module-subheader-kpis">
                <span class="module-subheader-title">Gestión de Accesos</span>
                <span class="module-kpi-chip">
                    <i class="bi bi-people-fill kpi-icon text-primary"></i>
                    <span class="kpi-value" id="totalUsuarios">0</span>
                    <span class="kpi-label">Usuarios</span>
                </span>
                <span class="module-kpi-chip">
                    <i class="bi bi-activity kpi-icon text-success"></i>
                    <span class="kpi-value" id="actividadReciente">0</span>
                    <span class="kpi-label">Actividad hoy</span>
                </span>
                <span class="module-kpi-chip">
                    <i class="bi bi-box-arrow-in-right kpi-icon text-warning"></i>
                    <span class="kpi-value" id="loginsHoy">0</span>
                    <span class="kpi-label">Logins hoy</span>
                </span>
            </div>
            <div class="module-subheader-actions">
                <button class="btn btn-primary" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#offcanvasNuevoUsuario">
                    <i class="bi bi-person-plus-fill"></i> Nuevo Usuario
                </button>
            </div>
        </div>

        <div class="row">
            <!-- COLUMNA FULL: Lista y Logs -->
            <div class="col-12">
                <!-- Filtros Tipo Chips (mismo layout que Equipos/Inventario) -->
                <div class="filters-row-wrap d-flex gap-2 overflow-x-auto hide-scrollbar pb-1 pb-lg-0 mb-4 w-100">
                    <span class="filter-chip active flex-shrink-0" onclick="switchTab('users', this)">Usuarios</span>
                    <span class="filter-chip flex-shrink-0" onclick="switchTab('logs', this)">Historial</span>
                    <span class="filter-chip flex-shrink-0" onclick="switchTab('audit', this)">Auditoría</span>
                </div>

                <div class="tab-content" id="pills-tabContent">

                    <!-- TABLA USUARIOS (escritorio) y Fichas (móvil) -->
                    <div class="tab-pane fade show active" id="pills-users">
                        <div class="glass-card">
                            <div class="app-table-wrap">
                                <div class="table-responsive">
                                    <table class="table table-custom mb-0">
                                        <thead>
                                            <tr>
                                                <th>Usuario</th>
                                                <th>Rol (empresa)</th>
                                                <th>Nivel de acceso</th>
                                                <th>Creado por</th>
                                                <th>Fecha</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tableUsersBody">
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">Cargando...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div id="usersCardsContainer" class="app-mobile-cards-wrap" style="min-height: 120px; padding: 0 0 1rem;"></div>
                        </div>
                    </div>

                    <!-- TABLA LOGS -->
                    <div class="tab-pane fade" id="pills-logs">
                        <div class="glass-card">
                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-custom mb-0 text-small">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Usuario</th>
                                            <th>Tipo</th>
                                            <th>Acción</th>
                                            <th>IP</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tableLogsBody">
                                        <!-- JS carga esto -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TABLA AUDITORÍA -->
                    <div class="tab-pane fade" id="pills-audit">
                        <div class="glass-card">
                            <h6 class="mb-3 text-info"><i class="bi bi-journal-text me-2"></i>Registro detallado de
                                acciones</h6>
                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-custom mb-0 text-small">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Usuario</th>
                                            <th>Acción</th>
                                            <th>Detalle</th>
                                            <th>Referencia</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tableAuditBody">
                                        <!-- JS carga esto -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- OFFCANVAS Nuevo Usuario -->
    <div class="offcanvas offcanvas-end offcanvas-custom text-white" tabindex="-1" id="offcanvasNuevoUsuario"
        data-bs-scroll="true" data-bs-backdrop="false" aria-labelledby="offcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title fw-bold" id="offcanvasLabel">Nuevo Usuario</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form id="formUsuario">
                <input type="hidden" name="action" value="create">

                <h6 class="text-primary mb-3 mt-0 border-bottom border-secondary pb-2 border-opacity-25">Datos del usuario</h6>

                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Email (para iniciar sesión)</label>
                    <input type="email" name="email" id="inputEmail" class="form-control" required placeholder="usuario@ejemplo.com">
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Nombre completo</label>
                    <input type="text" name="nombre" id="inputNombre" class="form-control" required placeholder="Ej. Juan Pérez">
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Nombre de usuario</label>
                    <input type="text" name="username" id="inputUsername" class="form-control" required placeholder="Ej. juanperez">
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Nivel de acceso</label>
                    <select name="rol" id="selectRolNuevo" class="form-select" required>
                        <option value="usuario" selected>Usuario — acceso por módulos</option>
                        <option value="admin">Administrador — acceso total</option>
                    </select>
                </div>

                <!-- Switches de módulos (solo para usuario) -->
                <div id="nuevosModulosSection" class="mb-4">
                    <h6 class="text-primary mb-2 border-bottom border-secondary pb-2 border-opacity-25">Módulos habilitados</h6>
                    <div class="d-flex flex-column gap-2">
                        <?php
                        $modulosDef = [
                            'equipos'    => ['label' => 'Equipos',    'icon' => 'bi-tools'],
                            'clientes'   => ['label' => 'Clientes',   'icon' => 'bi-person-lines-fill'],
                            'inventario' => ['label' => 'Inventario', 'icon' => 'bi-box-seam'],
                            'soporte'    => ['label' => 'Soporte',    'icon' => 'bi-headset'],
                            'mes_azul'   => ['label' => 'Mes Azul',   'icon' => 'bi-hourglass-split'],
                            'analiticas' => ['label' => 'Analíticas', 'icon' => 'bi-graph-up-arrow'],
                        ];
                        foreach ($modulosDef as $slug => $m): ?>
                        <div class="d-flex align-items-center justify-content-between p-2 px-3 rounded-3"
                             style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06);">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi <?= $m['icon'] ?> text-primary" style="width:18px;"></i>
                                <span class="small fw-medium"><?= $m['label'] ?></span>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input nuevo-modulo-switch" type="checkbox"
                                       name="modulos[]" value="<?= $slug ?>"
                                       id="nmod_<?= $slug ?>" role="switch"
                                       <?= in_array($slug, ['equipos','clientes','inventario','soporte','mes_azul']) ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Contraseña</label>
                    <input type="password" name="password" id="inputPassword" class="form-control" required placeholder="******" minlength="6">
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Confirmar contraseña</label>
                    <input type="password" name="password_confirm" id="inputPasswordConfirm" class="form-control" required placeholder="Repite la contraseña" minlength="6">
                    <div id="passwordMatchError" class="small text-danger mt-1 d-none">Las contraseñas no coinciden.</div>
                </div>

                <div class="d-grid mt-5">
                    <button type="submit" class="btn btn-primary rounded-pill py-3 fw-bold shadow-lg">
                        <i class="bi bi-check-circle me-2"></i>Crear Usuario
                    </button>
                </div>
            </form>
            <div id="msgBox" class="mt-3 text-center small"></div>
        </div>
    </div>

    <!-- OFFCANVAS Editar Usuario -->
    <div class="offcanvas offcanvas-end offcanvas-custom text-white" tabindex="-1" id="offcanvasEditarUsuario"
        data-bs-scroll="true" data-bs-backdrop="false" aria-labelledby="offcanvasEditarLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title fw-bold" id="offcanvasEditarLabel">Editar Usuario</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form id="formUsuarioEditar">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editUserId">

                <h6 class="text-primary mb-3 mt-0 border-bottom border-secondary pb-2 border-opacity-25">Datos del usuario</h6>

                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Nombre de usuario</label>
                    <input type="text" name="username" id="editUsername" class="form-control" required placeholder="Ej. Juan Pérez">
                </div>

                <!-- Nivel de acceso (editable) -->
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Nivel de acceso</label>
                    <select name="rol" id="editRolSelect" class="form-select">
                        <option value="usuario">Usuario — acceso por módulos</option>
                        <option value="admin">Administrador — acceso total</option>
                    </select>
                </div>

                <!-- Módulos visibles -->
                <div id="editModulosSection" class="mb-4">
                    <h6 class="text-primary mb-2 border-bottom border-secondary pb-2 border-opacity-25">Módulos habilitados</h6>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($modulosDef as $slug => $m): ?>
                        <div class="d-flex align-items-center justify-content-between p-2 px-3 rounded-3"
                             style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06);">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi <?= $m['icon'] ?> text-primary" style="width:18px;"></i>
                                <span class="small fw-medium"><?= $m['label'] ?></span>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input modulo-switch" type="checkbox"
                                       name="modulos[]" value="<?= $slug ?>"
                                       id="mod_<?= $slug ?>" role="switch">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted d-block mt-2" style="font-size:0.75rem;">Los cambios aplican en el próximo inicio de sesión del usuario.</small>
                </div>

                <h6 class="text-primary mb-2 mt-2 border-bottom border-secondary pb-2 border-opacity-25">Cambiar contraseña</h6>
                <small class="text-muted d-block mb-3" style="font-size:0.8rem;">Deja vacío para no cambiarla.</small>

                <div class="mb-3">
                    <label class="form-label text-muted small ps-2">Nueva contraseña</label>
                    <input type="password" name="password" id="editPassword" class="form-control" placeholder="******" minlength="6">
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small ps-2">Confirmar contraseña</label>
                    <input type="password" name="password_confirm" id="editPasswordConfirm" class="form-control" placeholder="Repite la contraseña" minlength="6">
                    <div id="passwordEditMatchError" class="small text-danger mt-1 d-none">Las contraseñas no coinciden.</div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary rounded-pill py-3 fw-bold shadow-lg">
                        <i class="bi bi-check-circle me-2"></i>Guardar cambios
                    </button>
                </div>
            </form>
            <div id="msgBoxEdit" class="mt-3 text-center small"></div>
        </div>
    </div>

    <script>
    (function () {
        'use strict';

        const MODULO_ICONOS = {
            equipos: 'bi-tools', clientes: 'bi-person-lines-fill',
            inventario: 'bi-box-seam', soporte: 'bi-headset',
            mes_azul: 'bi-hourglass-split', analiticas: 'bi-graph-up-arrow'
        };
        const TODOS_MODULOS = ['equipos','clientes','inventario','soporte','mes_azul','analiticas'];
        let usersLastList = [];

        onModuleReady(function () {
            loadUsers();
            loadLogs();
            loadAuditoria();
            window.addEventListener('resize', function () { if (usersLastList.length > 0) renderUsers(usersLastList); });
        });

        function switchTab(tabName, el) {
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            el.classList.add('active');
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('show', 'active'));
            const target = document.getElementById('pills-' + tabName);
            if (target) target.classList.add('show', 'active');
        }

        function parseJsonResponse(res) {
            return res.text().then(text => {
                const trimmed = text.trim();
                if (!trimmed || (trimmed[0] !== '{' && trimmed[0] !== '[')) {
                    if (res.redirected || trimmed.startsWith('<!') || trimmed.toLowerCase().includes('<html')) {
                        window.location.href = '../login?error=sesion';
                        throw new Error('Sesión expirada');
                    }
                    throw new Error('Respuesta inválida del servidor');
                }
                try { return JSON.parse(trimmed); } catch (e) { throw new Error('Error al procesar datos'); }
            });
        }

        // ── Switches helpers ──────────────────────────────────────────────────

        function aplicarEstadoSwitches(esAdmin, modulosActivos, prefix) {
            TODOS_MODULOS.forEach(slug => {
                const sw = document.getElementById(prefix + slug);
                if (!sw) return;
                if (esAdmin) {
                    sw.checked = true;
                    sw.disabled = true;
                } else {
                    sw.checked = modulosActivos.includes(slug);
                    sw.disabled = false;
                }
            });
        }

        // ── Nuevo Usuario: rol → mostrar/ocultar switches ─────────────────────

        document.getElementById('selectRolNuevo').addEventListener('change', function () {
            const esAdmin = this.value === 'admin';
            document.getElementById('nuevosModulosSection').style.display = esAdmin ? 'none' : '';
            if (esAdmin) {
                document.querySelectorAll('.nuevo-modulo-switch').forEach(sw => { sw.checked = true; sw.disabled = true; });
            } else {
                document.querySelectorAll('.nuevo-modulo-switch').forEach(sw => { sw.disabled = false; });
            }
        });

        // ── Editar Usuario: rol → actualizar switches ─────────────────────────

        document.getElementById('editRolSelect').addEventListener('change', function () {
            aplicarEstadoSwitches(this.value === 'admin', [], 'mod_');
        });

        // ── 1. Cargar Usuarios ────────────────────────────────────────────────

        function loadUsers() {
            fetch('../api/api_usuarios?type=users')
                .then(res => parseJsonResponse(res))
                .then(data => {
                    if (!data.ok) {
                        document.getElementById('totalUsuarios').textContent = '0';
                        document.getElementById('tableUsersBody').innerHTML =
                            '<tr><td colspan="6" class="text-center text-danger py-4">' + (data.error || 'Error al cargar usuarios.') + '</td></tr>';
                        return;
                    }
                    usersLastList = data.data || [];
                    document.getElementById('totalUsuarios').textContent = usersLastList.length;
                    renderUsers(usersLastList);
                })
                .catch(() => {
                    document.getElementById('tableUsersBody').innerHTML =
                        '<tr><td colspan="6" class="text-center text-danger py-4">Error al cargar datos.</td></tr>';
                });
        }

        function renderUsers(users) {
            const isMobile = window.innerWidth < 992;
            const tbody = document.getElementById('tableUsersBody');
            const cardsContainer = document.getElementById('usersCardsContainer');
            tbody.innerHTML = '';
            if (cardsContainer) cardsContainer.innerHTML = '';

            if (!users || users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No hay usuarios registrados.</td></tr>';
                return;
            }

            if (isMobile) {
                cardsContainer.innerHTML = users.map(u => {
                    const nombre = u.nombre_completo || u.username || '-';
                    const nivel  = u.rol === 'admin' ? 'Administrador' : 'Usuario';
                    return `<div class="app-mobile-card d-flex align-items-start justify-content-between gap-2">
                        <div class="flex-grow-1 min-w-0">
                            <div class="app-mobile-card-title">${esc(nombre)}</div>
                            <div class="app-mobile-card-subtitle">${nivel}</div>
                        </div>
                        <button type="button" class="app-mobile-card-more flex-shrink-0"
                            onclick="event.stopPropagation();openUserActionsSheet(${u.id})" aria-label="Más acciones">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                    </div>`;
                }).join('');
                return;
            }

            users.forEach(u => {
                const esAdmin  = u.rol === 'admin';
                const nombre   = esc(u.nombre_completo || u.username || '-');
                const rolBadge = esAdmin
                    ? '<span class="badge bg-warning text-dark rounded-pill">Admin</span>'
                    : '<span class="badge bg-secondary rounded-pill">Usuario</span>';
                let modulosHtml = esAdmin
                    ? '<span class="text-warning small">Acceso total</span>'
                    : (Array.isArray(u.modulos_permitidos) ? u.modulos_permitidos : [])
                        .map(m => `<i class="bi ${MODULO_ICONOS[m]||'bi-circle'} text-primary me-1" title="${m}"></i>`)
                        .join('') || '<span class="text-muted small">Sin módulos</span>';

                tbody.innerHTML += `
                    <tr>
                        <td class="fw-semibold text-white">${nombre}</td>
                        <td>${rolBadge}</td>
                        <td>${modulosHtml}</td>
                        <td class="small text-muted">${esc(u.created_by || '-')}</td>
                        <td class="small text-muted">${esc(u.created_at || '-')}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary me-1" onclick="openEditUser(${u.id})">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger border-0" onclick="deleteUser(${u.id})">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </td>
                    </tr>`;
            });
        }

        function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }

        function openUserActionsSheet(uid) {
            const u = usersLastList.find(i => i.id == uid);
            if (!u || typeof window.openBottomSheet !== 'function') return;
            window.openBottomSheet({
                title: 'Usuario',
                actions: [
                    { label: 'Editar usuario', icon: 'bi-pencil', onClick: () => openEditUser(u.id) },
                    { label: 'Eliminar usuario', icon: 'bi-trash', onClick: () => deleteUser(u.id), danger: true }
                ]
            });
        }

        // ── 2. Cargar Logs ────────────────────────────────────────────────────

        function loadLogs() {
            fetch('../api/api_usuarios?type=logs')
                .then(res => parseJsonResponse(res))
                .then(data => {
                    const tbody = document.getElementById('tableLogsBody');
                    tbody.innerHTML = '';
                    let actividadHoy = 0, loginsHoy = 0;
                    const hoy = new Date().toISOString().split('T')[0];
                    if (data.ok && data.data.length > 0) {
                        data.data.forEach(l => {
                            if (l.fecha_hora && l.fecha_hora.startsWith(hoy)) {
                                actividadHoy++;
                                if (l.accion === 'login_exitoso') loginsHoy++;
                            }
                            const color = l.accion === 'login_exitoso' ? 'text-success'
                                        : l.accion === 'login_fallido' ? 'text-danger'
                                        : l.accion === 'logout'        ? 'text-warning' : 'text-white';
                            tbody.innerHTML += `<tr>
                                <td class="small text-muted">${esc(l.fecha_hora||'-')}</td>
                                <td class="fw-semibold text-white">${esc(l.username||'-')}</td>
                                <td class="small text-light">${esc(l.tipo_usuario||'-')}</td>
                                <td class="${color} fw-semibold small">${esc((l.accion||'').replace(/_/g,' ').toUpperCase())}</td>
                                <td class="font-monospace small text-muted">${esc(l.ip_address||'-')}</td>
                            </tr>`;
                        });
                    }
                    document.getElementById('actividadReciente').textContent = actividadHoy;
                    document.getElementById('loginsHoy').textContent = loginsHoy;
                })
                .catch(() => {
                    document.getElementById('tableLogsBody').innerHTML =
                        '<tr><td colspan="5" class="text-center text-danger py-4">Error al cargar logs.</td></tr>';
                });
        }

        // ── 3. Cargar Auditoría ───────────────────────────────────────────────

        function loadAuditoria() {
            fetch('../api/api_usuarios?type=auditoria')
                .then(res => parseJsonResponse(res))
                .then(data => {
                    const tbody = document.getElementById('tableAuditBody');
                    tbody.innerHTML = '';
                    if (!data.ok || !data.data.length) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay registros de actividad.</td></tr>';
                        return;
                    }
                    data.data.forEach(a => {
                        const color = (a.accion||'').includes('crear')    ? 'text-success'
                                    : (a.accion||'').includes('eliminar') ? 'text-danger'
                                    : (a.accion||'').includes('editar')   ? 'text-warning'
                                    : (a.accion||'').includes('mensaje')  ? 'text-info' : 'text-muted';
                        const ref = (a.cliente && a.folio)
                            ? `<div class="d-flex flex-column"><span class="fw-medium text-white small">${esc(a.cliente)}</span><span class="font-monospace text-info small">#${esc(a.folio)}</span></div>`
                            : `<span class="font-monospace small text-muted">${esc(a.entidad_id||'-')}</span>`;
                        tbody.innerHTML += `<tr>
                            <td class="small text-muted" style="white-space:nowrap;">${esc(a.fecha||'-')}</td>
                            <td class="fw-semibold text-white">${esc(a.username||'-')}</td>
                            <td class="${color} fw-semibold small">${esc((a.accion||'').replace(/_/g,' ').toUpperCase())}</td>
                            <td class="small text-white-50">${esc(a.detalle||'-')}</td>
                            <td>${ref}</td>
                        </tr>`;
                    });
                })
                .catch(() => {
                    document.getElementById('tableAuditBody').innerHTML =
                        '<tr><td colspan="5" class="text-center text-danger py-4">Error al cargar auditoría.</td></tr>';
                });
        }

        // ── 4. Validaciones contraseña ────────────────────────────────────────

        function validarPass(passId, confirmId, errId) {
            const pass = document.getElementById(passId).value;
            const confirm = document.getElementById(confirmId).value;
            const el = document.getElementById(errId);
            if (confirm && pass !== confirm) el.classList.remove('d-none');
            else el.classList.add('d-none');
        }

        document.getElementById('inputPasswordConfirm').addEventListener('input', () => validarPass('inputPassword', 'inputPasswordConfirm', 'passwordMatchError'));
        document.getElementById('inputPassword').addEventListener('input', () => validarPass('inputPassword', 'inputPasswordConfirm', 'passwordMatchError'));
        document.getElementById('editPasswordConfirm').addEventListener('input', () => validarPass('editPassword', 'editPasswordConfirm', 'passwordEditMatchError'));
        document.getElementById('editPassword').addEventListener('input', () => validarPass('editPassword', 'editPasswordConfirm', 'passwordEditMatchError'));

        // ── 5. Crear Usuario ──────────────────────────────────────────────────

        document.getElementById('formUsuario').addEventListener('submit', function (e) {
            e.preventDefault();
            if (document.getElementById('inputPassword').value !== document.getElementById('inputPasswordConfirm').value) {
                document.getElementById('passwordMatchError').classList.remove('d-none');
                return;
            }
            document.getElementById('passwordMatchError').classList.add('d-none');

            fetch('../api/api_usuarios', { method: 'POST', body: new FormData(this) })
                .then(res => parseJsonResponse(res))
                .then(data => {
                    const msgBox = document.getElementById('msgBox');
                    if (data.ok) {
                        msgBox.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i> Usuario creado</span>';
                        this.reset();
                        document.getElementById('nuevosModulosSection').style.display = '';
                        loadUsers();
                    } else {
                        msgBox.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle me-1"></i> ${esc(data.error||'Error')}</span>`;
                    }
                })
                .catch(() => {
                    document.getElementById('msgBox').innerHTML = '<span class="text-danger fw-bold">Error de conexión.</span>';
                });
        });

        // ── 6. Eliminar Usuario ───────────────────────────────────────────────

        function deleteUser(id) {
            if (!confirm('¿Eliminar este usuario?')) return;
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fetch('../api/api_usuarios', { method: 'POST', body: fd })
                .then(res => parseJsonResponse(res))
                .then(data => { if (data.ok) loadUsers(); else alert(data.error || 'Error al eliminar'); })
                .catch(() => alert('Error de conexión'));
        }

        // ── 7. Abrir edición ──────────────────────────────────────────────────

        function openEditUser(id) {
            const user = usersLastList.find(u => u.id == id);
            if (!user) return;

            document.getElementById('editUserId').value = user.id;
            document.getElementById('editUsername').value = user.username || '';

            const esAdmin = user.rol === 'admin';
            const rolSelect = document.getElementById('editRolSelect');
            rolSelect.value = esAdmin ? 'admin' : 'usuario';

            const modulos = Array.isArray(user.modulos_permitidos)
                ? user.modulos_permitidos
                : (typeof user.modulos_permitidos === 'string'
                    ? JSON.parse(user.modulos_permitidos || '[]')
                    : []);

            aplicarEstadoSwitches(esAdmin, modulos, 'mod_');

            document.getElementById('editPassword').value = '';
            document.getElementById('editPasswordConfirm').value = '';
            document.getElementById('passwordEditMatchError').classList.add('d-none');
            document.getElementById('msgBoxEdit').innerHTML = '';

            const offcanvasEl = document.getElementById('offcanvasEditarUsuario');
            if (offcanvasEl) bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl).show();
        }

        // ── 8. Guardar edición ────────────────────────────────────────────────

        document.getElementById('formUsuarioEditar').addEventListener('submit', function (e) {
            e.preventDefault();
            const pass = document.getElementById('editPassword').value;
            const passConfirm = document.getElementById('editPasswordConfirm').value;
            if ((pass || passConfirm) && pass !== passConfirm) {
                document.getElementById('passwordEditMatchError').classList.remove('d-none');
                return;
            }
            document.getElementById('passwordEditMatchError').classList.add('d-none');

            fetch('../api/api_usuarios', { method: 'POST', body: new FormData(this) })
                .then(res => parseJsonResponse(res))
                .then(data => {
                    const msgBox = document.getElementById('msgBoxEdit');
                    if (data.ok) {
                        msgBox.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i> Cambios guardados</span>';
                        loadUsers();
                        setTimeout(() => {
                            bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('offcanvasEditarUsuario')).hide();
                        }, 600);
                    } else {
                        msgBox.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle me-1"></i> ${esc(data.error||'Error')}</span>`;
                    }
                })
                .catch(() => {
                    document.getElementById('msgBoxEdit').innerHTML = '<span class="text-danger fw-bold">Error de conexión.</span>';
                });
        });
    })(); // IIFE
    </script>

<?php if (!$isFragment): ?>
    <?php include '../includes/pwa_script.php'; ?>
</main>
</body>

</html>
<?php endif; ?>