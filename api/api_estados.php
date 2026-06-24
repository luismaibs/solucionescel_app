<?php
/**
 * API de Configuración de Estados — CRUD del sistema dinámico de estados por tenant.
 *
 * Todas las operaciones usan forceServiceRole=true porque el JWT de sesión PHP
 * no incluye app_metadata.tenant_id en los claims de Supabase, lo que hace que
 * current_tenant_id() retorne NULL y el RLS bloquee todas las filas.
 * La autorización real (isAdmin, tenant) se valida en PHP.
 *
 * Acciones:
 *   GET  ?action=tree           → Árbol completo (padres con hijos anidados)
 *   GET  ?action=get&id=        → Estado individual + datos de plantilla
 *   GET  ?action=list_templates → Listar plantillas disponibles
 *   POST action=save            → Crear o editar estado
 *   POST action=delete          → Eliminar estado (hard delete)
 *   POST action=reorder         → Actualizar orden
 */
header('Content-Type: application/json; charset=utf-8');
include '../config/auth.php';
requireLogin();
include '../config/db.php';

$isAdmin = isAdmin();
$tid = TenantContext::requireTenant();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonOk($data = [], string $message = 'OK'): void {
    echo json_encode(array_merge(['ok' => true, 'message' => $message], $data));
    exit;
}

function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

// ═══════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════

function generarSlug(string $nombre, ?string $parentId = null): string {
    $slug = strtolower(trim($nombre));
    $slug = preg_replace('/[áàä]/u', 'a', $slug);
    $slug = preg_replace('/[éèë]/u', 'e', $slug);
    $slug = preg_replace('/[íìï]/u', 'i', $slug);
    $slug = preg_replace('/[óòö]/u', 'o', $slug);
    $slug = preg_replace('/[úùü]/u', 'u', $slug);
    $slug = preg_replace('/[ñ]/u', 'n', $slug);
    $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
    $slug = trim($slug, '_');
    $slug = substr($slug, 0, 60);
    if ($slug === '') {
        $slug = 'estado_' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
    return ($parentId ? 'sub_' : '') . $slug;
}

function buscarOCrearCarpetaPlantillas(string $nombreCarpeta): ?int {
    global $supabase, $tid;

    $carpetaResult = $supabase->get('whatsapp_template_carpetas', [
        'select' => 'id',
        'tenant_id' => 'eq.' . $tid,
        'nombre' => 'eq.' . $nombreCarpeta,
        'limit' => '1',
    ], null, true);

    if ($carpetaResult['ok'] && !empty($carpetaResult['data'])) {
        return (int) $carpetaResult['data'][0]['id'];
    }

    $createCarpeta = $supabase->post('whatsapp_template_carpetas', [
        'nombre' => $nombreCarpeta,
        'tenant_id' => $tid,
    ], null, true);

    if ($createCarpeta['ok'] && !empty($createCarpeta['data'])) {
        return (int) $createCarpeta['data'][0]['id'];
    }

    return null;
}

// ═══════════════════════════════════════════════════════
//  GET
// ═══════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // ── TREE ──
    if ($action === 'tree') {
        $result = $supabase->rpc('rpc_estados_tree', ['p_tenant_id' => $tid]);

        if (!$result['ok']) {
            jsonError('Error al consultar estados: ' . ($result['error'] ?? 'sin detalle'));
        }

        $rows = $result['data'] ?? [];
        if (is_string($rows)) {
            $rows = json_decode($rows, true) ?? [];
        }

        $padres = [];
        $hijos  = [];
        foreach ($rows as $r) {
            if (empty($r['parent_id'])) {
                $r['hijos'] = [];
                $padres[$r['id']] = $r;
            } else {
                $hijos[] = $r;
            }
        }
        foreach ($hijos as $h) {
            if (isset($padres[$h['parent_id']])) {
                $padres[$h['parent_id']]['hijos'][] = $h;
            }
        }

        $primerIngreso = [];
        $reIngreso     = [];
        foreach ($padres as $p) {
            if ($p['tipo'] === 'primer_ingreso') {
                $primerIngreso[] = $p;
            } else {
                $reIngreso[] = $p;
            }
        }

        jsonOk(['primer_ingreso' => $primerIngreso, 're_ingreso' => $reIngreso]);
    }

    // ── GET ──
    if ($action === 'get') {
        $id = $_GET['id'] ?? '';
        if ($id === '') jsonError('ID requerido');

        $result = $supabase->get('estados_config', [
            'select'    => '*',
            'tenant_id' => 'eq.' . $tid,
            'id'        => 'eq.' . $id,
            'limit'     => '1',
        ], null, true);

        if (!$result['ok'] || empty($result['data'])) {
            jsonError('Estado no encontrado', 404);
        }

        $estado = $result['data'][0];

        if (!empty($estado['plantilla_id'])) {
            $tplResult = $supabase->get('whatsapp_templates', [
                'select' => 'id,title',
                'id'     => 'eq.' . $estado['plantilla_id'],
                'limit'  => '1',
            ], null, true);
            $estado['plantilla'] = ($tplResult['ok'] && !empty($tplResult['data']))
                ? $tplResult['data'][0] : null;
        }

        jsonOk(['estado' => $estado]);
    }

    // ── LIST_TEMPLATES ──
    if ($action === 'list_templates') {
        $carpetaId = buscarOCrearCarpetaPlantillas('Estados');
        if ($carpetaId === null) {
            jsonOk(['templates' => []]);
        }

        $result = $supabase->get('whatsapp_templates', [
            'select'    => 'id,title,carpeta_id',
            'carpeta_id' => 'eq.' . $carpetaId,
            'order'     => 'title.asc',
        ], null, true);
        $templates = $result['ok'] ? ($result['data'] ?? []) : [];
        jsonOk(['templates' => $templates]);
    }

    jsonError('Acción GET no válida');
}

// ═══════════════════════════════════════════════════════
//  POST
// ═══════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$isAdmin) jsonError('No autorizado — solo administradores', 403);

    // ── SAVE ──
    if ($action === 'save') {
        $id             = !empty($_POST['id']) ? $_POST['id'] : null;
        $parentId       = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
        $nombre         = trim($_POST['nombre'] ?? '');
        $descripcion    = trim($_POST['descripcion'] ?? '');
        $color          = trim($_POST['color'] ?? '#94a3b8');
        $tipo           = $_POST['tipo'] ?? 'primer_ingreso';
        $habReingreso   = !empty($_POST['habilitar_reingreso']);
        $seleccionable  = isset($_POST['seleccionable']) && $_POST['seleccionable'] !== '0' && $_POST['seleccionable'] !== '';
        $plantillaId    = !empty($_POST['plantilla_id']) ? (int) $_POST['plantilla_id'] : null;
        $orden          = isset($_POST['orden']) ? (int) $_POST['orden'] : 0;

        if ($nombre === '') jsonError('El nombre es requerido');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) jsonError('Color inválido (formato #RRGGBB)');
        if (!in_array($tipo, ['primer_ingreso', 're_ingreso'])) jsonError('Tipo inválido');

        if ($plantillaId !== null) {
            $carpetaId = buscarOCrearCarpetaPlantillas('Estados');
            $tplResult = $supabase->get('whatsapp_templates', [
                'select' => 'id',
                'id' => 'eq.' . $plantillaId,
                'carpeta_id' => 'eq.' . $carpetaId,
                'limit' => '1',
            ], null, true);

            if (!$tplResult['ok'] || empty($tplResult['data'])) {
                jsonError('Selecciona una plantilla de la carpeta Estados');
            }
        }

        // Subestado hereda tipo y color del padre
        if ($parentId) {
            $padre = $supabase->get('estados_config', [
                'select'    => 'tipo,color',
                'tenant_id' => 'eq.' . $tid,
                'id'        => 'eq.' . $parentId,
                'limit'     => '1',
            ], null, true);
            if ($padre['ok'] && !empty($padre['data'])) {
                $tipo  = $padre['data'][0]['tipo'];
                $color = $padre['data'][0]['color'];
            }
            $habReingreso = false;
        }

        if ($habReingreso && ($parentId || $tipo !== 'primer_ingreso')) {
            jsonError('Solo estados principales de Primer Ingreso pueden habilitar reingreso');
        }

        $data = [
            'tenant_id'           => $tid,
            'nombre'              => $nombre,
            'descripcion'         => $descripcion,
            'color'               => $color,
            'tipo'                => $tipo,
            'habilitar_reingreso' => $habReingreso,
            'seleccionable'       => $seleccionable,
            'orden'               => $orden,
            'parent_id'           => $parentId,
        ];

        $data['plantilla_id'] = $plantillaId;

        if ($id) {
            $data['updated_at'] = date('c');
            $result = $supabase->patch('estados_config', $data, [
                'tenant_id' => 'eq.' . $tid,
                'id'        => 'eq.' . $id,
            ], null, true);
        } else {
            $data['slug'] = generarSlug($nombre, $parentId);
            $result = $supabase->post('estados_config', $data, null, true);
        }

        if (!$result['ok']) {
            jsonError('Error al guardar: ' . ($result['error'] ?? 'desconocido'), 500);
        }

        $saved = $result['data'][0] ?? $result['data'] ?? [];
        jsonOk(['estado' => $saved], $id ? 'Estado actualizado' : 'Estado creado');
    }

    // ── DELETE ──
    if ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id === '') jsonError('ID requerido');

        $result = $supabase->delete('estados_config', [
            'tenant_id' => 'eq.' . $tid,
            'id'        => 'eq.' . $id,
        ], null, true);

        if (!$result['ok']) {
            jsonError('Error al eliminar: ' . ($result['error'] ?? 'desconocido'), 500);
        }

        jsonOk([], 'Estado eliminado correctamente');
    }

    // ── REORDER ──
    if ($action === 'reorder') {
        $id    = $_POST['id'] ?? '';
        $orden = isset($_POST['orden']) ? (int) $_POST['orden'] : 0;
        if ($id === '') jsonError('ID requerido');

        $result = $supabase->patch('estados_config', ['orden' => $orden], [
            'tenant_id' => 'eq.' . $tid,
            'id'        => 'eq.' . $id,
        ], null, true);

        if (!$result['ok']) {
            jsonError('Error al reordenar', 500);
        }
        jsonOk([], 'Orden actualizado');
    }

    jsonError('Acción POST no válida');
}
