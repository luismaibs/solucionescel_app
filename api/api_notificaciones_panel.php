<?php
/**
 * Panel de notificaciones para el header: dispositivos vencidos (90+ días) y soporte (pausadas + notificaciones).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Shared/TenantContext.php';
if (!class_exists('Utils', false)) {
    require_once __DIR__ . '/../src/Shared/Utils.php';
}

try {
    $tenantId = TenantContext::requireTenant();
    $base_path = strpos($_SERVER['SCRIPT_NAME'], '/modules/') !== false ? '../' : './';

    // Dispositivos vencidos: solo cuando están en estado "listo" y llevan 90+ días desde fecha_listo
    $noventaDiasAtras = date('Y-m-d', strtotime('-90 days'));
    $dispResult = $supabase->get('reparaciones', [
        'select' => 'id,folio_publico,equipo_marca,equipo_modelo,fecha_ingreso,fecha_listo,estado,cliente_id',
        'tenant_id' => 'eq.' . $tenantId,
        'deleted_at' => 'is.null',
        'estado' => 'in.(listo,listo_sin_garantia)',
        'fecha_listo' => 'lte.' . $noventaDiasAtras,
        'order' => 'fecha_listo.asc',
        'limit' => '20',
    ]);
    $dispositivos_vencidos = [];
    if ($dispResult['ok'] && !empty($dispResult['data'])) {
        foreach ($dispResult['data'] as $row) {
            $dias = Utils::daysPassed($row['fecha_listo']);
            $row['dias_en_taller'] = $dias;
            // Resolver nombre del cliente via query separada
            $row['cliente_nombre'] = '';
            if (!empty($row['cliente_id'])) {
                $cli = $supabase->get('clientes', [
                    'select' => 'nombre,apellido',
                    'tenant_id' => 'eq.' . $tenantId,
                    'id' => 'eq.' . $row['cliente_id'],
                    'deleted_at' => 'is.null',
                    'limit' => '1',
                ]);
                if ($cli['ok'] && !empty($cli['data'])) {
                    $row['cliente_nombre'] = trim(($cli['data'][0]['nombre'] ?? '') . ' ' . ($cli['data'][0]['apellido'] ?? ''));
                }
            }
            $dispositivos_vencidos[] = $row;
        }
    }

    // Soporte: conversaciones pausadas → usar repositorio
    $soporteRepo = new SoporteRepository($supabase);
    $soporte_conversaciones = $soporteRepo->findConversacionesPausadasParaNotificaciones(10);
    $soporte_pausadas_count = count($soporte_conversaciones);

    // Notificaciones del sistema (últimas 24h)
    $veinticuatroHorasAtras = date('Y-m-d\TH:i:s', strtotime('-24 hours'));
    $notifResult = $supabase->get('notificaciones_sistema', [
        'select' => 'id,titulo,mensaje,tipo,created_at',
        'tenant_id' => 'eq.' . $tenantId,
        'created_at' => 'gt.' . $veinticuatroHorasAtras,
        'order' => 'id.desc',
        'limit' => '15',
    ]);
    $notificaciones_sistema = ($notifResult['ok'] && !empty($notifResult['data'])) ? $notifResult['data'] : [];
    // Renombrar created_at a creado_at para compatibilidad con frontend
    foreach ($notificaciones_sistema as &$n) {
        $n['creado_at'] = $n['created_at'] ?? null;
    }
    unset($n);

    // Notificaciones configurables (notificaciones_config activas)
    $notifConfigResult = $supabase->get('notificaciones_config', [
        'select' => 'id,slug,titulo,mensaje,tipo,icono,created_at',
        'tenant_id' => 'eq.' . $tenantId,
        'activo' => 'eq.true',
        'order' => 'orden.asc,created_at.desc',
        'limit' => '20',
    ]);
    $notificaciones_configurables = ($notifConfigResult['ok'] && !empty($notifConfigResult['data'])) ? $notifConfigResult['data'] : [];
    foreach ($notificaciones_configurables as &$nc) {
        $nc['creado_at'] = $nc['created_at'] ?? null;
    }
    unset($nc);

    $total_alertas = count($dispositivos_vencidos) + $soporte_pausadas_count + count($notificaciones_sistema) + count($notificaciones_configurables);

    echo json_encode([
        'ok' => true,
        'total_alertas' => $total_alertas,
        'dispositivos_vencidos' => $dispositivos_vencidos,
        'soporte_pausadas_count' => $soporte_pausadas_count,
        'soporte_conversaciones' => $soporte_conversaciones,
        'notificaciones_sistema' => $notificaciones_sistema,
        'notificaciones_configurables' => $notificaciones_configurables,
        'base_path' => $base_path,
    ]);
} catch (Throwable $e) {
    error_log('api_notificaciones_panel: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error al cargar notificaciones']);
}
