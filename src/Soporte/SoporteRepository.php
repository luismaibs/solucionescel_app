<?php

/**
 * SoporteRepository (Supabase API)
 */
class SoporteRepository
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

    public function countConversacionesPausadas(): int
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('bot_conversaciones', [
            'select' => 'id',
            'tenant_id' => 'eq.' . $tid,
            'estado' => 'eq.pausado',
            'deleted_at' => 'is.null',
        ], $this->userToken());
        return $result['ok'] ? count($result['data'] ?? []) : 0;
    }

    public function countConversacionesReactivadasHoy(): int
    {
        $tid = TenantContext::requireTenant();
        $hoy = date('Y-m-d');
        $result = $this->api->get('bot_conversaciones', [
            'select' => 'id',
            'tenant_id' => 'eq.' . $tid,
            'estado' => 'eq.activo',
            'fecha_reactivacion' => "gte.{$hoy}",
            'deleted_at' => 'is.null',
        ], $this->userToken());
        return $result['ok'] ? count($result['data'] ?? []) : 0;
    }

    public function findConversacionPausadaExistente(string $remoteJid): ?array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('bot_conversaciones', [
            'select' => 'id,remote_jid,nombre_cliente,telefono,estado',
            'tenant_id' => 'eq.' . $tid,
            'remote_jid' => 'eq.' . $remoteJid,
            'estado' => 'eq.pausado',
            'deleted_at' => 'is.null',
            'limit' => '1',
        ], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            return $result['data'][0];
        }
        return null;
    }

    public function findConversacionesParaApi(int $limit = 50): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_conversaciones_api', [
            'p_tenant_id' => $tid,
            'p_limit' => $limit,
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    public function insertConversacionPausada(
        string $remoteJid, string $nombreCliente, string $telefono, string $mensaje
    ): int {
        $tid = TenantContext::requireTenant();
        $result = $this->api->post('bot_conversaciones', [
            'tenant_id' => $tid,
            'remote_jid' => $remoteJid,
            'nombre_cliente' => $nombreCliente,
            'telefono' => $telefono,
            'mensaje' => $mensaje,
            'estado' => 'pausado',
            'fecha_pausa' => date('c'),
        ], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            return (int) $result['data'][0]['id'];
        }
        throw new RuntimeException('Error al insertar conversacion: ' . ($result['error'] ?? 'desconocido'));
    }

    public function actualizarFechaPausa(string $remoteJid): void
    {
        $tid = TenantContext::requireTenant();
        $this->api->patch('bot_conversaciones', [
            'fecha_pausa' => date('c'),
        ], [
            'tenant_id' => 'eq.' . $tid,
            'remote_jid' => 'eq.' . $remoteJid,
            'deleted_at' => 'is.null',
        ], $this->userToken());
    }

    public function marcarConversacionReactivada(int $convId, string $remoteJid): void
    {
        $tid = TenantContext::requireTenant();
        $this->api->patch('bot_conversaciones', [
            'estado' => 'activo',
            'fecha_reactivacion' => date('c'),
        ], [
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $convId,
            'remote_jid' => 'eq.' . $remoteJid,
            'estado' => 'eq.pausado',
            'deleted_at' => 'is.null',
        ], $this->userToken());
    }

    public function insertNotificacionSistema(string $titulo, string $mensaje, string $tipo = 'warning'): void
    {
        $tid = TenantContext::requireTenant();
        $this->api->post('notificaciones_sistema', [
            'tenant_id' => $tid,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'tipo' => $tipo,
        ], $this->userToken());
    }

    public function countTotalConversaciones(): int
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('bot_conversaciones', [
            'select' => 'id',
            'tenant_id' => 'eq.' . $tid,
            'deleted_at' => 'is.null',
        ], $this->userToken());
        return $result['ok'] ? count($result['data'] ?? []) : 0;
    }

    public function countSoporteTotal(): int
    {
        return $this->countTotalConversaciones();
    }

    public function countSoportePendiente(): int
    {
        return $this->countConversacionesPausadas();
    }

    public function findTrendSoporte(int $dias = 7): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_trend_soporte', [
            'p_tenant_id' => $tid,
            'p_dias' => $dias,
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    public function findConversacionesPausadasParaNotificaciones(int $limit = 10): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('bot_conversaciones', [
            'select' => 'id,nombre_cliente,telefono,mensaje,fecha_pausa',
            'tenant_id' => 'eq.' . $tid,
            'estado' => 'eq.pausado',
            'deleted_at' => 'is.null',
            'order' => 'fecha_pausa.desc',
            'limit' => (string) $limit,
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    // ─── BOT MENSAJES ───

    public function insertMensaje(int $conversacionId, string $contenido, string $direccion = 'in'): int
    {
        $tid = TenantContext::requireTenant();
        $dir = ($direccion === 'out') ? 'out' : 'in';
        $result = $this->api->post('bot_mensajes', [
            'tenant_id' => $tid,
            'conversacion_id' => $conversacionId,
            'contenido' => $contenido,
            'direccion' => $dir,
        ], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            return (int) $result['data'][0]['id'];
        }
        throw new RuntimeException('Error al insertar mensaje: ' . ($result['error'] ?? 'desconocido'));
    }

    public function findMensajesByConversacion(int $conversacionId): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('bot_mensajes', [
            'select' => 'id,contenido,direccion,created_at',
            'tenant_id' => 'eq.' . $tid,
            'conversacion_id' => 'eq.' . $conversacionId,
            'order' => 'created_at.asc',
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }
}
