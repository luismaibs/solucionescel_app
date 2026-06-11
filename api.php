<?php
header('Content-Type: application/json');
$allowedOrigin = getenv('ALLOWED_ORIGIN');
header('Access-Control-Allow-Origin: ' . ($allowedOrigin ?: '*'));
include 'config/db.php';
require_once __DIR__ . '/src/Shared/TenantContext.php';

// ═══════════════════════════════════════════════════════════════
// BYPASS PARA N8N / CLIENTES INTERNOS (usa token en .env)
// ═══════════════════════════════════════════════════════════════
$internalTokenEnv = getenv('API_FOLIO_INTERNAL_TOKEN') ?: '';
$isInternalClient = false;
if ($internalTokenEnv !== '') {
    $headerToken = $_SERVER['HTTP_X_INTERNAL_TOKEN'] ?? '';
    $queryToken  = $_GET['internal_token'] ?? '';
    if (hash_equals($internalTokenEnv, (string) $headerToken) || hash_equals($internalTokenEnv, (string) $queryToken)) {
        $isInternalClient = true;
    }
}

// ═══════════════════════════════════════════════════════════════
// RATE LIMITING (evita saturación y ataques)
// ═══════════════════════════════════════════════════════════════
if (!$isInternalClient) {
    $rateLimitDir = sys_get_temp_dir() . '/sc_api_rate';
    if (!is_dir($rateLimitDir)) {
        @mkdir($rateLimitDir, 0755, true);
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ipHash = md5($ip);
    $ipFile = $rateLimitDir . '/' . $ipHash . '.json';

    $now = time();
    $limits = [
        'max_per_hour'   => 60, // 60 consultas/hora para uso normal
        'max_per_minute' => 15, // 15 burst en 1 min = bloqueo
        'block_minutes'  => 10, // Bloqueo tras exceder burst
    ];

    $data = ['ts' => [], 'blocked_until' => 0];
    if (file_exists($ipFile)) {
        $data = json_decode(file_get_contents($ipFile), true) ?: $data;
    }

    if ($data['blocked_until'] > $now) {
        http_response_code(429);
        echo json_encode(['found' => false, 'message' => 'Demasiadas consultas. Intenta más tarde.']);
        exit;
    }

    $data['ts'] = array_filter($data['ts'] ?? [], fn($t) => $t > $now - 3600);
    $countHour = count($data['ts']);
    $countMinute = count(array_filter($data['ts'], fn($t) => $t > $now - 60));

    if ($countHour >= $limits['max_per_hour']) {
        http_response_code(429);
        echo json_encode(['found' => false, 'message' => 'Límite de consultas alcanzado. Intenta en una hora.']);
        exit;
    }
    if ($countMinute >= $limits['max_per_minute']) {
        $data['blocked_until'] = $now + ($limits['block_minutes'] * 60);
        @file_put_contents($ipFile, json_encode($data));

        http_response_code(429);
        echo json_encode(['found' => false, 'message' => 'Demasiadas consultas. Intenta más tarde.']);
        exit;
    }

    $data['ts'][] = $now;
    @file_put_contents($ipFile, json_encode($data));
}

// ═══════════════════════════════════════════════════════════════
// SANITIZACIÓN (anti-inyección)
// ═══════════════════════════════════════════════════════════════
$folio = $_GET['folio'] ?? '';
$folio_limpio = trim($folio);

// Solo alfanuméricos, guiones, guiones bajos: ej. ABC123, SC-2024-001
$folio_limpio = preg_replace('/[^a-zA-Z0-9\-_]/', '', $folio_limpio);

if (empty($folio_limpio) || strlen($folio_limpio) > 32) {
    echo json_encode(['found' => false, 'message' => 'Folio no válido']);
    exit;
}

// API pública por folio: el tenant debe venir por header o query (ej. X-Tenant-ID o tenant_id)
$tenantId = isset($_SERVER['HTTP_X_TENANT_ID']) ? (int) $_SERVER['HTTP_X_TENANT_ID'] : (isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : null);
if ($tenantId === null || $tenantId < 1) {
    $tenantId = (int) (getenv('TENANT_ID_DEFAULT') ?: 1);
}
TenantContext::setTenantId($tenantId);

$repo = new ReparacionRepository($supabase);
$resultado = $repo->findByFolioActivo($folio_limpio);

if ($resultado) {
    $estadoDb = $resultado['estado'] ?? '';
    $mesAzulEstado = $resultado['mes_azul_estado'] ?? 'no_aplica';
    $tipoGarantia = $resultado['tipo_garantia'] ?? null;

    // Mapeo a los 9 estados finales
    // 1. Laboratorio
    // 2. Listo (con variantes de garantía)
    // 3. No Quedó
    // 4. Entregado
    // 5. Proceso de revisión técnica
    // 6. Garantía exitosa
    // 7. Garantía fallida
    // 8. Garantía entregada
    // 9. Inactivo (por entrega / por Mes Azul)
    $estadoFinal = 'laboratorio';
    $estadoTexto = 'Laboratorio';
    $estadoDetalle = null;

    if (in_array($estadoDb, ['ingresado', 'diagnostico', 'en_taller'], true)) {
        $estadoFinal = 'laboratorio';
        $estadoTexto = 'Laboratorio';
    } elseif (in_array($estadoDb, ['listo', 'listo_sin_garantia'], true)) {
        $estadoFinal = 'listo';
        $estadoTexto = 'Listo';
    } elseif ($estadoDb === 'no_quedo') {
        $estadoFinal = 'no_quedo';
        $estadoTexto = 'No Quedó';
    } elseif ($estadoDb === 'entregado') {
        $estadoFinal = 'entregado';
        $estadoTexto = 'Entregado';
    } elseif (in_array($estadoDb, ['garantia_activada', 'confirmacion_garantia'], true)) {
        $estadoFinal = 'proceso_revision';
        $estadoTexto = 'Proceso de revisión técnica';
    } elseif ($estadoDb === 'garantia_finalizada') {
        $estadoFinal = 'garantia_exitosa';
        $estadoTexto = 'Garantía exitosa';
    } elseif ($estadoDb === 'garantia_fallida') {
        $estadoFinal = 'garantia_fallida';
        $estadoTexto = 'Garantía fallida';
    } elseif ($estadoDb === 'garantia_entregada') {
        $estadoFinal = 'garantia_entregada';
        $estadoTexto = 'Garantía entregada';
    } elseif ($estadoDb === 'inactivo') {
        $estadoFinal = 'inactivo';
        $estadoTexto = 'Inactivo';
        $estadoDetalle = ($mesAzulEstado === 'inactivado')
            ? 'Inactividad por mes azul'
            : 'Inactividad por entrega';
    }

    // Etiquetas de tipo de garantía para estado Listo
    $tipoGarantiaLabels = [
        'garantia_tecnica_proveedor_30' => 'Garantía técnica y proveedor 30 días',
        'garantia_30_dias'              => 'Garantía técnica (30)',
        'garantia_60_dias'              => 'Garantía técnica (60)',
        'garantia_90_dias'              => 'Garantía técnica (90)',
        'sin_garantia'                  => 'Sin garantía',
    ];
    $tipoGarantiaLabel = $tipoGarantia && isset($tipoGarantiaLabels[$tipoGarantia])
        ? $tipoGarantiaLabels[$tipoGarantia]
        : null;

    if ($estadoFinal === 'listo' && $tipoGarantiaLabel) {
        $estadoDetalle = $tipoGarantiaLabel;
    }

    // Concatenar marca y modelo si existe marca
    $modelo_completo = (!empty($resultado['equipo_marca']) ? $resultado['equipo_marca'] . ' ' : '') . $resultado['equipo_modelo'];

    echo json_encode([
        'found' => true,
        'folio' => $resultado['folio_publico'],
        'cliente' => $resultado['cliente_nombre'],
        'modelo' => $modelo_completo,
        'estado_code' => $estadoDb,          // Estado crudo en BD
        'estado_final' => $estadoFinal,      // Uno de los 9 estados finales
        'estado_texto' => $estadoTexto,      // Texto principal para el usuario
        'estado_detalle' => $estadoDetalle,  // Subdetalle (tipo de garantía o motivo de inactividad)
        'tipo_garantia' => $tipoGarantia,
        'tipo_garantia_label' => $tipoGarantiaLabel,
        'inactivo_motivo' => $estadoFinal === 'inactivo' ? $estadoDetalle : null,
        'falla' => $resultado['falla_reportada'],
        'costo' => $resultado['costo_final'] ?? 'Pendiente' // Si agregas costos después
    ]);
} else {
    echo json_encode([
        'found' => false,
        'message' => 'No encontramos una orden activa con ese folio.'
    ]);
}
?>