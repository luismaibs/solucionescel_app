<?php
/**
 * API de Clientes — CRUD + búsqueda rápida
 *
 * Acciones POST: create, edit, delete, search, quick_create
 */
header('Content-Type: application/json');
include '../config/auth.php';
requireLogin();
include '../config/db.php';

$clienteRepo = new ClienteRepository($supabase);
$clienteService = new ClienteService($clienteRepo);

$currentUserId = getCurrentUserId();

// GET: búsqueda rápida
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'search') {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            echo json_encode(['ok' => true, 'items' => []]);
            exit;
        }
        $items = $clienteService->buscar($q);
        echo json_encode(['ok' => true, 'items' => $items]);
        exit;
    }

    if ($_GET['action'] === 'get') {
        $id = (int) ($_GET['id'] ?? 0);
        $cliente = $clienteService->obtener($id);
        if (!$cliente) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Cliente no encontrado']);
            exit;
        }
        echo json_encode(['ok' => true, 'cliente' => $cliente]);
        exit;
    }
}

// POST: acciones CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $result = $clienteService->crear([
            'nombre'   => trim($_POST['nombre'] ?? ''),
            'apellido' => trim($_POST['apellido'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? ''),
            'correo'   => trim($_POST['correo'] ?? ''),
            'user_id'  => $currentUserId,
        ]);
        if (!$result['ok']) {
            http_response_code(400);
        }
        echo json_encode($result);
        exit;
    }

    if ($action === 'quick_create') {
        $result = $clienteService->crearRapido([
            'nombre'   => trim($_POST['nombre'] ?? ''),
            'apellido' => trim($_POST['apellido'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? ''),
            'lada'     => trim($_POST['lada'] ?? '52'),
            'correo'   => trim($_POST['correo'] ?? ''),
            'user_id'  => $currentUserId,
        ]);
        if (!$result['ok']) {
            http_response_code(400);
        }
        echo json_encode($result);
        exit;
    }

    if ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $result = $clienteService->editar($id, [
            'nombre'   => trim($_POST['nombre'] ?? ''),
            'apellido' => trim($_POST['apellido'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? ''),
            'correo'   => trim($_POST['correo'] ?? ''),
        ]);
        if (!$result['ok']) {
            http_response_code(400);
        }
        echo json_encode($result);
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $result = $clienteService->eliminar($id);
        if (!$result['ok']) {
            http_response_code(400);
        }
        echo json_encode($result);
        exit;
    }
}

http_response_code(400);
echo json_encode(['ok' => false, 'message' => 'Acción no válida']);
