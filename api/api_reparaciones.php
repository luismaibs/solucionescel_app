<?php
include '../config/auth.php';
requireLogin();
include '../config/db.php';

$webhook_n8n = getenv('N8N_WEBHOOK_NOTIFICAR');
if (!$webhook_n8n) { throw new RuntimeException('N8N_WEBHOOK_NOTIFICAR no configurado'); }

$repo = new ReparacionRepository($supabase);
$clienteRepo = new ClienteRepository($supabase);
$garantiaRepo = new GarantiaRepository($supabase);
$timelineService = class_exists('EventoTimelineService') ? new EventoTimelineService($supabase) : null;
$mensajes = new MensajesService($supabase, $webhook_n8n, $timelineService);
$service = new ReparacionService($supabase, $repo, $clienteRepo, $garantiaRepo, $mensajes);

$action = $_POST['action'] ?? '';

function jsonResponse(bool $ok, string $message): void {
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

try {
    $handler = match ($action) {
        'save_templates' => function () use ($service) {
            if (isset($_POST['plantillas']) && is_array($_POST['plantillas'])) {
                $service->guardarPlantillas($_POST['plantillas']);
            }
            jsonResponse(true, 'Plantillas guardadas');
        },
        'create' => function () use ($service) {
            $service->crearReparacion($_POST);
            jsonResponse(true, 'Equipo creado exitosamente');
        },
        'edit' => function () use ($service) {
            $service->editarReparacion($_POST);
            jsonResponse(true, 'Equipo editado');
        },
        'update_status' => function () use ($service) {
            $id = (int) ($_POST['id'] ?? 0);
            $nuevoEstado = (string) ($_POST['nuevo_estado'] ?? '');
            $tipoGarantia = isset($_POST['tipo_garantia']) ? (string) $_POST['tipo_garantia'] : null;
            $service->cambiarEstado($id, $nuevoEstado, $tipoGarantia);
            jsonResponse(true, 'Estado actualizado');
        },
        'reactivar_sin_garantia' => function () use ($service) {
            $id = (int) ($_POST['id'] ?? 0);
            $service->reactivarSinGarantia($id);
            jsonResponse(true, 'Equipo reactivado');
        },
        'reactivar_inactivo' => function () use ($service) {
            $id = (int) ($_POST['id'] ?? 0);
            $service->reactivarInactivo($id);
            jsonResponse(true, 'Dispositivo reactivado');
        },
        'reactivar_entregado' => function () use ($service) {
            $id = (int) ($_POST['id'] ?? 0);
            $service->reactivarEntregado($id);
            jsonResponse(true, 'Dispositivo reactivado - Garantía activada');
        },
        'inactivar_garantia' => function () use ($service) {
            $id = (int) ($_POST['id'] ?? 0);
            $service->inactivarEquipo($id);
            jsonResponse(true, 'Equipo inactivado');
        },
        'iniciar_garantia' => function () use ($service) {
            $id = (int) ($_POST['id'] ?? 0);
            $service->iniciarGarantia($id);
            jsonResponse(true, 'Garantía iniciada');
        },
        'notify_extra' => function () use ($service) {
            $id = (int) ($_POST['id'] ?? 0);
            $tipoExtra = (string) ($_POST['tipo_extra'] ?? '');
            $service->enviarNotificacionExtra($id, $tipoExtra);
            jsonResponse(true, 'Notificación enviada');
        },
        'send_message' => function () use ($service) {
            $service->enviarMensajePersonalizado($_POST);
            jsonResponse(true, 'Mensaje enviado');
        },
        'delete' => function () use ($service) {
            $id = (int) ($_POST['id'] ?? 0);
            $service->eliminarReparacion($id);
            jsonResponse(true, 'Equipo eliminado');
        },
        default => function () {
            jsonResponse(false, 'Acción no reconocida');
        },
    };
    $handler();
} catch (InvalidArgumentException $e) {
    jsonResponse(false, $e->getMessage());
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
