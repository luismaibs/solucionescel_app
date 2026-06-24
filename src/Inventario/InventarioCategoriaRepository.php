<?php

/**
 * InventarioCategoriaRepository (Supabase API)
 */
class InventarioCategoriaRepository
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
        'servicios'  => 'inv_servicios_generales',
        'baterias'   => 'inv_baterias',
        'pantallas'  => 'inv_pantallas',
        'accesorios' => 'inv_accesorios',
    ];

    /* ─── LISTADOS PAGINADOS ─── */

    public function findServiciosPaginado(int $offset, int $limit, ?int &$total = null): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_servicios_paginado', [
            'p_tenant_id' => $tid, 'p_offset' => $offset, 'p_limit' => $limit,
        ], $this->userToken());
        if ($result['ok'] && is_array($result['data'])) {
            $row = isset($result['data'][0]) && is_array($result['data'][0]) ? $result['data'][0] : $result['data'];
            $total = (int) ($row['total_count'] ?? 0);
            $rows = $row['rows'] ?? [];
            return is_string($rows) ? json_decode($rows, true) ?? [] : (is_array($rows) ? $rows : []);
        }
        $total = 0;
        return [];
    }

    public function findBateriasPaginado(int $offset, int $limit, ?int &$total = null): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_baterias_paginado', [
            'p_tenant_id' => $tid, 'p_offset' => $offset, 'p_limit' => $limit,
        ], $this->userToken());
        if ($result['ok'] && is_array($result['data'])) {
            $row = isset($result['data'][0]) && is_array($result['data'][0]) ? $result['data'][0] : $result['data'];
            $total = (int) ($row['total_count'] ?? 0);
            $rows = $row['rows'] ?? [];
            return is_string($rows) ? json_decode($rows, true) ?? [] : (is_array($rows) ? $rows : []);
        }
        $total = 0;
        return [];
    }

    public function findPantallasPaginado(int $offset, int $limit, ?int &$total = null): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_pantallas_paginado', [
            'p_tenant_id' => $tid, 'p_offset' => $offset, 'p_limit' => $limit,
        ], $this->userToken());
        if ($result['ok'] && is_array($result['data'])) {
            $row = isset($result['data'][0]) && is_array($result['data'][0]) ? $result['data'][0] : $result['data'];
            $total = (int) ($row['total_count'] ?? 0);
            $rows = $row['rows'] ?? [];
            return is_string($rows) ? json_decode($rows, true) ?? [] : (is_array($rows) ? $rows : []);
        }
        $total = 0;
        return [];
    }

    public function findAccesoriosPaginado(int $offset, int $limit, ?int &$total = null): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_accesorios_paginado', [
            'p_tenant_id' => $tid, 'p_offset' => $offset, 'p_limit' => $limit,
        ], $this->userToken());
        if ($result['ok'] && is_array($result['data'])) {
            $row = isset($result['data'][0]) && is_array($result['data'][0]) ? $result['data'][0] : $result['data'];
            $total = (int) ($row['total_count'] ?? 0);
            $rows = $row['rows'] ?? [];
            return is_string($rows) ? json_decode($rows, true) ?? [] : (is_array($rows) ? $rows : []);
        }
        $total = 0;
        return [];
    }

    /* ─── SOFT DELETE ─── */

    public function softDeleteByCategoria(string $categoria, int $id): bool
    {
        if (!isset(self::TABLE_MAP[$categoria])) {
            throw new InvalidArgumentException("Categoria no valida: $categoria");
        }
        $tid = TenantContext::requireTenant();
        $table = self::TABLE_MAP[$categoria];
        $result = $this->api->patch($table, ['deleted_at' => date('c')], [
            'tenant_id' => 'eq.' . $tid, 'id' => 'eq.' . $id, 'deleted_at' => 'is.null',
        ], $this->userToken());
        return $result['ok'];
    }

    /* ─── UPDATE DE CAMPO (edición inline tipo hoja de cálculo) ─── */

    /**
     * Campos editables directamente por categoría → tipo de dato.
     * Los campos FK (marca_id, color_id, modelo_id…) y los derivados de joins
     * (marca_nombre…) NO se incluyen: requieren resolución de catálogo aparte.
     */
    private const EDITABLE_FIELDS = [
        'accesorios' => [
            'codigo' => 'string', 'nombre_producto' => 'string',
            'stock' => 'int', 'precio' => 'float',
        ],
        'baterias' => [
            'marca' => 'string', 'modelo_bateria' => 'string', 'codigo' => 'string',
            'calidad' => 'string', 'tipo' => 'string', 'tiempo' => 'string',
            'notas' => 'string', 'stock' => 'int', 'precio' => 'float',
        ],
        'pantallas' => [
            'calidad' => 'string', 'tiempo' => 'string', 'nota' => 'string',
            'precio' => 'float',
        ],
        'servicios' => [
            'subcategoria' => 'string', 'gama' => 'string', 'garantia' => 'string',
            'sistemas_operativos' => 'string', 'tiempo_entrega' => 'string',
            'nota' => 'string', 'precio' => 'float',
        ],
    ];

    public function camposEditables(string $categoria): array
    {
        return array_keys(self::EDITABLE_FIELDS[$categoria] ?? []);
    }

    /**
     * Actualiza una sola celda. Devuelve el valor normalizado guardado.
     * @throws InvalidArgumentException si la categoría o el campo no son válidos.
     */
    public function updateCampo(string $categoria, int $id, string $campo, $valor)
    {
        if (!isset(self::TABLE_MAP[$categoria])) {
            throw new InvalidArgumentException("Categoría no válida.");
        }
        $fields = self::EDITABLE_FIELDS[$categoria] ?? [];
        if (!isset($fields[$campo])) {
            throw new InvalidArgumentException("El campo '$campo' no es editable.");
        }

        // Normalizar según tipo
        switch ($fields[$campo]) {
            case 'int':
                $valor = max(0, (int) $valor);
                break;
            case 'float':
                $valor = round(max(0, (float) $valor), 2);
                break;
            default:
                $valor = trim((string) $valor);
                if ($valor === '') $valor = null;
                break;
        }

        $tid   = TenantContext::requireTenant();
        $table = self::TABLE_MAP[$categoria];
        $result = $this->api->patch($table, [$campo => $valor], [
            'tenant_id' => 'eq.' . $tid, 'id' => 'eq.' . $id, 'deleted_at' => 'is.null',
        ], $this->userToken());

        if (!$result['ok']) {
            throw new RuntimeException('No se pudo guardar el cambio.');
        }
        return $valor;
    }

    /* ─── KPIS ─── */

    public function getKpisByCategoria(string $categoria): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_kpis_categoria', [
            'p_tenant_id' => $tid, 'p_categoria' => $categoria,
        ], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            $data = $result['data'];
            if (is_string($data)) $data = json_decode($data, true);
            return is_array($data) ? $data : $this->defaultKpis();
        }
        return $this->defaultKpis();
    }

    private function defaultKpis(): array
    {
        return [
            ['label' => 'Total', 'value' => 0, 'icon' => 'bi-box', 'color' => 'primary'],
            ['label' => 'Precio Min', 'value' => '$0', 'icon' => 'bi-cash', 'color' => 'success'],
            ['label' => 'Precio Max', 'value' => '$0', 'icon' => 'bi-cash-stack', 'color' => 'info'],
        ];
    }
}
