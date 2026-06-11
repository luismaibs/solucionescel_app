<?php
include '../config/auth.php';
requireLogin();
include '../config/db.php';
require_once __DIR__ . '/../config/api_helper.php';
require_once __DIR__ . '/../src/Shared/TenantContext.php';

if (!isset($_GET['id'])) {
    jsonResponse([], 200);
    exit;
}

$id = (int) $_GET['id'];
$tenantId = TenantContext::requireTenant();

try {
    $result = $supabase->get('historial_mensajes', [
        'select' => '*',
        'tenant_id' => 'eq.' . $tenantId,
        'reparacion_id' => 'eq.' . $id,
        'order' => 'created_at.desc',
    ]);
    $logs = $result['data'] ?? [];

    $data = [];

    foreach ($logs as $log) {
        // Configuramos íconos y colores según el estado
        $badgeClass = 'bg-secondary bg-opacity-25 text-secondary';
        $icon = 'bi-chat-left';
        $textoEstado = 'Desconocido';

        // Lógica de Estado
        switch ($log['estado_envio']) {
            case 'enviado':
                $badgeClass = 'bg-success bg-opacity-25 text-success';
                $textoEstado = 'Enviado';
                $icon = 'bi-whatsapp'; // Ícono de éxito
                break;
            case 'fallido':
                $badgeClass = 'bg-danger bg-opacity-25 text-danger';
                $textoEstado = 'Fallido';
                $icon = 'bi-exclamation-triangle'; // Ícono de error
                break;
            case 'pendiente':
                $badgeClass = 'bg-warning bg-opacity-25 text-warning';
                $textoEstado = 'Pendiente';
                $icon = 'bi-hourglass';
                break;
        }

        // Lógica de Tipo de Mensaje (Personaliza el ícono)
        if ($log['tipo_mensaje'] === 'diagnostico')
            $icon = 'bi-clipboard-data';
        if ($log['tipo_mensaje'] === 'equipo_listo')
            $icon = 'bi-check-circle';
        if ($log['tipo_mensaje'] === 'no_quedo')
            $icon = 'bi-x-circle';
        if ($log['tipo_mensaje'] === 'entregado')
            $icon = 'bi-box-seam';

        // Formatear fecha (esquema usa created_at)
        $fechaRaw = $log['created_at'] ?? date('Y-m-d H:i:s');
        $fechaFmt = date('d M h:i A', strtotime($fechaRaw));

        $data[] = [
            'id' => $log['id'],
            'tipo_mensaje' => str_replace('_', ' ', $log['tipo_mensaje']),
            'contenido_mensaje' => $log['contenido_mensaje'],
            'estado_envio' => $log['estado_envio'],
            'respuesta_api' => $log['respuesta_api'], // Para mostrar errores técnicos si falló
            'fecha_fmt' => $fechaFmt,
            'badge_class' => $badgeClass,
            'icon' => $icon,
            'texto_estado' => $textoEstado,
            'enviado_por' => $log['enviado_por'] ?? null // Nuevo campo
        ];
    }

    jsonResponse(['data' => $data], 200);

} catch (Exception $e) {
    error_log('api_history: ' . $e->getMessage());
    jsonResponse(['message' => 'Error al cargar historial'], 500);
}