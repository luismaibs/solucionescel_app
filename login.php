<?php
try {
    include 'config/auth.php';
    // Asegurar que el CSRF token existe antes de mostrar el formulario
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
} catch (Throwable $e) {
    header('Content-Type: text/html; charset=utf-8');
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error</title></head><body style="font-family:sans-serif;max-width:600px;margin:2rem auto;padding:1rem;background:#fef2f2;color:#991b1b;">';
    echo '<h1>Error de configuración</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><small>' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</small></p></body></html>';
    exit;
}

// Si ya esta logueado (JWT en cookie o sesion PHP), mandar al panel
$jwt = getJwtFromRequest();
$yaLogueado = !empty($jwt) && jwtIsValid($jwt);
if ($yaLogueado) {
    header("Location: index");
    exit;
}

// Datos reales para el panel derecho (solo al mostrar el formulario)
$chartEstadosLabels = [];
$chartEstadosData = [];
$kpiTotal = 0;
$kpiEnTaller = 0;
$kpiListos = 0;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($supabase)) {
    try {
        $analiticasRepo = new AnaliticasRepository($supabase);
        $kpis = $analiticasRepo->getKpisYGraficoLogin();
        $chartEstadosLabels = $kpis['chart_estados_labels'];
        $chartEstadosData = $kpis['chart_estados_data'];
        $kpiTotal = $kpis['kpi_total'];
        $kpiEnTaller = $kpis['kpi_en_taller'];
        $kpiListos = $kpis['kpi_listos'];
    } catch (Throwable $e) {
        // Si falla la BD o faltan tablas, mostramos el login sin gráfico
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark" class="login-page-html">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso | SOLUCIONESCEL</title>
    <?php include 'includes/head_meta.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --login-bg: #0f172a;
            --login-card-bg: rgba(30, 41, 59, 0.85);
            --login-primary: #3b82f6;
            --login-panel-bg: linear-gradient(160deg, #1e3a5f 0%, #0f172a 50%, #1e1b4b 100%);
        }

        /* Bloquea scroll y anula padding global; fondo oscuro como los módulos */
        html.login-page-html,
        body.login-page {
            margin: 0 !important;
            padding: 0 !important;
            padding-top: 0 !important;
            height: 100%;
            min-height: 100vh;
            max-height: 100vh;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
            background: var(--login-bg);
            background-image: radial-gradient(circle at top right, #1e293b 0%, #0f172a 40%);
        }

        .login-wrapper {
            display: flex;
            min-height: 100vh;
            height: 100vh;
            overflow: hidden;
        }

        .login-left {
            flex: 0 0 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: var(--login-bg);
            background-image: radial-gradient(circle at top right, #1e293b 0%, #0f172a 40%);
        }

        .login-form-card {
            width: 100%;
            max-width: 400px;
            background: var(--login-card-bg);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 2.5rem 2.75rem;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.3);
        }

        .login-brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .login-brand-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(59, 130, 246, 0.12);
            color: var(--login-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .login-brand-text {
            font-weight: 700;
            font-size: 1.35rem;
            color: #f1f5f9;
            letter-spacing: -0.02em;
        }

        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            color: rgba(241, 245, 249, 0.75);
            font-size: 0.9375rem;
            margin-bottom: 1.75rem;
        }

        .login-left .form-label {
            color: rgba(241, 245, 249, 0.9);
            font-weight: 500;
            font-size: 0.875rem;
        }

        .login-left .form-control {
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.9375rem;
            background: rgba(15, 23, 42, 0.6);
            color: #f1f5f9;
        }

        .login-left .form-control:focus {
            border-color: var(--login-primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
            background: rgba(15, 23, 42, 0.8);
            color: #f1f5f9;
        }

        .login-left .form-control::placeholder {
            color: rgba(148, 163, 184, 0.8);
        }

        .btn-login {
            width: 100%;
            padding: 0.875rem;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
            color: white;
        }

        .login-right {
            flex: 0 0 50%;
            background: var(--login-panel-bg);
            background-image: radial-gradient(ellipse 80% 50% at 70% 20%, rgba(59, 130, 246, 0.15), transparent),
                radial-gradient(ellipse 60% 40% at 20% 80%, rgba(99, 102, 241, 0.12), transparent);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            position: relative;
        }

        .login-right-inner {
            width: 100%;
            max-width: 420px;
            text-align: center;
        }

        .login-panel-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .login-panel-subtitle {
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.9375rem;
            margin-bottom: 1.5rem;
        }

        .login-kpi-row {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .login-kpi-pill {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 0.65rem 1rem;
            min-width: 100px;
        }

        .login-kpi-pill-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
        }

        .login-kpi-pill-label {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.65);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .login-charts {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            width: 100%;
        }

        .login-chart-card {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 1.25rem;
        }

        .login-chart-title {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .login-chart-container {
            position: relative;
            height: 200px;
        }

        .login-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.75rem;
            color: rgba(241, 245, 249, 0.5);
        }

        .login-form-card .alert-danger {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
        }

        /* Móvil: solo formulario, sin panel derecho */
        @media (max-width: 991.98px) {
            .login-right {
                display: none !important;
            }

            .login-left {
                flex: 1 1 100%;
                min-height: 100vh;
                padding: 1.5rem 1.25rem;
            }

            .login-form-card {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>

<body class="login-page">
    <div class="login-wrapper">
        <div class="login-left">
            <div class="login-form-card text-start">
                <div class="login-brand">
                    <div class="login-brand-icon">
                        <i class="bi bi-cpu-fill"></i>
                    </div>
                    <span class="login-brand-text">SOLUCIONESCEL</span>
                </div>

                <h1 class="login-title">Bienvenido</h1>
                <p class="login-subtitle">Ingresa tu usuario y contraseña para acceder al panel.</p>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger border-0 py-2 mb-3 d-flex align-items-center">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <?php
                        $errKey = $_GET['error'];
                        $errMessages = [
                            'credenciales' => 'Credenciales incorrectas',
                            'email_no_confirmado' => 'Tu email no ha sido confirmado. Revisa tu bandeja.',
                            'rate_limit' => 'Demasiados intentos. Espera un momento.',
                            'no_autorizado' => 'No tienes permiso para acceder.',
                        ];
                        echo htmlspecialchars($errMessages[$errKey] ?? 'Error de autenticacion');
                        ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                            placeholder="tu@email.com" required autocomplete="username">
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="Contraseña" required autocomplete="current-password">
                    </div>

                    <button type="submit" class="btn btn-login">
                        Iniciar sesión <i class="bi bi-arrow-right-short ms-1"></i>
                    </button>
                </form>
                <p class="login-footer mb-0">© <?= date('Y') ?> SOLUCIONESCEL</p>
            </div>
        </div>

        <div class="login-right">
            <div class="login-right-inner">
                <h2 class="login-panel-title">Gestiona tu taller y operaciones desde un solo lugar.</h2>
                <p class="login-panel-subtitle">Inicia sesión para acceder al panel de reparaciones, inventario y soporte.</p>

                <div class="login-kpi-row">
                    <div class="login-kpi-pill">
                        <div class="login-kpi-pill-value"><?= $kpiTotal ?></div>
                        <div class="login-kpi-pill-label">Total</div>
                    </div>
                    <div class="login-kpi-pill">
                        <div class="login-kpi-pill-value"><?= $kpiEnTaller ?></div>
                        <div class="login-kpi-pill-label">En taller</div>
                    </div>
                    <div class="login-kpi-pill">
                        <div class="login-kpi-pill-value"><?= $kpiListos ?></div>
                        <div class="login-kpi-pill-label">Listos</div>
                    </div>
                </div>

                <div class="login-charts">
                    <div class="login-chart-card">
                        <div class="login-chart-title">Reparaciones por estado</div>
                        <div class="login-chart-container">
                            <canvas id="chartEstados"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var estadosLabels = <?= json_encode($chartEstadosLabels) ?>;
            var estadosData = <?= json_encode($chartEstadosData) ?>;
            var colors = ['#3b82f6', '#10b981', '#f59e0b', '#6366f1', '#ec4899', '#14b8a6', '#f97316'];

            if (typeof Chart !== 'undefined' && estadosLabels.length > 0) {
                new Chart(document.getElementById('chartEstados'), {
                    type: 'doughnut',
                    data: {
                        labels: estadosLabels,
                        datasets: [{
                            data: estadosData,
                            backgroundColor: colors.slice(0, estadosLabels.length),
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: '#94a3b8', font: { size: 11 } }
                            }
                        }
                    }
                });
            }

            // ── Progressive Enhancement: login via Supabase JS ──
            var form = document.querySelector('form[method="POST"]');
            if (form) {
                form.addEventListener('submit', function (e) {
                    // Solo hacer preventDefault si SupabaseFrontend está disponible
                    if (typeof window.SupabaseFrontend !== 'undefined') {
                        e.preventDefault();
                        var email = document.getElementById('email').value.trim();
                        var password = document.getElementById('password').value;
                        var btn = form.querySelector('button[type="submit"]');
                        var originalHtml = btn.innerHTML;

                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Iniciando...';

                        window.SupabaseFrontend.signIn(email, password).then(function (r) {
                            if (r.ok) {
                                window.location.href = 'index';
                            } else {
                                btn.disabled = false;
                                btn.innerHTML = originalHtml;
                                // Mostrar error inline
                                var alertDiv = form.querySelector('.alert-danger');
                                if (!alertDiv) {
                                    alertDiv = document.createElement('div');
                                    alertDiv.className = 'alert alert-danger border-0 py-2 mb-3 d-flex align-items-center';
                                    alertDiv.innerHTML = '<i class="bi bi-exclamation-circle me-2"></i>Credenciales incorrectas';
                                    form.insertBefore(alertDiv, form.firstChild);
                                } else {
                                    alertDiv.style.display = '';
                                }
                            }
                        }).catch(function () {
                            btn.disabled = false;
                            btn.innerHTML = originalHtml;
                            // Fallback: submit normal via PHP
                            form.submit();
                        });
                    }
                    // Si SupabaseFrontend no está disponible, permitir submit normal
                });
            }
        })();
    </script>
</body>

</html>
