<?php
require_once __DIR__ . '/../config/env_loader.php';
require_once __DIR__ . '/../config/api_helper.php';
$appDebug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
if ($appDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ============================================
// AUTENTICACIÓN
// ============================================
require_once __DIR__ . '/../config/auth.php';
requireLogin();
requireAdmin();

// ============================================
// CARGAR CONFIGURACIÓN
// ============================================
try {
    require_once __DIR__ . '/../config/db.php';
} catch (Exception $e) {
    error_log('api_analiticas db: ' . $e->getMessage());
    jsonResponse(['message' => 'Error al cargar configuración de BD'], 500);
    exit;
}

// Verificar que Supabase esta disponible
if (!isset($supabase)) {
    jsonResponse(['message' => 'Conexion a Supabase no disponible'], 500);
    exit;
}

// ============================================
// CAPA DE SOPORTE (REPOSITORIO + SERVICIO)
// ============================================
$soporteRepo = new SoporteRepository($supabase);
$soporteService = new SoporteService($soporteRepo);

// ============================================
// DETERMINAR LA ACCIÓN A REALIZAR
// ============================================

// Manejar solicitudes GET (obtener conversaciones)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'obtener_conversaciones') {
        try {
            $conversaciones = $soporteService->obtenerConversacionesFormateadasParaApi(50);
            jsonResponse(['conversaciones' => $conversaciones, 'total' => count($conversaciones)], 200);
        } catch (Exception $e) {
            error_log('api_analiticas obtener_conversaciones: ' . $e->getMessage());
            jsonResponse(['message' => 'Error al obtener conversaciones'], 500);
        }
        exit;
    }

    jsonResponse(['message' => 'Acción GET no válida'], 400);
    exit;
}

// Manejar solicitudes POST (pausar/reactivar bot)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);
    $action = $data['action'] ?? '';

    if ($action === 'reactivar_bot') {
        $respuesta = $soporteService->reactivarBot($data);
        $code = ($respuesta['success'] ?? false) ? 200 : 400;
        jsonResponse($respuesta, $code);
        exit;
    }

    jsonResponse(['message' => 'Acción POST no válida. Use action: reactivar_bot o action: pausar_bot'], 400);
    exit;
}

jsonResponse(['message' => 'Método HTTP no permitido'], 405);
exit;