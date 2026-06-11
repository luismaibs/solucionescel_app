<?php
/**
 * Script de cron para procesar el flujo Mes Azul diariamente.
 * Envía "Mes Azul Inicio" a equipos con 90+ días listos sin entregar,
 * y "Mes Azul Final" + inactivación a los que cumplen 5 días desde el inicio.
 *
 * Ejemplo crontab (ejecutar una vez al día a las 8:00):
 * 0 8 * * * cd /ruta/solucionescel && php cron/mes_azul_diario.php >> /var/log/mes_azul.log 2>&1
 */

// Ejecución por CLI únicamente (opcional: evitar llamadas por web)
if (php_sapi_name() !== 'cli' && !defined('STDIN')) {
    http_response_code(403);
    exit('Acceso denegado');
}

$baseDir = dirname(__DIR__);
chdir($baseDir);

require_once $baseDir . '/config/db.php';

$webhookN8n = getenv('N8N_WEBHOOK_NOTIFICAR') ?: '';

$repo = new ReparacionRepository($supabase);
$garantiaRepo = new GarantiaRepository($supabase);
$mensajes = new MensajesService($supabase, $webhookN8n);
$mesAzulService = new MesAzulService($supabase, $repo, $mensajes, $garantiaRepo);

try {
    $resultado = $mesAzulService->procesarMesAzulDiario();
    $msg = date('Y-m-d H:i:s') . ' Mes Azul: inicio_enviados=' . $resultado['inicio_enviados']
        . ', final_enviados=' . $resultado['final_enviados'];
    error_log($msg);
    if (php_sapi_name() === 'cli') {
        echo $msg . PHP_EOL;
    }
} catch (Throwable $e) {
    error_log('Mes Azul cron error: ' . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    }
    exit(1);
}
