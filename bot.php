<?php
// Cargar variables de entorno
require_once __DIR__ . '/config/env_loader.php';

$webhookSecret = getenv('WEBHOOK_SECRET');
if ($webhookSecret !== false && $webhookSecret !== '') {
    $provided = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? ($_GET['secret'] ?? null);
    if (!is_string($provided) || !hash_equals($webhookSecret, $provided)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Acceso denegado']);
        exit;
    }
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/src/Shared/TenantContext.php';

$tenantId = (int) (getenv('TENANT_ID_DEFAULT') ?: 1);
TenantContext::setTenantId($tenantId);

$remoteJid = isset($_GET['id']) ? $_GET['id'] : '';

$mensaje = '';
$tipo = '';

if (!empty($remoteJid)) {
    $soporteRepo = new SoporteRepository($supabase);
    $soporteService = new SoporteService($soporteRepo);

    $respuesta = $soporteService->reactivarBot(['remote_jid' => $remoteJid]);

    if ($respuesta['success'] ?? false) {
        $mensaje = '¡Bot reactivado exitosamente!';
        $tipo = 'exito';
    } else {
        $mensaje = $respuesta['error'] ?? 'Error al reactivar el bot. Intenta nuevamente.';
        $tipo = 'error';
    }
} else {
    $mensaje = 'ID de cliente no válido';
    $tipo = 'error';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reactivar Bot - SolucionesCel</title>
    <?php include 'includes/head_meta.php'; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .container {
            background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 450px; width: 100%; padding: 40px; text-align: center; animation: slideUp 0.5s ease;
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .icon { width: 80px; height: 80px; margin: 0 auto 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; }
        .icon.exito { background: #d4edda; color: #28a745; }
        .icon.error { background: #f8d7da; color: #dc3545; }
        h1 { color: #2d3748; font-size: 24px; margin-bottom: 12px; font-weight: 600; }
        p { color: #718096; font-size: 16px; line-height: 1.6; margin-bottom: 30px; }
        .btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 14px 32px; border-radius: 10px; font-size: 16px; font-weight: 500; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; text-decoration: none; display: inline-block; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4); }
        .btn:active { transform: translateY(0); }
        .logo { font-size: 14px; color: #a0aec0; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .logo strong { color: #667eea; font-weight: 600; }
    </style>
</head>

<body>
    <div class="container">
        <div class="icon <?php echo $tipo; ?>">
            <?php echo $tipo === 'exito' ? '✓' : '✕'; ?>
        </div>

        <h1><?php echo $tipo === 'exito' ? '¡Listo!' : 'Ups...'; ?></h1>
        <p><?php echo htmlspecialchars($mensaje); ?></p>

        <?php if ($tipo === 'exito'): ?>
            <p style="font-size: 14px; color: #a0aec0;">Puedes cerrar esta ventana</p>
        <?php else: ?>
            <button class="btn" onclick="window.location.reload()">Reintentar</button>
        <?php endif; ?>

        <div class="logo">
            <strong>SolucionesCel</strong> Bot Assistant
        </div>
    </div>
</body>
</html>
