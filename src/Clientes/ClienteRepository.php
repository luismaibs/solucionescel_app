<?php

/**
 * ClienteRepository (Supabase API)
 */
class ClienteRepository
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

    public function findPaginated(int $offset, int $limit, string $search = '', ?int &$total = null): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_clientes_paginated', [
            'p_tenant_id' => $tid,
            'p_offset' => $offset,
            'p_limit' => $limit,
            'p_search' => $search,
        ], $this->userToken());

        if ($result['ok'] && is_array($result['data'])) {
            $total = (int) ($result['data']['total_count'] ?? 0);
            $rows = $result['data']['rows'] ?? '[]';
            return json_decode(is_string($rows) ? $rows : '[]', true) ?? [];
        }

        $total = 0;
        return [];
    }

    private function findOneBy(array $filters): ?array
    {
        $tid = TenantContext::requireTenant();
        $query = array_merge([
            'select' => '*',
            'tenant_id' => 'eq.' . $tid,
            'deleted_at' => 'is.null',
            'limit' => '1',
        ], $filters);
        $result = $this->api->get('clientes', $query, $this->userToken());
        return ($result['ok'] && !empty($result['data'])) ? $result['data'][0] : null;
    }

    public function findById(int $id): ?array
    {
        return $this->findOneBy(['id' => 'eq.' . $id]);
    }

    public function findByTelefono(string $telefono): ?array
    {
        return $this->findOneBy(['telefono' => 'eq.' . $telefono]);
    }

    public function search(string $query, int $limit = 10): array
    {
        $tid = TenantContext::requireTenant();
        $like = '%' . $query . '%';
        // PostgREST usa ilike para LIKE case-insensitive con % wildcards
        $result = $this->api->get('clientes', [
            'select' => 'id,nombre,apellido,telefono,correo',
            'tenant_id' => 'eq.' . $tid,
            'deleted_at' => 'is.null',
            'or' => '(nombre.ilike.' . $like . ',apellido.ilike.' . $like . ',telefono.ilike.' . $like . ')',
            'order' => 'nombre.asc',
            'limit' => (string) $limit,
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    public function insert(string $nombre, string $apellido, string $telefono, ?string $correo, ?int $createdByUserId): int
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->post('clientes', [
            'tenant_id' => $tid,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'telefono' => $telefono,
            'correo' => $correo,
            'created_by_user_id' => $createdByUserId,
        ], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            return (int) $result['data'][0]['id'];
        }
        throw new RuntimeException('Error al insertar cliente: ' . ($result['error'] ?? 'desconocido'));
    }

    public function update(int $id, string $nombre, string $apellido, string $telefono, ?string $correo): void
    {
        $tid = TenantContext::requireTenant();
        $this->api->patch('clientes', [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'telefono' => $telefono,
            'correo' => $correo,
        ], [
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $id,
        ], $this->userToken());
    }

    public function softDelete(int $id): void
    {
        $tid = TenantContext::requireTenant();
        $this->api->patch('clientes', ['deleted_at' => date('c')], [
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $id,
        ], $this->userToken());
    }

    public function getEstadisticas(int $clienteId): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_cliente_estadisticas', [
            'p_tenant_id' => $tid,
            'p_cliente_id' => $clienteId,
        ], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            return $result['data'][0];
        }
        return ['total_equipos' => 0, 'completadas' => 0, 'en_proceso' => 0, 'con_garantia' => 0];
    }

    public function getEquipos(int $clienteId): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_cliente_equipos', [
            'p_tenant_id' => $tid,
            'p_cliente_id' => $clienteId,
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    public function telefonoExiste(string $telefono, ?int $excludeId = null): bool
    {
        $tid = TenantContext::requireTenant();
        $query = [
            'select' => 'id',
            'tenant_id' => 'eq.' . $tid,
            'telefono' => 'eq.' . $telefono,
            'deleted_at' => 'is.null',
            'limit' => '1',
        ];
        if ($excludeId !== null) {
            $query['id'] = 'neq.' . $excludeId;
        }
        $result = $this->api->get('clientes', $query, $this->userToken());
        return $result['ok'] && !empty($result['data']);
    }
}
