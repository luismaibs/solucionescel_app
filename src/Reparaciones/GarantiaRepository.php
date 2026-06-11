<?php

/**
 * GarantiaRepository (Supabase API)
 */
class GarantiaRepository
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

    public function findByReparacionId(int $reparacionId): ?array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('reparacion_garantias', [
            'select' => '*',
            'tenant_id' => 'eq.' . $tid,
            'reparacion_id' => 'eq.' . $reparacionId,
            'limit' => '1',
        ], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            return $result['data'][0];
        }
        return null;
    }

    public function upsertTipoGarantia(int $reparacionId, ?string $tipoGarantia): void
    {
        $tid = TenantContext::requireTenant();

        // Use service role for the lookup so RLS never hides an existing row
        $lookup = $this->api->get('reparacion_garantias', [
            'select'        => 'id',
            'tenant_id'     => 'eq.' . $tid,
            'reparacion_id' => 'eq.' . $reparacionId,
            'limit'         => '1',
        ], null, true);

        $existing = ($lookup['ok'] && !empty($lookup['data']));

        if ($existing) {
            $result = $this->api->patch('reparacion_garantias', [
                'tipo_garantia' => $tipoGarantia,
            ], [
                'tenant_id'     => 'eq.' . $tid,
                'reparacion_id' => 'eq.' . $reparacionId,
            ], null, true);
        } else {
            $result = $this->api->post('reparacion_garantias', [
                'reparacion_id' => $reparacionId,
                'tenant_id'     => $tid,
                'tipo_garantia' => $tipoGarantia,
            ], null, true);
        }

        if (!($result['ok'] ?? false)) {
            error_log('[GarantiaRepository] upsertTipoGarantia failed for reparacion ' . $reparacionId . ': ' . ($result['error'] ?? json_encode($result)));
        }
    }

    public function updateInicioGarantiaReactivado(int $reparacionId, int $value): void
    {
        $tid = TenantContext::requireTenant();
        $this->api->patch('reparacion_garantias', [
            'inicio_garantia_reactivado' => (bool) $value,
        ], [
            'tenant_id' => 'eq.' . $tid,
            'reparacion_id' => 'eq.' . $reparacionId,
        ], $this->userToken());
    }

    public function reactivarSinGarantia(int $reparacionId): void
    {
        $tid = TenantContext::requireTenant();
        // Actualizar reparacion
        $this->api->patch('reparaciones', ['estado' => 'en_taller'], [
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $reparacionId,
        ], $this->userToken());
        // Actualizar garantia
        $this->api->patch('reparacion_garantias', [
            'inicio_garantia_reactivado' => false,
        ], [
            'tenant_id' => 'eq.' . $tid,
            'reparacion_id' => 'eq.' . $reparacionId,
        ], $this->userToken());
    }

    public function iniciarGarantia(int $reparacionId): void
    {
        $tid = TenantContext::requireTenant();
        // Actualizar reparacion
        $this->api->patch('reparaciones', ['estado' => 'garantia_activada'], [
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $reparacionId,
        ], $this->userToken());
        // Upsert garantia (service role to bypass RLS on INSERT)
        $lookup = $this->api->get('reparacion_garantias', [
            'select'        => 'id',
            'tenant_id'     => 'eq.' . $tid,
            'reparacion_id' => 'eq.' . $reparacionId,
            'limit'         => '1',
        ], null, true);
        $existingG = ($lookup['ok'] && !empty($lookup['data']));
        if ($existingG) {
            $this->api->patch('reparacion_garantias', [
                'inicio_garantia_reactivado' => true,
            ], [
                'tenant_id'     => 'eq.' . $tid,
                'reparacion_id' => 'eq.' . $reparacionId,
            ], null, true);
        } else {
            $this->api->post('reparacion_garantias', [
                'reparacion_id' => $reparacionId,
                'tenant_id'     => $tid,
                'inicio_garantia_reactivado' => true,
            ], null, true);
        }
    }

    public function inactivar(int $reparacionId): void
    {
        $tid = TenantContext::requireTenant();
        $this->api->patch('reparaciones', ['estado' => 'inactivo'], [
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $reparacionId,
        ], $this->userToken());
        $this->api->patch('reparacion_garantias', [
            'inicio_garantia_reactivado' => false,
        ], [
            'tenant_id' => 'eq.' . $tid,
            'reparacion_id' => 'eq.' . $reparacionId,
        ], $this->userToken());
    }

    public function updateMesAzulInicioEnviado(int $reparacionId): void
    {
        $tid = TenantContext::requireTenant();
        $existing = $this->findByReparacionId($reparacionId);
        $now = date('c');

        if ($existing) {
            $this->api->patch('reparacion_garantias', [
                'mes_azul_inicio_enviado' => $now,
                'mes_azul_estado'        => 'esperando_final',
            ], [
                'tenant_id'     => 'eq.' . $tid,
                'reparacion_id' => 'eq.' . $reparacionId,
            ], null, true);
        } else {
            $this->api->post('reparacion_garantias', [
                'reparacion_id'          => $reparacionId,
                'tenant_id'              => $tid,
                'mes_azul_inicio_enviado'=> $now,
                'mes_azul_estado'        => 'esperando_final',
            ], null, true);
        }
    }

    public function updateMesAzulFinalEnviado(int $reparacionId): void
    {
        $tid = TenantContext::requireTenant();
        $this->api->rpc('rpc_mes_azul_finalizar', [
            'p_tenant_id' => $tid,
            'p_reparacion_id' => $reparacionId,
        ], $this->userToken());
    }

    public function resetMesAzul(int $reparacionId): void
    {
        $tid = TenantContext::requireTenant();
        $this->api->patch('reparacion_garantias', [
            'mes_azul_inicio_enviado' => null,
            'mes_azul_final_enviado' => null,
            'mes_azul_estado' => 'no_aplica',
            'mes_azul_fecha_inactivacion' => null,
        ], [
            'tenant_id' => 'eq.' . $tid,
            'reparacion_id' => 'eq.' . $reparacionId,
        ], $this->userToken());
    }
}
