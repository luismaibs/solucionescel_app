<?php
require_once __DIR__ . '/../config/api_guard.php';

$webhook_n8n = getenv('N8N_WEBHOOK_NOTIFICAR');
if (!$webhook_n8n) {
    jsonResponse(['message' => 'Webhook N8N no configurado'], 500);
}

$repo = new ReparacionRepository($supabase);
$clienteRepo = new ClienteRepository($supabase);
$garantiaRepo = new GarantiaRepository($supabase);
$timelineService = class_exists('EventoTimelineService') ? new EventoTimelineService($supabase) : null;
$mensajes = new MensajesService($supabase, $webhook_n8n, $timelineService);
$service = new ReparacionService($supabase, $repo, $clienteRepo, $garantiaRepo, $mensajes);

$action = $_POST['action'] ?? '';

// Alias local con la firma simple que usa este archivo
function repOk(string $message): void { jsonResponse(['message' => $message]); }
function repErr(string $message, int $code = 400): void { jsonResponse(['message' => $message], $code); }

try {
    $handler = match ($action) {
        'save_templates' => function () use ($service) {
            if (isset($_POST['plantillas']) && is_array($_POST['plantillas'])) {
                $service->guardarPlantillas($_POST['plantillas']);
            }
            repOk('Plantillas guardadas');
        },
        'create' => function () use ($service) {
            $service->crearReparacion($_POST);
            repOk('Equipo creado exitosamente');
        },
        'edit' => function () use ($service) {
            $service->editarReparacion($_POST);
            repOk('Equipo editado');
        },
        'update_status' => function () use ($service) {
            $id = (int) ($_POST['id'] ?? 0);
            $nuevoEstado = (string) ($_POST['nuevo_estado'] ?? '');
            $tipoGarantia = isset($_POST['tipo_garantia']) ? (string) $_POST['tipo_garantia'] : null;
            $service->cambiarEstado($id, $nuevoEstado, $tipoGarantia);
            repOk('Estado actualizado');
        },
        'reactivar_sin_garantia' => function () use ($service) {
            $id = (int) ($_POST['id'] ?? 0);
            $service->reactivarSinGarantia($id);
            repOk('Equipo reactivado');
        },
        'reactivar_inactivo' => function () use ($service) {
            $id = (int) ($_POST['id'] ?? 0);
            $service->reactivarInactivo($id);
            repOk('Dispositivo reactivado');
        },
        'reactivar_entregado' => function () use ($service) {
            $id = (int) ($_POST['id'] ?? 0);
            $service->reactivarEntregado($id);
            repOk('Dispositivo reactivado - Garantía activada');
        },
        'inactivar_garantia' => function () use ($service) {
            $id = (int) ($_POST['id'] ?? 0);
            $service->inactivarEquipo($id);
            repOk('Equipo inactivado');
        },
        'iniciar_garantia' => function () use ($service) {
            $id = (int) ($_POST['id'] ?? 0);
            $service->iniciarGarantia($id);
            repOk('Garantía iniciada');
        },
        'notify_extra' => function () use ($service) {
            $id = (int) ($_POST['id'] ?? 0);
            $tipoExtra = (string) ($_POST['tipo_extra'] ?? '');
            $service->enviarNotificacionExtra($id, $tipoExtra);
            repOk('Notificación enviada');
        },
        'send_message' => function () use ($service) {
            $service->enviarMensajePersonalizado($_POST);
            repOk('Mensaje enviado');
        },
        'delete' => function () use ($service) {
            $id = (int) ($_POST['id'] ?? 0);
            $service->eliminarReparacion($id);
            repOk('Equipo eliminado');
        },
        default => function () {
            repErr('Acción no reconocida');
        },
    };
    $handler();
} catch (InvalidArgumentException $e) {
    jsonResponse(false, $e->getMessage());
} catch (Exception $e) {
    repErr('Error: ' . $e->getMessage());
}
