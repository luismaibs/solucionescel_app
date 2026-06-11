<?php
/**
 * API: Asistente IA de Analíticas
 *
 * Endpoint para procesar consultas en lenguaje natural usando DeepSeek.
 *
 * Acciones disponibles (POST):
 *   - query:           Procesar una pregunta
 *   - get_history:     Obtener historial reciente
 *   - get_saved:       Obtener análisis guardados
 *   - save_analysis:   Guardar un análisis
 *   - delete_analysis: Eliminar un análisis
 *   - get_analysis:    Obtener un análisis por ID
 */

require_once __DIR__ . '/../config/env_loader.php';

$appDebug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
if ($appDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

header('Content-Type: application/json; charset=utf-8');

// ============================================
// AUTENTICACION (auth.php ya inicia sesion, verifica JWT, e incluye db.php)
// ============================================
require_once __DIR__ . '/../config/auth.php';
requireLogin();
requireAdmin();

if (!isset($supabase)) {
    echo json_encode(['success' => false, 'error' => 'Conexion a BD no disponible']);
    exit;
}

$deepseekApiKey = getenv('DEEPSEEK_API_KEY');
if (empty($deepseekApiKey)) {
    echo json_encode(['success' => false, 'error' => 'API Key de DeepSeek no configurada en .env']);
    exit;
}

$repo = new AIAnalyticsRepository($supabase);
$service = new AIAnalyticsService($repo, $deepseekApiKey);

// ============================================
// PROCESAR SOLICITUDES
// ============================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido. Use POST.']);
    exit;
}

$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true);
$action  = $data['action'] ?? '';
$username = getCurrentUsername();

try {
    switch ($action) {

        // ─── Procesar pregunta con IA ───
        case 'query':
            $pregunta = trim($data['question'] ?? '');

            if (empty($pregunta)) {
                echo json_encode(['success' => false, 'error' => 'La pregunta no puede estar vacía']);
                exit;
            }

            if (mb_strlen($pregunta) > 1000) {
                echo json_encode(['success' => false, 'error' => 'La pregunta es demasiado larga (máx 1000 caracteres)']);
                exit;
            }

            // Conversación: usar el ID enviado por el frontend
            $conversacionId = trim($data['conversacion_id'] ?? '');
            if (empty($conversacionId)) {
                // Si no llega, generar uno nuevo (fallback)
                $conversacionId = sprintf(
                    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
            }

            // Activar modo razonador si el usuario lo solicitó
            $useReasoner = !empty($data['use_reasoner']);
            $service->activarRazonador($useReasoner);

            $resultado = $service->procesarPregunta($pregunta, $username, $conversacionId);
            $resultado['model_used'] = $useReasoner ? 'deepseek-reasoner' : 'deepseek-chat';
            $resultado['conversacion_id'] = $conversacionId;
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        // ─── Obtener lista de conversaciones ───
        case 'get_history':
            $limit = min((int) ($data['limit'] ?? 30), 100);
            $historial = $repo->obtenerConversaciones($username, $limit);
            echo json_encode([
                'success'   => true,
                'historial' => $historial,
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ─── Obtener TODOS los mensajes de una conversación ───
        case 'get_conversation':
            $convId = trim($data['conversacion_id'] ?? '');

            if (empty($convId)) {
                echo json_encode(['success' => false, 'error' => 'ID de conversación no proporcionado']);
                exit;
            }

            $mensajes = $repo->obtenerMensajesConversacion($convId);

            if (empty($mensajes)) {
                echo json_encode(['success' => false, 'error' => 'Conversación no encontrada']);
                exit;
            }

            echo json_encode([
                'success'         => true,
                'conversacion_id' => $convId,
                'mensajes'        => $mensajes,
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ─── Obtener análisis guardados ───
        case 'get_saved':
            $limit = min((int) ($data['limit'] ?? 50), 100);
            $guardados = $repo->obtenerHistorialGuardado($username, $limit);
            echo json_encode([
                'success'   => true,
                'guardados' => $guardados,
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ─── Guardar conversación ───
        case 'save_analysis':
            $id     = (int) ($data['id'] ?? 0);
            $convId = trim($data['conversacion_id'] ?? '');
            $titulo = trim($data['titulo'] ?? '');

            if (!empty($convId)) {
                $repo->marcarGuardado(null, $convId, $titulo ?: null);
                echo json_encode(['success' => true, 'message' => 'Conversación guardada']);
                break;
            }

            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de análisis no válido']);
                exit;
            }

            $repo->marcarGuardado($id, null, $titulo ?: null);
            echo json_encode(['success' => true, 'message' => 'Análisis guardado']);
            break;

        // ─── Eliminar un análisis ───
        case 'delete_analysis':
            $id = (int) ($data['id'] ?? 0);

            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID no válido']);
                exit;
            }

            $repo->eliminarAnalisis($id);
            echo json_encode(['success' => true, 'message' => 'Análisis eliminado']);
            break;

        // ─── Obtener un análisis por ID ───
        case 'get_analysis':
            $id = (int) ($data['id'] ?? 0);

            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID no válido']);
                exit;
            }

            $analisis = $repo->obtenerAnalisisPorId($id);
            if (!$analisis) {
                echo json_encode(['success' => false, 'error' => 'Análisis no encontrado']);
                exit;
            }

            echo json_encode([
                'success'  => true,
                'analisis' => $analisis,
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'error' => "Acción no válida: $action"]);
            break;
    }
} catch (Exception $e) {
    error_log('api_ai_query error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error'   => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
