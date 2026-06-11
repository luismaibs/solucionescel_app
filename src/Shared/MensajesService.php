<?php

/**
 * MensajesService (Supabase API)
 *
 * Encapsula lógica de plantillas y envío de notificaciones via n8n.
 */
class MensajesService
{
    private SupabaseClient $api;
    private string $webhookN8n;
    private ?EventoTimelineService $timelineService;

    public function __construct(SupabaseClient $api, string $webhookN8n, ?EventoTimelineService $timelineService = null)
    {
        $this->api = $api;
        $this->webhookN8n = $webhookN8n;
        $this->timelineService = $timelineService;
    }

    public function obtenerMensajeProcesado(string $tipo, array $datos): string
    {
        if ($tipo === 'mensaje_personalizado' && isset($datos['mensaje_texto'])) {
            return $datos['mensaje_texto'];
        }
        if ($tipo === 'reactivar_inactivo' || $tipo === 'reactivar_entregado') {
            return 'Proceso de revision tecnica';
        }

        // Si es un cambio de estado, usar plantilla de estados_config
        if (str_starts_with($tipo, 'estado:')) {
            $slug = substr($tipo, 7);
            // Si enviarNotificacion ya pre-cargó la plantilla, usarla directamente
            $content = $datos['plantilla_contenido'] ?? '';
            if ($content === '') {
                $estadoInfo = $this->getEstadoInfoCompleto($slug);
                $content = $estadoInfo['plantilla_contenido'];
            }
            if ($content !== '') {
                return $this->reemplazarVariables($content, $datos);
            }
            $nombre = $datos['estado_nombre'] ?? $slug;
            return "Tu equipo ha cambiado de estado: {$nombre}";
        }

        // Para tipos no-estado: intentar estados_config primero (ej: en_taller, iniciar_garantia)
        $estadoInfo = $this->getEstadoInfoCompleto($tipo);
        if ($estadoInfo['plantilla_contenido'] !== '') {
            return $this->reemplazarVariables($estadoInfo['plantilla_contenido'], $datos);
        }

        // Fallback legacy: configuracion_mensajes
        $tenantId = TenantContext::getTenantIdOrDefault();
        $result = $this->api->get('configuracion_mensajes', [
            'select' => 'plantilla',
            'tenant_id' => 'eq.' . $tenantId,
            'clave' => 'eq.' . $tipo,
            'limit' => '1',
        ]);

        $plantilla = '';
        if ($result['ok'] && !empty($result['data'])) {
            $plantilla = $result['data'][0]['plantilla'] ?? '';
        }

        if ($plantilla === '') {
            return "Notificacion: " . str_replace('_', ' ', $tipo);
        }

        return $this->reemplazarVariables($plantilla, $datos);
    }

    /**
     * Obtiene nombre, color y plantilla completa de un estado por su slug.
     * Consulta estados_config → whatsapp_templates en una sola pasada.
     */
    private function getEstadoInfoCompleto(string $slug): array
    {
        $tenantId = TenantContext::getTenantIdOrDefault();
        $info = [
            'estado_slug'         => $slug,
            'estado_nombre'       => '',
            'estado_color'        => '',
            'plantilla_id'        => null,
            'plantilla_titulo'    => '',
            'plantilla_contenido' => '',
        ];

        $result = $this->api->get('estados_config', [
            'select'    => 'nombre,color,plantilla_id',
            'tenant_id' => 'eq.' . $tenantId,
            'slug'      => 'eq.' . $slug,
            'activo'    => 'eq.true',
            'limit'     => '1',
        ]);

        if (!$result['ok'] || empty($result['data'])) {
            return $info;
        }

        $row = $result['data'][0];
        $info['estado_nombre'] = $row['nombre'] ?? '';
        $info['estado_color']  = $row['color'] ?? '';
        $plantillaId           = $row['plantilla_id'] ?? null;

        if ($plantillaId) {
            $info['plantilla_id'] = (int) $plantillaId;
            $tplResult = $this->api->get('whatsapp_templates', [
                'select' => 'title,content',
                'id'     => 'eq.' . (int) $plantillaId,
                'limit'  => '1',
            ]);
            if ($tplResult['ok'] && !empty($tplResult['data'])) {
                $tpl = $tplResult['data'][0];
                $info['plantilla_titulo']    = $tpl['title'] ?? '';
                $info['plantilla_contenido'] = $tpl['content'] ?? '';
            }
        }

        return $info;
    }

    /**
     * Devuelve el nombre legible de un sub-estado (hijo) por slug.
     */
    private function getNombreSubEstado(string $slug): string
    {
        $tenantId = TenantContext::getTenantIdOrDefault();
        $result = $this->api->get('estados_config', [
            'select'    => 'nombre',
            'tenant_id' => 'eq.' . $tenantId,
            'slug'      => 'eq.' . $slug,
            'limit'     => '1',
        ]);
        if ($result['ok'] && !empty($result['data'])) {
            return $result['data'][0]['nombre'] ?? $slug;
        }
        return $slug;
    }

    private function reemplazarVariables(string $plantilla, array $datos): string
    {
        $reemplazos = [
            '{{cliente}}' => $datos['cliente'] ?? '',
            '{{folio}}' => $datos['folio'] ?? '',
            '{{modelo}}' => $datos['modelo'] ?? '',
            '{{falla}}' => $datos['falla'] ?? '',
            '{{fecha}}' => $datos['fecha_ingreso'] ?? '',
        ];

        return str_replace(array_keys($reemplazos), array_values($reemplazos), $plantilla);
    }

    public function enviarNotificacion(array $datos, int $reparacionId, ?int $clienteId = null): void
    {
        // ── 1. Enriquecer con info completa de estado y plantilla ──
        $tipo = $datos['tipo'] ?? '';
        if (str_starts_with($tipo, 'estado:')) {
            $estadoSlug = substr($tipo, 7);
            $estadoInfo = $this->getEstadoInfoCompleto($estadoSlug);
            // Merge: estadoInfo primero para que los campos explícitos de $datos los sobreescriban si existen
            $datos = array_merge($estadoInfo, $datos);
        }

        // ── 2. Enriquecer sub-estado ──
        $subestadoSlug = $datos['subestado_slug'] ?? ($datos['tipo_garantia'] ?? null);
        if ($subestadoSlug && $subestadoSlug !== '') {
            $datos['subestado_slug']   = $subestadoSlug;
            $datos['subestado_nombre'] = $this->getNombreSubEstado($subestadoSlug);
        }

        // ── 3. Asegurar IDs de referencia ──
        if (!isset($datos['reparacion_id'])) {
            $datos['reparacion_id'] = $reparacionId;
        }
        if (!isset($datos['cliente_id']) && $clienteId !== null) {
            $datos['cliente_id'] = $clienteId;
        }

        // ── 4. Generar mensaje procesado (reutiliza plantilla_contenido ya cargada) ──
        $contenidoLog = $this->obtenerMensajeProcesado($datos['tipo'], $datos);
        $datos['mensaje_generado'] = $contenidoLog;

        $tenantId = TenantContext::getTenantIdOrDefault();
        $userId = getCurrentUserId();

        $logId = null;
        try {
            $result = $this->api->post('historial_mensajes', [
                'tenant_id' => $tenantId,
                'reparacion_id' => $reparacionId,
                'tipo_mensaje' => $datos['tipo'],
                'contenido_mensaje' => $contenidoLog,
                'estado_envio' => 'pendiente',
                'user_id' => $userId,
            ]);
            if ($result['ok'] && !empty($result['data'])) {
                $logId = $result['data'][0]['id'] ?? null;
                if ($logId) $datos['log_id'] = $logId;
            }
        } catch (Exception $e) {
            error_log("Error historial mensajes: " . $e->getMessage());
        }

        // Enviar a n8n (fire-and-forget, 500ms timeout)
        if (!empty($this->webhookN8n)) {
            $ch = curl_init($this->webhookN8n);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
            curl_exec($ch);
            curl_close($ch);
        }

        // Registrar en timeline
        if ($this->timelineService && $clienteId) {
            try {
                $this->timelineService->registrar(
                    'mensaje_enviado',
                    'Mensaje enviado: ' . str_replace('_', ' ', $datos['tipo']),
                    mb_substr($contenidoLog, 0, 200),
                    $clienteId,
                    $reparacionId,
                    ['canal' => 'WhatsApp', 'tipo_mensaje' => $datos['tipo']],
                    $userId
                );
            } catch (Exception $e) {
                error_log("MensajesService timeline: " . $e->getMessage());
            }
        }
    }

    public function listarPlantillas(): array
    {
        $tenantId = TenantContext::getTenantIdOrDefault();
        $result = $this->api->get('configuracion_mensajes', [
            'select' => '*',
            'tenant_id' => 'eq.' . $tenantId,
            'order' => 'id.asc',
        ]);
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }
}
