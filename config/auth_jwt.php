<?php
/**
 * auth_jwt.php — Funciones de autenticacion via JWT en cookie HttpOnly.
 *
 * Este archivo NO inicia sesion PHP ni incluye db.php.
 * Solo define constantes y helpers reutilizables.
 * Es incluido por auth_bridge.php y auth.php.
 */

// Dependencias para sincronizarPerfilDesdeJwt()
if (!class_exists('UsuarioRepository', false)) {
    require_once dirname(__DIR__) . '/src/Usuarios/UsuarioRepository.php';
}
if (!class_exists('UsuarioService', false)) {
    require_once dirname(__DIR__) . '/src/Usuarios/UsuarioService.php';
}

// ─── Constantes de cookies ───
define('JWT_COOKIE', 'solcel_jwt');
define('REFRESH_COOKIE', 'solcel_refresh');

// ─── Cache de claims por request ───
$_JWT_CLAIMS_CACHE = null;
$_JWT_PROFILE_SYNCED = false;

// ─── JWT decode (sin verificacion de firma) ───

function jwtDecode(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
    if ($payload === false) return null;
    return json_decode($payload, true) ?: null;
}

function jwtIsValid(string $token): bool
{
    $claims = jwtDecode($token);
    if (!$claims) return false;

    $now = time();

    // Expiry (exp) — obligatorio
    if (!isset($claims['exp']) || $claims['exp'] <= $now) {
        return false;
    }

    // Not Before (nbf) — si existe, debe ser pasado
    if (isset($claims['nbf']) && $claims['nbf'] > $now) {
        return false;
    }

    // Issuer (iss) — debe coincidir con la URL de Supabase
    $supabaseUrl = rtrim((string) getenv('SUPABASE_URL'), '/');
    if ($supabaseUrl !== '' && isset($claims['iss'])) {
        if ($claims['iss'] !== $supabaseUrl && $claims['iss'] !== ($supabaseUrl . '/')) {
            return false;
        }
    }

    // Audience (aud) — debe ser 'authenticated'
    if (isset($claims['aud'])) {
        $aud = is_array($claims['aud']) ? $claims['aud'] : [$claims['aud']];
        if (!in_array('authenticated', $aud, true)) {
            return false;
        }
    }

    return true;
}

// ─── Extraer JWT del request ───

function getJwtFromRequest(): ?string
{
    // 1. Cookie HttpOnly (principal, seteada por PHP tras login)
    if (!empty($_COOKIE[JWT_COOKIE])) {
        return $_COOKIE[JWT_COOKIE];
    }

    // 2. Authorization header (para fetch() desde JS)
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (!empty($headers['Authorization'])) {
            $parts = explode(' ', $headers['Authorization'], 2);
            if (count($parts) === 2 && strcasecmp($parts[0], 'Bearer') === 0) {
                return $parts[1];
            }
        }
    } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $parts = explode(' ', $_SERVER['HTTP_AUTHORIZATION'], 2);
        if (count($parts) === 2 && strcasecmp($parts[0], 'Bearer') === 0) {
            return $parts[1];
        }
    }

    // 3. Fallback temporal: sesion PHP (compatibilidad durante migracion)
    if (!empty($_SESSION['supabase_access_token'])) {
        return $_SESSION['supabase_access_token'];
    }

    return null;
}

// ─── Obtener claims verificados (cache por request) ───

function getJwtClaims(): ?array
{
    global $_JWT_CLAIMS_CACHE;
    if ($_JWT_CLAIMS_CACHE !== null) {
        return $_JWT_CLAIMS_CACHE ?: null;
    }

    $jwt = getJwtFromRequest();
    if (!$jwt) {
        $_JWT_CLAIMS_CACHE = false;
        return null;
    }

    $claims = jwtDecode($jwt);
    if (!$claims || (isset($claims['exp']) && $claims['exp'] <= time())) {
        $_JWT_CLAIMS_CACHE = false;
        return null;
    }

    $_JWT_CLAIMS_CACHE = $claims;
    return $claims;
}

// ─── Helpers de identidad del usuario desde JWT ───

function getCurrentAuthUserId(): ?string
{
    $claims = getJwtClaims();
    return $claims['sub'] ?? null;
}

function getCurrentRole(): string
{
    // 1. Cache de sesion (mas rapido, poblado tras login)
    if (!empty($_SESSION['user_role'])) {
        return $_SESSION['user_role'];
    }
    // 2. JWT app_metadata
    $claims = getJwtClaims();
    if ($claims) {
        return $claims['app_metadata']['rol'] ?? 'usuario';
    }
    return '';
}

function getCurrentTenantId(): ?int
{
    if (!empty($_SESSION['tenant_id'])) {
        return (int) $_SESSION['tenant_id'];
    }
    $claims = getJwtClaims();
    if ($claims) {
        $tenantId = (int) ($claims['app_metadata']['tenant_id'] ?? 0);
        if ($tenantId > 0) return $tenantId;
    }
    return null;
}

function getCurrentUsername(): string
{
    if (!empty($_SESSION['username'])) {
        return $_SESSION['username'];
    }
    $claims = getJwtClaims();
    return $claims['email'] ?? '';
}

function getCurrentFullName(): string
{
    if (!empty($_SESSION['nombre_completo'])) {
        return $_SESSION['nombre_completo'];
    }
    $claims = getJwtClaims();
    return $claims['user_metadata']['full_name'] ?? ($claims['email'] ?? '');
}

function getCurrentUserId(): ?int
{
    if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
        return (int) $_SESSION['user_id'];
    }
    return null;
}

// ─── Cookies JWT ───

function setJwtCookies(string $accessToken, string $refreshToken): void
{
    $claims = jwtDecode($accessToken);
    $jwtExpiry = $claims['exp'] ?? (time() + 3600);
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
}

function clearJwtCookies(): void
{
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie(JWT_COOKIE, '', ['expires' => 1, 'path' => '/', 'domain' => '', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
    setcookie(REFRESH_COOKIE, '', ['expires' => 1, 'path' => '/', 'domain' => '', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
}

// ─── Sincronizar perfil de usuario desde JWT a sesion PHP (cache) ───

function sincronizarPerfilDesdeJwt(string $jwt): void
{
    global $_JWT_PROFILE_SYNCED;
    if ($_JWT_PROFILE_SYNCED) return;

    $claims = jwtDecode($jwt);
    if (!$claims) return;

    $appMeta = $claims['app_metadata'] ?? [];
    $tenantId = (int) ($appMeta['tenant_id'] ?? 0);
    if ($tenantId < 1) {
        $tenantId = (int) (getenv('TENANT_ID_DEFAULT') ?: 1);
    }
    $authUserId = $claims['sub'] ?? '';

    $_SESSION['tenant_id'] = $tenantId;

    // Buscar perfil en tabla usuarios via API
    if (!empty($authUserId) && class_exists('SupabaseClient')) {
        try {
            $client = new SupabaseClient();
            $userRepo = new UsuarioRepository($client);
            $authUserData = [
                'id'            => $authUserId,
                'email'         => $claims['email'] ?? '',
                'user_metadata' => $claims['user_metadata'] ?? [],
                'app_metadata'  => $appMeta,
            ];
            $perfilUsuario = $userRepo->upsertFromAuthUser($authUserData, $tenantId, UsuarioService::DEFAULT_MODULOS);

            if (!empty($perfilUsuario) && !empty($perfilUsuario['id'])) {
                $_SESSION['user_id'] = (int) $perfilUsuario['id'];
                $_SESSION['user_role'] = $perfilUsuario['rol'];
                $_SESSION['username'] = $perfilUsuario['username'];
                $_SESSION['nombre_completo'] = $perfilUsuario['nombre_completo'];
                $rawMod = $perfilUsuario['modulos_permitidos'] ?? null;
                if (is_array($rawMod)) {
                    $_SESSION['modulos_permitidos'] = $rawMod;
                } elseif (is_string($rawMod)) {
                    $_SESSION['modulos_permitidos'] = json_decode($rawMod, true) ?? UsuarioService::DEFAULT_MODULOS;
                } else {
                    $_SESSION['modulos_permitidos'] = UsuarioService::DEFAULT_MODULOS;
                }
                $_JWT_PROFILE_SYNCED = true;
                return;
            }
        } catch (Exception $e) {
            error_log("Error buscando perfil usuario via JWT: " . $e->getMessage());
        }
    }

    // Fallback: usar claims del JWT (sin user_id integer — no hay perfil en BD)
    unset($_SESSION['user_id']);
    $_SESSION['auth_user_id'] = $authUserId;
    $_SESSION['user_role'] = $appMeta['rol'] ?? 'usuario';
    $_SESSION['username'] = $claims['email'] ?? '';
    $_SESSION['nombre_completo'] = $claims['user_metadata']['full_name'] ?? ($claims['email'] ?? '');

    $_JWT_PROFILE_SYNCED = true;
}
