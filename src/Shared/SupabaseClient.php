<?php

/**
 * SupabaseClient
 *
 * Cliente HTTP completo para Supabase self-hosted:
 * - REST/PostgREST (CRUD + RPC)
 * - Auth (signIn, signOut, refresh, getUser)
 * - Storage (upload, download)
 * - Realtime (WebSocket — delegado al frontend JS)
 *
 * Variables de entorno requeridas:
 *   SUPABASE_URL, SUPABASE_ANON_KEY, SUPABASE_SERVICE_ROLE_KEY
 *   SUPABASE_TIMEOUT (opcional, default 15)
 *   SUPABASE_CA_BUNDLE (opcional, para Windows/desarrollo)
 *   SUPABASE_VERIFY_SSL (opcional, default true)
 */
class SupabaseClient
{
    private string $baseUrl;
    private string $anonKey;
    private string $serviceRoleKey;
    private int $timeoutSeconds;
    private ?string $caBundlePath;
    private bool $verifySsl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) getenv('SUPABASE_URL'), '/');
        $this->anonKey = (string) getenv('SUPABASE_ANON_KEY');
        $this->serviceRoleKey = (string) getenv('SUPABASE_SERVICE_ROLE_KEY');
        $timeout = (int) (getenv('SUPABASE_TIMEOUT') ?: 15);
        $this->timeoutSeconds = $timeout > 0 ? $timeout : 15;

        $bundle = (string) getenv('SUPABASE_CA_BUNDLE');
        $this->caBundlePath = ($bundle !== '' && is_file($bundle)) ? $bundle : null;

        $verify = strtolower((string) getenv('SUPABASE_VERIFY_SSL'));
        $this->verifySsl = !($verify === '0' || $verify === 'false' || $verify === 'no');
    }

    // ═══════════════════════════════════════════════════════
    //  CONFIGURACION / HEALTHCHECK
    // ═══════════════════════════════════════════════════════

    /**
     * Resuelve la autenticacion automatica:
     * - Si se pasa un userToken explicito → se usa ese JWT
     * - Si es null → intenta obtener el JWT del request actual (RLS-aware)
     * - Si no hay JWT ni sesion → usa service_role (backward-compat para cron/webhooks)
     *
     * @return array [string|null $token, bool $useServiceRole]
     */
    private function resolveAuth(?string $userToken): array
    {
        if ($userToken !== null) {
            return [$userToken, false];
        }

        if (function_exists('getJwtFromRequest')) {
            $jwt = getJwtFromRequest();
            if ($jwt) {
                return [$jwt, false];
            }
        }

        return [null, true];
    }

    public function validateConfiguration(): array
    {
        $errors = [];
        if ($this->baseUrl === '') {
            $errors[] = 'SUPABASE_URL no configurado';
        }
        if ($this->anonKey === '') {
            $errors[] = 'SUPABASE_ANON_KEY no configurado';
        }
        if ($this->serviceRoleKey === '') {
            $errors[] = 'SUPABASE_SERVICE_ROLE_KEY no configurado';
        }
        return ['ok' => empty($errors), 'errors' => $errors];
    }

    public function healthcheck(): array
    {
        $config = $this->validateConfiguration();
        if (!$config['ok']) {
            return ['ok' => false, 'message' => 'Configuracion incompleta de Supabase', 'errors' => $config['errors']];
        }
        $checks = [];
        $checks[] = $this->request('GET', '/auth/v1/settings', null, false);
        $checks[] = $this->request('GET', '/rest/v1/', null, true);
        $checks[] = $this->request('GET', '/storage/v1/bucket', null, true);
        $allOk = true;
        foreach ($checks as $check) {
            if (!$check['ok']) { $allOk = false; break; }
        }
        return ['ok' => $allOk, 'base_url' => $this->baseUrl, 'checks' => $checks];
    }

    // ═══════════════════════════════════════════════════════
    //  REST API (PostgREST)
    // ═══════════════════════════════════════════════════════

    /**
     * GET /rest/v1/{table}
     *
     * @param string $table          Nombre de la tabla o vista
     * @param array  $query          Query params (select, order, filters...)
     * @param string|null $userToken JWT del usuario. null = auto-detectar de sesion, '' = forzar service_role
     * @return array                 ['ok' => bool, 'data' => array, 'count' => int|null, 'error' => string|null]
     */
    public function get(string $table, array $query = [], ?string $userToken = null, bool $forceServiceRole = false): array
    {
        $qs = $this->buildQueryString($query);
        $path = '/rest/v1/' . urlencode($table) . ($qs ? '?' . $qs : '');
        if ($forceServiceRole) {
            $result = $this->request('GET', $path, null, true, null);
        } else {
            [$token, $useServiceRole] = $this->resolveAuth($userToken);
            $result = $this->request('GET', $path, null, $useServiceRole, $token);
        }
        return $this->parseApiResponse($result);
    }

    /**
     * POST /rest/v1/{table}
     */
    public function post(string $table, array $data, ?string $userToken = null, bool $forceServiceRole = false): array
    {
        $path = '/rest/v1/' . urlencode($table);
        $body = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($forceServiceRole) {
            $result = $this->request('POST', $path, $body, true, null, [
                'Prefer: return=representation',
                'Content-Type: application/json',
            ]);
        } else {
            [$token, $useServiceRole] = $this->resolveAuth($userToken);
            $result = $this->request('POST', $path, $body, $useServiceRole, $token, [
                'Prefer: return=representation',
                'Content-Type: application/json',
            ]);
        }
        return $this->parseApiResponse($result);
    }

    /**
     * PATCH /rest/v1/{table}
     */
    public function patch(string $table, array $data, array $query, ?string $userToken = null, bool $forceServiceRole = false): array
    {
        $qs = $this->buildQueryString($query);
        $path = '/rest/v1/' . urlencode($table) . ($qs ? '?' . $qs : '');
        $body = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($forceServiceRole) {
            $result = $this->request('PATCH', $path, $body, true, null, [
                'Prefer: return=representation',
                'Content-Type: application/json',
            ]);
        } else {
            [$token, $useServiceRole] = $this->resolveAuth($userToken);
            $result = $this->request('PATCH', $path, $body, $useServiceRole, $token, [
                'Prefer: return=representation',
                'Content-Type: application/json',
            ]);
        }
        return $this->parseApiResponse($result);
    }

    /**
     * DELETE /rest/v1/{table}
     */
    public function delete(string $table, array $query, ?string $userToken = null, bool $forceServiceRole = false): array
    {
        $qs = $this->buildQueryString($query);
        $path = '/rest/v1/' . urlencode($table) . ($qs ? '?' . $qs : '');
        if ($forceServiceRole) {
            $result = $this->request('DELETE', $path, null, true, null);
        } else {
            [$token, $useServiceRole] = $this->resolveAuth($userToken);
            $result = $this->request('DELETE', $path, null, $useServiceRole, $token);
        }
        return $this->parseApiResponse($result);
    }

    /**
     * POST /rest/v1/rpc/{function}
     */
    public function rpc(string $function, array $params = [], ?string $userToken = null): array
    {
        $path = '/rest/v1/rpc/' . urlencode($function);
        $body = !empty($params) ? json_encode($params, JSON_UNESCAPED_UNICODE) : '{}';
        [$token, $useServiceRole] = $this->resolveAuth($userToken);
        $result = $this->request('POST', $path, $body, $useServiceRole, $token, [
            'Content-Type: application/json',
        ]);
        return $this->parseApiResponse($result);
    }

    // ═══════════════════════════════════════════════════════
    //  AUTH
    // ═══════════════════════════════════════════════════════

    /**
     * POST /auth/v1/token?grant_type=password
     *
     * @return array ['ok' => bool, 'access_token' => string, 'refresh_token' => string, 'user' => array, 'error' => string]
     */
    public function signInWithPassword(string $email, string $password): array
    {
        $body = json_encode(['email' => $email, 'password' => $password]);
        $result = $this->request('POST', '/auth/v1/token?grant_type=password', $body, false, null, [
            'Content-Type: application/json',
        ]);

        if (!$result['ok']) {
            $errorMsg = $this->extractAuthError($result);
            return ['ok' => false, 'error' => $errorMsg];
        }

        $response = $result['data'] ?? [];
        return [
            'ok' => true,
            'access_token' => $response['access_token'] ?? '',
            'refresh_token' => $response['refresh_token'] ?? '',
            'expires_in' => $response['expires_in'] ?? 3600,
            'user' => $response['user'] ?? [],
        ];
    }

    /**
     * POST /auth/v1/token?grant_type=refresh_token
     */
    public function refreshToken(string $refreshToken): array
    {
        $body = json_encode(['refresh_token' => $refreshToken]);
        $result = $this->request('POST', '/auth/v1/token?grant_type=refresh_token', $body, false, null, [
            'Content-Type: application/json',
        ]);

        if (!$result['ok']) {
            return ['ok' => false, 'error' => 'Token refresh failed'];
        }

        $response = $result['data'] ?? [];
        return [
            'ok' => true,
            'access_token' => $response['access_token'] ?? '',
            'refresh_token' => $response['refresh_token'] ?? '',
        ];
    }

    /**
     * POST /auth/v1/logout
     */
    public function signOut(string $accessToken): array
    {
        $result = $this->request('POST', '/auth/v1/logout', null, false, $accessToken, [
            'Content-Type: application/json',
        ]);
        return ['ok' => $result['ok'], 'error' => $result['ok'] ? null : $result['message']];
    }

    /**
     * GET /auth/v1/user
     */
    public function getUser(string $jwt): array
    {
        $result = $this->request('GET', '/auth/v1/user', null, false, $jwt);
        if (!$result['ok']) {
            return ['ok' => false, 'error' => $result['message']];
        }
        return ['ok' => true, 'user' => $result['data'] ?? []];
    }

    /**
     * POST /auth/v1/admin/users (crea usuario via Admin API — requiere service_role)
     */
    public function adminCreateUser(array $userData): array
    {
        $body = json_encode($userData);
        $result = $this->request('POST', '/auth/v1/admin/users', $body, true, null, [
            'Content-Type: application/json',
        ]);

        if (!$result['ok']) {
            return ['ok' => false, 'error' => $this->extractAuthError($result)];
        }
        return ['ok' => true, 'user' => $result['data'] ?? []];
    }

    /**
     * PUT /auth/v1/admin/users/{id} (actualiza usuario via Admin API — requiere service_role)
     */
    public function adminUpdateUser(string $authUserId, array $userData): array
    {
        $body = json_encode($userData);
        $result = $this->request('PUT', '/auth/v1/admin/users/' . urlencode($authUserId), $body, true, null, [
            'Content-Type: application/json',
        ]);

        if (!$result['ok']) {
            return ['ok' => false, 'error' => $this->extractAuthError($result)];
        }
        return ['ok' => true, 'user' => $result['data'] ?? []];
    }

    /**
     * GET /auth/v1/admin/users — lista todos los usuarios Auth (requiere service_role)
     */
    public function listAuthUsers(int $page = 1, int $perPage = 1000): array
    {
        $result = $this->request(
            'GET',
            '/auth/v1/admin/users?page=' . $page . '&per_page=' . $perPage,
            null,
            true
        );
        if (!$result['ok']) {
            return ['ok' => false, 'users' => [], 'error' => $result['message']];
        }
        $data = $result['data'] ?? [];
        $users = $data['users'] ?? (isset($data[0]) ? $data : []);
        return ['ok' => true, 'users' => is_array($users) ? $users : []];
    }

    /**
     * DELETE /auth/v1/admin/users/{id} (elimina usuario via Admin API — requiere service_role)
     */
    public function adminDeleteUser(string $authUserId): array
    {
        $result = $this->request('DELETE', '/auth/v1/admin/users/' . urlencode($authUserId), null, true, null);

        if (!$result['ok']) {
            return ['ok' => false, 'error' => $this->extractAuthError($result)];
        }
        return ['ok' => true];
    }

    /**
     * Decodifica un JWT sin verificar firma (solo para leer claims).
     */
    public static function decodeJwtClaims(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return null;

        $payload = $parts[1];
        $decoded = base64_decode(strtr($payload, '-_', '+/'), true);
        if ($decoded === false) return null;

        return json_decode($decoded, true) ?: null;
    }

    // ═══════════════════════════════════════════════════════
    //  STORAGE
    // ═══════════════════════════════════════════════════════

    /**
     * POST /storage/v1/object/{bucket}/{path}
     *
     * @param string $bucket      Nombre del bucket
     * @param string $path        Ruta del objeto (ej. "1/42_datos.json")
     * @param string $content     Contenido binario o texto
     * @param string $contentType MIME type (default: application/octet-stream)
     * @param string|null $userToken JWT del usuario
     * @return array ['ok' => bool, 'error' => string|null]
     */
    public function uploadFile(
        string $bucket,
        string $path,
        string $content,
        string $contentType = 'application/octet-stream',
        ?string $userToken = null
    ): array {
        $encodedBucket = rawurlencode($bucket);
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
        $url = "/storage/v1/object/{$encodedBucket}/{$encodedPath}";

        [$token, $useServiceRole] = $this->resolveAuth($userToken);
        $result = $this->request('POST', $url, $content, $useServiceRole, $token, [
            'Content-Type: ' . $contentType,
            'x-upsert: true',
        ]);

        return ['ok' => $result['ok'], 'error' => $result['ok'] ? null : $result['message']];
    }

    /**
     * GET /storage/v1/object/{bucket}/{path}
     *
     * @return string|null Contenido del archivo, o null si no existe
     */
    public function downloadFile(string $bucket, string $path, ?string $userToken = null): ?string
    {
        $encodedBucket = rawurlencode($bucket);
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
        $url = "/storage/v1/object/{$encodedBucket}/{$encodedPath}";

        [$token, $useServiceRole] = $this->resolveAuth($userToken);
        $result = $this->request('GET', $url, null, $useServiceRole, $token);

        if (!$result['ok']) return null;
        return $result['raw_body'] ?? null;
    }

    /**
     * DELETE /storage/v1/object/{bucket}/{path}
     */
    public function deleteFile(string $bucket, string $path, ?string $userToken = null): array
    {
        $encodedBucket = rawurlencode($bucket);
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
        $url = "/storage/v1/object/{$encodedBucket}/{$encodedPath}";
        [$token, $useServiceRole] = $this->resolveAuth($userToken);
        $result = $this->request('DELETE', $url, null, $useServiceRole, $token);
        return ['ok' => $result['ok'], 'error' => $result['ok'] ? null : $result['message']];
    }

    // ═══════════════════════════════════════════════════════
    //  INTERNAL: HTTP request
    // ═══════════════════════════════════════════════════════

    /**
     * @param string $method         GET|POST|PATCH|DELETE
     * @param string $path           /rest/v1/..., /auth/v1/..., /storage/v1/...
     * @param string|null $body      JSON payload (o contenido binario para Storage)
     * @param bool $useServiceRole   Usar SERVICE_ROLE_KEY (true) o ANON_KEY (false)
     * @param string|null $userToken JWT del usuario (si != null, se usa en vez de la key)
     * @param array $extraHeaders    Headers adicionales
     * @return array                 ['ok' => bool, 'status_code' => int, 'data' => array|null, 'raw_body' => string|null, 'message' => string]
     */
    private function request(
        string $method,
        string $path,
        ?string $body,
        bool $useServiceRole,
        ?string $userToken = null,
        array $extraHeaders = []
    ): array {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);

        if ($ch === false) {
            return ['ok' => false, 'path' => $path, 'status_code' => 0, 'data' => null, 'raw_body' => null, 'message' => 'No se pudo inicializar cURL'];
        }

        // Header de autorización
        if ($userToken !== null) {
            $authHeader = 'Authorization: Bearer ' . $userToken;
            $apiKeyHeader = 'apikey: ' . $this->anonKey;
        } elseif ($useServiceRole) {
            $authHeader = 'Authorization: Bearer ' . $this->serviceRoleKey;
            $apiKeyHeader = 'apikey: ' . $this->serviceRoleKey;
        } else {
            $authHeader = 'Authorization: Bearer ' . $this->anonKey;
            $apiKeyHeader = 'apikey: ' . $this->anonKey;
        }

        if ($body !== null) {
            $hasContentType = false;
            foreach ($extraHeaders as $h) {
                if (stripos($h, 'Content-Type:') === 0) {
                    $hasContentType = true;
                    break;
                }
            }
            if (!$hasContentType) {
                $extraHeaders[] = 'Content-Type: application/json';
            }
        }

        $headers = array_merge(
            [$apiKeyHeader, $authHeader, 'Accept: application/json'],
            $extraHeaders
        );

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeoutSeconds);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeoutSeconds);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);
        if ($this->caBundlePath !== null) {
            curl_setopt($ch, CURLOPT_CAINFO, $this->caBundlePath);
        }

        // Reutilizar conexiones TCP para requests secuenciales (evita TLS handshake por cada call)
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);

        $rawBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($rawBody === false) {
            return [
                'ok' => false, 'path' => $path, 'status_code' => $statusCode,
                'data' => null, 'raw_body' => null,
                'message' => 'Error de red: ' . $curlError,
            ];
        }

        $ok = $statusCode >= 200 && $statusCode < 300;

        return [
            'ok' => $ok,
            'path' => $path,
            'status_code' => $statusCode,
            'data' => $this->safeJsonDecode($rawBody),
            'raw_body' => $rawBody,
            'message' => $ok ? 'OK' : 'HTTP ' . $statusCode,
        ];
    }

    // ═══════════════════════════════════════════════════════
    //  INTERNAL: helpers
    // ═══════════════════════════════════════════════════════

    private function safeJsonDecode(string $raw): mixed
    {
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $decoded;
    }

    private function parseApiResponse(array $result): array
    {
        if (!$result['ok']) {
            return ['ok' => false, 'data' => [], 'error' => $result['message'] ?? 'Error de API', 'status_code' => $result['status_code']];
        }
        return ['ok' => true, 'data' => $result['data'] ?? [], 'error' => null, 'status_code' => $result['status_code']];
    }

    private function extractAuthError(array $result): string
    {
        $data = $result['data'] ?? [];
        if (isset($data['error_description'])) {
            return (string) $data['error_description'];
        }
        if (isset($data['msg'])) {
            return (string) $data['msg'];
        }
        if (isset($data['message'])) {
            return (string) $data['message'];
        }
        return $result['message'] ?? 'Error de autenticacion';
    }

    private function buildQueryString(array $params): string
    {
        if (empty($params)) return '';
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === null) continue;
            if (is_array($value)) {
                foreach ($value as $v) {
                    $parts[] = urlencode($key) . '=' . urlencode((string) $v);
                }
            } else {
                $parts[] = urlencode($key) . '=' . urlencode((string) $value);
            }
        }
        return implode('&', $parts);
    }
}
