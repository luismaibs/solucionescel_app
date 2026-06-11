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
            $total = (int) ($result['data']['total_count'] ?? 0);
            $rows = $result['data']['rows'] ?? [];
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
            $total = (int) ($result['data']['total_count'] ?? 0);
            $rows = $result['data']['rows'] ?? [];
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
            $total = (int) ($result['data']['total_count'] ?? 0);
            $rows = $result['data']['rows'] ?? [];
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
            $total = (int) ($result['data']['total_count'] ?? 0);
            $rows = $result['data']['rows'] ?? [];
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
