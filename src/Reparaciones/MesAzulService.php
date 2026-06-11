<?php

/**
 * MesAzulService
 *
 * Lógica de negocio del proceso Mes Azul: envío de webhooks
 * "Mes Azul Inicio" (90 días sin entrega) y "Mes Azul Final" (+5 días),
 * e inactivación de dispositivos.
 */
class MesAzulService
{
    private SupabaseClient $api;
    private ReparacionRepository $repo;
    private GarantiaRepository $garantiaRepo;
    private MensajesService $mensajes;

    public function __construct(SupabaseClient $api, ReparacionRepository $repo, MensajesService $mensajes, GarantiaRepository $garantiaRepo)
    {
        $this->api = $api;
        $this->repo = $repo;
        $this->garantiaRepo = $garantiaRepo;
        $this->mensajes = $mensajes;
    }

    /**
     * Ejecuta la verificación periódica: envía Mes Azul Inicio a quienes cumplen 90 días
     * y Mes Azul Final (+ inactivar) a quienes cumplen 5 días desde el inicio.
     *
     * @return array{inicio_enviados: int, final_enviados: int}
     */
    public function procesarMesAzulDiario(): array
    {
        $inicioEnviados = 0;
        $finalEnviados = 0;

        $paraInicio = $this->repo->findEquiposParaMesAzulInicio();
        foreach ($paraInicio as $equipo) {
            $ok = $this->enviarMesAzulInicio((int) $equipo['id']);
            if ($ok) {
                $inicioEnviados++;
            }
        }

        $paraFinal = $this->repo->findEquiposParaMesAzulFinal();
        foreach ($paraFinal as $equipo) {
            $ok = $this->enviarMesAzulFinal((int) $equipo['id']);
            if ($ok) {
                $finalEnviados++;
            }
        }

        return ['inicio_enviados' => $inicioEnviados, 'final_enviados' => $finalEnviados];
    }

    /**
     * Envía el webhook "Mes Azul Inicio" a n8n y actualiza el registro.
     */
    public function enviarMesAzulInicio(int $reparacionId): bool
    {
        return $this->enviarMesAzul('inicio', $reparacionId);
    }

    /**
     * Envía el webhook "Mes Azul Final" a n8n e inactiva el equipo.
     */
    public function enviarMesAzulFinal(int $reparacionId): bool
    {
        return $this->enviarMesAzul('final', $reparacionId);
    }

    private function enviarMesAzul(string $tipo, int $reparacionId): bool
    {
        $equipo = $this->repo->findById($reparacionId);
        if (!$equipo) {
            return false;
        }

        $esInicio = $tipo === 'inicio';
        $modeloCompleto = trim(($equipo['equipo_marca'] ?? '') . ' ' . ($equipo['equipo_modelo'] ?? ''));

        $webhookData = [
            'tipo' => $esInicio ? 'mes_azul_inicio' : 'mes_azul_final',
            'telefono' => $equipo['telefono'],
            'cliente' => $equipo['cliente_nombre'],
            'modelo' => $modeloCompleto,
            'folio' => $equipo['folio_publico'],
            'falla' => $equipo['falla_reportada'] ?? '',
            'fecha_ingreso' => $equipo['fecha_ingreso'] ?? '',
            'fecha_listo' => $equipo['fecha_listo'] ?? '',
        ];

        if ($esInicio) {
            $fechaListo = $equipo['fecha_listo'] ?? null;
            $webhookData['dias_transcurridos'] = $fechaListo
                ? (new DateTime($fechaListo))->diff(new DateTime())->days
                : 90;
        } else {
            $webhookData['mes_azul_inicio_enviado'] = $equipo['mes_azul_inicio_enviado'] ?? '';
        }

        $clienteId = (int) ($equipo['cliente_id'] ?? 0);
        $this->mensajes->enviarNotificacion($webhookData, $reparacionId, $clienteId ?: null);

        if ($esInicio) {
            $this->garantiaRepo->updateMesAzulInicioEnviado($reparacionId);
        } else {
            $this->garantiaRepo->updateMesAzulFinalEnviado($reparacionId);
        }

        if (function_exists('registrarActividad')) {
            registrarActividad(
                $esInicio ? 'mes_azul_inicio' : 'mes_azul_final',
                ($esInicio ? 'Envió Mes Azul Inicio' : 'Envió Mes Azul Final e inactivó') . " - Folio: {$equipo['folio_publico']}",
                $reparacionId,
                'reparacion'
            );
        }

        return true;
    }

    /**
     * Días restantes para que se cumplan los 5 días desde mes_azul_inicio_enviado.
     * Devuelve 0 si ya pasaron los 5 días.
     *
     * @param string|null $fechaInicio datetime mes_azul_inicio_enviado
     * @return int
     */
    public static function calcularDiasRestantes(?string $fechaInicio): int
    {
        if (!$fechaInicio) {
            return 5;
        }
        try {
            $inicio = new DateTime($fechaInicio);
            $final = (clone $inicio)->modify('+5 days');
            $hoy = new DateTime('today');
            if ($hoy >= $final) {
                return 0;
            }
            return $hoy->diff($final)->days;
        } catch (Exception $e) {
            return 5;
        }
    }

    /**
     * Dispositivos en Mes Azul activo (esperando el período de 5 días).
     * Incluye días restantes y confirmación de webhook inicio.
     *
     * @return array
     */
    public function obtenerDispositivosActivos(): array
    {
        $lista = $this->repo->findDispositivosMesAzulActivo();
        foreach ($lista as &$row) {
            $row['dias_restantes'] = self::calcularDiasRestantes($row['mes_azul_inicio_enviado'] ?? null);
            $row['webhook_inicio_ok'] = !empty($row['mes_azul_inicio_enviado']);
        }
        unset($row);
        return $lista;
    }

    /**
     * Historial de dispositivos inactivados por Mes Azul.
     *
     * @return array
     */
    public function obtenerHistorial(): array
    {
        return $this->repo->findDispositivosMesAzulHistorial();
    }

    /**
     * Dispositivos con 90+ días listos (independientemente de si se enviaron avisos Mes Azul).
     * dias_transcurridos ya viene calculado desde SQL.
     */
    public function obtenerDispositivos90DiasOmas(): array
    {
        return $this->repo->findDispositivosCon90DiasOmas();
    }
}
