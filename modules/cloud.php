<?php
include '../config/auth.php';
requireLogin();
include_once '../includes/fragment_helper.php';

if (function_exists('puedeVerModulo') && !puedeVerModulo('opencloud')) {
    redirectTo('index', 'error=no_autorizado');
}

$cloudUrl = 'https://cloud.solucionescel.com';
?>
<?php if (!$isFragment): ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cloud | SOLUCIONESCEL</title>
    <?php include '../includes/head_meta.php'; ?>
<?php endif; ?>
    <style data-module-css="cloud">
        body.with-sidebar .cloud-content.main-content-push {
            padding-top: calc(var(--topbar-height) + env(safe-area-inset-top, 0px));
            padding-left: 0;
            padding-right: 0;
            padding-bottom: 0;
            height: 100dvh;
            overflow: hidden;
        }

        .cloud-frame {
            width: 100%;
            height: calc(100dvh - var(--topbar-height) - env(safe-area-inset-top, 0px));
            border: 0;
            display: block;
            background: #ffffff;
        }

        @media (max-width: 991.98px) {
            body.with-sidebar .cloud-content.main-content-push {
                margin-left: 0;
                width: 100%;
                max-width: 100% !important;
                padding-top: calc(var(--topbar-height) + env(safe-area-inset-top, 0px));
                padding-left: 0;
                padding-right: 0;
                padding-bottom: 0;
            }
        }
    </style>
<?php if (!$isFragment): ?>
</head>

<body>
    <?php include '../includes/header.php'; ?>
<?php endif; ?>

    <div class="cloud-content main-content-push">
        <iframe
            class="cloud-frame"
            src="<?= htmlspecialchars($cloudUrl) ?>"
            title="Cloud SOLUCIONESCEL"
            loading="eager"
            allow="clipboard-read; clipboard-write; fullscreen; web-share"
            referrerpolicy="strict-origin-when-cross-origin"></iframe>
    </div>

<?php if (!$isFragment): ?>
    <?php include '../includes/pwa_script.php'; ?>
</main>
</body>
</html>
<?php endif; ?>
