<?php

/**
 * AIAnalyticsRepository (Supabase)
 *
 * Capa de datos para historial de analisis IA.
 * - DB queries via Supabase PostgREST
 * - Archivos via Supabase Storage (en vez de disco local)
 *
 * Requiere variable de entorno: SUPABASE_STORAGE_BUCKET (default: 'ai_historial')
 */
class AIAnalyticsRepository
{
    private SupabaseClient $api;
    private string $storageBucket;

    public function __construct(SupabaseClient $api)
    {
        $this->api = $api;
        $this->storageBucket = getenv('SUPABASE_STORAGE_BUCKET') ?: 'ai_historial';
    }

    private function userToken(): ?string
    {
        return getJwtFromRequest();
    }

    // ═══════════════════════════════════════════════════════
    //  STORAGE (reemplaza file_put_contents / file_get_contents)
    // ═══════════════════════════════════════════════════════

    private function uploadStorageFile(string $path, string $content): bool
    {
        $result = $this->api->uploadFile(
            $this->storageBucket,
            $path,
            $content,
            'application/json',
            $this->userToken()
        );
        return $result['ok'];
    }

    private function downloadStorageFile(string $path): ?string
    {
        return $this->api->downloadFile($this->storageBucket, $path, $this->userToken());
    }

    private function deleteStorageFile(string $path): void
    {
        $this->api->deleteFile($this->storageBucket, $path, $this->userToken());
    }

    // ═══════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════

    private function resolveUserId(string $username): ?int
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('usuarios', [
            'select' => 'id',
            'tenant_id' => 'eq.' . $tid,
            'username' => 'eq.' . $username,
            'deleted_at' => 'is.null',
            'limit' => '1',
        ], $this->userToken());

        return ($result['ok'] && !empty($result['data'])) ? (int) $result['data'][0]['id'] : null;
    }

    // ═══════════════════════════════════════════════════════
    //  GUARDAR ANALISIS
    // ═══════════════════════════════════════════════════════

    /**
     * Guarda un analisis en el historial. Sube JSONs de datos/visualizacion al Storage.
     */
    public function guardarAnalisis(array $datos): int
    {
        $tid = TenantContext::requireTenant();

        // Resolver user_id
        $userId = $datos['user_id'] ?? null;
        if ($userId === null && !empty($datos['username'])) {
            $userId = $this->resolveUserId($datos['username']);
        }
        if ($userId === null) {
            $userId = getCurrentUserId();
        }

        // Insertar registro en DB
        $row = [
            'tenant_id' => $tid,
            'user_id' => $userId,
            'conversacion_id' => $datos['conversacion_id'],
            'titulo' => $datos['titulo'] ?? null,
            'pregunta' => $datos['pregunta'],
            'respuesta' => $datos['respuesta'] ?? null,
            'sql_generado' => $datos['sql_generado'] ?? null,
            'guardado' => ($datos['guardado'] ?? 0) ? true : false,
        ];

        $result = $this->api->post('ai_analisis_historial', $row, $this->userToken());
        if (!$result['ok'] || empty($result['data'])) {
            throw new RuntimeException('Error al guardar analisis: ' . ($result['error'] ?? 'desconocido'));
        }

        $id = (int) $result['data'][0]['id'];
        $rutaDatos = null;
        $rutaViz = null;

        // Subir datos_resultado a Storage
        if (!empty($datos['datos_resultado'])) {
            $path = "{$tid}/{$id}_datos.json";
            if ($this->uploadStorageFile($path, $datos['datos_resultado'])) {
                $rutaDatos = $path;
            }
        }

        // Subir visualizacion a Storage
        if (!empty($datos['visualizacion'])) {
            $path = "{$tid}/{$id}_viz.json";
            if ($this->uploadStorageFile($path, $datos['visualizacion'])) {
                $rutaViz = $path;
            }
        }

        // Actualizar rutas en DB
        if ($rutaDatos !== null || $rutaViz !== null) {
            $update = [];
            if ($rutaDatos !== null) $update['ruta_datos_resultado'] = $rutaDatos;
            if ($rutaViz !== null) $update['ruta_visualizacion'] = $rutaViz;
            $this->api->patch('ai_analisis_historial', $update, [
                'tenant_id' => 'eq.' . $tid,
                'id' => 'eq.' . $id,
            ], $this->userToken());
        }

        return $id;
    }

    // ═══════════════════════════════════════════════════════
    //  CONSULTAS
    // ═══════════════════════════════════════════════════════

    public function marcarGuardado(?int $id = null, ?string $conversacionId = null, ?string $titulo = null): bool
    {
        $tid = TenantContext::requireTenant();
        $data = ['guardado' => true];
        if ($titulo !== null) $data['titulo'] = $titulo;

        $query = ['tenant_id' => 'eq.' . $tid];
        if ($id !== null) $query['id'] = 'eq.' . $id;
        if ($conversacionId !== null) $query['conversacion_id'] = 'eq.' . $conversacionId;

        $result = $this->api->patch('ai_analisis_historial', $data, $query, $this->userToken());
        return $result['ok'];
    }

    public function eliminarAnalisis(int $id): bool
    {
        $tid = TenantContext::requireTenant();

        // Primero obtener rutas para borrar archivos del Storage
        $result = $this->api->get('ai_analisis_historial', [
            'select' => 'ruta_datos_resultado,ruta_visualizacion',
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $id,
            'limit' => '1',
        ], $this->userToken());

        if ($result['ok'] && !empty($result['data'])) {
            $row = $result['data'][0];
            if (!empty($row['ruta_datos_resultado'])) {
                $this->deleteStorageFile($row['ruta_datos_resultado']);
            }
            if (!empty($row['ruta_visualizacion'])) {
                $this->deleteStorageFile($row['ruta_visualizacion']);
            }
        }

        $del = $this->api->delete('ai_analisis_historial', [
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $id,
        ], $this->userToken());
        return $del['ok'];
    }

    public function obtenerHistorialGuardado(string $username, int $limit = 50): array
    {
        $tid = TenantContext::requireTenant();
        $userId = $this->resolveUserId($username);
        if ($userId === null) return [];

        $result = $this->api->get('ai_analisis_historial', [
            'select' => 'id,titulo,pregunta,respuesta,ruta_visualizacion,created_at',
            'tenant_id' => 'eq.' . $tid,
            'user_id' => 'eq.' . $userId,
            'guardado' => 'is.true',
            'order' => 'created_at.desc',
            'limit' => (string) $limit,
        ], $this->userToken());

        $rows = $result['ok'] ? ($result['data'] ?? []) : [];

        foreach ($rows as &$r) {
            if (!empty($r['ruta_visualizacion'])) {
                $r['visualizacion'] = $this->downloadStorageFile($r['ruta_visualizacion']);
            }
            unset($r['ruta_visualizacion']);
        }
        return $rows;
    }

    public function obtenerConversaciones(string $username, int $limit = 30): array
    {
        $tid = TenantContext::requireTenant();
        $userId = $this->resolveUserId($username);
        if ($userId === null) return [];

        $result = $this->api->rpc('rpc_ai_conversaciones', [
            'p_tenant_id' => $tid,
            'p_user_id' => $userId,
            'p_limit' => $limit,
        ], $this->userToken());

        return $result['ok'] ? ($result['data'] ?? []) : [];
    }

    public function obtenerMensajesConversacion(string $conversacionId): array
    {
        $tid = TenantContext::requireTenant();

        $result = $this->api->get('ai_analisis_historial', [
            'select' => '*',
            'tenant_id' => 'eq.' . $tid,
            'conversacion_id' => 'eq.' . $conversacionId,
            'order' => 'created_at.asc',
        ], $this->userToken());

        $rows = $result['ok'] ? ($result['data'] ?? []) : [];

        foreach ($rows as &$r) {
            if (!empty($r['ruta_datos_resultado'])) {
                $r['datos_resultado'] = $this->downloadStorageFile($r['ruta_datos_resultado']);
            }
            if (!empty($r['ruta_visualizacion'])) {
                $r['visualizacion'] = $this->downloadStorageFile($r['ruta_visualizacion']);
            }
            unset($r['ruta_datos_resultado'], $r['ruta_visualizacion']);
        }
        return $rows;
    }

    public function obtenerAnalisisPorId(int $id): ?array
    {
        $tid = TenantContext::requireTenant();

        $result = $this->api->get('ai_analisis_historial', [
            'select' => '*',
            'tenant_id' => 'eq.' . $tid,
            'id' => 'eq.' . $id,
            'limit' => '1',
        ], $this->userToken());

        if (!$result['ok'] || empty($result['data'])) return null;
        $row = $result['data'][0];

        if (!empty($row['ruta_datos_resultado'])) {
            $row['datos_resultado'] = $this->downloadStorageFile($row['ruta_datos_resultado']);
        }
        if (!empty($row['ruta_visualizacion'])) {
            $row['visualizacion'] = $this->downloadStorageFile($row['ruta_visualizacion']);
        }
        unset($row['ruta_datos_resultado'], $row['ruta_visualizacion']);

        return $row;
    }

    public function ejecutarConsultaSegura(string $sql): array
    {
        $result = $this->api->rpc('rpc_ejecutar_consulta_segura', ['p_sql' => $sql], $this->userToken());

        if (!$result['ok'] || empty($result['data'])) {
            return [
                'success' => false,
                'error'   => $result['error'] ?? 'Error desconocido al ejecutar consulta',
            ];
        }

        $rpcData = $result['data'];

        if (is_string($rpcData)) {
            $rpcData = json_decode($rpcData, true);
        }
        if (!is_array($rpcData)) {
            return ['success' => false, 'error' => 'Respuesta inesperada del RPC'];
        }

        if (isset($rpcData['error'])) {
            return ['success' => false, 'error' => $rpcData['error']];
        }

        return [
            'success'   => true,
            'data'      => $rpcData['rows'] ?? [],
            'columns'   => $rpcData['columns'] ?? [],
            'row_count' => $rpcData['row_count'] ?? count($rpcData['rows'] ?? []),
        ];
    }

}
