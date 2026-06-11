<?php
/**
 * API — Crear Pantalla
 * POST: Inserta en inv_pantallas (con FK a catálogos dinámicos)
 */
include __DIR__ . '/../../config/auth.php';
requireLogin();
include __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/Shared/TenantContext.php';
require_once __DIR__ . '/embedding_helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido.']);
    exit;
}

try {
    $modelo_id         = (int) ($_POST['modelo_id'] ?? 0);
    $modelo_tecnico_id = (int) ($_POST['modelo_tecnico_id'] ?? 0);
    $calidad           = trim($_POST['calidad'] ?? '');
    $precio            = (float) ($_POST['precio'] ?? 0);
    $tiempo            = trim($_POST['tiempo'] ?? '');
    $nota              = trim($_POST['nota'] ?? '') ?: null;

    if ($modelo_id <= 0)         throw new InvalidArgumentException('Selecciona un modelo.');
    if ($modelo_tecnico_id <= 0) throw new InvalidArgumentException('Selecciona un modelo técnico.');

    $calidadesValidas = InventarioConstantes::CALIDADES_PANTALLA;
    if (!in_array($calidad, $calidadesValidas, true)) {
        throw new InvalidArgumentException('Calidad no válida.');
    }

    if ($precio <= 0) throw new InvalidArgumentException('El precio debe ser mayor a 0.');

    $tiemposValidos = InventarioConstantes::TIEMPOS_ENTREGA;
    if (!in_array($tiempo, $tiemposValidos, true)) {
        throw new InvalidArgumentException('Tiempo de entrega no válido.');
    }

    $tenantId = TenantContext::requireTenant();

    // Verificar FKs (ambos apuntan a la tabla compartida modelos)
    $check = $supabase->get('modelos', [
        'select' => 'id',
        'tenant_id' => 'eq.' . $tenantId,
        'id' => 'eq.' . $modelo_id,
        'activo' => 'eq.true',
        'limit' => '1',
    ]);
    if (empty($check['data'])) throw new InvalidArgumentException('Modelo no válido.');

    $check = $supabase->get('modelos', [
        'select' => 'id',
        'tenant_id' => 'eq.' . $tenantId,
        'id' => 'eq.' . $modelo_tecnico_id,
        'activo' => 'eq.true',
        'limit' => '1',
    ]);
    if (empty($check['data'])) throw new InvalidArgumentException('Modelo técnico no válido.');

    $result = $supabase->post('inv_pantallas', [
        'tenant_id'         => $tenantId,
        'modelo_id'         => $modelo_id,
        'modelo_tecnico_id' => $modelo_tecnico_id,
        'calidad'           => $calidad,
        'precio'            => $precio,
        'tiempo'            => $tiempo,
        'nota'              => $nota,
    ]);

    $newId = (int) ($result['data'][0]['id'] ?? 0);

    indexarEmbeddingSiDisponible($tenantId, 'pantallas', $newId, [
        'calidad' => $calidad,
        'tiempo' => $tiempo,
    ]);

    echo json_encode([
        'ok'      => true,
        'message' => 'Pantalla creada correctamente.',
        'id'      => $newId,
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error al crear pantalla.']);
}
