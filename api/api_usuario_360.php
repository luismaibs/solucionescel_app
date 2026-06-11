<?php
/**
 * API Vista 360° del usuario autenticado
 * GET — Estadísticas personales del usuario en sesión
 */
header('Content-Type: application/json');
include '../config/auth.php';
requireLogin();
include '../config/db.php';

$userId   = getCurrentUserId();
$tenantId = getCurrentTenantId();
$token    = getJwtFromRequest();

if (!$userId || !$tenantId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'No autenticado']);
    exit;
}

try {
    // 1. Datos del perfil + fecha de alta
    $usuarioResult = $supabase->get('usuarios', [
        'select'     => 'id,nombre_completo,username,rol,created_at',
        'tenant_id'  => 'eq.' . $tenantId,
        'id'         => 'eq.' . $userId,
        'deleted_at' => 'is.null',
        'limit'      => '1',
    ], $token);

    $usuario = ($usuarioResult['ok'] && !empty($usuarioResult['data']))
        ? $usuarioResult['data'][0]
        : [];

    // 2. Clientes ingresados por este usuario
    $clientesResult = $supabase->get('clientes', [
        'select'              => 'id',
        'tenant_id'           => 'eq.' . $tenantId,
        'created_by_user_id'  => 'eq.' . $userId,
        'deleted_at'          => 'is.null',
        'limit'               => '10000',
    ], $token);

    $clientesIngresados = ($clientesResult['ok'])
        ? count($clientesResult['data'] ?? [])
        : 0;

    // 3. Reparaciones totales gestionadas por este usuario
    $repsResult = $supabase->get('reparaciones', [
        'select'             => 'id,estado',
        'tenant_id'          => 'eq.' . $tenantId,
        'created_by_user_id' => 'eq.' . $userId,
        'deleted_at'         => 'is.null',
        'limit'              => '10000',
    ], $token);

    $reparaciones = ($repsResult['ok']) ? ($repsResult['data'] ?? []) : [];
    $repsTotal    = count($reparaciones);

    // Los estados que representan un trabajo completado/entregado
    $estadosExito = ['entregado', 'garantia_entregada', 'listo', 'listo_sin_garantia'];
    $repsCompletadas = count(array_filter($reparaciones, function ($r) use ($estadosExito) {
        return in_array($r['estado'] ?? '', $estadosExito, true);
    }));

    $tasaExito = $repsTotal > 0
        ? round(($repsCompletadas / $repsTotal) * 100, 1)
        : 0;

    // 4. Días en el sistema desde la fecha de alta
    $diasSistema = 0;
    if (!empty($usuario['created_at'])) {
        try {
            $fechaAlta   = new DateTime($usuario['created_at']);
            $hoy         = new DateTime();
            $diasSistema = (int) $hoy->diff($fechaAlta)->days;
        } catch (Exception $e) {}
    }

    echo json_encode([
        'ok'   => true,
        'data' => [
            'nombre_completo'     => $usuario['nombre_completo'] ?? '',
            'username'            => $usuario['username'] ?? '',
            'rol'                 => $usuario['rol'] ?? '',
            'created_at'          => $usuario['created_at'] ?? null,
            'dias_en_sistema'     => $diasSistema,
            'clientes_ingresados' => $clientesIngresados,
            'reparaciones_total'  => $repsTotal,
            'reparaciones_completadas' => $repsCompletadas,
            'tasa_exito'          => $tasaExito,
        ],
    ]);

} catch (Throwable $e) {
    error_log('api_usuario_360: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error al cargar perfil']);
}
