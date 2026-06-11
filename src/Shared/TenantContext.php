<?php

/**
 * TenantContext
 *
 * Almacena el tenant_id del taller actual para la petición.
 * Los Repositories deben usar requireTenant() antes de cualquier consulta
 * a tablas con datos por tenant. En web se setea tras login; en API/cron
 * puede inyectarse por header (X-Tenant-ID) o variable de entorno.
 */
class TenantContext
{
    /** @var int|null */
    private static $tenantId = null;

    public static function setTenantId(?int $id): void
    {
        self::$tenantId = $id;
    }

    public static function getTenantId(): ?int
    {
        return self::$tenantId;
    }

    public static function clear(): void
    {
        self::$tenantId = null;
    }

    /**
     * Devuelve el tenant actual o lanza si no está seteado.
     *
     * @throws RuntimeException si no hay tenant en contexto
     */
    public static function requireTenant(): int
    {
        $id = self::$tenantId;
        if ($id === null || $id < 1) {
            throw new RuntimeException('TenantContext: no hay tenant_id en contexto. Asegúrese de estar logueado o de enviar X-Tenant-ID en la petición.');
        }
        return $id;
    }

    /**
     * Devuelve el tenant actual o un fallback configurable.
     * Centraliza la lógica repetida de getenv('TENANT_ID_DEFAULT') ?: 1.
     */
    public static function getTenantIdOrDefault(): int
    {
        $id = self::$tenantId;
        if ($id === null || $id < 1) {
            $id = (int) (getenv('TENANT_ID_DEFAULT') ?: 1);
        }
        return $id;
    }
}
