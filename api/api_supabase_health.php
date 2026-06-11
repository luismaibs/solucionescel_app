<?php
include '../config/auth.php';
requireLogin();
requireAdmin();
require_once __DIR__ . '/../config/api_helper.php';

try {
    $client = new SupabaseClient();
    $result = $client->healthcheck();
    $statusCode = ($result['ok'] ?? false) ? 200 : 503;
    jsonResponse($result, $statusCode);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'message' => 'Fallo inesperado en healthcheck de Supabase',
        'error' => $e->getMessage(),
    ], 500);
}

