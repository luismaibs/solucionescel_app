<?php
/**
 * API — Crear Accesorio
 * POST: Inserta en inv_accesorios (con FK a catálogos dinámicos)
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
    $subcategoria_id = (int) ($_POST['subcategoria_id'] ?? 0);
    $marca_id        = (int) ($_POST['marca_id'] ?? 0);
    $color_id        = (int) ($_POST['color_id'] ?? 0);
    $codigo          = trim($_POST['codigo'] ?? '');
    $nombre          = trim($_POST['nombre_producto'] ?? '');
    $stock           = (int) ($_POST['stock'] ?? 0);
    $precio          = (float) ($_POST['precio'] ?? 0);

    if ($subcategoria_id <= 0) throw new InvalidArgumentException('Selecciona una subcategoría.');
    if ($marca_id <= 0)        throw new InvalidArgumentException('Selecciona una marca.');
    if ($color_id <= 0)        throw new InvalidArgumentException('Selecciona un color.');
    if ($codigo === '')        throw new InvalidArgumentException('El código es requerido.');
    if ($nombre === '')        throw new InvalidArgumentException('El nombre del producto es requerido.');
    if ($stock < 0)            throw new InvalidArgumentException('El stock no puede ser negativo.');
    if ($precio <= 0)          throw new InvalidArgumentException('El precio debe ser mayor a 0.');

    $tenantId = TenantContext::requireTenant();

    // Verificar que los IDs existen (en este tenant)
    $check = $supabase->get('subcategorias', [
        'select' => 'id',
        'tenant_id' => 'eq.' . $tenantId,
        'id' => 'eq.' . $subcategoria_id,
        'activo' => 'eq.true',
        'limit' => '1',
    ]);
    if (empty($check['data'])) throw new InvalidArgumentException('Subcategoría no válida.');

    $check = $supabase->get('marcas', [
        'select' => 'id',
        'tenant_id' => 'eq.' . $tenantId,
        'id' => 'eq.' . $marca_id,
        'activo' => 'eq.true',
        'limit' => '1',
    ]);
    if (empty($check['data'])) throw new InvalidArgumentException('Marca no válida.');

    $check = $supabase->get('colores', [
        'select' => 'id',
        'tenant_id' => 'eq.' . $tenantId,
        'id' => 'eq.' . $color_id,
        'activo' => 'eq.true',
        'limit' => '1',
    ]);
    if (empty($check['data'])) throw new InvalidArgumentException('Color no válido.');

    $result = $supabase->post('inv_accesorios', [
        'tenant_id'   => $tenantId,
        'subcategoria_id' => $subcategoria_id,
        'marca_id'    => $marca_id,
        'codigo'      => $codigo,
        'nombre_producto' => $nombre,
        'stock'       => $stock,
        'precio'      => $precio,
        'color_id'    => $color_id,
    ]);

    $newId = (int) ($result['data'][0]['id'] ?? 0);

    indexarEmbeddingSiDisponible($tenantId, 'accesorios', $newId, [
        'codigo' => $codigo,
        'nombre_producto' => $nombre,
    ]);

    echo json_encode([
        'ok'      => true,
        'message' => 'Accesorio creado correctamente.',
        'id'      => $newId,
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error al crear accesorio.']);
}
