<?php
$asset_base = strpos($_SERVER['SCRIPT_NAME'], '/modules/') !== false ? '../' : './';
// APP_VERSION se define en env_loader (via auth.php o db.php que ya fue incluido antes)
$_av = defined('APP_VERSION') ? APP_VERSION : date('Ymd');
?>
<!-- Resource Hints — acelera conexión a CDNs -->
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="dns-prefetch" href="https://cdn.sheetjs.com">

<!-- Google Fonts (centralizado — no repetir en módulos) -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- Bootstrap Icons (centralizado) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<!-- Bootstrap CSS (centralizado) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Tema: aplicar ANTES del primer pintado para evitar flash -->
<script>
(function(){
    var t = localStorage.getItem('sc_theme') || 'dark';
    document.documentElement.setAttribute('data-bs-theme', t);
    var themeColor = t === 'light' ? '#f1f5f9' : '#0f172a';
    document.addEventListener('DOMContentLoaded', function() {
        var m = document.querySelector('meta[name="theme-color"]');
        if (m) m.setAttribute('content', themeColor);
    });
})();
</script>
<!-- PWA Meta Tags -->
<meta name="viewport"
    content="width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=no, viewport-fit=cover">

<!-- PWA Manifest -->
<link rel="manifest" href="<?= $asset_base ?>manifest.json">

<!-- Theme Color -->
<meta name="theme-color" content="#0f172a">
<meta name="msapplication-TileColor" content="#0f172a">
<meta name="msapplication-navbutton-color" content="#0f172a">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

<!-- PWA Display Mode -->
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="SOLUCIONESCEL">

<!-- Icons -->
<link rel="icon" type="image/svg+xml" href="<?= $asset_base ?>assets/logo.svg">
<link rel="apple-touch-icon" href="<?= $asset_base ?>assets/logo.svg">
<link rel="apple-touch-icon" sizes="180x180" href="<?= $asset_base ?>assets/logo.svg">

<!-- Global UI Styles -->
<link rel="stylesheet" href="<?= $asset_base ?>assets/css/app.css?v=<?= $_av ?>">

<!-- Supabase JS SDK (UMD para window.supabase global) -->
<script defer src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.min.js"></script>
<?php include __DIR__ . '/../config/supabase_js_config.php'; ?>
<script defer src="<?= $asset_base ?>assets/js/supabase-client.js?v=<?= $_av ?>"></script>
<!-- Utilidades compartidas (escapeHtml, fmtDate, getEstadoColor, getEstadoLabel, SCToast, onModuleReady) -->
<script defer src="<?= $asset_base ?>assets/js/utils.js?v=<?= $_av ?>"></script>
<!-- Shared UI Layout Scripts -->
<script defer src="<?= $asset_base ?>assets/js/bottom-sheet.js?v=<?= $_av ?>"></script>

<!-- Bootstrap JS Bundle -->
<script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- SPA Router — navegación sin full reload -->
<script defer src="<?= $asset_base ?>assets/js/spa-router.js?v=<?= $_av ?>"></script>

<!-- Splash Screens (iOS) -->
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

<!-- Description for PWA -->
<meta name="description" content="Sistema completo de gestión para taller y usuarios - SOLUCIONESCEL">
<meta name="application-name" content="SOLUCIONESCEL">
