<?php
include_once __DIR__ . '/env_loader.php';
include_once __DIR__ . '/db.php';

// Funciones JWT cookie (getJwtFromRequest, getJwtClaims, etc.)
require_once __DIR__ . '/auth_jwt.php';

// TenantContext
if (!class_exists('TenantContext', false)) {
    require_once dirname(__DIR__) . '/src/Shared/TenantContext.php';
}

// SupabaseClient
if (!class_exists('SupabaseClient', false)) {
    require_once dirname(__DIR__) . '/src/Shared/SupabaseClient.php';
}

// UsuarioRepository (needed for profile auto-creation during sync)
if (!class_exists('UsuarioRepository', false)) {
    require_once dirname(__DIR__) . '/src/Usuarios/UsuarioRepository.php';
}
if (!class_exists('UsuarioService', false)) {
    require_once dirname(__DIR__) . '/src/Usuarios/UsuarioService.php';
}

// ─── Configuracion de sesion PHP (solo para CSRF + cache de perfil) ───
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 2592000;
    ini_set('session.gc_maxlifetime', $lifetime);
    ini_set('session.cookie_lifetime', $lifetime);
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// ─── Restaurar contexto de tenant ───
// Lee de cookie JWT (primario) o sesion (cache)
$jwt = getJwtFromRequest();
if ($jwt && jwtIsValid($jwt)) {
    // Sincronizar perfil desde JWT a cache de sesion si no esta ya
    if (empty($_SESSION['tenant_id']) || empty($_SESSION['user_id'])) {
        sincronizarPerfilDesdeJwt($jwt);
    }
    $tenantId = $_SESSION['tenant_id'] ?? getCurrentTenantId();
    if ($tenantId) {
        TenantContext::setTenantId((int) $tenantId);
    }
} elseif (!empty($_SESSION['tenant_id'])) {
    TenantContext::setTenantId((int) $_SESSION['tenant_id']);
}

// ═══════════════════════════════════════════════════════
//  FUNCIONES DE SESION / LOGS (via Supabase API)
// ═══════════════════════════════════════════════════════

function logSesion(?int $userId, string $tipo_usuario, string $accion): void
{
    $tenantId = TenantContext::getTenantIdOrDefault();
    try {
        getSupabase()->post('sesiones_log', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'tipo_usuario' => $tipo_usuario,
            'accion' => $accion,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
    } catch (Exception $e) {
        error_log("Error logging sesion: " . $e->getMessage());
    }
}

function registrarActividad($accion, $detalle, $entidad_id = null, $entidad_tipo = null): void
{
    $tenantId = TenantContext::getTenantIdOrDefault();
    $userId = getCurrentUserId();
    try {
        getSupabase()->post('actividad_logs', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'accion' => $accion,
            'detalle' => $detalle,
            'entidad_id' => $entidad_id,
            'entidad_tipo' => $entidad_tipo,
        ]);
    } catch (Exception $e) {
        error_log("Error auditoria: " . $e->getMessage());
    }
}

// ═══════════════════════════════════════════════════════
//  PERMISOS (basados en JWT, con fallback a sesion)
// ═══════════════════════════════════════════════════════

function isAdmin(): bool
{
    // 1. Verificar via claims del JWT (app_metadata.rol)
    $jwt = getJwtFromRequest();
    if ($jwt) {
        $claims = jwtDecode($jwt);
        if ($claims && ($claims['app_metadata']['rol'] ?? '') === 'admin') {
            return true;
        }
    }
    // 2. Fallback: cache de sesion PHP
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function tienePermiso(string $slug): bool
{
    if (isAdmin()) return true;

    $rol = getCurrentRole();
    if ($rol === '') return false;

    try {
        $result = getSupabase()->rpc('tiene_permiso', ['p_rol' => $rol, 'p_slug' => $slug]);
        return $result['ok'] && $result['data'] === true;
    } catch (Throwable $e) {
        return false;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        redirectTo('index', 'error=no_autorizado');
    }
}

function puedeVerModulo(string $slug): bool
{
    if (isAdmin()) return true;
    $modulos = $_SESSION['modulos_permitidos'] ?? null;
    if ($modulos === null) {
        return in_array($slug, UsuarioService::DEFAULT_MODULOS, true);
    }
    if (is_string($modulos)) {
        $modulos = json_decode($modulos, true) ?? [];
    }
    return in_array($slug, (array)$modulos, true);
}

// ═══════════════════════════════════════════════════════
//  PROTECCION DE PAGINAS (JWT cookie + refresh + sesion fallback)
// ═══════════════════════════════════════════════════════

function requireLogin(): void
{
    // ── 1. Verificar JWT en cookie HttpOnly ──
    $jwt = getJwtFromRequest();
    if ($jwt && jwtIsValid($jwt)) {
        // Sincronizar cache de sesion si es necesario
        if (empty($_SESSION['tenant_id']) || empty($_SESSION['user_id'])) {
            sincronizarPerfilDesdeJwt($jwt);
        }
        // Backwards compat: mantener $_SESSION['supabase_access_token'] actualizado
        $_SESSION['supabase_access_token'] = $jwt;
        return;
    }

    // ── 2. JWT expirado — intentar refresh ──
    if (!empty($_COOKIE[REFRESH_COOKIE])) {
        try {
            $client = new SupabaseClient();
            $refresh = $client->refreshToken($_COOKIE[REFRESH_COOKIE]);
            if ($refresh['ok']) {
                $newJwt = $refresh['access_token'];
                $newRefresh = $refresh['refresh_token'];

                setJwtCookies($newJwt, $newRefresh);
                sincronizarPerfilDesdeJwt($newJwt);
                $_SESSION['supabase_access_token'] = $newJwt;
                return;
            }
        } catch (Throwable $e) {
            error_log("JWT refresh failed: " . $e->getMessage());
        }
    }

    // ── 3. Fallback: sesion PHP legacy (compatibilidad) ──
    if (!empty($_SESSION['supabase_access_token'])) {
        if (jwtIsValid($_SESSION['supabase_access_token'])) {
            // Migrar a cookie para futuros requests
            $refresh = $_SESSION['supabase_refresh_token'] ?? '';
            if ($refresh !== '') {
                setJwtCookies($_SESSION['supabase_access_token'], $refresh);
            } else {
                // Solo cookie del JWT, sin refresh
                $claims = jwtDecode($_SESSION['supabase_access_token']);
                $expiry = $claims['exp'] ?? (time() + 3600);
                $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                setcookie(JWT_COOKIE, $_SESSION['supabase_access_token'], [
                    'expires' => $expiry, 'path' => '/', 'domain' => '',
                    'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict'
                ]);
            }
            return;
        }
    }

    // ── 4. Sin sesion valida → redirect login ──
    TenantContext::clear();
    session_unset();
    session_destroy();
    clearJwtCookies();
    redirectTo('login');
}

// ═══════════════════════════════════════════════════════
//  LOGIN: SUPABASE AUTH (POST handler)
// ═══════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Error de seguridad: Token CSRF invalido.");
    }

    $inputUser = trim($_POST['email'] ?? $_POST['username'] ?? '');
    $inputPass = $_POST['password'] ?? '';
    $supabaseError = null;

    // ── SUPABASE AUTH ──
    $supabaseUrl = getenv('SUPABASE_URL');
    if (!empty($supabaseUrl) && filter_var($inputUser, FILTER_VALIDATE_EMAIL)) {
        try {
            $client = new SupabaseClient();
            $result = $client->signInWithPassword($inputUser, $inputPass);

            if ($result['ok']) {
                $accessToken = $result['access_token'];
                $refreshToken = $result['refresh_token'];
                $claims = jwtDecode($accessToken);
                $appMeta = $claims['app_metadata'] ?? [];
                $tenantId = (int) ($appMeta['tenant_id'] ?? 0);
                $rol = $appMeta['rol'] ?? 'usuario';
                $authUserId = $claims['sub'] ?? '';

                if ($tenantId < 1) {
                    $tenantId = (int) (getenv('TENANT_ID_DEFAULT') ?: 1);
                }

                TenantContext::setTenantId($tenantId);

                // ── Establecer cookies HttpOnly (fuente de verdad) ──
                setJwtCookies($accessToken, $refreshToken);

                // ── Cache de sesion PHP (perfil, no auth) ──
                $_SESSION['tenant_id'] = $tenantId;

                // Buscar perfil en tabla usuarios; si no existe, auto-crear
                $perfilUsuario = null;
                if (!empty($authUserId)) {
                    try {
                        $userRepo = new UsuarioRepository($client);
                        $authUserData = [
                            'id'    => $authUserId,
                            'email' => $inputUser,
                            'user_metadata' => $claims['user_metadata'] ?? [],
                            'app_metadata'  => $appMeta,
                        ];
                        $perfilUsuario = $userRepo->upsertFromAuthUser($authUserData, $tenantId, UsuarioService::DEFAULT_MODULOS);
                        if (empty($perfilUsuario['id'])) {
                            $perfilUsuario = null;
                        }
                    } catch (Exception $e) {
                        error_log("Error buscando/creando perfil usuario: " . $e->getMessage());
                    }
                }

                if ($perfilUsuario) {
                    $_SESSION['user_id'] = (int) $perfilUsuario['id'];
                    $_SESSION['user_role'] = $perfilUsuario['rol'];
                    $_SESSION['username'] = $perfilUsuario['username'];
                    $_SESSION['nombre_completo'] = $perfilUsuario['nombre_completo'];
                } else {
                    unset($_SESSION['user_id']);
                    $_SESSION['auth_user_id'] = $authUserId;
                    $_SESSION['user_role'] = $rol;
                    $_SESSION['username'] = $claims['email'] ?? $inputUser;
                    $_SESSION['nombre_completo'] = $claims['user_metadata']['full_name']
                        ?? $claims['email'] ?? $inputUser;
                }

                $_SESSION['user_logged_in'] = true;
                $_SESSION['remote_addr'] = $_SERVER['REMOTE_ADDR'];

                // Backwards compat (IREMOS removiendo gradualmente)
                $_SESSION['supabase_access_token'] = $accessToken;
                $_SESSION['supabase_refresh_token'] = $refreshToken;

                logSesion($perfilUsuario ? (int) $perfilUsuario['id'] : null, 'usuario', 'login_exitoso');
                redirectTo('index');
            }

            $supabaseError = $result['error'] ?? 'credenciales';
            error_log("Supabase login fallido para {$inputUser}: " . ($supabaseError ?: 'sin error especifico'));
            error_log("Supabase raw response: ok={$result['ok']}, error={$supabaseError}");
        } catch (Throwable $e) {
            error_log("Supabase login exception para {$inputUser}: " . $e->getMessage());
        }
    }

    // ── LOGIN FALLIDO ──
    $fallbackTenant = (int) (getenv('TENANT_ID_DEFAULT') ?: 1);
    TenantContext::setTenantId($fallbackTenant);
    logSesion(null, 'usuario', 'login_fallido');

    // Mapear errores de Supabase a mensajes genericos (sin exponer detalles)
    $errorMap = [
        'Invalid login credentials' => 'credenciales',
        'Email not confirmed' => 'email_no_confirmado',
        'Too many requests' => 'rate_limit',
    ];
    $errorMsg = $errorMap[$supabaseError] ?? 'credenciales';
    sleep(1);
    redirectTo('login', "error={$errorMsg}");
}

// ═══════════════════════════════════════════════════════
//  LOGOUT
// ═══════════════════════════════════════════════════════

if (isset($_GET['logout'])) {
    $logUserId = getCurrentUserId();
    $logRole = getCurrentRole();

    if ($logUserId || !empty($_SESSION['username'])) {
        logSesion($logUserId, $logRole ?: 'usuario', 'logout');
    }

    // Sign out en Supabase via API
    $jwt = getJwtFromRequest();
    if ($jwt) {
        try {
            $client = new SupabaseClient();
            $client->signOut($jwt);
        } catch (Throwable $e) {
            error_log("Supabase signOut error: " . $e->getMessage());
        }
    }

    // Limpiar cookies JWT
    clearJwtCookies();

    // Limpiar sesion PHP
    TenantContext::clear();
    session_unset();
    session_destroy();

    redirectTo('login');
}

// ═══════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════

/**
 * Detecta si el request actual es un fragment request del SPA router
 */
function isFragmentRequest(): bool
{
    return (!empty($_GET['fragment']) && $_GET['fragment'] == '1')
        || (!empty($_SERVER['HTTP_X_FRAGMENT']) && $_SERVER['HTTP_X_FRAGMENT'] == '1');
}

function redirectTo(string $path, string $query = ''): void
{
    $url = APP_BASE_PATH . $path;
    if ($query !== '') {
        $url .= (strpos($url, '?') === false ? '?' : '&') . $query;
    }

    // Fragment requests: retornar 401/403 JSON para que el SPA router maneje el redirect
    if (isFragmentRequest()) {
        http_response_code($path === 'login' ? 401 : 403);
        header('Content-Type: application/json');
        header('X-Redirect-Url: ' . $url);
        echo json_encode(['redirect' => $url]);
        exit;
    }

    header("Location: {$url}");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
