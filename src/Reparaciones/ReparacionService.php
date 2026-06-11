<?php

/**
 * ReparacionService
 *
 * Lógica de negocio del módulo de equipos/reparaciones.
 * No maneja redirecciones ni headers: eso queda en los controladores.
 */
class ReparacionService
{
    private SupabaseClient $api;
    private ReparacionRepository $repo;
    private ClienteRepository $clienteRepo;
    private GarantiaRepository $garantiaRepo;
    private MensajesService $mensajes;
    private ?EventoTimelineService $timelineService;

    private MesAzulService $mesAzulService;

    public function __construct(
        SupabaseClient $api,
        ReparacionRepository $repo,
        ClienteRepository $clienteRepo,
        GarantiaRepository $garantiaRepo,
        MensajesService $mensajes
    ) {
        $this->api = $api;
        $this->repo = $repo;
        $this->clienteRepo = $clienteRepo;
        $this->garantiaRepo = $garantiaRepo;
        $this->mensajes = $mensajes;
        $this->mesAzulService = new MesAzulService($api, $repo, $mensajes, $garantiaRepo);

        if (class_exists('EventoTimelineService')) {
            $this->timelineService = new EventoTimelineService($api);
        }
    }

    // ═══════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════

    private function registrarEvento(string $tipo, string $titulo, string $desc = '', ?int $clienteId = null, ?int $reparacionId = null, array $meta = []): void
    {
        if ($this->timelineService) {
            $this->timelineService->registrar($tipo, $titulo, $desc, $clienteId, $reparacionId, $meta);
        }
    }

    private function buildPayload(array $equipo, string $tipo, array $extra = []): array
    {
        $modeloCompleto = trim(
            (!empty($equipo['equipo_marca']) ? $equipo['equipo_marca'] . ' ' : '')
            . ($equipo['equipo_modelo'] ?? '')
        );

        return array_merge([
            // Identificadores
            'tipo'             => $tipo,
            'reparacion_id'    => isset($equipo['id']) ? (int) $equipo['id'] : null,
            'folio'            => $equipo['folio_publico'] ?? '',
            // Cliente
            'cliente_id'       => isset($equipo['cliente_id']) ? (int) $equipo['cliente_id'] : null,
            'cliente'          => $equipo['cliente_nombre'] ?? '',
            'cliente_apellido' => $equipo['cliente_apellido'] ?? '',
            'cliente_correo'   => $equipo['cliente_correo'] ?? '',
            'telefono'         => $equipo['telefono'] ?? '',
            // Dispositivo
            'equipo_marca'     => $equipo['equipo_marca'] ?? '',
            'equipo_modelo'    => $equipo['equipo_modelo'] ?? '',
            'modelo'           => $modeloCompleto,
            'falla'            => $equipo['falla_reportada'] ?? '',
            'fecha_ingreso'    => $equipo['fecha_ingreso'] ?? '',
        ], $extra);
    }

    private function esEstadoListo(string $slug): bool
    {
        $tid = TenantContext::requireTenant();
        $result = $this->api->get('estados_config', [
            'select' => 'id',
            'tenant_id' => 'eq.' . $tid,
            'slug' => 'eq.' . $slug,
            'parent_id' => 'is.null',
            'limit' => '1',
        ]);

        if (!$result['ok'] || empty($result['data'])) {
            return in_array($slug, ['listo', 'listo_sin_garantia']);
        }

        return $slug === 'listo' || $slug === 'listo_sin_garantia';
    }

    /**
     * Patrón unificado para acciones que cambian estado + notifican.
     * Reemplaza 9 métodos duplicados similares.
     */
    private function ejecutarAccion(int $id, string $accion, array $cambios, string $tipoMsg, string $auditAction, string $auditDetail, array $event = [], array $extraData = []): array
    {
        $equipo = $this->repo->findById($id);
        if (!$equipo) {
            throw new InvalidArgumentException('Reparación no encontrada');
        }

        $estadoAnterior = $equipo['estado'] ?? '';

        // Aplicar cambios según la acción
        switch ($accion) {
            case 'estado':
                $this->repo->updateEstado($id, $cambios['estado']);
                $esListo = $this->esEstadoListo($cambios['estado']);
                if (!empty($cambios['tipo_garantia'])) {
                    $this->garantiaRepo->upsertTipoGarantia($id, $cambios['tipo_garantia']);
                }
                if ($esListo) {
                    $this->repo->updateFechaListo($id);
                }
                if ($cambios['estado'] === 'entregado') {
                    $this->garantiaRepo->resetMesAzul($id);
                }
                break;
            case 'iniciar_garantia':
                $this->garantiaRepo->iniciarGarantia($id);
                break;
            case 'reactivar_sin_garantia':
                $this->garantiaRepo->reactivarSinGarantia($id);
                break;
            case 'inactivar':
                $this->garantiaRepo->inactivar($id);
                break;
        }

        registrarActividad($auditAction, $auditDetail, $id, 'reparacion');

        // Registrar timeline
        $clienteId = $equipo['cliente_id'] ?? null;
        $this->registrarEvento(
            $event['tipo'] ?? 'cambio_estado',
            $event['titulo'] ?? '',
            $event['desc'] ?? '',
            $clienteId ? (int) $clienteId : null,
            $id,
            $event['meta'] ?? []
        );

        // Notificar (algunos estados no notifican)
        if (!in_array($accion, ['inactivar'])) {
            $payload = $this->buildPayload($equipo, $tipoMsg, $extraData);
            $this->mensajes->enviarNotificacion($payload, $id, $clienteId ? (int) $clienteId : null);
        }

        return $equipo;
    }

    // ═══════════════════════════════════════════════════════
    //  CRUD
    // ═══════════════════════════════════════════════════════

    public function guardarPlantillas(array $plantillas): void
    {
        if (empty($plantillas)) {
            return;
        }
        $tenantId = TenantContext::requireTenant();
        foreach ($plantillas as $id => $texto) {
            $this->api->patch('configuracion_mensajes', [
                'plantilla' => trim((string) $texto),
            ], [
                'tenant_id' => 'eq.' . $tenantId,
                'id' => 'eq.' . (int) $id,
            ]);
        }
    }

    public function crearReparacion(array $data): int
    {
        $telefonoFinal = Utils::formatearTelefono(
            (string) ($data['lada'] ?? ''),
            (string) ($data['telefono'] ?? '')
        );

        $fecha = !empty($data['fecha_ingreso'])
            ? $data['fecha_ingreso'] . ' ' . date('H:i:s')
            : date('Y-m-d H:i:s');

        // Resolver marca
        $marca = $this->resolverMarca($data);

        // Resolver usuario
        $ingresadoPorUserId = $this->resolverUsuario($data);

        // Resolver cliente
        $nombreCliente = mb_strtoupper(trim((string) ($data['nombre'] ?? '')), 'UTF-8');
        $clienteId = !empty($data['cliente_id']) ? (int) $data['cliente_id'] : $this->resolverCliente($nombreCliente, $telefonoFinal);

        $falla = (string) ($data['falla'] ?? '');
        $modelo = trim((string) ($data['modelo'] ?? ''));
        $equipoMarcaId = isset($data['equipo_marca_id']) && (string) $data['equipo_marca_id'] !== '' ? (int) $data['equipo_marca_id'] : null;

        $tempFolio = 'SC-TMP-' . uniqid();

        $newId = $this->repo->insertReparacion(
            $tempFolio,
            $clienteId,
            $marca,
            $modelo,
            $falla,
            $fecha,
            $ingresadoPorUserId,
            $equipoMarcaId
        );

        $folioDefinitivo = 'SC-' . str_pad((string) $newId, 4, '0', STR_PAD_LEFT);
        $this->repo->actualizarFolioPublico($newId, $folioDefinitivo);

        registrarActividad('crear_equipo', "Folio: {$folioDefinitivo} - Cliente: {$nombreCliente}", $newId, 'reparacion');

        $modeloCompleto = trim($marca . ' ' . $modelo);

        $this->registrarEvento(
            'equipo_ingresado',
            "Equipo ingresado: {$modeloCompleto}",
            "Folio {$folioDefinitivo} — Problema: {$falla}",
            $clienteId,
            $newId,
            ['folio' => $folioDefinitivo, 'marca' => $marca, 'modelo' => $modelo, 'falla' => $falla]
        );

        $this->mensajes->enviarNotificacion(
            $this->buildPayload([
                'telefono' => $telefonoFinal,
                'cliente_nombre' => $nombreCliente,
                'equipo_marca' => $marca,
                'equipo_modelo' => $modelo,
                'folio_publico' => $folioDefinitivo,
                'falla_reportada' => $falla,
                'fecha_ingreso' => $fecha,
            ], 'en_taller'),
            $newId,
            $clienteId
        );

        return $newId;
    }

    private function resolverMarca(array $data): string
    {
        $equipoMarcaId = isset($data['equipo_marca_id']) && (string) $data['equipo_marca_id'] !== '' ? (int) $data['equipo_marca_id'] : null;
        $marca = trim((string) ($data['marca'] ?? ''));

        if ($equipoMarcaId !== null) {
            $nombreMarca = $this->repo->getEquipoMarcaNombre($equipoMarcaId);
            if ($nombreMarca !== null) return $nombreMarca;
        } elseif ($marca !== '') {
            return $marca;
        }
        return $marca;
    }

    private function resolverUsuario(array $data): ?int
    {
        $username = trim((string) ($data['ingresado_por'] ?? ''));
        if ($username === '') return getCurrentUserId();

        $tenantId = TenantContext::requireTenant();
        $result = $this->api->get('usuarios', [
            'select' => 'id',
            'tenant_id' => 'eq.' . $tenantId,
            'username' => 'eq.' . $username,
            'deleted_at' => 'is.null',
            'limit' => '1',
        ]);
        if ($result['ok'] && !empty($result['data'])) {
            return (int) $result['data'][0]['id'];
        }
        return getCurrentUserId();
    }

    private function resolverCliente(string $nombre, string $telefono): int
    {
        $cliente = $this->clienteRepo->findByTelefono($telefono);
        if ($cliente) return (int) $cliente['id'];

        return $this->clienteRepo->insert(
            $nombre ?: 'Sin registrar',
            '',
            $telefono,
            null,
            getCurrentUserId()
        );
    }

    public function editarReparacion(array $data): void
    {
        $telefonoFinal = Utils::formatearTelefono(
            (string) ($data['lada'] ?? ''),
            (string) ($data['telefono'] ?? '')
        );

        $idInterno = (int) ($data['id_interno'] ?? 0);
        $equipo = $this->repo->findById($idInterno);

        $marca = $this->resolverMarca($data);
        $modelo = trim((string) ($data['modelo'] ?? ''));
        $equipoMarcaId = isset($data['equipo_marca_id']) && (string) $data['equipo_marca_id'] !== '' ? (int) $data['equipo_marca_id'] : null;

        $nombreCliente = mb_strtoupper(trim((string) ($data['nombre'] ?? '')), 'UTF-8');
        $falla = (string) ($data['falla'] ?? '');

        $clienteId = isset($data['cliente_id']) && $data['cliente_id'] !== '' ? (int) $data['cliente_id'] : ($equipo ? (int) ($equipo['cliente_id'] ?? 0) : 0);
        if ($clienteId <= 0) {
            throw new InvalidArgumentException('Se requiere un cliente vinculado a la reparación.');
        }

        $this->repo->updateReparacion($idInterno, $clienteId, $marca, $modelo, $falla, $equipoMarcaId);

        if ($nombreCliente !== '' || $telefonoFinal !== '') {
            $cliente = $this->clienteRepo->findById($clienteId);
            if ($cliente) {
                $this->clienteRepo->update(
                    $clienteId,
                    $nombreCliente !== '' ? $nombreCliente : $cliente['nombre'],
                    $cliente['apellido'] ?? '',
                    $telefonoFinal !== '' ? $telefonoFinal : $cliente['telefono'],
                    $cliente['correo'] ?? null
                );
            }
        }

        $folioActual = $this->repo->getFolioPublicoById($idInterno) ?? '';
        $this->registrarEvento(
            'equipo_editado',
            'Equipo editado',
            "Se actualizaron datos del equipo. Folio: {$folioActual}",
            $equipo ? ($equipo['cliente_id'] ?? null) : null,
            $idInterno,
            ['folio' => $folioActual]
        );

        registrarActividad('editar_equipo', "Actualizó datos del Folio: {$folioActual}", $idInterno, 'reparacion');
    }

    // ═══════════════════════════════════════════════════════
    //  CAMBIO DE ESTADO
    // ═══════════════════════════════════════════════════════

    public function cambiarEstado(int $id, string $nuevoEstado, ?string $tipoGarantia = null): void
    {
        if ($nuevoEstado === 'inactivo') {
            throw new InvalidArgumentException('Usa inactivarEquipo() para pasar a Inactivo.');
        }

        $equipo = $this->repo->findById($id);
        $estadoAnterior = $equipo['estado'] ?? '';

        $tipoMsg = 'estado:' . $nuevoEstado;

        $eventoTitulo = "Estado: {$estadoAnterior} → {$nuevoEstado}";

        $extraData = ['estado_anterior' => $estadoAnterior];
        if ($tipoGarantia) {
            $extraData['tipo_garantia']  = $tipoGarantia;
            $extraData['subestado_slug'] = $tipoGarantia;
        }

        $this->ejecutarAccion(
            $id,
            'estado',
            ['estado' => $nuevoEstado, 'tipo_garantia' => $tipoGarantia],
            $tipoMsg,
            'cambio_estado',
            "Cambió estado a: {$nuevoEstado}",
            [
                'tipo' => 'cambio_estado',
                'titulo' => $eventoTitulo,
                'desc' => "Estado: {$estadoAnterior} → {$nuevoEstado}",
                'meta' => ['estado_anterior' => $estadoAnterior, 'estado_nuevo' => $nuevoEstado],
            ],
            $extraData
        );
    }

    public function reactivarSinGarantia(int $id): void
    {
        $equipo = $this->repo->findById($id);
        $clienteId = $equipo ? ($equipo['cliente_id'] ?? null) : null;

        $this->ejecutarAccion(
            $id,
            'reactivar_sin_garantia',
            [],
            'en_taller',
            'reactivar_sin_garantia',
            "Reactivó sin garantía (estado Laboratorio)",
            [
                'tipo' => 'garantia_reactivada',
                'titulo' => 'Equipo reactivado sin garantía',
                'desc' => 'Reingresó al taller sin garantía',
                'meta' => ['con_garantia' => false],
            ]
        );
    }

    public function reactivarInactivo(int $id): void
    {
        $equipo = $this->repo->findById($id);
        if (!$equipo) throw new InvalidArgumentException('Reparación no encontrada');
        if (($equipo['estado'] ?? '') !== 'inactivo') {
            throw new InvalidArgumentException('Solo se puede reactivar un dispositivo en estado inactivo');
        }

        $this->ejecutarAccion(
            $id,
            'reactivar_sin_garantia',
            [],
            'reactivar_inactivo',
            'reactivar_inactivo',
            "Reactivó dispositivo inactivo (Proceso de revisión técnica)",
            [
                'tipo' => 'reactivar_inactivo',
                'titulo' => 'Dispositivo reactivado',
                'desc' => 'Proceso de revisión técnica enviado',
                'meta' => [],
            ]
        );
    }

    public function reactivarEntregado(int $id): void
    {
        $equipo = $this->repo->findById($id);
        if (!$equipo) throw new InvalidArgumentException('Reparación no encontrada');
        $estado = $equipo['estado'] ?? '';
        if ($estado !== 'entregado' && $estado !== 'garantia_entregada') {
            throw new InvalidArgumentException('Solo se puede reactivar un dispositivo en estado Entregado o Garantía entregada');
        }

        $this->ejecutarAccion(
            $id,
            'iniciar_garantia',
            [],
            'reactivar_entregado',
            'reactivar_entregado',
            "Reactivó dispositivo entregado a Garantía activada",
            [
                'tipo' => 'reactivar_entregado',
                'titulo' => 'Dispositivo reactivado',
                'desc' => 'Garantía activada - Proceso de revisión técnica enviado',
                'meta' => [],
            ]
        );
    }

    public function inactivarEquipo(int $id): void
    {
        $this->ejecutarAccion(
            $id,
            'inactivar',
            [],
            '',
            'inactivar_equipo',
            "Inactivó equipo (estado Inactivo, sin notificación)",
            [
                'tipo' => 'cambio_estado',
                'titulo' => 'Equipo inactivado',
                'desc' => 'El equipo fue inactivado sin enviar notificación',
                'meta' => [],
            ]
        );
    }

    public function iniciarGarantia(int $id): void
    {
        $equipo = $this->repo->findById($id);

        $this->ejecutarAccion(
            $id,
            'iniciar_garantia',
            [],
            'iniciar_garantia',
            'iniciar_garantia',
            "Inició garantía (Reactivar)",
            [
                'tipo' => 'garantia_activada',
                'titulo' => 'Garantía iniciada',
                'desc' => 'Se activó la garantía del equipo',
                'meta' => ['con_garantia' => true],
            ]
        );
    }

    public function enviarNotificacionExtra(int $id, string $tipoExtra): void
    {
        if ($tipoExtra === 'mes_azul_inicio') {
            $this->mesAzulService->enviarMesAzulInicio($id);
            return;
        }
        if ($tipoExtra === 'mes_azul_final') {
            $this->mesAzulService->enviarMesAzulFinal($id);
            return;
        }

        // Genérico: enviar sin afectar BD
        $equipo = $this->repo->findById($id);
        if (!$equipo) return;
        $payload = $this->buildPayload($equipo, $tipoExtra);
        $this->mensajes->enviarNotificacion($payload, $id, $equipo['cliente_id'] ?? null);
        registrarActividad('notificacion_extra', "Envió aviso: {$tipoExtra}", $id, 'reparacion');
    }

    public function enviarMensajePersonalizado(array $payload): void
    {
        $idInterno = (int) ($payload['id_interno'] ?? 0);

        $data = $this->buildPayload([
            'telefono' => $payload['telefono'] ?? '',
            'cliente_nombre' => $payload['nombre'] ?? '',
            'equipo_marca' => '',
            'equipo_modelo' => $payload['modelo'] ?? '',
            'folio_publico' => $payload['folio'] ?? '',
            'falla_reportada' => '',
            'fecha_ingreso' => $payload['fecha_ingreso'] ?? '',
        ], 'mensaje_personalizado', ['mensaje_texto' => $payload['mensaje_texto'] ?? '']);

        $this->mensajes->enviarNotificacion($data, $idInterno);
        registrarActividad('mensaje_personalizado', "Envió mensaje manual", $idInterno, 'reparacion');
    }

    public function eliminarReparacion(int $id): void
    {
        $this->repo->softDelete($id);
        registrarActividad('eliminar_equipo', "Eliminó equipo ID: {$id}", $id, 'reparacion');
    }
}
