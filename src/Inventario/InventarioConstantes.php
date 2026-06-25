<?php

/**
 * InventarioConstantes
 *
 * Valores validos centralizados para todo el modulo de inventario.
 * Fuente unica de verdad para validaciones server-side.
 * Evita duplicacion entre endpoints CRUD, importacion y servicios.
 */
class InventarioConstantes
{
    // Servicios Generales
    public const SUBCATEGORIAS_SERVICIOS = [
        'desbloqueo', 'liberaciones', 'servicios', 'reparaciones', 'software',
    ];

    public const GAMAS = [
        'baja', 'media', 'alta', 'premium', 's.premium', 'todas las gamas',
    ];

    public const SISTEMAS_OPERATIVOS = [
        'Android', 'iPhone OS', 'Windows', 'macOS', 'iPadOS', 'Otros',
    ];

    // Baterias
    public const CALIDADES_BATERIA = [
        'Genérico', 'Larga duración', 'Original',
    ];

    public const TIPOS_BATERIA = [
        'Interna', 'Externa',
    ];

    // Pantallas
    public const CALIDADES_PANTALLA = [
        'C1', 'C2', 'C3',
    ];

    public const CALIDADES_PANTALLA_LABELS = [
        'C1' => 'Genérico',
        'C2' => 'Intermedio',
        'C3' => 'Original',
    ];

    public const TIEMPOS_PANTALLA = [
        'TAD 1', 'TAD 2', 'TAD 3', 'TAD 4',
        'TAP 1', 'TAP 2', 'TAP 3', 'TAP 4',
        'OTR 1', 'OTR 2', 'OTR 3', 'OTR 4',
    ];

    public const TIEMPOS_PANTALLA_LABELS = [
        '1' => 'Instalación inmediata 4hrs',
        '2' => '2-3 días full',
        '3' => '3-5 días estándar',
        '4' => 'Envío internacional 20-30 días',
    ];

    // Compartido: tiempos de entrega
    public const TIEMPOS_ENTREGA = [
        'Instalación inmediata 4hrs',
        '2-3 días full',
        '3-5 días estándar',
        'Envío internacional 20-30 días',
    ];
}
