<?php
include '../config/auth.php';
requireLogin();
include '../config/db.php';
header('Content-Type: application/json');

try {
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 50;

    if ($page < 1) {
        $page = 1;
    }

    // Limitar per_page a un rango razonable
    if ($perPage < 5) {
        $perPage = 5;
    } elseif ($perPage > 200) {
        $perPage = 200;
    }

    $offset = ($page - 1) * $perPage;

    $repo = new ReparacionRepository($supabase);
    $total = 0;
    $items = $repo->findForPanelPaginated($offset, $perPage, $total);

    echo json_encode([
        'ok'       => true,
        'page'     => $page,
        'per_page' => $perPage,
        'total'    => $total,
        'items'    => $items,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'message' => 'Error al cargar reparaciones',
    ]);
}

