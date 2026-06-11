<?php

class UsuarioService
{
    public const DEFAULT_MODULOS = ['equipos','clientes','inventario','soporte','mes_azul'];
    public const VALID_MODULOS   = ['equipos','clientes','inventario','soporte','mes_azul','analiticas','plantillas'];

    private UsuarioRepository $repo;

    public function __construct(UsuarioRepository $repo)
    {
        $this->repo = $repo;
    }

    // ─── CREAR USUARIO ───

    public function crearUsuario(array $input): array
    {
        $email   = trim($input['email'] ?? '');
        $username = trim($input['username'] ?? '');
        $password = (string) ($input['password'] ?? '');
        $passwordConfirm = (string) ($input['password_confirm'] ?? '');
        $nombre  = trim($input['nombre'] ?? '');
        if ($nombre === '') {
            $nombre = trim($input['nombre_completo'] ?? $username);
        }
        $rol = trim($input['rol'] ?? 'usuario');

        if ($email === '' || $username === '' || $password === '') {
            return ['success' => false, 'error' => 'Email, nombre de usuario y contraseña son obligatorios'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'El email no tiene un formato válido'];
        }
        if ($password !== $passwordConfirm) {
            return ['success' => false, 'error' => 'Las contraseñas no coinciden'];
        }
        if (strlen($password) < 6) {
            return ['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres'];
        }

        $rol = in_array($rol, ['admin', 'usuario'], true) ? $rol : 'usuario';

        try {
            $existing = $this->repo->findUsuarioByUsername($username);
            if ($existing) {
                return ['success' => false, 'error' => 'El usuario ya existe'];
            }

            $tenantId = TenantContext::requireTenant();
            if ($rol === 'admin') {
                $modulos = self::VALID_MODULOS;
            } else {
                $rawMod  = $input['modulos'] ?? null;
                $modulos = (is_array($rawMod) && !empty($rawMod))
                    ? array_values(array_intersect($rawMod, self::VALID_MODULOS))
                    : self::DEFAULT_MODULOS;
                if (empty($modulos)) $modulos = self::DEFAULT_MODULOS;
            }

            $supabase   = new SupabaseClient();
            $authResult = $supabase->adminCreateUser([
                'email'          => $email,
                'password'       => $password,
                'email_confirm'  => true,
                'user_metadata'  => ['full_name' => $nombre, 'username' => $username],
                'app_metadata'   => [
                    'tenant_id' => (string) $tenantId,
                    'rol'       => $rol,
                    'modulos'   => $modulos,
                ],
            ]);

            if (!$authResult['ok']) {
                return ['success' => false, 'error' => 'Error en Supabase Auth: ' . ($authResult['error'] ?? 'desconocido')];
            }

            $authUserId = $authResult['user']['id'] ?? '';
            if ($authUserId === '') {
                return ['success' => false, 'error' => 'No se obtuvo el ID del usuario de Supabase Auth'];
            }

            $this->repo->insertUsuario(
                $username, $authUserId, $nombre, $rol,
                getCurrentUserId(), $modulos
            );

            if (function_exists('registrarActividad')) {
                registrarActividad('usuario_creado', "Usuario {$username} ({$email}) creado con rol {$rol}", null, 'usuario');
            }

            return ['success' => true, 'msg' => 'Usuario creado exitosamente'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error: ' . $e->getMessage()];
        }
    }

    // ─── ACTUALIZAR USUARIO ───

    public function actualizarUsuario(array $input): array
    {
        $id      = (int) ($input['id'] ?? 0);
        $username = trim($input['username'] ?? '');
        $nombre  = trim($input['nombre'] ?? '');
        if ($nombre === '') $nombre = trim($input['nombre_completo'] ?? $username);
        $password        = (string) ($input['password'] ?? '');
        $passwordConfirm = (string) ($input['password_confirm'] ?? '');

        if ($id <= 0)       return ['success' => false, 'error' => 'ID de usuario inválido'];
        if ($username === '') return ['success' => false, 'error' => 'El nombre de usuario es obligatorio'];

        $cambiarPassword = ($password !== '' || $passwordConfirm !== '');
        if ($cambiarPassword) {
            if ($password !== $passwordConfirm) {
                return ['success' => false, 'error' => 'Las contraseñas no coinciden'];
            }
            if (strlen($password) < 6) {
                return ['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres'];
            }
        }

        try {
            $existing = $this->repo->findUsuarioByUsername($username);
            if ($existing && (int) $existing['id'] !== $id) {
                return ['success' => false, 'error' => 'Ya existe otro usuario con ese nombre'];
            }

            $usuario = $this->repo->findUsuarioById($id);
            if (!$usuario) {
                return ['success' => false, 'error' => 'Usuario no encontrado'];
            }

            // Rol: usar el enviado si es válido, sino mantener el actual
            $rolActual = $usuario['rol'] ?? 'usuario';
            $rolInput  = trim($input['rol'] ?? '');
            $nuevoRol  = in_array($rolInput, ['admin', 'usuario'], true) ? $rolInput : $rolActual;

            // Módulos según rol resultante
            if ($nuevoRol === 'admin') {
                $modulos = self::VALID_MODULOS;
            } else {
                $rawMod = $input['modulos'] ?? null;
                if (is_array($rawMod) && !empty($rawMod)) {
                    $modulos = array_values(array_intersect($rawMod, self::VALID_MODULOS));
                    if (empty($modulos)) $modulos = ['equipos'];
                } else {
                    $currentMod = $usuario['modulos_permitidos'] ?? null;
                    if (is_array($currentMod)) {
                        $modulos = $currentMod;
                    } elseif (is_string($currentMod)) {
                        $modulos = json_decode($currentMod, true) ?? self::DEFAULT_MODULOS;
                    } else {
                        $modulos = self::DEFAULT_MODULOS;
                    }
                }
            }

            $this->repo->updateUsuario($id, $username, $nombre, $nuevoRol, $modulos);

            $authUserId = $usuario['auth_user_id'] ?? null;
            if ($authUserId) {
                $authUpdate = ['app_metadata' => ['rol' => $nuevoRol, 'modulos' => $modulos]];
                if ($cambiarPassword) {
                    $authUpdate['password'] = $password;
                }
                try {
                    $supabase = new SupabaseClient();
                    $upd = $supabase->adminUpdateUser($authUserId, $authUpdate);
                    if (!$upd['ok']) {
                        return ['success' => false, 'error' => 'Usuario actualizado pero falló la actualización en Auth: ' . ($upd['error'] ?? '')];
                    }
                } catch (Throwable $e) {
                    error_log("Error actualizando usuario en Auth: " . $e->getMessage());
                }
            }

            if (function_exists('registrarActividad')) {
                registrarActividad('usuario_editado', "Usuario ID {$id} actualizado", $id, 'usuario');
            }

            return ['success' => true, 'msg' => 'Usuario actualizado correctamente'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ─── CREAR ROL ───

    public function crearRol(array $input): array
    {
        $nombreRol = trim($input['nombre_rol'] ?? '');
        $slugRol   = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($input['slug_rol'] ?? '')));

        if ($nombreRol === '' || $slugRol === '') {
            return ['success' => false, 'error' => 'Nombre y slug del rol son obligatorios'];
        }

        try {
            if ($this->repo->findRolBySlug($slugRol)) {
                return ['success' => false, 'error' => 'Ya existe un rol con ese slug'];
            }
            $id = $this->repo->insertRol($nombreRol, $slugRol);
            if (function_exists('registrarActividad')) {
                registrarActividad('rol_creado', "Rol {$nombreRol} ({$slugRol}) creado", $id, 'rol');
            }
            return ['success' => true, 'msg' => 'Rol creado', 'id' => $id];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error de BD: ' . $e->getMessage()];
        }
    }

    // ─── ELIMINAR USUARIO ───

    public function eliminarUsuario(int $id): array
    {
        if ($id <= 0) return ['success' => false, 'error' => 'ID de usuario inválido'];

        try {
            $this->repo->softDeleteUsuario($id);
            if (function_exists('registrarActividad')) {
                registrarActividad('usuario_eliminado', "Usuario ID {$id} eliminado", $id, 'usuario');
            }
            return ['success' => true, 'msg' => 'Usuario eliminado'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error de BD'];
        }
    }

    // ─── LISTADOS ───

    public function listarUsuarios(): array
    {
        try {
            $users = $this->repo->findAllUsuarios();

            $usernameById = [];
            foreach ($users as $u) {
                $usernameById[$u['id']] = $u['username'];
            }

            foreach ($users as &$u) {
                $mod = $u['modulos_permitidos'] ?? null;
                if (is_string($mod)) {
                    $u['modulos_permitidos'] = json_decode($mod, true) ?? self::DEFAULT_MODULOS;
                } elseif (!is_array($mod)) {
                    $u['modulos_permitidos'] = self::DEFAULT_MODULOS;
                }
                $u['created_by'] = $usernameById[$u['created_by_user_id']] ?? null;
            }
            unset($u);

            return ['success' => true, 'data' => $users];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sincronizarDesdeAuth(): array
    {
        try {
            $tenantId = TenantContext::requireTenant();
            $supabase = new SupabaseClient();
            $authResult = $supabase->listAuthUsers();

            if (!$authResult['ok'] || empty($authResult['users'])) {
                return ['success' => false, 'error' => 'No se pudieron obtener usuarios de Supabase Auth'];
            }

            $dbUsers      = $this->repo->findAllUsuarios();
            $dbAuthIds    = array_column($dbUsers, 'auth_user_id');
            $creados      = 0;

            foreach ($authResult['users'] as $authUser) {
                $authId       = $authUser['id'] ?? '';
                $userTenantId = (int) ($authUser['app_metadata']['tenant_id'] ?? 0);
                if (!$authId) continue;
                if ($userTenantId > 0 && $userTenantId !== $tenantId) continue;
                if (in_array($authId, $dbAuthIds, true)) continue;

                try {
                    $this->repo->upsertFromAuthUser($authUser, $tenantId);
                    $creados++;
                } catch (Throwable $e) {
                    error_log("Sync usuario falló para auth_id={$authId}: " . $e->getMessage());
                }
            }

            return ['success' => true, 'msg' => "Sincronización completada. {$creados} perfiles creados."];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function listarRoles(): array
    {
        try {
            return ['success' => true, 'data' => $this->repo->findAllRoles()];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error al listar roles'];
        }
    }

    public function listarLogs(): array
    {
        try {
            return ['success' => true, 'data' => $this->repo->findUltimosLogs(100)];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function listarAuditoria(): array
    {
        try {
            return ['success' => true, 'data' => $this->repo->findUltimaAuditoria(100)];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
