<?php
/**
 * Script de diagnostico de login
 * Prueba directamente contra Supabase Auth y muestra la respuesta cruda.
 * USO: Accede desde el navegador a /debug_login.php?email=TU_EMAIL&password=TU_PASSWORD
 */

require_once __DIR__ . '/config/env_loader.php';
require_once __DIR__ . '/src/Shared/SupabaseClient.php';

header('Content-Type: text/html; charset=utf-8');

$email = $_GET['email'] ?? '';
$password = $_GET['password'] ?? '';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Debug Login</title>
    <style>
        body { font-family: monospace; max-width: 900px; margin: 2rem auto; padding: 1rem; background: #0f172a; color: #e2e8f0; }
        h2 { color: #38bdf8; }
        pre { background: #1e293b; padding: 1rem; border-radius: 8px; overflow-x: auto; }
        .ok { color: #4ade80; }
        .error { color: #f87171; }
        .info { color: #fbbf24; }
        input, button { padding: 8px 12px; margin: 4px; border-radius: 6px; border: 1px solid #334155; background: #1e293b; color: #e2e8f0; }
        button { background: #2563eb; cursor: pointer; border: none; }
        label { display: block; margin-top: 8px; color: #94a3b8; }
    </style>
</head>
<body>
<h2>Debug Login - Supabase Auth</h2>

<form method="GET">
    <label>Email:</label>
    <input type="email" name="email" size="40" value="<?= htmlspecialchars($email) ?>" placeholder="tu@email.com">
    <label>Password:</label>
    <input type="password" name="password" size="40" value="<?= htmlspecialchars($password) ?>">
    <br><br>
    <button type="submit">Probar Login</button>
</form>

<?php if ($email && $password): ?>

<h3>Configuracion</h3>
<pre>
SUPABASE_URL: <?= htmlspecialchars(getenv('SUPABASE_URL')) ?>

SUPABASE_ANON_KEY (primeros 30 chars): <?= htmlspecialchars(substr(getenv('SUPABASE_ANON_KEY') ?: 'NOT SET', 0, 30)) ?>...
SSL verify: <?= getenv('SUPABASE_VERIFY_SSL') ?>
CA Bundle: <?= htmlspecialchars(getenv('SUPABASE_CA_BUNDLE') ?: 'not set') ?> (exists: <?= is_file(getenv('SUPABASE_CA_BUNDLE') ?: '') ? 'YES' : 'NO' ?>)
PHP curl: <?= extension_loaded('curl') ? 'YES' : 'NO' ?>
</pre>

<h3>Probando login para: <?= htmlspecialchars($email) ?></h3>

<?php
try {
    $client = new SupabaseClient();

    $start = microtime(true);
    $result = $client->signInWithPassword($email, $password);
    $elapsed = round((microtime(true) - $start) * 1000, 1);

    echo "<p class='info'>Tiempo de respuesta: {$elapsed}ms</p>";
    echo "<pre>";
    echo "ok: " . var_export($result['ok'] ?? null, true) . "\n";
    echo "access_token (primeros 50): " . htmlspecialchars(substr($result['access_token'] ?? '', 0, 50)) . "...\n";
    echo "refresh_token (primeros 50): " . htmlspecialchars(substr($result['refresh_token'] ?? '', 0, 50)) . "...\n";
    echo "error: " . htmlspecialchars($result['error'] ?? 'NINGUNO') . "\n";
    if (!empty($result['user'])) {
        echo "user.id: " . htmlspecialchars($result['user']['id'] ?? 'N/A') . "\n";
        echo "user.email: " . htmlspecialchars($result['user']['email'] ?? 'N/A') . "\n";
        echo "user.role: " . htmlspecialchars($result['user']['role'] ?? 'N/A') . "\n";
        echo "user.app_metadata: " . json_encode($result['user']['app_metadata'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "</pre>";

    if (!$result['ok']) {
        echo "<p class='error'>FALLO: " . htmlspecialchars($result['error'] ?? 'Error desconocido') . "</p>";

        // Intentar con curl directo para ver respuesta cruda
        echo "<h3>Prueba directa con curl (raw response)</h3>";
        $url = rtrim(getenv('SUPABASE_URL'), '/') . '/auth/v1/token?grant_type=password';
        $body = json_encode(['email' => $email, 'password' => $password]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . getenv('SUPABASE_ANON_KEY'),
                'Authorization: Bearer ' . getenv('SUPABASE_ANON_KEY'),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        // SSL
        $verifySsl = !(getenv('SUPABASE_VERIFY_SSL') === '0' || getenv('SUPABASE_VERIFY_SSL') === 'false');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);
        $caBundle = getenv('SUPABASE_CA_BUNDLE');
        if ($caBundle && is_file($caBundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        echo "<pre>";
        echo "HTTP Status: {$httpCode}\n";
        echo "Curl Error: " . ($curlError ?: 'NINGUNO') . "\n";
        echo "Raw Body: " . htmlspecialchars($raw ?: '(vacio)') . "\n";
        echo "</pre>";

        if ($raw) {
            $decoded = json_decode($raw, true);
            if ($decoded) {
                echo "<p class='error'>Supabase error_description: <strong>" . htmlspecialchars($decoded['error_description'] ?? $decoded['msg'] ?? 'N/A') . "</strong></p>";
                if (!empty($decoded['error'])) {
                    echo "<p>Supabase error code: <strong>" . htmlspecialchars($decoded['error']) . "</strong></p>";
                }
            }
        }
    } else {
        echo "<p class='ok'>LOGIN EXITOSO!</p>";
    }

    // Verificar que el usuario existe en Supabase Auth (usando service_role)
    echo "<h3>Verificacion de usuario en Supabase Auth (admin API)</h3>";
    try {
        $listResult = $client->listAuthUsers();
        if ($listResult['ok']) {
            $found = null;
            foreach ($listResult['users'] as $u) {
                if (($u['email'] ?? '') === strtolower(trim($email))) {
                    $found = $u;
                    break;
                }
            }
            if ($found) {
                echo "<p class='ok'>Usuario ENCONTRADO en Supabase Auth:</p>";
                echo "<pre>";
                echo "ID: " . htmlspecialchars($found['id']) . "\n";
                echo "Email: " . htmlspecialchars($found['email']) . "\n";
                echo "Email confirmado: " . ($found['email_confirmed_at'] ?? 'NO') . "\n";
                echo "Ultimo login: " . ($found['last_sign_in_at'] ?? 'NUNCA') . "\n";
                echo "Creado: " . ($found['created_at'] ?? '?') . "\n";
                echo "app_metadata: " . json_encode($found['app_metadata'] ?? [], JSON_PRETTY_PRINT) . "\n";
                echo "</pre>";
            } else {
                echo "<p class='error'>Usuario NO encontrado en Supabase Auth. Total users: " . count($listResult['users']) . "</p>";
                echo "<pre>";
                foreach ($listResult['users'] as $u) {
                    echo htmlspecialchars($u['email'] ?? '?') . " | created: " . ($u['created_at'] ?? '?') . "\n";
                }
                echo "</pre>";
            }
        } else {
            echo "<p class='error'>No se pudo consultar usuarios: " . htmlspecialchars($listResult['error'] ?? '?') . "</p>";
        }
    } catch (Throwable $e) {
        echo "<p class='error'>Error listando usuarios: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

} catch (Throwable $e) {
    echo "<p class='error'>EXCEPCION: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

endif;
?>

<p style="margin-top:2rem;color:#64748b;font-size:0.85rem;">
    Elimina este archivo despues de usarlo. Contiene datos sensibles en los logs.
</p>
</body>
</html>
