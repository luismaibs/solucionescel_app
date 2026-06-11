<?php

/**
 * ReparacionRepository (Supabase API)
 *
 * Capa de acceso a datos para reparaciones via Supabase PostgREST + RPC.
 * Queries simples (sin JOINs) → PostgREST directo
 * Queries complejas (con JOINs) → funciones RPC en PostgreSQL
 */
class ReparacionRepository
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

    // ═══════════════════════════════════════════════════════
    //  KPIs Y LISTADO
    // ═══════════════════════════════════════════════════════

    /**
     * Devuelve {activos, listos, taller, viejos} via RPC.
     */
    public function findKpis(): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_reparaciones_kpis', ['p_tenant_id' => $tid], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    /**
     * Reparaciones paginadas con total.
     */
    public function findForPanelPaginated(int $offset, int $limit, ?int &$total = null): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_reparaciones_panel_paginated', [
            'p_tenant_id' => $tid,
            'p_offset' => $offset,
            'p_limit' => $limit,
        ], $this->userToken());

        if ($result['ok'] && is_array($result['data'])) {
            $total = (int) ($result['data']['total_count'] ?? 0);
            $rows = [];
            if (is_string($result['data']['rows'] ?? null)) {
                $rows = json_decode($result['data']['rows'], true) ?? [];
            } elseif (is_array($result['data']['rows'] ?? null)) {
                $rows = $result['data']['rows'];
            }
            return $rows;
        }

        $total = 0;
        return [];
    }

    // ═══════════════════════════════════════════════════════
    //  CATÁLOGOS AUXILIARES
    // ═══════════════════════════════════════════════════════

    public function findDistinctMarcasModelos(): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_marcas_modelos_distinct', ['p_tenant_id' => $tid], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    public function findUsuariosParaIngreso(): array
    {
        try {
            $tid = TenantContext::requireTenant();
            $result = $this->api->get('usuarios', [
                'select' => 'id,username,nombre_completo',
                'tenant_id' => 'eq.' . $tid,
                'deleted_at' => 'is.null',
                'order' => 'nombre_completo.asc',
            ], $this->userToken());
            return $result['ok'] ? ($result['data'] ?? []) : [];
        } catch (Exception $e) {
            return [];
        }
    }

    public function getEquiposMarcas(): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('marcas', [
            'select' => 'id,nombre',
            'tenant_id' => 'eq.' . $tid,
            'order' => 'nombre.asc',
        ], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    public function findOrCreateEquipoMarcaId(string $nombre): int
    {
        $tid = TenantContext::requireTenant();
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre de marca no puede estar vacio.');
        }

        $result = $this->api->get('marcas', [
            'select' => 'id',
            'tenant_id' => 'eq.' . $tid,
            'nombre' => 'ilike.' . $nombre,
            'limit' => '1',
        ], $this->userToken());

        if ($result['ok'] && !empty($result['data'])) {
            return (int) $result['data'][0]['id'];
        }

        $result = $this->api->post('marcas', [
            'tenant_id' => $tid,
            'nombre' => $nombre,
        ], $this->userToken());

        if ($result['ok'] && !empty($result['data'])) {
            return (int) $result['data'][0]['id'];
        }

        throw new RuntimeException('No se pudo crear la marca: ' . ($result['error'] ?? 'desconocido'));
    }

    public function getEquipoMarcaNombre(int $equipoMarcaId): ?string
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('marcas', [
            'select' => 'nombre',
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $equipoMarcaId,
            'limit' => '1',
        ], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            return (string) $result['data'][0]['nombre'];
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════
    //  BUSQUEDAS POR ID / FOLIO
    // ═══════════════════════════════════════════════════════

    public function findById(int $id): ?array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_reparacion_by_id', [
            'p_tenant_id' => $tid,
            'p_id' => $id,
        ], $this->userToken());

        if ($result['ok'] && !empty($result['data'])) {
            $data = $result['data'];
            // Formato: array de filas [{...}]
            if (isset($data[0]) && is_array($data[0])) {
                return $data[0];
            }
            // Formato: rows como JSON string {"rows": "[{...}]"}
            if (isset($data['rows'])) {
                $rows = is_string($data['rows'])
                    ? (json_decode($data['rows'], true) ?? [])
                    : (is_array($data['rows']) ? $data['rows'] : []);
                return !empty($rows) ? $rows[0] : null;
            }
            // Formato: objeto asociativo directo
            return $data;
        }

        // Fallback: query directa si el RPC falla o no existe
        return $this->findByIdDirect($id);
    }

    private function findByIdDirect(int $id): ?array
    {
        $tid = TenantContext::requireTenant();
        $repResult = $this->api->get('reparaciones', [
            'select' => 'id,folio_publico,cliente_id,equipo_marca,equipo_modelo,falla_reportada,estado,fecha_ingreso',
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $id,
            'deleted_at' => 'is.null',
            'limit' => '1',
        ], $this->userToken());

        if (!$repResult['ok'] || empty($repResult['data'])) {
            return null;
        }

        $row = $repResult['data'][0] ?? null;
        if (!$row) {
            return null;
        }

        // Obtener datos del cliente para el payload de notificación
        if (!empty($row['cliente_id'])) {
            $cResult = $this->api->get('clientes', [
                'select' => 'nombre,apellido,telefono,correo',
                'tenant_id' => 'eq.' . $tid,
                'id' => 'eq.' . $row['cliente_id'],
                'deleted_at' => 'is.null',
                'limit' => '1',
            ], $this->userToken());

            if ($cResult['ok'] && !empty($cResult['data'])) {
                $c = $cResult['data'][0];
                $row['cliente_nombre']    = $c['nombre'] ?? '';
                $row['cliente_apellido']  = $c['apellido'] ?? '';
                $row['cliente_correo']    = $c['correo'] ?? '';
                $row['telefono']          = $c['telefono'] ?? '';
            }
        }

        return $row;
    }

    public function findByFolioActivo(string $folio): ?array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_reparacion_by_folio', [
            'p_tenant_id' => $tid,
            'p_folio' => trim($folio),
        ], $this->userToken());

        if ($result['ok'] && !empty($result['data'])) {
            return $result['data'][0] ?? $result['data'];
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════
    //  ESCRITURA
    // ═══════════════════════════════════════════════════════

    public function insertReparacion(
        string $folioTemporal,
        int $clienteId,
        string $marca,
        string $modelo,
        string $falla,
        string $fechaIngreso,
        ?int $createdByUserId,
        ?int $equipoMarcaId = null
    ): int {
        $tid = TenantContext::requireTenant();

        $data = [
            'tenant_id' => $tid,
            'folio_publico' => $folioTemporal,
            'cliente_id' => $clienteId,
            'equipo_marca' => $marca,
            'equipo_modelo' => $modelo,
            'falla_reportada' => $falla,
            'estado' => 'en_taller',
            'fecha_ingreso' => $fechaIngreso,
            'created_by_user_id' => $createdByUserId,
            'equipo_marca_id' => $equipoMarcaId,
        ];

        $result = $this->api->post('reparaciones', $data, $this->userToken());

        if ($result['ok'] && !empty($result['data'])) {
            return (int) $result['data'][0]['id'];
        }

        throw new RuntimeException('Error al crear reparacion: ' . ($result['error'] ?? 'desconocido'));
    }

    public function actualizarFolioPublico(int $id, string $folioPublico): void
    {
        $tid = TenantContext::requireTenant();
        $this->api->patch('reparaciones', ['folio_publico' => $folioPublico], [
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $id,
        ], $this->userToken());
    }

    public function getProximoFolio(): ?string
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_proximo_folio', ['p_tenant_id' => $tid], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            return (string) $result['data'];
        }
        return null;
    }

    public function updateReparacion(
        int $id,
        int $clienteId,
        string $marca,
        string $modelo,
        string $falla,
        ?int $equipoMarcaId = null
    ): void {
        $tid = TenantContext::requireTenant();
        $data = [
            'cliente_id' => $clienteId,
            'equipo_marca' => $marca,
            'equipo_modelo' => $modelo,
            'falla_reportada' => $falla,
            'equipo_marca_id' => $equipoMarcaId,
        ];
        $this->api->patch('reparaciones', $data, [
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $id,
        ], $this->userToken());
    }

    public function updateEstado(int $id, string $nuevoEstado): void
    {
        $tid = TenantContext::requireTenant();
        $this->api->patch('reparaciones', ['estado' => $nuevoEstado], [
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $id,
        ], $this->userToken());
    }

    public function softDelete(int $id): void
    {
        $tid = TenantContext::requireTenant();
        $this->api->patch('reparaciones', ['deleted_at' => date('c')], [
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $id,
        ], $this->userToken());
    }

    public function getEstadosSistema(): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('estados_config', [
            'select' => 'slug,nombre,color',
            'tenant_id' => 'eq.' . $tid,
            'parent_id' => 'is.null',
            'activo' => 'eq.true',
            'order' => 'orden.asc',
        ], $this->userToken());

        $estados = [];
        if ($result['ok']) {
            foreach ($result['data'] ?? [] as $row) {
                $estados[] = [
                    'slug' => $row['slug'],
                    'label' => $row['nombre'],
                    'color' => $row['color'] ?? '#94a3b8',
                ];
            }
        }
        return $estados;
    }

    public function getFolioPublicoById(int $id): ?string
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('reparaciones', [
            'select' => 'folio_publico',
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $id,
            'limit' => '1',
        ], $this->userToken());
        if ($result['ok'] && !empty($result['data'])) {
            return (string) $result['data'][0]['folio_publico'];
        }
        return null;
    }

    public function updateFechaListo(int $id): void
    {
        $tid = TenantContext::requireTenant();
        $this->api->patch('reparaciones', ['fecha_listo' => date('c')], [
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $id,
        ], $this->userToken());
    }

    // ═══════════════════════════════════════════════════════
    //  MES AZUL
    // ═══════════════════════════════════════════════════════

    public function findDispositivosCon90DiasOmas(): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_dispositivos_90dias', ['p_tenant_id' => $tid], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    public function findEquiposParaMesAzulInicio(): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_mes_azul_inicio_ids', ['p_tenant_id' => $tid], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    public function findEquiposParaMesAzulFinal(): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_mes_azul_final_ids', ['p_tenant_id' => $tid], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    public function findDispositivosMesAzulActivo(): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_mes_azul_activo', ['p_tenant_id' => $tid], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    public function findDispositivosMesAzulHistorial(): array
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->rpc('rpc_mes_azul_historial', ['p_tenant_id' => $tid], $this->userToken());
        return $result['ok'] ? ($result['data'] ?? []) : [];
    }
}
