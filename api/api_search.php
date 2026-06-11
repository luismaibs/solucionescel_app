<?php
/**
 * Búsqueda global: busca en TODAS las tablas del sistema.
 *
 * GET ?q=término  (mínimo 3 caracteres)
 *
 * Tablas buscadas:
 *  - reparaciones (equipos)
 *  - inventario (inventario clásico)
 *  - inv_servicios_generales
 *  - inv_baterias
 *  - inv_accesorios (+ catálogos JOIN)
 *  - inv_pantallas  (+ catálogos JOIN)
 *  - bot_conversaciones (soporte)
 *  - usuarios (solo admin)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$base_path = isset($_GET['base_path']) ? trim($_GET['base_path']) : '/';
$base_path = preg_replace('#[^a-zA-Z0-9_\-/.]#', '', $base_path);
if (empty($base_path)) $base_path = '/';
if (substr($base_path, -1) !== '/') $base_path .= '/';

$limit = 5;
$results = [];

if (mb_strlen($q) < 3) {
    echo json_encode(['ok' => true, 'q' => $q, 'results' => new stdClass()]);
    exit;
}

$term = '*' . $q . '*';
$tenantId = TenantContext::requireTenant();

// ── Helper: busca con ilike en columnas específicas ──
function searchSupabase(SupabaseClient $supabase, string $table, array $query, int $limit): array {
    $query['limit'] = (string) $limit;
    $result = $supabase->get($table, $query);
    if ($result['ok']) {
        return $result['data'] ?? [];
    }
    error_log("[BusquedaGlobal] Error en tabla $table: " . ($result['error'] ?? 'desconocido'));
    return [];
}

// ═══════════════ EQUIPOS (reparaciones) ═══════════════
$rows = searchSupabase($supabase, 'reparaciones', [
    'select' => 'id,folio_publico,equipo_marca,equipo_modelo,estado,cliente_id',
    'tenant_id' => 'eq.' . $tenantId,
    'deleted_at' => 'is.null',
    'or' => '(folio_publico.ilike.' . $term . ',equipo_marca.ilike.' . $term . ',equipo_modelo.ilike.' . $term . ')',
    'order' => 'id.desc',
], $limit);

// Búsqueda complementaria por cliente
$remaining = $limit - count($rows);
$allClienteIds = [];
if ($remaining > 0) {
    $clienteMatches = searchSupabase($supabase, 'clientes', [
        'select' => 'id',
        'tenant_id' => 'eq.' . $tenantId,
        'deleted_at' => 'is.null',
        'or' => '(nombre.ilike.' . $term . ',apellido.ilike.' . $term . ',telefono.ilike.' . $term . ')',
    ], 100);
    $foundClienteIds = array_column($clienteMatches, 'id');
    $existingRepIds = array_column($rows, 'id');
    if (!empty($foundClienteIds)) {
        $orParts = array_map(function ($cid) { return 'cliente_id.eq.' . $cid; }, $foundClienteIds);
        $extraRows = searchSupabase($supabase, 'reparaciones', [
            'select' => 'id,folio_publico,equipo_marca,equipo_modelo,estado,cliente_id',
            'tenant_id' => 'eq.' . $tenantId,
            'deleted_at' => 'is.null',
            'or' => '(' . implode(',', $orParts) . ')',
            'order' => 'id.desc',
        ], $limit);
        foreach ($extraRows as $er) {
            if (!in_array($er['id'], $existingRepIds)) {
                $rows[] = $er;
                $existingRepIds[] = $er['id'];
            }
        }
        $rows = array_slice($rows, 0, $limit);
    }
    $allClienteIds = array_unique(array_merge(array_column($clienteMatches, 'id'), array_filter(array_column($rows, 'cliente_id'))));
} else {
    $allClienteIds = array_unique(array_filter(array_column($rows, 'cliente_id')));
}

// Hidratar nombres de cliente
$clienteMap = [];
if (!empty($allClienteIds)) {
    foreach ($allClienteIds as $cid) {
        $cRes = $supabase->get('clientes', [
            'select' => 'nombre,apellido,telefono',
            'tenant_id' => 'eq.' . $tenantId,
            'id' => 'eq.' . $cid,
            'deleted_at' => 'is.null',
            'limit' => '1',
        ]);
        if ($cRes['ok'] && !empty($cRes['data'])) {
            $clienteMap[$cid] = $cRes['data'][0];
        }
    }
}

$results['equipos'] = [
    'label'  => 'Equipos / Taller',
    'icon'   => 'bi-tools',
    'count'  => count($rows),
    'url'    => $base_path . 'modules/panel',
    'items'  => array_map(function ($r) use ($base_path, $clienteMap) {
        $cid = $r['cliente_id'] ?? null;
        $clienteNombre = '';
        if ($cid && isset($clienteMap[$cid])) {
            $clienteNombre = trim(($clienteMap[$cid]['nombre'] ?? '') . ' ' . ($clienteMap[$cid]['apellido'] ?? ''));
        }
        return [
            'titulo'    => ($r['folio_publico'] ?? '') . ' – ' . $clienteNombre,
            'subtitulo' => trim(($r['equipo_marca'] ?? '') . ' ' . ($r['equipo_modelo'] ?? '')),
            'badge'     => $r['estado'] ?? '',
            'url'       => $base_path . 'index.php',
        ];
    }, $rows),
];

// ═══════════════ SERVICIOS GENERALES ═══════════════
$rows = searchSupabase($supabase, 'inv_servicios_generales', [
    'select' => 'id,subcategoria,gama,sistemas_operativos,precio,garantia',
    'tenant_id' => 'eq.' . $tenantId,
    'deleted_at' => 'is.null',
    'or' => '(subcategoria.ilike.' . $term . ',gama.ilike.' . $term . ',sistemas_operativos.ilike.' . $term . ',nota.ilike.' . $term . ')',
    'order' => 'id.desc',
], $limit);

if (!empty($rows)) {
    $results['servicios_generales'] = [
        'label'  => 'Inventario – Servicios',
        'icon'   => 'bi-gear-wide-connected',
        'count'  => count($rows),
        'url'    => $base_path . 'modules/inventario',
        'items'  => array_map(function ($r) use ($base_path) {
            return [
                'titulo'    => ucfirst($r['subcategoria'] ?? '') . ' – $' . number_format((float) $r['precio'], 2),
                'subtitulo' => 'Gama: ' . ($r['gama'] ?? '') . ' · SO: ' . ($r['sistemas_operativos'] ?? ''),
                'badge'     => 'Servicio',
                'url'       => $base_path . 'modules/inventario',
            ];
        }, $rows),
    ];
}

// ═══════════════ BATERÍAS ═══════════════
$batMarcaIds = []; $batModeloIds = []; $batMarcaMap = []; $batModeloMap = [];

foreach (searchSupabase($supabase, 'marcas', ['select' => 'id,nombre', 'tenant_id' => 'eq.' . $tenantId, 'nombre' => 'ilike.' . $term], $limit) as $m) {
    $batMarcaIds[] = $m['id']; $batMarcaMap[$m['id']] = $m['nombre'];
}
foreach (searchSupabase($supabase, 'modelos', ['select' => 'id,nombre', 'tenant_id' => 'eq.' . $tenantId, 'nombre' => 'ilike.' . $term], $limit) as $m) {
    $batModeloIds[] = $m['id']; $batModeloMap[$m['id']] = $m['nombre'];
}

$batOrParts = ['calidad.ilike.' . $term, 'notas.ilike.' . $term];
if (!empty($batMarcaIds))  $batOrParts[] = 'marca_id.in.(' . implode(',', $batMarcaIds) . ')';
if (!empty($batModeloIds)) $batOrParts[] = 'modelo_id.in.(' . implode(',', $batModeloIds) . ')';

$rows = searchSupabase($supabase, 'inv_baterias', [
    'select'     => 'id,marca_id,modelo_id,calidad,tipo',
    'tenant_id'  => 'eq.' . $tenantId,
    'deleted_at' => 'is.null',
    'or'         => '(' . implode(',', $batOrParts) . ')',
    'order'      => 'id.desc',
], $limit);

if (!empty($rows)) {
    foreach ($rows as $r) {
        $mid = $r['marca_id'] ?? 0;
        if ($mid && !isset($batMarcaMap[$mid])) {
            $res = $supabase->get('marcas', ['select' => 'nombre', 'tenant_id' => 'eq.' . $tenantId, 'id' => 'eq.' . $mid, 'limit' => '1']);
            if ($res['ok'] && !empty($res['data'])) $batMarcaMap[$mid] = $res['data'][0]['nombre'];
        }
        $mid2 = $r['modelo_id'] ?? 0;
        if ($mid2 && !isset($batModeloMap[$mid2])) {
            $res = $supabase->get('modelos', ['select' => 'nombre', 'tenant_id' => 'eq.' . $tenantId, 'id' => 'eq.' . $mid2, 'limit' => '1']);
            if ($res['ok'] && !empty($res['data'])) $batModeloMap[$mid2] = $res['data'][0]['nombre'];
        }
    }
    $results['baterias'] = [
        'label'  => 'Inventario – Baterías',
        'icon'   => 'bi-battery-charging',
        'count'  => count($rows),
        'url'    => $base_path . 'modules/inventario',
        'items'  => array_map(function ($r) use ($base_path, $batMarcaMap, $batModeloMap) {
            return [
                'titulo'    => ($batMarcaMap[$r['marca_id']] ?? '') . ' – ' . ($batModeloMap[$r['modelo_id']] ?? ''),
                'subtitulo' => 'Calidad: ' . ($r['calidad'] ?? '') . ' · Tipo: ' . ($r['tipo'] ?? ''),
                'badge'     => 'Batería',
                'url'       => $base_path . 'modules/inventario',
            ];
        }, $rows),
    ];
}

// ═══════════════ ACCESORIOS ═══════════════
$rows = searchSupabase($supabase, 'inv_accesorios', [
    'select' => 'id,codigo,nombre_producto,stock,precio,subcategoria_id,marca_id,color_id',
    'tenant_id' => 'eq.' . $tenantId,
    'deleted_at' => 'is.null',
    'or' => '(nombre_producto.ilike.' . $term . ',codigo.ilike.' . $term . ')',
    'order' => 'id.desc',
], $limit);

if (!empty($rows)) {
    // Resolver catálogos para accesorios
    $subIds = array_unique(array_filter(array_column($rows, 'subcategoria_id')));
    $marcaIds = array_unique(array_filter(array_column($rows, 'marca_id')));
    $colorIds = array_unique(array_filter(array_column($rows, 'color_id')));
    $subMap = []; $marcaMap = []; $colorMap = [];
    foreach ($subIds as $sid) {
        $r = $supabase->get('subcategorias', ['select' => 'nombre', 'tenant_id' => 'eq.' . $tenantId, 'id' => 'eq.' . $sid, 'limit' => '1']);
        if ($r['ok'] && !empty($r['data'])) $subMap[$sid] = $r['data'][0]['nombre'];
    }
    foreach ($marcaIds as $mid) {
        $r = $supabase->get('marcas', ['select' => 'nombre', 'tenant_id' => 'eq.' . $tenantId, 'id' => 'eq.' . $mid, 'limit' => '1']);
        if ($r['ok'] && !empty($r['data'])) $marcaMap[$mid] = $r['data'][0]['nombre'];
    }
    foreach ($colorIds as $colid) {
        $r = $supabase->get('colores', ['select' => 'nombre', 'tenant_id' => 'eq.' . $tenantId, 'id' => 'eq.' . $colid, 'limit' => '1']);
        if ($r['ok'] && !empty($r['data'])) $colorMap[$colid] = $r['data'][0]['nombre'];
    }

    $results['accesorios'] = [
        'label'  => 'Inventario – Accesorios',
        'icon'   => 'bi-headphones',
        'count'  => count($rows),
        'url'    => $base_path . 'modules/inventario',
        'items'  => array_map(function ($r) use ($base_path, $subMap, $marcaMap, $colorMap) {
            $s = $subMap[$r['subcategoria_id']] ?? '';
            $m = $marcaMap[$r['marca_id']] ?? '';
            $c = $colorMap[$r['color_id']] ?? '';
            return [
                'titulo'    => ($r['nombre_producto'] ?? '') . ' (' . ($r['codigo'] ?? '') . ')',
                'subtitulo' => $m . ' · ' . $c . ' · $' . number_format((float) $r['precio'], 2),
                'badge'     => 'Accesorio',
                'url'       => $base_path . 'modules/inventario',
            ];
        }, $rows),
    ];
}

// ═══════════════ PANTALLAS ═══════════════
$rows = searchSupabase($supabase, 'inv_pantallas', [
    'select' => 'id,calidad,precio,tiempo,modelo_id,modelo_tecnico_id',
    'tenant_id' => 'eq.' . $tenantId,
    'deleted_at' => 'is.null',
    'or' => '(calidad.ilike.' . $term . ',nota.ilike.' . $term . ')',
    'order' => 'id.desc',
], $limit);

if (!empty($rows)) {
    $modIds = array_unique(array_filter(array_column($rows, 'modelo_id')));
    $tecIds = array_unique(array_filter(array_column($rows, 'modelo_tecnico_id')));
    $modMap = []; $tecMap = [];
    foreach ($modIds as $mid) {
        $r = $supabase->get('modelos', ['select' => 'nombre', 'tenant_id' => 'eq.' . $tenantId, 'id' => 'eq.' . $mid, 'limit' => '1']);
        if ($r['ok'] && !empty($r['data'])) $modMap[$mid] = $r['data'][0]['nombre'];
    }
    foreach ($tecIds as $tid) {
        $r = $supabase->get('modelos', ['select' => 'nombre', 'tenant_id' => 'eq.' . $tenantId, 'id' => 'eq.' . $tid, 'limit' => '1']);
        if ($r['ok'] && !empty($r['data'])) $tecMap[$tid] = $r['data'][0]['nombre'];
    }

    $results['pantallas'] = [
        'label'  => 'Inventario – Pantallas',
        'icon'   => 'bi-phone',
        'count'  => count($rows),
        'url'    => $base_path . 'modules/inventario',
        'items'  => array_map(function ($r) use ($base_path, $modMap, $tecMap) {
            $modelo = $modMap[$r['modelo_id']] ?? '';
            $tecnico = $tecMap[$r['modelo_tecnico_id']] ?? '';
            return [
                'titulo'    => $modelo . ' – $' . number_format((float) $r['precio'], 2),
                'subtitulo' => 'Calidad: ' . ($r['calidad'] ?? '') . ' · ' . $tecnico,
                'badge'     => 'Pantalla',
                'url'       => $base_path . 'modules/inventario',
            ];
        }, $rows),
    ];
}

// ═══════════════ CLIENTES ═══════════════
$rows = searchSupabase($supabase, 'clientes', [
    'select' => 'id,nombre,apellido,telefono,correo',
    'tenant_id' => 'eq.' . $tenantId,
    'deleted_at' => 'is.null',
    'or' => '(nombre.ilike.' . $term . ',apellido.ilike.' . $term . ',telefono.ilike.' . $term . ',correo.ilike.' . $term . ')',
    'order' => 'nombre.asc',
], $limit);

if (!empty($rows)) {
    $results['clientes'] = [
        'label'  => 'Clientes',
        'icon'   => 'bi-person-lines-fill',
        'count'  => count($rows),
        'url'    => $base_path . 'modules/clientes',
        'items'  => array_map(function ($r) use ($base_path) {
            return [
                'titulo'    => ($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? ''),
                'subtitulo' => ($r['telefono'] ?? '') . ($r['correo'] ? ' · ' . $r['correo'] : ''),
                'badge'     => 'Cliente',
                'url'       => $base_path . 'modules/cliente_360.php?id=' . $r['id'],
            ];
        }, $rows),
    ];
}

// ═══════════════ SOPORTE (conversaciones) ═══════════════
$rows = searchSupabase($supabase, 'bot_conversaciones', [
    'select' => 'id,nombre_cliente,telefono,mensaje,estado',
    'tenant_id' => 'eq.' . $tenantId,
    'deleted_at' => 'is.null',
    'or' => '(nombre_cliente.ilike.' . $term . ',telefono.ilike.' . $term . ',mensaje.ilike.' . $term . ')',
    'order' => 'id.desc',
], $limit);

if (!empty($rows)) {
    $results['soporte'] = [
        'label'  => 'Soporte',
        'icon'   => 'bi-headset',
        'count'  => count($rows),
        'url'    => $base_path . 'modules/soporte',
        'items'  => array_map(function ($r) use ($base_path) {
            $msg = $r['mensaje'] ?? '';
            return [
                'titulo'    => $r['nombre_cliente'] ?? '',
                'subtitulo' => mb_strlen($msg) > 60 ? mb_substr($msg, 0, 60) . '…' : $msg,
                'badge'     => $r['estado'] ?? '',
                'url'       => $base_path . 'modules/soporte',
            ];
        }, $rows),
    ];
}

// ═══════════════ USUARIOS (solo admin) ═══════════════
if (function_exists('isAdmin') && isAdmin()) {
    $rows = searchSupabase($supabase, 'usuarios', [
        'select' => 'id,username,nombre_completo,rol',
        'tenant_id' => 'eq.' . $tenantId,
        'deleted_at' => 'is.null',
        'or' => '(username.ilike.' . $term . ',nombre_completo.ilike.' . $term . ')',
        'order' => 'nombre_completo.asc',
    ], $limit);

    if (!empty($rows)) {
        $results['usuarios'] = [
            'label'  => 'Usuarios',
            'icon'   => 'bi-people-fill',
            'count'  => count($rows),
            'url'    => $base_path . 'modules/usuarios',
            'items'  => array_map(function ($r) use ($base_path) {
                return [
                    'titulo'    => $r['nombre_completo'] ?? $r['username'],
                    'subtitulo' => '@' . ($r['username'] ?? '') . ' · ' . ucfirst($r['rol'] ?? ''),
                    'badge'     => $r['rol'] ?? '',
                    'url'       => $base_path . 'modules/usuarios',
                ];
            }, $rows),
        ];
    }
}

// Filtrar categorías vacías
$results = array_filter($results, function ($cat) {
    return !empty($cat['items']);
});

echo json_encode([
    'ok'      => true,
    'q'       => $q,
    'results' => empty($results) ? new stdClass() : $results,
]);
