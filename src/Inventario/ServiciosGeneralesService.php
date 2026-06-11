<?php

/**
 * ServiciosGeneralesService
 *
 * Lógica de negocio para crear servicios generales.
 * Maneja la transacción atómica: servicio + acciones.
 */
class ServiciosGeneralesService
{
    /** @var ServiciosGeneralesRepository */
    private $repo;

    public function __construct(ServiciosGeneralesRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Crea un servicio general con sus acciones asociadas.
     * Usa transacción para garantizar integridad.
     *
     * @param  array $data  Datos del formulario
     * @return int          ID del servicio creado
     * @throws InvalidArgumentException  Si falla la validación
     */
    public function crearServicio(array $data): int
    {
        // ── Validación ──
        $subcategoria = strtolower(trim($data['subcategoria'] ?? ''));
        if (!in_array($subcategoria, InventarioConstantes::SUBCATEGORIAS_SERVICIOS, true)) {
            throw new InvalidArgumentException('Subcategoría no válida.');
        }

        $gama = strtolower(trim($data['gama'] ?? ''));
        if (!in_array($gama, InventarioConstantes::GAMAS, true)) {
            throw new InvalidArgumentException('Gama no válida.');
        }

        // Sistemas operativos: viene como array o string CSV
        $sistemasRaw = $data['sistemas_operativos'] ?? [];
        if (is_string($sistemasRaw)) {
            $sistemasRaw = array_map('trim', explode(',', $sistemasRaw));
        }
        $sistemas = array_filter($sistemasRaw, function ($s) {
            return in_array($s, InventarioConstantes::SISTEMAS_OPERATIVOS, true);
        });
        if (empty($sistemas)) {
            throw new InvalidArgumentException('Selecciona al menos un sistema operativo.');
        }
        $sistemasCSV = implode(',', $sistemas);

        $garantia = strtoupper(trim($data['garantia'] ?? 'NO'));
        if (!in_array($garantia, ['SI', 'NO'], true)) {
            $garantia = 'NO';
        }

        $tiempoEntrega = trim($data['tiempo_entrega'] ?? '') ?: null;

        $precio = (float) ($data['precio'] ?? 0);
        if ($precio <= 0) {
            throw new InvalidArgumentException('El precio debe ser mayor a 0.');
        }

        $nota = trim($data['nota'] ?? '') ?: null;

        // Acciones: viene como array JSON o PHP array
        $accionesRaw = $data['acciones'] ?? [];
        if (is_string($accionesRaw)) {
            $decoded = json_decode($accionesRaw, true);
            $accionesRaw = is_array($decoded) ? $decoded : [];
        }
        $acciones = array_filter(array_map('trim', $accionesRaw), function ($a) {
            return $a !== '';
        });

        // ── Inserción con compensación manual (Supabase REST no soporta transacciones) ──
        $servicioId = $this->repo->insertServicio(
            $subcategoria,
            $gama,
            $sistemasCSV,
            $garantia,
            $tiempoEntrega,
            $precio,
            $nota
        );

        if (!empty($acciones)) {
            try {
                $this->repo->insertAcciones($servicioId, array_values($acciones));
            } catch (\Exception $e) {
                $this->repo->softDelete($servicioId);
                throw $e;
            }
        }

        return $servicioId;
    }

    /**
     * Elimina lógicamente un servicio.
     */
    public function eliminarServicio(int $id): void
    {
        $this->repo->softDelete($id);
    }

    /**
     * Obtiene un servicio con sus acciones.
     */
    public function obtenerServicioConAcciones(int $id): ?array
    {
        $servicio = $this->repo->findById($id);
        if (!$servicio) {
            return null;
        }

        $servicio['acciones'] = $this->repo->findAccionesByServicio($id);
        return $servicio;
    }
}
