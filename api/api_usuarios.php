<?php
include '../config/auth.php';
requireLogin();
requireAdmin();
include '../config/db.php';
require_once __DIR__ . '/../config/api_helper.php';

$usuarioRepo = new UsuarioRepository($supabase);
$usuarioService = new UsuarioService($usuarioRepo);

function sendUsuarioResponse($result) {
    $code = ($result['success'] ?? false) ? 200 : 400;
    jsonResponse($result, $code);
}

// --- ACCIONES POST ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        sendUsuarioResponse($usuarioService->crearUsuario($_POST));
        exit;
    }

    if ($action === 'create_role') {
        sendUsuarioResponse($usuarioService->crearRol($_POST));
        exit;
    }

    if ($action === 'update') {
        sendUsuarioResponse($usuarioService->actualizarUsuario($_POST));
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        sendUsuarioResponse($usuarioService->eliminarUsuario($id));
        exit;
    }

    if ($action === 'sync_auth') {
        sendUsuarioResponse($usuarioService->sincronizarDesdeAuth());
        exit;
    }

    jsonResponse(['message' => 'Acción POST no válida'], 400);
    exit;
}

// GET: LISTAR USUARIOS, ROLES Y LOGS
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = $_GET['type'] ?? 'users';

    if ($type === 'users') {
        sendUsuarioResponse($usuarioService->listarUsuarios());
        exit;
    }

    if ($type === 'roles') {
        sendUsuarioResponse($usuarioService->listarRoles());
        exit;
    }

    if ($type === 'logs') {
        sendUsuarioResponse($usuarioService->listarLogs());
        exit;
    }

    if ($type === 'auditoria') {
        sendUsuarioResponse($usuarioService->listarAuditoria());
        exit;
    }

    jsonResponse(['message' => 'Tipo de consulta no válido'], 400);
    exit;
}

