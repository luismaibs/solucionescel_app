<?php
/**
 * API — Importar inventario por categoria via Supabase
 *
 * POST (JSON): { "categoria": "servicios|baterias|pantallas|accesorios", "rows": [ {...}, ... ] }
 */
include __DIR__ . '/../../config/auth.php';
requireLogin();
include __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/Shared/TenantContext.php';
require_once __DIR__ . '/embedding_helper.php';

header('Content-Type: application/json; charset=utf-8');

$tenantId = TenantContext::requireTenant();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Solo POST']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['categoria']) || empty($input['rows'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'categoria y rows requeridos']);
    exit;
}

$categoria = $input['categoria'];
$rows = $input['rows'];

$imported = 0;
$columnErrors = [];

switch ($categoria) {
    case 'servicios':
        $repo = new ServiciosGeneralesRepository($supabase);
        $validSubs = InventarioConstantes::SUBCATEGORIAS_SERVICIOS;
        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2;
            $sub = trim($row['subcategoria'] ?? '');
            $gama = trim($row['gama'] ?? '');
            $sistemas = trim($row['sistemas_operativos'] ?? '');
            $precio = $row['precio'] ?? '';

            if ($sub === '') {
                $columnErrors[] = "Fila {$rowNum} — Subcategoria vacia. Debe ser: " . implode(', ', $validSubs);
                continue;
            }
            if (!in_array($sub, $validSubs, true)) {
                $columnErrors[] = "Fila {$rowNum} — Subcategoria '{$sub}' no valida. Opciones: " . implode(', ', $validSubs);
                continue;
            }
            if ($gama === '') {
                $columnErrors[] = "Fila {$rowNum} — Gama vacia (campo obligatorio)";
                continue;
            }
            if ($sistemas === '') {
                $columnErrors[] = "Fila {$rowNum} — Sistemas operativos vacio (campo obligatorio)";
                continue;
            }
            if ($precio === '' || !is_numeric($precio) || (float) $precio < 0) {
                $columnErrors[] = "Fila {$rowNum} — Precio invalido: '{$precio}'. Debe ser un numero positivo.";
                continue;
            }
            try {
                $svcId = $repo->insertServicio(
                    $sub,
                    $gama,
                    $sistemas,
                    trim($row['garantia'] ?? 'NO'),
                    trim($row['tiempo_entrega'] ?? '') ?: null,
                    (float) $precio,
                    trim($row['nota'] ?? '') ?: null
                );
                $accionesStr = trim($row['acciones'] ?? '');
                if ($accionesStr !== '') {
                    $acciones = array_filter(array_map('trim', explode("\n", $accionesStr)));
                    $repo->insertAcciones($svcId, $acciones);
                }
                $imported++;
            } catch (\Throwable $e) {
                $columnErrors[] = "Fila {$rowNum} — Error al insertar: " . $e->getMessage();
            }
        }
        break;

    case 'baterias':
        $validCalidades = ['Original', 'Generico'];
        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2;
            $marca = trim($row['marca'] ?? '');
            $modelo = trim($row['modelo_bateria'] ?? '');
            $calidad = trim($row['calidad'] ?? '');
            $tipo = trim($row['tipo'] ?? '');
            $tiempo = trim($row['tiempo'] ?? '');
            $precio = $row['precio'] ?? '';

            if ($marca === '') {
                $columnErrors[] = "Fila {$rowNum} — Marca vacia (campo obligatorio)";
                continue;
            }
            if ($modelo === '') {
                $columnErrors[] = "Fila {$rowNum} — Modelo bateria vacio (campo obligatorio)";
                continue;
            }
            if ($calidad !== '' && !in_array($calidad, $validCalidades, true)) {
                $columnErrors[] = "Fila {$rowNum} — Calidad '{$calidad}' no valida. Opciones: " . implode(', ', $validCalidades);
                continue;
            }
            if ($tipo === '') {
                $columnErrors[] = "Fila {$rowNum} — Tipo vacio (campo obligatorio)";
                continue;
            }
            if ($tiempo === '') {
                $columnErrors[] = "Fila {$rowNum} — Tiempo vacio (campo obligatorio)";
                continue;
            }
            if ($precio === '' || !is_numeric($precio) || (float) $precio < 0) {
                $columnErrors[] = "Fila {$rowNum} — Precio invalido: '{$precio}'. Debe ser un numero positivo.";
                continue;
            }
            try {
                // findOrCreate marca
                $mRes = $supabase->get('marcas', ['select' => 'id', 'tenant_id' => 'eq.' . $tenantId, 'nombre' => 'ilike.' . $marca, 'activo' => 'is.true', 'limit' => '1']);
                $marcaId = !empty($mRes['data']) ? (int) $mRes['data'][0]['id'] : (int) ($supabase->post('marcas', ['tenant_id' => $tenantId, 'nombre' => $marca])['data'][0]['id'] ?? 0);

                // findOrCreate modelo
                $mdRes = $supabase->get('modelos', ['select' => 'id', 'tenant_id' => 'eq.' . $tenantId, 'nombre' => 'ilike.' . $modelo, 'activo' => 'is.true', 'limit' => '1']);
                $modeloId = !empty($mdRes['data']) ? (int) $mdRes['data'][0]['id'] : (int) ($supabase->post('modelos', ['tenant_id' => $tenantId, 'nombre' => $modelo])['data'][0]['id'] ?? 0);

                $supabase->post('inv_baterias', [
                    'tenant_id' => $tenantId,
                    'marca_id'  => $marcaId,
                    'modelo_id' => $modeloId,
                    'calidad'   => $calidad ?: 'Generico',
                    'tipo'      => $tipo,
                    'tiempo'    => $tiempo,
                    'notas'     => trim($row['notas'] ?? '') ?: null,
                    'precio'    => (float) $precio,
                    'stock'     => (int) ($row['stock'] ?? 0),
                    'codigo'    => trim($row['codigo'] ?? '') ?: null,
                ]);
                $imported++;
            } catch (\Throwable $e) {
                $columnErrors[] = "Fila {$rowNum} — Error al insertar: " . $e->getMessage();
            }
        }
        break;

    case 'pantallas':
        $validCalidades = InventarioConstantes::CALIDADES_PANTALLA;
        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2;
            $modeloNombre = trim($row['modelo'] ?? '');
            $modeloTecnicoNombre = trim($row['modelo_tecnico'] ?? '');
            $calidad = trim($row['calidad'] ?? '');
            $precio = $row['precio'] ?? '';
            $tiempo = trim($row['tiempo'] ?? '');

            if ($modeloNombre === '') {
                $columnErrors[] = "Fila {$rowNum} — Modelo vacio (campo obligatorio)";
                continue;
            }
            if ($modeloTecnicoNombre === '') {
                $columnErrors[] = "Fila {$rowNum} — Modelo tecnico vacio (campo obligatorio)";
                continue;
            }
            if ($calidad === '') {
                $columnErrors[] = "Fila {$rowNum} — Calidad vacia. Opciones: " . implode(', ', $validCalidades);
                continue;
            }
            if (!in_array($calidad, $validCalidades, true)) {
                $columnErrors[] = "Fila {$rowNum} — Calidad '{$calidad}' no valida. Opciones: " . implode(', ', $validCalidades);
                continue;
            }
            if ($tiempo === '') {
                $columnErrors[] = "Fila {$rowNum} — Tiempo vacio (campo obligatorio)";
                continue;
            }
            if ($precio === '' || !is_numeric($precio) || (float) $precio < 0) {
                $columnErrors[] = "Fila {$rowNum} — Precio invalido: '{$precio}'. Debe ser un numero positivo.";
                continue;
            }
            try {
                // Resolver o crear modelo (tabla compartida)
                $modeloResult = $supabase->get('modelos', [
                    'select' => 'id', 'tenant_id' => 'eq.' . $tenantId,
                    'nombre' => 'ilike.' . $modeloNombre, 'activo' => 'is.true', 'limit' => '1',
                ]);
                if (!empty($modeloResult['data'])) {
                    $modeloId = (int) $modeloResult['data'][0]['id'];
                } else {
                    $create = $supabase->post('modelos', ['tenant_id' => $tenantId, 'nombre' => $modeloNombre]);
                    $modeloId = (int) ($create['data'][0]['id'] ?? 0);
                }

                // Resolver o crear modelo tecnico (tabla compartida)
                $tecResult = $supabase->get('modelos', [
                    'select' => 'id', 'tenant_id' => 'eq.' . $tenantId,
                    'nombre' => 'ilike.' . $modeloTecnicoNombre, 'activo' => 'is.true', 'limit' => '1',
                ]);
                if (!empty($tecResult['data'])) {
                    $modeloTecId = (int) $tecResult['data'][0]['id'];
                } else {
                    $create = $supabase->post('modelos', ['tenant_id' => $tenantId, 'nombre' => $modeloTecnicoNombre]);
                    $modeloTecId = (int) ($create['data'][0]['id'] ?? 0);
                }

                $supabase->post('inv_pantallas', [
                    'tenant_id' => $tenantId,
                    'modelo_id' => $modeloId,
                    'modelo_tecnico_id' => $modeloTecId,
                    'calidad' => $calidad,
                    'precio' => (float) $precio,
                    'tiempo' => $tiempo,
                    'nota' => trim($row['nota'] ?? '') ?: null,
                ]);
                $imported++;
            } catch (\Throwable $e) {
                $columnErrors[] = "Fila {$rowNum} — Error al insertar: " . $e->getMessage();
            }
        }
        break;

    case 'accesorios':
        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2;
            $subNombre = trim($row['subcategoria'] ?? '');
            $marcaNombre = trim($row['marca'] ?? '');
            $colorNombre = trim($row['color'] ?? '');
            $codigo = trim($row['codigo'] ?? '');
            $nombreProducto = trim($row['nombre_producto'] ?? '');
            $stock = $row['stock'] ?? '';
            $precio = $row['precio'] ?? '';

            if ($subNombre === '') {
                $columnErrors[] = "Fila {$rowNum} — Subcategoria vacia (campo obligatorio)";
                continue;
            }
            if ($marcaNombre === '') {
                $columnErrors[] = "Fila {$rowNum} — Marca vacia (campo obligatorio)";
                continue;
            }
            if ($colorNombre === '') {
                $columnErrors[] = "Fila {$rowNum} — Color vacio (campo obligatorio)";
                continue;
            }
            if ($codigo === '') {
                $columnErrors[] = "Fila {$rowNum} — Codigo vacio (campo obligatorio)";
                continue;
            }
            if ($nombreProducto === '') {
                $columnErrors[] = "Fila {$rowNum} — Nombre producto vacio (campo obligatorio)";
                continue;
            }
            if ($stock === '' || !is_numeric($stock) || (int) $stock < 0) {
                $columnErrors[] = "Fila {$rowNum} — Stock invalido: '{$stock}'. Debe ser un numero entero >= 0.";
                continue;
            }
            if ($precio === '' || !is_numeric($precio) || (float) $precio < 0) {
                $columnErrors[] = "Fila {$rowNum} — Precio invalido: '{$precio}'. Debe ser un numero positivo.";
                continue;
            }
            try {
                // findOrCreate subcategoria
                $subResult = $supabase->get('subcategorias', [
                    'select' => 'id', 'tenant_id' => 'eq.' . $tenantId,
                    'nombre' => 'ilike.' . $subNombre, 'activo' => 'is.true', 'limit' => '1',
                ]);
                $subId = !empty($subResult['data']) ? (int) $subResult['data'][0]['id'] : (int) ($supabase->post('subcategorias', ['tenant_id' => $tenantId, 'nombre' => $subNombre])['data'][0]['id'] ?? 0);

                // findOrCreate marca
                $marcaResult = $supabase->get('marcas', [
                    'select' => 'id', 'tenant_id' => 'eq.' . $tenantId,
                    'nombre' => 'ilike.' . $marcaNombre, 'activo' => 'is.true', 'limit' => '1',
                ]);
                $marcaId = !empty($marcaResult['data']) ? (int) $marcaResult['data'][0]['id'] : (int) ($supabase->post('marcas', ['tenant_id' => $tenantId, 'nombre' => $marcaNombre])['data'][0]['id'] ?? 0);

                // findOrCreate color
                $colorResult = $supabase->get('colores', [
                    'select' => 'id', 'tenant_id' => 'eq.' . $tenantId,
                    'nombre' => 'ilike.' . $colorNombre, 'activo' => 'is.true', 'limit' => '1',
                ]);
                $colorId = !empty($colorResult['data']) ? (int) $colorResult['data'][0]['id'] : (int) ($supabase->post('colores', ['tenant_id' => $tenantId, 'nombre' => $colorNombre])['data'][0]['id'] ?? 0);

                $supabase->post('inv_accesorios', [
                    'tenant_id' => $tenantId,
                    'subcategoria_id' => $subId,
                    'marca_id' => $marcaId,
                    'color_id' => $colorId,
                    'codigo' => $codigo,
                    'nombre_producto' => $nombreProducto,
                    'stock' => (int) $stock,
                    'precio' => (float) $precio,
                ]);
                $imported++;
            } catch (\Throwable $e) {
                $columnErrors[] = "Fila {$rowNum} — Error al insertar: " . $e->getMessage();
            }
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Categoria no valida: ' . $categoria]);
        exit;
}

echo json_encode([
    'ok' => true,
    'imported' => $imported,
    'errors' => $columnErrors,
    'total' => count($rows),
]);

// Cerrar conexion al cliente para que el reindex no bloquee la respuesta
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
ignore_user_abort(true);
set_time_limit(0);

// Reindexar en background (no bloqueante)
if ($imported > 0) {
    try {
        reindexarCategoriaBackground($tenantId, $categoria);
    } catch (\Throwable $e) {
        error_log('reindex after import error: ' . $e->getMessage());
    }
}
