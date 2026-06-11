<?php

/**
 * UsuarioRepository (Supabase API)
 */
class UsuarioRepository
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

    // ─── USUARIOS ───

    public function insertUsuario(
        string $username,
        string $authUserId,
        string $nombreCompleto,
        string $rol,
        ?int $createdByUserId,
        ?array $modulos = null
    ): int {
        $modulos = $modulos ?? UsuarioService::DEFAULT_MODULOS;
        $tid = TenantContext::requireTenant();
        $result = $this->api->post('usuarios', [
            'tenant_id'          => $tid,
            'username'           => $username,
            'auth_user_id'       => $authUserId,
            'nombre_completo'    => $nombreCompleto,
            'rol'                => $rol,
            'created_by_user_id' => $createdByUserId,
            'modulos_permitidos' => $modulos,
        ], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            return (int) $result['data'][0]['id'];
        }
        throw new RuntimeException('Error al insertar usuario: ' . ($result['error'] ?? 'desconocido'));
    }

    public function findUsuarioByUsername(string $username): ?array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('usuarios', [
            'select'     => '*',
            'tenant_id'  => 'eq.' . $tid,
            'username'   => 'eq.' . $username,
            'deleted_at' => 'is.null',
            'limit'      => '1',
        ], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            return $result['data'][0];
        }
        return null;
    }

    public function findByAuthUserId(string $authUserId): ?array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('usuarios', [
            'select'     => 'id,username,nombre_completo,rol,modulos_permitidos,tenant_id',
            'tenant_id'  => 'eq.' . $tid,
            'auth_user_id' => 'eq.' . $authUserId,
            'deleted_at' => 'is.null',
            'limit'      => '1',
        ], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            return $result['data'][0];
        }
        return null;
    }

    public function softDeleteUsuario(int $id): void
    {
        $tid = TenantContext::requireTenant();
        $this->api->patch('usuarios', ['deleted_at' => date('c')], [
            'tenant_id' => 'eq.' . $tid,
            'id'        => 'eq.' . $id,
        ], $this->userToken());
    }

    public function findUsuarioById(int $id): ?array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('usuarios', [
            'select'     => '*',
            'tenant_id'  => 'eq.' . $tid,
            'id'         => 'eq.' . $id,
            'deleted_at' => 'is.null',
            'limit'      => '1',
        ], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            return $result['data'][0];
        }
        return null;
    }

    public function updateUsuario(
        int $id,
        string $username,
        string $nombreCompleto,
        string $rol,
        array $modulos
    ): void {
        $tid = TenantContext::requireTenant();
        $this->api->patch('usuarios', [
            'username'           => $username,
            'nombre_completo'    => $nombreCompleto,
            'rol'                => $rol,
            'modulos_permitidos' => $modulos,
        ], [
            'tenant_id' => 'eq.' . $tid,
            'id'        => 'eq.' . $id,
        ], $this->userToken());
    }

    public function findAllUsuarios(): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('usuarios', [
            'select'     => 'id,username,auth_user_id,nombre_completo,rol,modulos_permitidos,created_at,created_by_user_id',
            'tenant_id'  => 'eq.' . $tid,
            'deleted_at' => 'is.null',
            'order'      => 'created_at.desc',
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    /**
     * Crea una fila en `usuarios` para un auth user de Supabase que no tiene perfil aún.
     * Devuelve la fila creada (o la existente si ya existe).
     */
    public function upsertFromAuthUser(array $authUser, int $tenantId, ?array $modulos = null): array
    {
        $modulos = $modulos ?? UsuarioService::DEFAULT_MODULOS;
        $authUserId = $authUser['id'] ?? '';
        if (!$authUserId) {
            throw new RuntimeException('Auth user sin ID');
        }

        $check = $this->api->get('usuarios', [
            'select'       => 'id,username,nombre_completo,rol,modulos_permitidos',
            'tenant_id'    => 'eq.' . $tenantId,
            'auth_user_id' => 'eq.' . $authUserId,
            'deleted_at'   => 'is.null',
            'limit'        => '1',
        ]);
        if ($check['ok'] && !empty($check['data'])) {
            return $check['data'][0];
        }

        $email    = $authUser['email'] ?? '';
        $username = strstr($email, '@', true) ?: $authUserId;
        $nombre   = $authUser['user_metadata']['full_name'] ?? $username;
        $rol      = $authUser['app_metadata']['rol'] ?? 'usuario';

        $created = $this->api->post('usuarios', [
            'tenant_id'          => $tenantId,
            'username'           => $username,
            'auth_user_id'       => $authUserId,
            'nombre_completo'    => $nombre,
            'rol'                => $rol,
            'modulos_permitidos' => $modulos,
        ]);

        if ($created['ok'] && !empty($created['data'])) {
            return $created['data'][0];
        }

        return [
            'id'                 => null,
            'username'           => $username,
            'auth_user_id'       => $authUserId,
            'nombre_completo'    => $nombre,
            'rol'                => $rol,
            'modulos_permitidos' => $modulos,
            'created_at'         => null,
        ];
    }

    // ─── ROLES ───

    public function findRolBySlug(string $slug): ?array
    {
        $result = $this->api->get('roles', [
            'select' => 'id,nombre,slug',
            'slug'   => 'eq.' . $slug,
            'limit'  => '1',
        ], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            return $result['data'][0];
        }
        return null;
    }

    public function insertRol(string $nombre, string $slug): int
    {
        $result = $this->api->post('roles', ['nombre' => $nombre, 'slug' => $slug], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            return (int) $result['data'][0]['id'];
        }
        throw new RuntimeException('Error al insertar rol: ' . ($result['error'] ?? 'desconocido'));
    }

    public function findAllRoles(): array
    {
        $result = $this->api->get('roles', [
            'select' => 'id,nombre,slug',
            'order'  => 'nombre.asc',
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    // ─── LOGS ───

    public function findUltimosLogs(int $limit = 100): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('sesiones_log', [
            'select'    => '*',
            'tenant_id' => 'eq.' . $tid,
            'order'     => 'created_at.desc',
            'limit'     => (string) $limit,
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    // ─── AUDITORIA ───

    public function findUltimaAuditoria(int $limit = 100): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_auditoria_reciente', [
            'p_tenant_id' => $tid,
            'p_limit'     => $limit,
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }
}
