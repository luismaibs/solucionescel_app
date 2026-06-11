<?php

/**
 * EventoTimelineService (Supabase API)
 */
class EventoTimelineService
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

    public function registrar(
        string $tipo, string $titulo, string $descripcion = '',
        ?int $clienteId = null, ?int $reparacionId = null,
        array $metadata = [], ?int $userId = null
    ): int {
        if ($userId === null) {
            $userId = getCurrentUserId();
        }
        $tid = TenantContext::requireTenant();

        $result = $this->api->post('eventos_timeline', [
            'tenant_id' => $tid,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'cliente_id' => $clienteId,
            'reparacion_id' => $reparacionId,
            'metadata' => !empty($metadata)
                ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            'user_id' => $userId,
        ], $this->userToken());

        if ($result['ok'] && !empty($result['data'])) {
            return (int) $result['data'][0]['id'];
        }
        return 0;
    }

    /**
     * Timeline de un cliente con JOIN a reparaciones (RPC).
     */
    public function getTimelineCliente(
        int $clienteId, int $offset = 0, int $limit = 50,
        ?int &$total = null, bool $incluirMensajes = true
    ): array {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_timeline_cliente', [
            'p_tenant_id' => $tid,
            'p_cliente_id' => $clienteId,
            'p_offset' => $offset,
            'p_limit' => $limit,
        ], $this->userToken());

        if ($result['ok'] && is_array($result['data'])) {
            $data = $result['data'];
            if (isset($data['total_count'])) {
                $total = (int) $data['total_count'];
                $rows = is_string($data['rows'] ?? null)
                    ? json_decode($data['rows'], true) ?? []
                    : ($data['rows'] ?? []);
            } else {
                $rows = $data;
                $total = count($rows);
            }
            if (!$incluirMensajes) {
                $rows = array_filter($rows, fn($r) => ($r['tipo'] ?? '') !== 'mensaje_enviado');
            }
            return array_values($rows);
        }

        $total = 0;
        return [];
    }

    public function getTimelineClienteSoloAcciones(
        int $clienteId, int $offset = 0, int $limit = 50, ?int &$total = null
    ): array {
        return $this->getTimelineCliente($clienteId, $offset, $limit, $total, false);
    }

    /**
     * Timeline de un equipo (RPC).
     */
    public function getTimelineEquipo(
        int $reparacionId, int $offset = 0, int $limit = 50, ?int &$total = null
    ): array {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_timeline_equipo', [
            'p_tenant_id' => $tid,
            'p_reparacion_id' => $reparacionId,
            'p_offset' => $offset,
            'p_limit' => $limit,
        ], $this->userToken());

        if ($result['ok'] && is_array($result['data'])) {
            $data = $result['data'];
            if (isset($data['total_count'])) {
                $total = (int) $data['total_count'];
                return is_string($data['rows'] ?? null)
                    ? json_decode($data['rows'], true) ?? []
                    : ($data['rows'] ?? []);
            }
            $total = count($data);
            return $data;
        }

        $total = 0;
        return [];
    }

    public static function getEventoConfig(string $tipo): array
    {
        $config = [
            'equipo_ingresado'    => ['icon' => 'bi-phone-fill',             'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.1)',  'label' => 'Equipo ingresado'],
            'cambio_estado'       => ['icon' => 'bi-arrow-repeat',           'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)',  'label' => 'Cambio de estado'],
            'mensaje_enviado'     => ['icon' => 'bi-chat-dots-fill',         'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.1)',  'label' => 'Mensaje enviado'],
            'garantia_activada'   => ['icon' => 'bi-shield-check',           'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.1)',  'label' => 'Proceso de revision tecnica'],
            'garantia_reactivada' => ['icon' => 'bi-arrow-clockwise',        'color' => '#06b6d4', 'bg' => 'rgba(6,182,212,0.1)',   'label' => 'Garantia reactivada'],
            'equipo_entregado'    => ['icon' => 'bi-box-seam-fill',          'color' => '#22c55e', 'bg' => 'rgba(34,197,94,0.1)',   'label' => 'Equipo entregado'],
            'equipo_editado'      => ['icon' => 'bi-pencil-square',          'color' => '#a78bfa', 'bg' => 'rgba(167,139,250,0.1)', 'label' => 'Equipo editado'],
            'cliente_creado'      => ['icon' => 'bi-person-plus-fill',       'color' => '#60a5fa', 'bg' => 'rgba(96,165,250,0.1)',  'label' => 'Cliente registrado'],
            'cliente_editado'     => ['icon' => 'bi-person-lines-fill',      'color' => '#94a3b8', 'bg' => 'rgba(148,163,184,0.1)', 'label' => 'Cliente editado'],
            'otro'                => ['icon' => 'bi-gear-fill',              'color' => '#64748b', 'bg' => 'rgba(100,116,139,0.1)', 'label' => 'Evento del sistema'],
        ];
        return $config[$tipo] ?? $config['otro'];
    }
}
