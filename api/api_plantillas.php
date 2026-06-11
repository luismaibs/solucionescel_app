<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
include '../config/auth.php';
requireLogin();
requireAdmin();
include '../config/db.php';

$supabase = getSupabase();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ─── LISTAR PLANTILLAS ───
if ($action === 'list') {
    try {
        $carpetaId = $_GET['carpeta_id'] ?? null;
        $params = ['select' => '*,carpeta:whatsapp_template_carpetas(id,nombre)', 'order' => 'created_at.desc'];
        if ($carpetaId !== null && $carpetaId !== '') {
            $params['carpeta_id'] = 'eq.' . $carpetaId;
        }
        $r = $supabase->get('whatsapp_templates', $params);
        echo json_encode(['ok' => true, 'data' => $r['data'] ?? []]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ─── LISTAR CARPETAS ───
if ($action === 'carpetas') {
    try {
        $r = $supabase->get('whatsapp_template_carpetas', ['select' => '*', 'order' => 'nombre.asc']);
        echo json_encode(['ok' => true, 'data' => $r['data'] ?? []]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ─── GUARDAR PLANTILLA ───
if ($action === 'save' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!$input) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'JSON invalido']); exit; }

    $title = trim($input['title'] ?? '');
    $content = $input['content'] ?? '';
    if ($title === '') { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'El titulo es obligatorio']); exit; }

    $row = ['title' => $title, 'content' => $content];
    if (isset($input['carpeta_id'])) {
        $row['carpeta_id'] = $input['carpeta_id'] ? (int) $input['carpeta_id'] : null;
    }

    try {
        if (!empty($input['id'])) {
            $row['updated_at'] = date('c');
            $supabase->patch('whatsapp_templates', $row, ['id' => 'eq.' . $input['id']]);
            $savedId = $input['id'];
        } else {
            $r = $supabase->post('whatsapp_templates', $row);
            $savedId = $r['data'][0]['id'] ?? 0;
        }
        $r = $supabase->get('whatsapp_templates', ['select' => '*,carpeta:whatsapp_template_carpetas(id,nombre)', 'id' => 'eq.' . $savedId, 'limit' => '1']);
        echo json_encode(['ok' => true, 'id' => (int) $savedId, 'record' => $r['data'][0] ?? null]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ─── ELIMINAR PLANTILLA ───
if ($action === 'delete' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!$input || empty($input['id'])) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'ID invalido']); exit; }
    try {
        $supabase->delete('whatsapp_templates', ['id' => 'eq.' . $input['id']]);
        echo json_encode(['ok' => true, 'id' => $input['id']]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ─── GUARDAR CARPETA ───
if ($action === 'save_carpeta' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!$input) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'JSON invalido']); exit; }

    $nombre = trim($input['nombre'] ?? '');
    if ($nombre === '') { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'El nombre es obligatorio']); exit; }

    try {
        $tenantId = TenantContext::getTenantIdOrDefault();
        $row = ['nombre' => $nombre, 'tenant_id' => $tenantId];
        if (!empty($input['id'])) {
            $supabase->patch('whatsapp_template_carpetas', $row, ['id' => 'eq.' . $input['id']]);
            $savedId = $input['id'];
        } else {
            $r = $supabase->post('whatsapp_template_carpetas', $row);
            $savedId = $r['data'][0]['id'] ?? 0;
        }
        $r = $supabase->get('whatsapp_template_carpetas', ['select' => '*', 'id' => 'eq.' . $savedId, 'limit' => '1']);
        echo json_encode(['ok' => true, 'id' => (int) $savedId, 'record' => $r['data'][0] ?? null]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ─── ELIMINAR CARPETA ───
if ($action === 'delete_carpeta' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!$input || empty($input['id'])) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'ID invalido']); exit; }
    try {
        $supabase->delete('whatsapp_template_carpetas', ['id' => 'eq.' . $input['id']]);
        echo json_encode(['ok' => true, 'id' => $input['id']]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Accion no valida']);
