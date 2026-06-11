<?php

class InventarioEmbeddingRepository
{
    private SupabaseClient $api;

    public function __construct(SupabaseClient $api)
    {
        $this->api = $api;
    }

    private function userToken(): ?string
    {
        return getJwtFromRequest();
    }

    private const TABLE_MAP = [
        'accesorios' => 'inv_accesorios',
        'baterias'   => 'inv_baterias',
        'pantallas'  => 'inv_pantallas',
        'servicios'  => 'inv_servicios_generales',
    ];

    public function upsertEmbedding(
        int $tenantId,
        string $categoria,
        int $productoId,
        string $textoBusqueda,
        array $embedding
    ): bool {
        $existing = $this->api->get('inv_embeddings', [
            'select' => 'id',
            'tenant_id' => 'eq.' . $tenantId,
            'categoria' => 'eq.' . $categoria,
            'producto_id' => 'eq.' . $productoId,
            'limit' => '1',
        ], $this->userToken());

        $embeddingStr = '[' . implode(',', $embedding) . ']';

        if (!empty($existing['data'])) {
            $id = (int) $existing['data'][0]['id'];
            $result = $this->api->patch('inv_embeddings', [
                'texto_busqueda' => $textoBusqueda,
                'embedding' => $embeddingStr,
                'updated_at' => date('c'),
            ], [
                'id' => 'eq.' . $id,
                'tenant_id' => 'eq.' . $tenantId,
            ], $this->userToken());
            return $result['ok'];
        }

        $result = $this->api->post('inv_embeddings', [
            'tenant_id' => $tenantId,
            'categoria' => $categoria,
            'producto_id' => $productoId,
            'texto_busqueda' => $textoBusqueda,
            'embedding' => $embeddingStr,
        ], $this->userToken());

        return $result['ok'];
    }

    public function deleteEmbedding(int $tenantId, string $categoria, int $productoId): bool
    {
        $result = $this->api->delete('inv_embeddings', [
            'tenant_id' => 'eq.' . $tenantId,
            'categoria' => 'eq.' . $categoria,
            'producto_id' => 'eq.' . $productoId,
        ], $this->userToken());
        return $result['ok'];
    }

    public function buscarPorEmbedding(int $tenantId, array $embedding, int $limit = 10): array
    {
        $embeddingStr = '[' . implode(',', $embedding) . ']';
        $result = $this->api->rpc('rpc_buscar_inventario', [
            'p_tenant_id' => $tenantId,
            'p_embedding' => $embeddingStr,
            'p_limit' => $limit,
        ], $this->userToken());

        if ($result['ok'] && is_array($result['data'])) {
            return $result['data'];
        }
        return [];
    }

    public function obtenerIdsActivos(int $tenantId, string $categoria): array
    {
        $result = $this->api->rpc('rpc_inv_ids_para_embedding', [
            'p_tenant_id' => $tenantId,
            'p_categoria' => $categoria,
        ], $this->userToken());

        if ($result['ok'] && is_array($result['data'])) {
            return array_column($result['data'], 'producto_id');
        }
        return [];
    }

    public function obtenerProducto(int $tenantId, string $categoria, int $productoId): ?array
    {
        $table = self::TABLE_MAP[$categoria] ?? null;
        if (!$table) return null;

        $result = $this->api->get($table, [
            'select' => '*',
            'tenant_id' => 'eq.' . $tenantId,
            'id' => 'eq.' . $productoId,
            'deleted_at' => 'is.null',
            'limit' => '1',
        ], $this->userToken());

        if ($result['ok'] && !empty($result['data'])) {
            return $result['data'][0];
        }
        return null;
    }

    public function obtenerProductosPorIds(int $tenantId, string $categoria, array $productoIds): array
    {
        if (empty($productoIds)) return [];

        $table = self::TABLE_MAP[$categoria] ?? null;
        if (!$table) return [];

        $ids = implode(',', array_map('intval', $productoIds));

        $result = $this->api->get($table, [
            'select' => '*',
            'tenant_id' => 'eq.' . $tenantId,
            'id' => 'in.(' . $ids . ')',
            'deleted_at' => 'is.null',
        ], $this->userToken());

        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    public function obtenerProductosConCatalogosBatch(int $tenantId, array $hits): array
    {
        if (empty($hits)) return [];

        $porCategoria = [];
        foreach ($hits as $hit) {
            $cat = $hit['categoria'] ?? '';
            $pid = (int)($hit['producto_id'] ?? 0);
            if ($cat === '' || $pid <= 0) continue;
            $porCategoria[$cat][] = $pid;
        }

        $indexados = [];
        foreach ($porCategoria as $cat => $ids) {
            $productos = $this->obtenerProductosPorIds($tenantId, $cat, $ids);
            foreach ($productos as $p) {
                $key = $cat . ':' . (int)($p['id'] ?? 0);
                $indexados[$key] = $p;
            }
        }

        $catalogos = $this->cargarCatalogosEnLote($tenantId, $porCategoria, $indexados);
        $accionesServicios = $this->cargarAccionesServiciosEnLote($tenantId, $indexados);

        $resultado = [];
        foreach ($indexados as $key => $producto) {
            [$cat] = explode(':', $key, 2);
            switch ($cat) {
                case 'accesorios':
                    $producto['_subcategoria'] = $catalogos['subcategorias'][(int)($producto['subcategoria_id'] ?? 0)] ?? '';
                    $producto['_marca'] = $catalogos['marcas'][(int)($producto['marca_id'] ?? 0)] ?? '';
                    $producto['_color'] = $catalogos['colores'][(int)($producto['color_id'] ?? 0)] ?? '';
                    break;
                case 'baterias':
                    $producto['_marca'] = $catalogos['marcas'][(int)($producto['marca_id'] ?? 0)] ?? '';
                    $producto['_modelo'] = $catalogos['modelos'][(int)($producto['modelo_id'] ?? 0)] ?? '';
                    break;
                case 'pantallas':
                    $producto['_modelo'] = $catalogos['modelos'][(int)($producto['modelo_id'] ?? 0)] ?? '';
                    $producto['_modelo_tecnico'] = $catalogos['modelos'][(int)($producto['modelo_tecnico_id'] ?? 0)] ?? '';
                    break;
                case 'servicios':
                    $producto['_acciones'] = $accionesServicios[(int)($producto['id'] ?? 0)] ?? [];
                    break;
            }
            $resultado[$key] = $producto;
        }

        return $resultado;
    }

    private function cargarCatalogosEnLote(int $tenantId, array $porCategoria, array $productos): array
    {
        $cache = [];

        $catalogoMap = [
            'subcategorias' => ['accesorios' => ['subcategoria_id']],
            'marcas'        => ['accesorios' => ['marca_id'], 'baterias' => ['marca_id']],
            'colores'       => ['accesorios' => ['color_id']],
            'modelos'       => ['baterias' => ['modelo_id'], 'pantallas' => ['modelo_id', 'modelo_tecnico_id']],
        ];

        foreach ($catalogoMap as $tabla => $catCols) {
            $ids = [];
            foreach ($catCols as $cat => $cols) {
                foreach ($porCategoria[$cat] ?? [] as $pid) {
                    $key = $cat . ':' . $pid;
                    $p = $productos[$key] ?? null;
                    if (!$p) continue;
                    foreach ($cols as $col) {
                        $val = (int)($p[$col] ?? 0);
                        if ($val > 0) $ids[$val] = true;
                    }
                }
            }

            if (empty($ids)) { $cache[$tabla] = []; continue; }

            $idList = implode(',', array_keys($ids));
            $result = $this->api->get($tabla, [
                'select' => 'id,nombre',
                'tenant_id' => 'eq.' . $tenantId,
                'id' => 'in.(' . $idList . ')',
                'activo' => 'eq.true',
            ], $this->userToken());

            $cache[$tabla] = [];
            foreach ($result['data'] ?? [] as $row) {
                $cache[$tabla][(int)$row['id']] = $row['nombre'] ?? '';
            }
        }

        return $cache;
    }

    private function cargarAccionesServiciosEnLote(int $tenantId, array $productos): array
    {
        $servicioIds = [];
        foreach ($productos as $key => $p) {
            if (strpos($key, 'servicios:') === 0) {
                $servicioIds[] = (int)($p['id'] ?? 0);
            }
        }
        if (empty($servicioIds)) return [];

        $idList = implode(',', $servicioIds);

        $result = $this->api->get('inv_servicios_acciones', [
            'select' => 'servicio_id,accion',
            'tenant_id' => 'eq.' . $tenantId,
            'servicio_id' => 'in.(' . $idList . ')',
            'deleted_at' => 'is.null',
            'order' => 'orden.asc',
        ], $this->userToken());

        $acciones = [];
        foreach ($result['data'] ?? [] as $row) {
            $sid = (int)($row['servicio_id'] ?? 0);
            if (!isset($acciones[$sid])) $acciones[$sid] = [];
            $acciones[$sid][] = $row['accion'] ?? '';
        }
        return $acciones;
    }

    public function obtenerProductoConCatalogos(int $tenantId, string $categoria, int $productoId): ?array
    {
        $producto = $this->obtenerProducto($tenantId, $categoria, $productoId);
        if (!$producto) return null;

        switch ($categoria) {
            case 'accesorios':
                $producto['_subcategoria'] = $this->obtenerNombreCatalogo($tenantId, 'subcategorias', (int)($producto['subcategoria_id'] ?? 0));
                $producto['_marca'] = $this->obtenerNombreCatalogo($tenantId, 'marcas', (int)($producto['marca_id'] ?? 0));
                $producto['_color'] = $this->obtenerNombreCatalogo($tenantId, 'colores', (int)($producto['color_id'] ?? 0));
                break;
            case 'baterias':
                $producto['_marca'] = $this->obtenerNombreCatalogo($tenantId, 'marcas', (int)($producto['marca_id'] ?? 0));
                $producto['_modelo'] = $this->obtenerNombreCatalogo($tenantId, 'modelos', (int)($producto['modelo_id'] ?? 0));
                break;
            case 'pantallas':
                $producto['_modelo'] = $this->obtenerNombreCatalogo($tenantId, 'modelos', (int)($producto['modelo_id'] ?? 0));
                $producto['_modelo_tecnico'] = $this->obtenerNombreCatalogo($tenantId, 'modelos', (int)($producto['modelo_tecnico_id'] ?? 0));
                break;
            case 'servicios':
                $producto['_acciones'] = $this->obtenerAccionesServicio($tenantId, $productoId);
                break;
        }

        return $producto;
    }

    private function obtenerNombreCatalogo(int $tenantId, string $table, int $id): string
    {
        if ($id <= 0) return '';
        $result = $this->api->get($table, [
            'select' => 'nombre',
            'tenant_id' => 'eq.' . $tenantId,
            'id' => 'eq.' . $id,
            'limit' => '1',
        ], $this->userToken());
        return $result['data'][0]['nombre'] ?? '';
    }

    private function obtenerAccionesServicio(int $tenantId, int $servicioId): array
    {
        $result = $this->api->get('inv_servicios_acciones', [
            'select' => 'accion',
            'tenant_id' => 'eq.' . $tenantId,
            'servicio_id' => 'eq.' . $servicioId,
            'order' => 'orden.asc',
        ], $this->userToken());
        return array_column($result['data'] ?? [], 'accion');
    }
}
