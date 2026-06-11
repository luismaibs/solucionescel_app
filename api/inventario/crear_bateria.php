<?php
/**
 * API — Crear Batería
 * POST: Inserta en inv_baterias
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
    // Validaciones
    $marca_id  = (int) ($_POST['marca_id'] ?? 0);
    $modelo_id = (int) ($_POST['modelo_id'] ?? 0);
    if ($marca_id <= 0)  throw new InvalidArgumentException('Selecciona una marca.');
    if ($modelo_id <= 0) throw new InvalidArgumentException('Selecciona un modelo.');

    // Campos multi-valor (CSV)
    $calidadValidos = InventarioConstantes::CALIDADES_BATERIA;
    $calidadRaw = $_POST['calidad'] ?? '';
    $calidad = array_filter(array_map('trim', explode(',', $calidadRaw)), function ($v) use ($calidadValidos) {
        return in_array($v, $calidadValidos, true);
    });
    if (empty($calidad)) {
        throw new InvalidArgumentException('Selecciona al menos una calidad.');
    }

    $tipoValidos = InventarioConstantes::TIPOS_BATERIA;
    $tipoRaw = $_POST['tipo'] ?? '';
    $tipo = array_filter(array_map('trim', explode(',', $tipoRaw)), function ($v) use ($tipoValidos) {
        return in_array($v, $tipoValidos, true);
    });
    if (empty($tipo)) {
        throw new InvalidArgumentException('Selecciona al menos un tipo.');
    }

    $tiempoValidos = InventarioConstantes::TIEMPOS_ENTREGA;
    $tiempoRaw = $_POST['tiempo'] ?? '';
    $tiempo = array_filter(array_map('trim', explode(',', $tiempoRaw)), function ($v) use ($tiempoValidos) {
        return in_array($v, $tiempoValidos, true);
    });
    if (empty($tiempo)) {
        throw new InvalidArgumentException('Selecciona al menos un tiempo de entrega.');
    }

    $notas = trim($_POST['notas'] ?? '') ?: null;
    $precio = isset($_POST['precio']) ? (float) $_POST['precio'] : 0.0;
    $stock = isset($_POST['stock']) ? (int) $_POST['stock'] : 0;
    $codigo = trim($_POST['codigo'] ?? '') ?: null;

    $tenantId = TenantContext::requireTenant();

    $checkMarca = $supabase->get('marcas', [
        'select' => 'id',
        'tenant_id' => 'eq.' . $tenantId,
        'id' => 'eq.' . $marca_id,
        'activo' => 'eq.true',
        'limit' => '1',
    ]);
    if (empty($checkMarca['data'])) throw new InvalidArgumentException('Marca no válida.');

    $checkModelo = $supabase->get('modelos', [
        'select' => 'id',
        'tenant_id' => 'eq.' . $tenantId,
        'id' => 'eq.' . $modelo_id,
        'activo' => 'eq.true',
        'limit' => '1',
    ]);
    if (empty($checkModelo['data'])) throw new InvalidArgumentException('Modelo no válido.');

    $result = $supabase->post('inv_baterias', [
        'tenant_id' => $tenantId,
        'marca_id'  => $marca_id,
        'modelo_id' => $modelo_id,
        'calidad'   => implode(',', $calidad),
        'tipo'      => implode(',', $tipo),
        'tiempo'    => implode(',', $tiempo),
        'notas'     => $notas,
        'precio'    => $precio,
        'stock'     => $stock,
        'codigo'    => $codigo,
    ]);

    $newId = (int) ($result['data'][0]['id'] ?? 0);

    indexarEmbeddingSiDisponible($tenantId, 'baterias', $newId);

    echo json_encode([
        'ok'      => true,
        'message' => 'Batería creada correctamente.',
        'id'      => $newId,
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error al crear batería.']);
}
