<?php

/**
 * ReparacionDashboardService
 *
 * Lógica específica para el panel de equipos:
 * - Construcción de mapas de marcas/modelos.
 */
class ReparacionDashboardService
{
    /**
     * Construye el mapa marcas -> [modelos] usado en el panel.
     */
    public static function construirMarcasMap(array $rawMM): array
    {
        $map = [];
        foreach ($rawMM as $row) {
            $marca = $row['equipo_marca'] ?? '';
            $modelo = $row['equipo_modelo'] ?? '';
            if ($marca === '' || $modelo === '') {
                continue;
            }
            if (!isset($map[$marca])) {
                $map[$marca] = [];
            }
            if (!in_array($modelo, $map[$marca], true)) {
                $map[$marca][] = $modelo;
            }
        }
        return $map;
    }
}
