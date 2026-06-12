<?php
include 'config/auth.php';
requireLogin();
include_once 'includes/fragment_helper.php';

$displayName = trim(getCurrentFullName() ?: getCurrentUsername());
if ($displayName !== '') {
    $parts = preg_split('/\s+/', $displayName);
    $first = substr($parts[0], 0, 1);
    $last  = isset($parts[count($parts) - 1]) ? substr($parts[count($parts) - 1], 0, 1) : '';
    $initials = strtoupper($first . $last);
} else {
    $initials = 'SC';
}
$currentRole = getCurrentRole();
$rolLabel    = $currentRole === 'admin' ? 'Administrador' : 'Usuario';
$rolColor    = $currentRole === 'admin' ? 'warning' : 'primary';
?>
<?php if (!$isFragment): ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mi Perfil 360° | SOLUCIONESCEL</title>
    <?php include 'includes/head_meta.php'; ?>
<?php endif; ?>
    <style data-module-css="index">
        .info-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 600; margin-bottom: 0.25rem; }
        .info-value { font-size: 0.95rem; color: var(--text-main); font-weight: 500; }
        .glass-card { background: var(--glass-bg); backdrop-filter: blur(12px); border: 1px solid var(--glass-border); border-radius: var(--radius-xl); box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        .back-link { color: var(--text-muted); text-decoration: none; transition: color 0.15s; font-size: 0.9rem; }
        .back-link:hover { color: var(--text-main); }
        .pwa-install-card { background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(59,130,246,0.15)); border: 1px solid rgba(99,102,241,0.3); border-radius: var(--radius-xl); padding: 1.5rem; }
    </style>
<?php if (!$isFragment): ?>
</head>

<body>
    <?php include 'includes/header.php'; ?>
<?php endif; ?>

    <div class="container-xl main-content-push" style="max-width: 960px;">
        <div class="mb-4">
            <a href="modules/panel" class="back-link"><i class="bi bi-arrow-left me-1"></i>Volver al panel</a>
        </div>

        <!-- Loading -->
        <div id="loadingState" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted small mt-3">Cargando tu perfil...</p>
        </div>

        <!-- Contenido principal -->
        <div id="mainContent" class="d-none">

            <!-- Hero del perfil -->
            <div class="glass-card p-4 mb-4">
                <div class="u360-hero">
                    <div class="u360-avatar"><?= htmlspecialchars($initials) ?></div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h3 class="fw-bold m-0" id="heroNombre"><?= htmlspecialchars($displayName) ?></h3>
                            <span class="badge bg-<?= $rolColor ?> bg-opacity-20 text-<?= $rolColor ?> border border-<?= $rolColor ?> border-opacity-25 rounded-pill px-3"><?= htmlspecialchars($rolLabel) ?></span>
                        </div>
                        <p class="text-muted small mb-0 mt-1" id="heroEmail"><?= htmlspecialchars(getCurrentUsername()) ?></p>
                        <p class="text-muted small mb-0" id="heroFechaAlta"></p>
                    </div>
                </div>
            </div>

            <!-- KPIs personales -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="u360-stat-card d-flex flex-column gap-2">
                        <div class="u360-stat-icon bg-primary bg-opacity-15 text-primary"><i class="bi bi-calendar-check"></i></div>
                        <div class="u360-stat-value" id="kpiDias">—</div>
                        <div class="u360-stat-label">Días en el sistema</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="u360-stat-card d-flex flex-column gap-2">
                        <div class="u360-stat-icon bg-success bg-opacity-15 text-success"><i class="bi bi-people"></i></div>
                        <div class="u360-stat-value" id="kpiClientes">—</div>
                        <div class="u360-stat-label">Clientes ingresados</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="u360-stat-card d-flex flex-column gap-2">
                        <div class="u360-stat-icon bg-info bg-opacity-15 text-info"><i class="bi bi-tools"></i></div>
                        <div class="u360-stat-value" id="kpiReps">—</div>
                        <div class="u360-stat-label">Reparaciones gestionadas</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="u360-stat-card d-flex flex-column gap-2">
                        <div class="u360-stat-icon bg-warning bg-opacity-15 text-warning"><i class="bi bi-graph-up-arrow"></i></div>
                        <div class="u360-stat-value" id="kpiTasa">—</div>
                        <div class="u360-stat-label">Tasa de éxito</div>
                    </div>
                </div>
            </div>

            <!-- Detalle de actividad + PWA -->
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="glass-card p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Resumen de actividad</h6>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="info-label">Miembro desde</div>
                                <div class="info-value" id="detFechaAlta">—</div>
                            </div>
                            <div class="col-6">
                                <div class="info-label">Rol en el sistema</div>
                                <div class="info-value"><?= htmlspecialchars($rolLabel) ?></div>
                            </div>
                            <div class="col-6">
                                <div class="info-label">Reparaciones completadas</div>
                                <div class="info-value" id="detCompletadas">—</div>
                            </div>
                            <div class="col-6">
                                <div class="info-label">Reparaciones en curso / pendientes</div>
                                <div class="info-value" id="detPendientes">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Instalar PWA -->
                    <div class="pwa-install-card d-flex flex-column gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="u360-stat-icon bg-primary bg-opacity-20 text-primary">
                                <i class="bi bi-phone-vibrate fs-5"></i>
                            </div>
                            <div>
                                <div class="fw-semibold" style="font-size:0.95rem;">Instalar la app</div>
                                <div class="text-muted" style="font-size:0.78rem;">Acceso rápido desde tu dispositivo</div>
                            </div>
                        </div>
                        <p class="text-muted small mb-0">
                            Instala SOLUCIONESCEL como aplicación web en tu celular o computadora para abrirla sin navegador y en pantalla completa.
                        </p>
                        <button type="button" class="btn btn-primary w-100 rounded-pill"
                            data-bs-toggle="modal" data-bs-target="#pwaInstallModal">
                            <i class="bi bi-download me-2"></i>Instalar PWA
                        </button>
                    </div>
                </div>
            </div>

        </div><!-- /mainContent -->

        <!-- Estado de error -->
        <div id="errorState" class="d-none text-center py-5">
            <i class="bi bi-exclamation-triangle text-warning fs-1 mb-3 d-block"></i>
            <p class="text-muted">No se pudieron cargar los datos del perfil.</p>
            <button class="btn btn-sm btn-outline-primary rounded-pill" onclick="cargarPerfil()">
                <i class="bi bi-arrow-clockwise me-1"></i>Reintentar
            </button>
        </div>

    </div><!-- /container -->

    <script>
    function fmtFecha(iso) {
        if (!iso) return '—';
        var d = new Date(iso);
        var meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
        return d.getDate() + ' ' + meses[d.getMonth()] + ' ' + d.getFullYear();
    }

    function cargarPerfil() {
        document.getElementById('loadingState').classList.remove('d-none');
        document.getElementById('mainContent').classList.add('d-none');
        document.getElementById('errorState').classList.add('d-none');

        fetch((window.APP_API_BASE || 'api/') + 'api_usuario_360')
            .then(function(r){ return r.json(); })
            .then(function(res) {
                if (!res.ok) throw new Error(res.message || 'Error');
                var d = res.data;

                document.getElementById('heroFechaAlta').textContent = d.created_at
                    ? 'Miembro desde ' + fmtFecha(d.created_at) : '';
                document.getElementById('kpiDias').textContent      = d.dias_en_sistema;
                document.getElementById('kpiClientes').textContent  = d.clientes_ingresados;
                document.getElementById('kpiReps').textContent      = d.reparaciones_total;
                document.getElementById('kpiTasa').textContent      = d.tasa_exito + '%';

                document.getElementById('detFechaAlta').textContent    = fmtFecha(d.created_at);
                document.getElementById('detCompletadas').textContent  = d.reparaciones_completadas;
                document.getElementById('detPendientes').textContent   = d.reparaciones_total - d.reparaciones_completadas;

                document.getElementById('loadingState').classList.add('d-none');
                document.getElementById('mainContent').classList.remove('d-none');
            })
            .catch(function() {
                document.getElementById('loadingState').classList.add('d-none');
                document.getElementById('errorState').classList.remove('d-none');
            });
    }

    onModuleReady(cargarPerfil);
    </script>

<?php if (!$isFragment): ?>
    <?php include 'includes/pwa_script.php'; ?>
</main>
</body>
</html>
<?php endif; ?>
