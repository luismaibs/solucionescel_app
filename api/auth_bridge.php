<?php
/**
 * Puente de autenticacion Supabase JS <-> PHP.
 *
 * Recibe JWT desde el frontend (login via supabase-js) y
 * establece las cookies HttpOnly correspondientes.
 * Tambien maneja el logout iniciado desde JS.
 *
 * Rate limiting: maximo 10 intentos de login por IP en 60 s.
 */
require_once __DIR__ . '/../config/env_loader.php';
require_once __DIR__ . '/../src/Shared/SupabaseClient.php';
require_once __DIR__ . '/../config/auth_jwt.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// ─── Rate Limiting ─────────────────────────────────────────

function bridgeRateLimit(string $key, int $maxAttempts, int $windowSec): bool
{
    $dir = sys_get_temp_dir() . '/solcel_ratelimit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $file = $dir . '/' . md5($key) . '.json';
    $now = time();
    $data = ['attempts' => [], 'blocked_until' => 0];

    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }

    // Si esta bloqueado, verificar si ya expiro
    if ($data['blocked_until'] > $now) {
        return false;
    }

    // Podar timestamps viejos
    $cutoff = $now - $windowSec;
    $data['attempts'] = array_values(array_filter($data['attempts'], function ($t) use ($cutoff) {
        return $t > $cutoff;
    }));

    // Registrar este intento
    $data['attempts'][] = $now;

    if (count($data['attempts']) > $maxAttempts) {
        $data['blocked_until'] = $now + 300; // Bloquear 5 min
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return false;
    }

    @file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === '') {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Rate limiting: login
    if (!bridgeRateLimit('login_' . $clientIp, 10, 60)) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'Demasiados intentos. Intentalo de nuevo en unos minutos.']);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    $accessToken = $body['access_token'] ?? '';
    $refreshToken = $body['refresh_token'] ?? '';

    if (empty($accessToken) || empty($refreshToken)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Tokens requeridos']);
        exit;
    }

    // Verificar JWT con Supabase antes de confiar en sus claims
    $client = new SupabaseClient();
    $userCheck = $client->getUser($accessToken);
    if (!$userCheck['ok']) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Token no verificado por Supabase']);
        exit;
    }

    // Decodificar claims para obtener expiry
    $claims = jwtDecode($accessToken);
    if (!$claims || !isset($claims['exp'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'JWT invalido']);
        exit;
    }

    $jwtExpiry = (int) $claims['exp'];
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    setcookie(JWT_COOKIE, $accessToken, [
        'expires' => $jwtExpiry,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    setcookie(REFRESH_COOKIE, $refreshToken, [
        'expires' => time() + 2592000,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    // Sincronizar sesion PHP (cache de perfil) desde Supabase
    sincronizarPerfilDesdeJwt($accessToken);

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'logout') {
    // Revocar JWT en Supabase
    $jwt = getJwtFromRequest();
    if ($jwt) {
        try {
            $client = new SupabaseClient();
            $client->signOut($jwt);
        } catch (Throwable $e) {
            error_log("Bridge signOut error: " . $e->getMessage());
        }
    }

    // Limpiar cookies JWT
    clearJwtCookies();

    // Limpiar sesion PHP
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Accion no valida']);
