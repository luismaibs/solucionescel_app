<?php

/**
 * SoporteService
 *
 * Lógica de negocio para soporte humano y bot:
 * - KPIs de conversaciones
 * - Operaciones de pausa/reactivación del bot
 */
class SoporteService
{
    private const MESES_CORTOS = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    private SoporteRepository $repo;

    public function __construct(SoporteRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * KPIs de cabecera para el módulo de soporte.
     */
    public function obtenerResumenPanel(): array
    {
        return [
            'totalConvActivas' => $this->repo->countConversacionesPausadas(),
            'totalReactivadasHoy' => $this->repo->countConversacionesReactivadasHoy(),
            'totalHistoricoPausas' => $this->repo->countTotalConversaciones(),
        ];
    }

    /**
     * Devuelve conversaciones formateadas para la API (igual que obtenerConversaciones actual).
     */
    public function obtenerConversacionesFormateadasParaApi(int $limit = 50): array
    {
        $conversaciones = $this->repo->findConversacionesParaApi($limit);

        return array_map(function (array $conv) {
            $minutos = (int) ($conv['minutos_transcurridos'] ?? 0);

            if ($minutos < 60) {
                $tiempo = $minutos . ' min';
            } elseif ($minutos < 1440) {
                $horas = (int) floor($minutos / 60);
                $tiempo = $horas . 'h ' . ($minutos % 60) . 'min';
            } else {
                $timestamp = strtotime($conv['fecha_pausa']);
                $dia = date('j', $timestamp);
                $mes = self::MESES_CORTOS[(int) date('n', $timestamp)];
                $hora = date('g:i A', $timestamp);
                $tiempo = "$dia $mes $hora";
            }

            return [
                'id' => $conv['id'],
                'remote_jid' => $conv['remote_jid'],
                'nombre_cliente' => $conv['nombre_cliente'],
                'telefono' => $conv['telefono'],
                'mensaje' => $conv['mensaje'] ?? 'Sin contexto',
                'estado' => $conv['estado'],
                'fecha_pausa' => $conv['fecha_pausa'],
                'fecha_reactivacion' => $conv['fecha_reactivacion'],
                'tiempo_transcurrido' => $tiempo,
            ];
        }, $conversaciones);
    }

    /**
     * Registra una pausa de bot (llamada desde n8n) y devuelve respuesta API.
     */
    public function registrarPausaBot(array $data): array
    {
        // Limpieza de datos (por si n8n envía con error de sintaxis \"=\")
        $remoteJid = ltrim((string) ($data['remote_jid'] ?? ''), '=');
        $nombreCliente = ltrim((string) ($data['nombre_cliente'] ?? 'Cliente'), '=');
        $telefono = ltrim((string) ($data['telefono'] ?? ''), '=');
        $mensaje = ltrim((string) ($data['mensaje'] ?? ''), '=');

        if ($remoteJid === '' || $telefono === '') {
            return [
                'success' => false,
                'error' => 'Datos incompletos',
                'received' => [
                    'remote_jid' => $remoteJid,
                    'nombre_cliente' => $nombreCliente,
                    'telefono' => $telefono,
                ],
            ];
        }

        try {
            $existente = $this->repo->findConversacionPausadaExistente($remoteJid);

            if ($existente !== null) {
                $this->repo->actualizarFechaPausa($remoteJid);
                return [
                    'success' => true,
                    'message' => 'Conversación actualizada',
                    'action' => 'update',
                    'id' => (int) $existente['id'],
                ];
            }

            $newId = $this->repo->insertConversacionPausada(
                $remoteJid,
                $nombreCliente,
                $telefono,
                $mensaje
            );

            try {
                $tituloNotif = "🚨 Atención Requerida";
                $cuerpoNotif = "Cliente {$nombreCliente} solicita hablar con alguien. \"{$mensaje}\"";
                $this->repo->insertNotificacionSistema($tituloNotif, $cuerpoNotif, 'warning');
            } catch (Exception $e) {
                // Ignoramos error de notificación para no romper el flujo principal
            }

            return [
                'success' => true,
                'message' => 'Conversación registrada',
                'action' => 'insert',
                'id' => $newId,
                'remote_jid' => $remoteJid,
                'nombre_cliente' => $nombreCliente,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error de base de datos',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Reactiva el bot: llama al webhook n8n y actualiza la BD local.
     */
    public function reactivarBot(array $data): array
    {
        $remoteJid = trim((string) ($data['remote_jid'] ?? ''));
        $convId = (int) ($data['conv_id'] ?? 0);

        if ($remoteJid === '') {
            return [
                'success' => false,
                'error' => 'remote_jid requerido para reactivación',
            ];
        }

        if ($convId <= 0) {
            $existente = $this->repo->findConversacionPausadaExistente($remoteJid);
            if ($existente === null) {
                return [
                    'success' => false,
                    'error' => 'No se encontró conversación pausada para este remote_jid',
                ];
            }
            $convId = (int) $existente['id'];
        }

        // 1. Llamar al webhook n8n "Reactivar Bot" (mismo trigger que bot.php)
        $webhookBase = getenv('N8N_WEBHOOK_REANUDAR');
        if (empty($webhookBase)) {
            return [
                'success' => false,
                'error' => 'Webhook N8N_WEBHOOK_REANUDAR no configurado. Configura .env con la URL del webhook de reanudar bot.',
            ];
        }
        $url = $webhookBase . '?remoteJid=' . urlencode($remoteJid);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => 'No se pudo reactivar el bot en n8n (servicio no respondió correctamente). Intenta desde el enlace de WhatsApp o más tarde.',
            ];
        }

        // 2. Actualizar BD local
        try {
            $this->repo->marcarConversacionReactivada($convId, $remoteJid);

            // No podemos usar rowCount() directamente desde aquí de manera portable,
            // pero podemos devolver un mensaje genérico de éxito.
            return [
                'success' => true,
                'message' => 'Bot reactivado correctamente',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al actualizar el estado en el panel',
                'details' => $e->getMessage(),
            ];
        }
    }
}

