<?php

/**
 * ServiciosGeneralesRepository (Supabase API)
 */
class ServiciosGeneralesRepository
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

    public function insertServicio(
        string $subcategoria, string $gama, string $sistemasOperativos,
        string $garantia, ?string $tiempoEntrega, float $precio, ?string $nota
    ): int {
        $tid = TenantContext::requireTenant();
        $result = $this->api->post('inv_servicios_generales', [
            'tenant_id' => $tid, 'subcategoria' => $subcategoria, 'gama' => $gama,
            'sistemas_operativos' => $sistemasOperativos, 'garantia' => $garantia,
            'tiempo_entrega' => $tiempoEntrega, 'precio' => $precio, 'nota' => $nota,
        ], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) return (int) $result['data'][0]['id'];
        throw new RuntimeException('Error al insertar servicio: ' . ($result['error'] ?? 'desconocido'));
    }

    public function insertAcciones(int $servicioId, array $acciones): void
    {
        if (empty($acciones)) return;
        $tid = TenantContext::requireTenant();
        foreach ($acciones as $i => $accion) {
            $this->api->post('inv_servicios_acciones', [
                'tenant_id' => $tid, 'servicio_id' => $servicioId,
                'accion' => trim($accion), 'orden' => $i,
            ], $this->userToken());
        }
    }

    public function findAccionesByServicio(int $servicioId): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('inv_servicios_acciones', [
            'select' => '*',
            'tenant_id' => 'eq.' . $tid,
            'servicio_id' => 'eq.' . $servicioId,
            'deleted_at' => 'is.null',
            'order' => 'orden.asc',
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    public function findById(int $id): ?array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('inv_servicios_generales', [
            'select' => '*', 'tenant_id' => 'eq.' . $tid, 'id' => 'eq.' . $id,
            'deleted_at' => 'is.null', 'limit' => '1',
        ], $this->userToken());
        return ($result['ok'] && !empty($result['data'])) ? $result['data'][0] : null;
    }

    public function softDelete(int $id): void
    {
        $tid = TenantContext::requireTenant();
        $this->api->patch('inv_servicios_generales', ['deleted_at' => date('c')], [
            'tenant_id' => 'eq.' . $tid, 'id' => 'eq.' . $id,
        ], $this->userToken());
    }
}
