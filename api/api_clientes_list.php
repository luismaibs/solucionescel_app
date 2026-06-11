<?php
/**
 * API de listado paginado de clientes
 */
header('Content-Type: application/json');
include '../config/auth.php';
requireLogin();
include '../config/db.php';

try {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(200, max(5, (int) ($_GET['per_page'] ?? 30)));
    $search = trim($_GET['search'] ?? '');

    $clienteRepo = new ClienteRepository($supabase);
    $clienteService = new ClienteService($clienteRepo);

    $result = $clienteService->listarPaginado($page, $perPage, $search);

    echo json_encode([
        'ok' => true,
        'page' => $result['page'],
        'per_page' => $result['per_page'],
        'total' => $result['total'],
        'items' => $result['items'],
    ]);
} catch (Throwable $e) {
    error_log('api_clientes_list: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error al cargar clientes']);
}
