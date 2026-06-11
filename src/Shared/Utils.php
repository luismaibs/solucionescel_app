<?php

/**
 * Utils
 *
 * Funciones de ayuda reutilizables en todo el sistema.
 */
class Utils
{
    /**
     * Calcula cuántos días han pasado desde una fecha dada hasta ahora.
     *
     * @param string $dateString
     * @return int
     */
    public static function daysPassed(string $dateString): int
    {
        try {
            $date = new DateTime($dateString);
            $now = new DateTime();
            $interval = $date->diff($now);
            return (int) $interval->days;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Formatea un teléfono en el formato esperado para WhatsApp / n8n.
     *
     * Replica la lógica existente de formatearTelefono en api/api_reparaciones.php.
     *
     * @param string $lada
     * @param string $numero
     * @return string
     */
    public static function formatearTelefono(string $lada, string $numero): string
    {
        $numeroLim = preg_replace('/[^0-9]/', '', $numero);

        // Lógica MX: 52 + 10 dígitos
        if ($lada === '+52' || $lada === '52' || $lada === '+521') {
            return '52' . substr($numeroLim, -10);
        }

        return str_replace('+', '', $lada) . $numeroLim;
    }
}

