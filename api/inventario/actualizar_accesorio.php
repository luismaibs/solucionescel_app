<?php
/**
 * API — Actualizar Accesorio
 * POST: Actualiza todos los campos de un accesorio en inv_accesorios
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
    $id              = (int) ($_POST['id'] ?? 0);
    $subcategoria_id = (int) ($_POST['subcategoria_id'] ?? 0);
    $marca_id        = (int) ($_POST['marca_id'] ?? 0);
    $color_id        = (int) ($_POST['color_id'] ?? 0);
    $codigo          = trim($_POST['codigo'] ?? '');
    $nombre          = trim($_POST['nombre_producto'] ?? '');
    $stock           = (int) ($_POST['stock'] ?? 0);
    $precio          = (float) ($_POST['precio'] ?? 0);

    if ($id <= 0)              throw new InvalidArgumentException('ID de accesorio no válido.');
    if ($subcategoria_id <= 0) throw new InvalidArgumentException('Selecciona una subcategoría.');
    if ($marca_id <= 0)        throw new InvalidArgumentException('Selecciona una marca.');
    if ($color_id <= 0)        throw new InvalidArgumentException('Selecciona un color.');
    if ($codigo === '')        throw new InvalidArgumentException('El código es requerido.');
    if ($nombre === '')        throw new InvalidArgumentException('El nombre del producto es requerido.');
    if ($stock < 0)            throw new InvalidArgumentException('El stock no puede ser negativo.');
    if ($precio <= 0)          throw new InvalidArgumentException('El precio debe ser mayor a 0.');

    $tenantId = TenantContext::requireTenant();

    $result = $supabase->patch('inv_accesorios', [
        'subcategoria_id'  => $subcategoria_id,
        'marca_id'         => $marca_id,
        'codigo'           => $codigo,
        'nombre_producto'  => $nombre,
        'stock'            => $stock,
        'precio'           => $precio,
        'color_id'         => $color_id,
    ], [
        'tenant_id'  => 'eq.' . $tenantId,
        'id'         => 'eq.' . $id,
        'deleted_at' => 'is.null',
    ]);

    if (!$result['ok']) {
        throw new RuntimeException('No se pudo actualizar el accesorio.');
    }

    indexarEmbeddingSiDisponible($tenantId, 'accesorios', $id, [
        'codigo'          => $codigo,
        'nombre_producto' => $nombre,
    ]);

    echo json_encode([
        'ok'      => true,
        'message' => 'Accesorio actualizado correctamente.',
        'id'      => $id,
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error al actualizar accesorio.']);
}
