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
        'Original', 'Intermedio', 'Genérico',
    ];

    // Compartido: tiempos de entrega
    public const TIEMPOS_ENTREGA = [
        'Instalación inmediata 4hrs',
        '2-3 días full',
        '3-5 días estándar',
        'Envío internacional 20-30 días',
    ];
}
