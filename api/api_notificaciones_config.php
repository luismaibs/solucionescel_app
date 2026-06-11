<?php
/**
 * API de Configuración de Notificaciones — CRUD de notificaciones y grupos por tenant.
 *
 * GET  ?action=list          → Lista notificaciones activas (con grupo y plantilla)
 * GET  ?action=get&id=       → Notificación individual + datos de plantilla
 * GET  ?action=list_templates → Listar plantillas disponibles
 * GET  ?action=list_grupos   → Listar grupos de notificaciones
 * POST action=save           → Crear o editar notificación (admin)
 * POST action=delete         → Eliminar notificación (admin)
 * POST action=save_grupo     → Crear o editar grupo (admin)
 * POST action=delete_grupo   → Eliminar grupo (admin)
 * POST action=enviar         → Enviar notificación a cliente de una reparación
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

function getJwt(): ?string {
    return getJwtFromRequest();
}

// ═══════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════

function generarSlugNotif(string $titulo): string {
    $slug = strtolower(trim($titulo));
    $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
    $slug = trim($slug, '_');
    $slug = substr($slug, 0, 60);
    if ($slug === '') {
        $slug = 'notif_' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
    return 'notif_' . $slug;
}

function buscarOCrearPlantillaNotif(string $titulo): ?int {
    global $supabase, $tid;
    $nombreCarpeta = 'Automatizaciones de Notificaciones';

    $carpetaResult = $supabase->get('whatsapp_template_carpetas', [
        'select' => 'id',
        'tenant_id' => 'eq.' . $tid,
        'nombre' => 'eq.' . $nombreCarpeta,
        'limit' => '1',
    ], getJwt());

    $carpetaId = null;
    if ($carpetaResult['ok'] && !empty($carpetaResult['data'])) {
        $carpetaId = (int) $carpetaResult['data'][0]['id'];
    } else {
        $createCarpeta = $supabase->post('whatsapp_template_carpetas', [
            'nombre' => $nombreCarpeta,
            'tenant_id' => $tid,
        ], getJwt());
        if ($createCarpeta['ok'] && !empty($createCarpeta['data'])) {
            $carpetaId = (int) $createCarpeta['data'][0]['id'];
        }
    }

    $body = json_encode([
        'title' => 'Notificación: ' . $titulo,
        'content' => 'Notificación automática: ' . $titulo,
        'carpeta_id' => $carpetaId,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(rtrim(getenv('SUPABASE_URL'), '/') . '/rest/v1/whatsapp_templates');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . getenv('SUPABASE_ANON_KEY'),
        'Authorization: Bearer ' . (getJwt() ?: getenv('SUPABASE_SERVICE_ROLE_KEY')),
        'Prefer: return=representation',
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300) {
        $data = json_decode($raw, true);
        if (!empty($data[0]['id'])) {
            return (int) $data[0]['id'];
        }
    }
    return null;
}

function interpolarPlantilla(string $content, array $equipo): string {
    $modelo = trim(
        (!empty($equipo['equipo_marca']) ? $equipo['equipo_marca'] . ' ' : '')
        . ($equipo['equipo_modelo'] ?? '')
    );
    $reemplazos = [
        '{{cliente}}' => $equipo['cliente_nombre'] ?? '',
        '{{folio}}'   => $equipo['folio_publico'] ?? '',
        '{{modelo}}'  => $modelo,
        '{{falla}}'   => $equipo['falla_reportada'] ?? '',
        '{{fecha}}'   => !empty($equipo['fecha_ingreso'])
            ? date('d/m/Y', strtotime($equipo['fecha_ingreso']))
            : '',
    ];
    return str_replace(array_keys($reemplazos), array_values($reemplazos), $content);
}

// ═══════════════════════════════════════════════════════
//  GET
// ═══════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // ── LIST ──
    if ($action === 'list') {
        $baseFilters = [
            'tenant_id' => 'eq.' . $tid,
            'activo' => 'eq.true',
            'order' => 'orden.asc,created_at.desc',
        ];

        // Intento con grupos y plantillas (requiere migración 0015)
        $result = $supabase->get('notificaciones_config', array_merge($baseFilters, [
            'select' => 'id,slug,titulo,mensaje,tipo,icono,plantilla_id,grupo_id,orden,activo,grupos_notificaciones(id,nombre),whatsapp_templates(id,title)',
        ]), getJwt());

        // Fallback sin grupo_id si la migración aún no se ejecutó
        if (!$result['ok']) {
            $result = $supabase->get('notificaciones_config', array_merge($baseFilters, [
                'select' => 'id,slug,titulo,mensaje,tipo,icono,plantilla_id,orden,activo,whatsapp_templates(id,title)',
            ]), getJwt());
        }

        if (!$result['ok']) {
            jsonError('Error al consultar notificaciones');
        }

        jsonOk(['notificaciones' => $result['data'] ?? []]);
    }

    // ── GET ──
    if ($action === 'get') {
        $id = $_GET['id'] ?? '';
        if ($id === '') jsonError('ID requerido');

        $result = $supabase->get('notificaciones_config', [
            'select' => '*,grupos_notificaciones(id,nombre),whatsapp_templates(id,title)',
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $id,
            'limit' => '1',
        ], getJwt());

        // Fallback sin joins si migración no aplicada
        if (!$result['ok']) {
            $result = $supabase->get('notificaciones_config', [
                'select' => '*,whatsapp_templates(id,title)',
                'tenant_id' => 'eq.' . $tid,
                'id' => 'eq.' . $id,
                'limit' => '1',
            ], getJwt());
        }

        if (!$result['ok'] || empty($result['data'])) {
            jsonError('Notificación no encontrada', 404);
        }

        jsonOk(['notificacion' => $result['data'][0]]);
    }

    // ── LIST_TEMPLATES ──
    if ($action === 'list_templates') {
        $result = $supabase->get('whatsapp_templates', [
            'select' => 'id,title,carpeta_id',
            'order' => 'title.asc',
        ], getJwt());
        $templates = $result['ok'] ? ($result['data'] ?? []) : [];
        jsonOk(['templates' => $templates]);
    }

    // ── LIST_GRUPOS ──
    if ($action === 'list_grupos') {
        $result = $supabase->get('grupos_notificaciones', [
            'select' => 'id,nombre,orden',
            'tenant_id' => 'eq.' . $tid,
            'activo' => 'eq.true',
            'order' => 'orden.asc,nombre.asc',
        ], getJwt());

        jsonOk(['grupos' => $result['ok'] ? ($result['data'] ?? []) : []]);
    }

    jsonError('Acción GET no válida');
}

// ═══════════════════════════════════════════════════════
//  POST
// ═══════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── SAVE ──
    if ($action === 'save') {
        if (!$isAdmin) jsonError('No autorizado — solo administradores', 403);

        $id = !empty($_POST['id']) ? $_POST['id'] : null;
        $titulo = trim($_POST['titulo'] ?? '');
        $mensaje = trim($_POST['mensaje'] ?? '');
        $tipo = $_POST['tipo'] ?? 'info';
        $icono = trim($_POST['icono'] ?? 'bell-fill');
        $plantillaId = !empty($_POST['plantilla_id']) ? (int) $_POST['plantilla_id'] : null;
        $grupoId = !empty($_POST['grupo_id']) ? (int) $_POST['grupo_id'] : null;
        $orden = isset($_POST['orden']) ? (int) $_POST['orden'] : 0;

        if ($titulo === '') jsonError('El título es requerido');
        if (!in_array($tipo, ['info', 'warning', 'error', 'success'])) jsonError('Tipo inválido');

        $data = [
            'tenant_id' => $tid,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'tipo' => $tipo,
            'icono' => $icono,
            'orden' => $orden,
            'grupo_id' => $grupoId,
        ];

        if ($plantillaId === null && $titulo !== '') {
            $plantillaId = buscarOCrearPlantillaNotif($titulo);
        }
        $data['plantilla_id'] = $plantillaId;

        if ($id) {
            $data['updated_at'] = date('c');
            $result = $supabase->patch('notificaciones_config', $data, [
                'tenant_id' => 'eq.' . $tid,
                'id' => 'eq.' . $id,
            ], getJwt());
        } else {
            $data['slug'] = generarSlugNotif($titulo);
            $result = $supabase->post('notificaciones_config', $data, getJwt());
        }

        if (!$result['ok']) {
            jsonError('Error al guardar: ' . ($result['error'] ?? 'desconocido'), 500);
        }

        $saved = $result['data'][0] ?? $result['data'] ?? [];
        jsonOk(['notificacion' => $saved], $id ? 'Notificación actualizada' : 'Notificación creada');
    }

    // ── DELETE ──
    if ($action === 'delete') {
        if (!$isAdmin) jsonError('No autorizado — solo administradores', 403);

        $id = $_POST['id'] ?? '';
        if ($id === '') jsonError('ID requerido');

        $result = $supabase->delete('notificaciones_config', [
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $id,
        ], getJwt());

        if (!$result['ok']) {
            jsonError('Error al eliminar: ' . ($result['error'] ?? 'desconocido'), 500);
        }

        jsonOk([], 'Notificación eliminada correctamente');
    }

    // ── SAVE_GRUPO ──
    if ($action === 'save_grupo') {
        if (!$isAdmin) jsonError('No autorizado — solo administradores', 403);

        $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
        $nombre = trim($_POST['nombre'] ?? '');
        $orden = isset($_POST['orden']) ? (int) $_POST['orden'] : 0;

        if ($nombre === '') jsonError('El nombre del grupo es requerido');

        $data = [
            'tenant_id' => $tid,
            'nombre' => $nombre,
            'orden' => $orden,
        ];

        if ($id) {
            $result = $supabase->patch('grupos_notificaciones', $data, [
                'tenant_id' => 'eq.' . $tid,
                'id' => 'eq.' . $id,
            ], getJwt());
        } else {
            $result = $supabase->post('grupos_notificaciones', $data, getJwt());
        }

        if (!$result['ok']) {
            jsonError('Error al guardar grupo: ' . ($result['error'] ?? 'desconocido'), 500);
        }

        $saved = $result['data'][0] ?? $result['data'] ?? [];
        jsonOk(['grupo' => $saved], $id ? 'Grupo actualizado' : 'Grupo creado');
    }

    // ── DELETE_GRUPO ──
    if ($action === 'delete_grupo') {
        if (!$isAdmin) jsonError('No autorizado — solo administradores', 403);

        $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
        if (!$id) jsonError('ID de grupo requerido');

        $result = $supabase->delete('grupos_notificaciones', [
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $id,
        ], getJwt());

        if (!$result['ok']) {
            jsonError('Error al eliminar grupo: ' . ($result['error'] ?? 'desconocido'), 500);
        }

        jsonOk([], 'Grupo eliminado correctamente');
    }

    // ── ENVIAR ──
    if ($action === 'enviar') {
        $repairId = !empty($_POST['repair_id']) ? (int) $_POST['repair_id'] : 0;
        $notifId  = trim($_POST['notif_id'] ?? '');

        if (!$repairId) jsonError('ID de reparación requerido');
        if ($notifId === '') jsonError('ID de notificación requerido');

        // 1. Obtener notificación config
        $notifResult = $supabase->get('notificaciones_config', [
            'select' => 'id,titulo,mensaje,plantilla_id',
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $notifId,
            'activo' => 'eq.true',
            'limit' => '1',
        ], getJwt());

        if (!$notifResult['ok'] || empty($notifResult['data'])) {
            jsonError('Notificación no encontrada', 404);
        }
        $notif = $notifResult['data'][0];

        // 2. Obtener datos de la reparación
        $repo = new ReparacionRepository($supabase);
        $equipo = $repo->findById($repairId);
        if (!$equipo) {
            jsonError('Reparación no encontrada', 404);
        }

        $telefono = $equipo['telefono'] ?? '';
        if (empty($telefono)) {
            jsonError('El cliente no tiene teléfono registrado');
        }

        // 3. Obtener contenido de plantilla (si existe), o usar campo mensaje
        $mensajeTexto = '';
        $plantillaId = !empty($notif['plantilla_id']) ? (int) $notif['plantilla_id'] : null;

        if ($plantillaId) {
            $tplResult = $supabase->get('whatsapp_templates', [
                'select' => 'content',
                'id' => 'eq.' . $plantillaId,
                'limit' => '1',
            ], getJwt());

            if ($tplResult['ok'] && !empty($tplResult['data'])) {
                $mensajeTexto = interpolarPlantilla(
                    $tplResult['data'][0]['content'] ?? '',
                    $equipo
                );
            }
        }

        if ($mensajeTexto === '') {
            $mensajeTexto = interpolarPlantilla($notif['mensaje'] ?? $notif['titulo'], $equipo);
        }

        // 4. Enviar via MensajesService (mismo patron que api_reparaciones.php)
        $webhookN8n = getenv('N8N_WEBHOOK_NOTIFICAR');
        if (empty($webhookN8n)) {
            jsonError('Webhook de notificaciones no configurado', 500);
        }

        $timelineService = class_exists('EventoTimelineService') ? new EventoTimelineService($supabase) : null;
        $mensajes = new MensajesService($supabase, $webhookN8n, $timelineService);

        $modelo = trim(
            (!empty($equipo['equipo_marca']) ? $equipo['equipo_marca'] . ' ' : '')
            . ($equipo['equipo_modelo'] ?? '')
        );

        $payload = [
            'tipo'          => 'mensaje_personalizado',
            'reparacion_id' => $repairId,
            'folio'         => $equipo['folio_publico'] ?? '',
            'cliente_id'    => isset($equipo['cliente_id']) ? (int) $equipo['cliente_id'] : null,
            'cliente'       => $equipo['cliente_nombre'] ?? '',
            'telefono'      => $telefono,
            'modelo'        => $modelo,
            'falla'         => $equipo['falla_reportada'] ?? '',
            'fecha_ingreso' => $equipo['fecha_ingreso'] ?? '',
            'mensaje_texto' => $mensajeTexto,
            'notif_titulo'  => $notif['titulo'],
        ];

        $mensajes->enviarNotificacion($payload, $repairId, $payload['cliente_id'] ?: null);
        registrarActividad(
            'notificacion_config',
            'Envió notificación: ' . ($notif['titulo']),
            $repairId,
            'reparacion'
        );

        jsonOk([], 'Notificación enviada correctamente');
    }

    jsonError('Acción POST no válida');
}
