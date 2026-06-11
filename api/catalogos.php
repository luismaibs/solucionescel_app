<?php
/**
 * API unificada de catálogos (colores, marcas, subcategorías, modelos, modelos técnicos)
 *
 * GET  ?tipo=colores|marcas|subcategorias|modelos|modelos_tecnicos&action=listar  → listar items
 * POST action=agregar&tipo=...&nombre=...  → agregar item
 */
include __DIR__ . '/../config/auth.php';
requireLogin();
include __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Shared/TenantContext.php';
header('Content-Type: application/json; charset=utf-8');

$TIPOS_VALIDOS = [
    'colores'       => ['table' => 'colores',       'label' => 'Color'],
    'marcas'        => ['table' => 'marcas',         'label' => 'Marca'],
    'subcategorias' => ['table' => 'subcategorias',  'label' => 'Subcategoría'],
    'modelos'       => ['table' => 'modelos',         'label' => 'Modelo'],
];

$tipo = trim($_GET['tipo'] ?? $_POST['tipo'] ?? '');
$action = trim($_GET['action'] ?? $_POST['action'] ?? 'listar');

if (!isset($TIPOS_VALIDOS[$tipo])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Tipo de catálogo no válido.']);
    exit;
}

$cfg = $TIPOS_VALIDOS[$tipo];
$table = $cfg['table'];

if ($action === 'listar' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $tenantId = TenantContext::requireTenant();
        $query = [
            'select' => 'id,nombre',
            'tenant_id' => 'eq.' . $tenantId,
            'order' => 'nombre.asc',
        ];
        $query['activo'] = 'eq.true';
        $result = $supabase->get($table, $query);
        $items = ($result['ok'] && !empty($result['data'])) ? $result['data'] : [];
        echo json_encode(['ok' => true, 'items' => $items]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Error al listar.']);
    } catch (RuntimeException $e) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Tenant no establecido.']);
    }
    exit;
}

if ($action === 'agregar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    if (strlen($nombre) > 200) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'El nombre no puede exceder 200 caracteres.']);
        exit;
    }
    if ($nombre === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'El nombre es requerido.']);
        exit;
    }

    try {
        $tenantId = TenantContext::requireTenant();
        $data = ['tenant_id' => $tenantId, 'nombre' => $nombre];
        $result = $supabase->post($table, $data);
        if ($result['ok'] && !empty($result['data'])) {
            $id = (int) $result['data'][0]['id'];
            echo json_encode(['ok' => true, 'id' => $id, 'nombre' => $nombre, 'message' => $cfg['label'] . ' agregado.']);
        } else {
            $errMsg = $result['error'] ?? 'Error desconocido';
            if (strpos($errMsg, 'duplicate') !== false || strpos($errMsg, '23505') !== false) {
                http_response_code(409);
                echo json_encode(['ok' => false, 'message' => 'Este ' . strtolower($cfg['label']) . ' ya existe.']);
            } else {
                http_response_code(500);
                echo json_encode(['ok' => false, 'message' => 'Error de base de datos.']);
            }
        }
    } catch (RuntimeException $e) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Tenant no establecido.']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'message' => 'Acción no válida.']);
