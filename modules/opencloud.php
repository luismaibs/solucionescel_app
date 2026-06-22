<?php
include '../config/auth.php';
requireLogin();
include_once '../includes/fragment_helper.php';

if (function_exists('puedeVerModulo') && !puedeVerModulo('opencloud')) {
    redirectTo('index', 'error=no_autorizado');
}

$openCloudUrl = 'https://cloud.solucionescel.com';
?>
<?php if (!$isFragment): ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>OpenCloud | SOLUCIONESCEL</title>
    <?php include '../includes/head_meta.php'; ?>
<?php endif; ?>
    <style data-module-css="opencloud">
        .opencloud-shell {
            height: calc(100dvh - var(--topbar-height) - var(--subheader-height) - 72px);
            min-height: 620px;
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            background: rgba(15, 23, 42, 0.72);
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.24);
        }

        .opencloud-frame {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
            background: #ffffff;
        }

        @media (max-width: 991.98px) {
            .opencloud-shell {
                height: calc(100dvh - var(--topbar-height) - var(--subheader-height) - 52px);
                min-height: 520px;
                border-radius: var(--radius-md);
            }
        }
    </style>
<?php if (!$isFragment): ?>
</head>

<body>
    <?php include '../includes/header.php'; ?>
<?php endif; ?>

    <div class="container-xl main-content-push with-subheader pb-4" style="max-width: 1600px;">
        <div class="module-subheader">
            <div class="module-subheader-kpis">
                <span class="module-subheader-title">OpenCloud</span>
                <span class="module-kpi-chip">
                    <i class="bi bi-cloud-arrow-up-fill kpi-icon text-primary"></i>
                    <span class="kpi-value">Cloud</span>
                    <span class="kpi-label">SOLUCIONESCEL</span>
                </span>
            </div>
            <div class="module-subheader-actions">
                <a href="<?= htmlspecialchars($openCloudUrl) ?>" target="_blank" rel="noopener noreferrer"
                    class="btn btn-outline-light" style="border-color: rgba(255,255,255,0.2);">
                    <i class="bi bi-box-arrow-up-right"></i> Abrir aparte
                </a>
            </div>
        </div>

        <div class="opencloud-shell">
            <iframe
                class="opencloud-frame"
                src="<?= htmlspecialchars($openCloudUrl) ?>"
                title="OpenCloud SOLUCIONESCEL"
                loading="eager"
                allow="clipboard-read; clipboard-write; fullscreen; web-share"
                referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
    </div>

<?php if (!$isFragment): ?>
    <?php include '../includes/pwa_script.php'; ?>
</main>
</body>
</html>
<?php endif; ?>
