<?php

/**
 * API — KPIs de Inventario por Categoría
 *
 * GET  ?categoria=servicios|baterias|pantallas|accesorios
 *       → Devuelve array de KPIs específicos para la categoría.
 *
 * Respuesta: { ok: true, kpis: [ {label, value, icon, color}, ... ] }
 */

include __DIR__ . '/../../config/auth.php';
requireLogin();
include __DIR__ . '/../../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$categoriasValidas = ['servicios', 'baterias', 'pantallas', 'accesorios'];

try {
    $categoria = $_GET['categoria'] ?? '';

    if (!in_array($categoria, $categoriasValidas, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Categoría no válida.']);
        exit;
    }

    $repo = new InventarioCategoriaRepository($supabase);
    $kpis = $repo->getKpisByCategoria($categoria);

    echo json_encode([
        'ok'   => true,
        'kpis' => $kpis,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error al obtener KPIs.']);
}
