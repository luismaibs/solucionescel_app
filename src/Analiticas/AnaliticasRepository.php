<?php

/**
 * AnaliticasRepository (Supabase API)
 *
 * Queries simples → PostgREST directo
 * Queries agregadas con JOINs → RPC
 */
class AnaliticasRepository
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

    private function count(string $table, array $filters): int
    {
        $tid = TenantContext::requireTenant();
        $query = ['select' => 'id', 'limit' => '10000'] + $filters + ['tenant_id' => 'eq.' . $tid];
        $result = $this->api->get($table, $query, $this->userToken());
        return $result['ok'] ? count($result['data'] ?? []) : 0;
    }

    public function countReparacionesTotales(): int
    {
        return $this->count('reparaciones', ['deleted_at' => 'is.null']);
    }

    public function countReparacionesActivas(): int
    {
        return $this->count('reparaciones', [
            'deleted_at' => 'is.null',
            'estado' => 'neq.entregado',
        ]);
    }

    public function countReparacionesViejas(int $dias = 90): int
    {
        $tid = TenantContext::requireTenant();
        $fecha = date('c', strtotime("-{$dias} days"));
        $result = $this->api->get('reparaciones', [
            'select' => 'id',
            'tenant_id' => 'eq.' . $tid,
            'deleted_at' => 'is.null',
            'estado' => 'neq.entregado',
            'fecha_ingreso' => 'lt.' . $fecha,
        ], $this->userToken());
        return $result['ok'] ? count($result['data'] ?? []) : 0;
    }

    public function countExitoYFallidos(): array
    {
        $exito = $this->count('reparaciones', [
            'deleted_at' => 'is.null',
            'or' => '(estado.eq.listo,estado.eq.entregado)',
        ]);
        $fallidos = $this->count('reparaciones', [
            'deleted_at' => 'is.null',
            'estado' => 'eq.no_quedo',
        ]);
        return ['exito' => $exito, 'fallidos' => $fallidos];
    }

    public function findTopMarcas(int $limit = 6): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_top_marcas', [
            'p_tenant_id' => $tid,
            'p_limit' => $limit,
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    public function findTopModelos(int $limit = 5): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_top_modelos', [
            'p_tenant_id' => $tid,
            'p_limit' => $limit,
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    public function findTendenciaMensual(int $meses = 6): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_tendencia_mensual', [
            'p_tenant_id' => $tid,
            'p_meses' => $meses,
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    /**
     * Stats de inventario (RPC).
     */
    public function getInventarioStats(): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_inventario_stats', ['p_tenant_id' => $tid], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            $data = $result['data'][0] ?? $result['data'];
            if (is_string($data)) $data = json_decode($data, true);
            return is_array($data) ? $data : ['valor_total' => 0, 'items_totales' => 0];
        }
        return ['valor_total' => 0, 'items_totales' => 0];
    }

    public function getDistribucionPorCategoria(): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_inventario_distribucion_categoria', [
            'p_tenant_id' => $tid,
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    public function getDistribucionPorSubcategoria(int $limit = 8): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_inventario_distribucion_subcat', [
            'p_tenant_id' => $tid,
            'p_limit' => $limit,
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    /**
     * KPIs del login (RPC).
     */
    public function getKpisYGraficoLogin(): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_kpis_login', ['p_tenant_id' => $tid], $this->userToken());

        if ($result['ok'] && !empty($result['data'])) {
            $data = $result['data'];
            if (is_string($data)) $data = json_decode($data, true);
            if (is_array($data)) {
                $chartEstados = $data['chart_estados'] ?? [];
                $labels = array_column($chartEstados, 'estado');
                $values = array_column($chartEstados, 'total');
                return [
                    'chart_estados_labels' => $labels,
                    'chart_estados_data' => $values,
                    'kpi_total' => (int) ($data['kpi_total'] ?? 0),
                    'kpi_en_taller' => (int) ($data['kpi_en_taller'] ?? 0),
                    'kpi_listos' => (int) ($data['kpi_listos'] ?? 0),
                ];
            }
        }

        return [
            'chart_estados_labels' => [],
            'chart_estados_data' => [],
            'kpi_total' => 0,
            'kpi_en_taller' => 0,
            'kpi_listos' => 0,
        ];
    }
}
