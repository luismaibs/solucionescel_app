<?php

/**
 * ClienteService
 *
 * Lógica de negocio del módulo de clientes.
 */
class ClienteService
{
    private ClienteRepository $repo;

    private const MAX_NOMBRE = 200;
    private const MAX_APELLIDO = 200;
    private const MAX_TELEFONO = 25;
    private const MAX_CORREO = 255;

    public function __construct(ClienteRepository $repo)
    {
        $this->repo = $repo;
    }

    public function listarPaginado(int $page, int $perPage, string $search = ''): array
    {
        $offset = ($page - 1) * $perPage;
        $total = 0;
        $items = $this->repo->findPaginated($offset, $perPage, $search, $total);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function buscar(string $query): array
    {
        return $this->repo->search($query);
    }

    public function obtener(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    private function validarDatosCliente(array $data): array
    {
        $nombre = mb_strtoupper(trim($data['nombre'] ?? ''), 'UTF-8');
        $apellido = mb_strtoupper(trim($data['apellido'] ?? ''), 'UTF-8');
        $telefono = trim($data['telefono'] ?? '');
        $correo = !empty($data['correo']) ? trim($data['correo']) : null;

        if ($nombre === '' || $apellido === '' || $telefono === '') {
            return ['ok' => false, 'message' => 'Nombre, apellido y teléfono son requeridos'];
        }

        if (mb_strlen($nombre) > self::MAX_NOMBRE) {
            return ['ok' => false, 'message' => 'Nombre no puede exceder ' . self::MAX_NOMBRE . ' caracteres.'];
        }
        if (mb_strlen($apellido) > self::MAX_APELLIDO) {
            return ['ok' => false, 'message' => 'Apellido no puede exceder ' . self::MAX_APELLIDO . ' caracteres.'];
        }
        if (mb_strlen($telefono) > self::MAX_TELEFONO) {
            return ['ok' => false, 'message' => 'Teléfono no puede exceder ' . self::MAX_TELEFONO . ' caracteres.'];
        }
        if ($correo !== null && strlen($correo) > self::MAX_CORREO) {
            return ['ok' => false, 'message' => 'Correo no puede exceder ' . self::MAX_CORREO . ' caracteres.'];
        }
        if ($correo !== null && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'El formato del correo no es válido'];
        }

        return [
            'ok' => true,
            'data' => compact('nombre', 'apellido', 'telefono', 'correo'),
        ];
    }

    public function crear(array $data): array
    {
        $validado = $this->validarDatosCliente($data);
        if (!$validado['ok']) {
            return $validado;
        }
        $d = $validado['data'];

        if ($this->repo->telefonoExiste($d['telefono'])) {
            return ['ok' => false, 'message' => 'Ya existe un cliente con ese teléfono'];
        }

        $userId = $data['user_id'] ?? getCurrentUserId();
        $id = $this->repo->insert($d['nombre'], $d['apellido'], $d['telefono'], $d['correo'], $userId);

        registrarActividad('crear_cliente', "Cliente: {$d['nombre']} {$d['apellido']}", $id, 'cliente');

        return ['ok' => true, 'id' => $id, 'message' => 'Cliente creado exitosamente'];
    }

    /**
     * Crea un cliente rápido desde el flujo de registro de equipo.
     * Devuelve el ID del cliente o el existente si ya existe ese teléfono.
     */
    public function crearRapido(array $data): array
    {
        if (!empty($data['lada']) && !empty($data['telefono'])) {
            $data['telefono'] = Utils::formatearTelefono($data['lada'], $data['telefono']);
        }

        $validado = $this->validarDatosCliente($data);
        if (!$validado['ok']) {
            return $validado;
        }
        $d = $validado['data'];

        $existente = $this->repo->findByTelefono($d['telefono']);
        if ($existente) {
            return [
                'ok' => true,
                'id' => $existente['id'],
                'nombre_completo' => $existente['nombre'] . ' ' . $existente['apellido'],
                'existente' => true,
                'message' => 'Cliente existente vinculado',
            ];
        }

        $userId = $data['user_id'] ?? getCurrentUserId();
        $id = $this->repo->insert($d['nombre'], $d['apellido'], $d['telefono'], $d['correo'], $userId);

        registrarActividad('crear_cliente', "Cliente rápido: {$d['nombre']} {$d['apellido']}", $id, 'cliente');

        return [
            'ok' => true,
            'id' => $id,
            'nombre_completo' => $d['nombre'] . ' ' . $d['apellido'],
            'existente' => false,
            'message' => 'Cliente creado y vinculado',
        ];
    }

    public function editar(int $id, array $data): array
    {
        $validado = $this->validarDatosCliente($data);
        if (!$validado['ok']) {
            return $validado;
        }
        $d = $validado['data'];

        if ($this->repo->telefonoExiste($d['telefono'], $id)) {
            return ['ok' => false, 'message' => 'Ya existe otro cliente con ese teléfono'];
        }

        $this->repo->update($id, $d['nombre'], $d['apellido'], $d['telefono'], $d['correo']);

        registrarActividad('editar_cliente', "Actualizó cliente ID: {$id}", $id, 'cliente');

        return ['ok' => true, 'message' => 'Cliente actualizado'];
    }

    public function eliminar(int $id): array
    {
        $cliente = $this->repo->findById($id);
        if (!$cliente) {
            return ['ok' => false, 'message' => 'Cliente no encontrado'];
        }

        $this->repo->softDelete($id);
        registrarActividad('eliminar_cliente', "Eliminó cliente: {$cliente['nombre']} {$cliente['apellido']}", $id, 'cliente');

        return ['ok' => true, 'message' => 'Cliente eliminado'];
    }

    public function obtenerDatos360(int $id): ?array
    {
        $cliente = $this->repo->findById($id);
        if (!$cliente) {
            return null;
        }

        $estadisticas = $this->repo->getEstadisticas($id);
        $equipos = $this->repo->getEquipos($id);

        return [
            'cliente' => $cliente,
            'estadisticas' => $estadisticas,
            'equipos' => $equipos,
        ];
    }
}
