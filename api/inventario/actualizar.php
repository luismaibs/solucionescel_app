<?php

/**
 * API — Inventario: actualizar una celda (edición inline tipo hoja de cálculo)
 *
 * POST JSON: { categoria, id, campo, valor }
 *   → PATCH del campo indicado en la tabla correspondiente.
 *
 * Solo permite los campos de la whitelist EDITABLE_FIELDS del repositorio.
 */

include __DIR__ . '/../../config/auth.php';
requireLogin();
include __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/embedding_helper.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido.']);
    exit;
}

try {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?: [];

    $categoria = $body['categoria'] ?? '';
    $id        = (int) ($body['id'] ?? 0);
    $campo     = $body['campo'] ?? '';
    $valor     = $body['valor'] ?? null;

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'ID no válido.']);
        exit;
    }

    $repo = new InventarioCategoriaRepository($supabase);
    $guardado = $repo->updateCampo($categoria, $id, $campo, $valor);

    // Reindexar embedding si el campo afecta la búsqueda textual
    $camposIndexables = ['nombre_producto', 'codigo', 'modelo_bateria', 'marca', 'subcategoria'];
    if (in_array($campo, $camposIndexables, true)) {
        try {
            $tenantId = TenantContext::requireTenant();
            indexarEmbeddingSiDisponible($tenantId, $categoria, $id);
        } catch (Throwable $e) {
            // No bloquear el guardado por un fallo de reindexado
            error_log('inventario actualizar reindex: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'ok'      => true,
        'message' => 'Cambio guardado.',
        'valor'   => $guardado,
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('inventario actualizar error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error al guardar el cambio.']);
}
