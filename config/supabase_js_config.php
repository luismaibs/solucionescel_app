<?php
/**
 * Configuracion de Supabase expuesta al frontend JavaScript.
 *
 * Se incluye en paginas que necesitan el cliente supabase-js.
 * Expone URL y ANON_KEY (NUNCA la service_role).
 */
include_once __DIR__ . '/env_loader.php';

$supabaseJsUrl = rtrim((string) getenv('SUPABASE_URL'), '/');
$supabaseJsKey = getenv('SUPABASE_ANON_KEY') ?: '';
?>
<script>
window.SUPABASE_CONFIG = {
    url: <?= json_encode($supabaseJsUrl) ?>,
    anonKey: <?= json_encode($supabaseJsKey) ?>
};
</script>
