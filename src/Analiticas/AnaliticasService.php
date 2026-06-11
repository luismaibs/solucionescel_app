<?php

/**
 * AnaliticasService
 *
 * Lógica de agregación y formateo de datos para el módulo de analíticas.
 */
class AnaliticasService
{
    /**
     * @var AnaliticasRepository
     */
    private $repo;

    /**
     * @var \SoporteRepository|null
     */
    private $soporteRepo;

    public function __construct(AnaliticasRepository $repo, \SoporteRepository $soporteRepo)
    {
        $this->repo = $repo;
        $this->soporteRepo = $soporteRepo;
    }

    private const MESES_CORTOS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    /**
     * Devuelve todos los datos necesarios para el panel de analíticas.
     */
    public function obtenerDatosPanel(): array
    {
        $exitoFallidos = $this->repo->countExitoYFallidos();
        $exito = $exitoFallidos['exito'];
        $fallidos = $exitoFallidos['fallidos'];
        $tasaExito = ($exito + $fallidos) > 0
            ? round(($exito / ($exito + $fallidos + 0.0001)) * 100, 1)
            : 0;

        $topMarcas = $this->repo->findTopMarcas(6);
        $topModelos = $this->repo->findTopModelos(5);
        $tendenciaReparaciones = $this->repo->findTendenciaMensual(6);
        $catInventario = $this->repo->getDistribucionPorCategoria();
        $subCatInventario = $this->repo->getDistribucionPorSubcategoria(8);
        $invStats = $this->repo->getInventarioStats();
        $trendSoporte = $this->soporteRepo->findTrendSoporte(7);

        return [
            'totalHist'        => $this->repo->countReparacionesTotales(),
            'totalActivos'     => $this->repo->countReparacionesActivas(),
            'totalViejos'      => $this->repo->countReparacionesViejas(90),
            'totalExito'       => $exito,
            'totalFallidos'    => $fallidos,
            'tasaExito'        => $tasaExito,
            'topMarcas'        => $topMarcas,
            'topModelos'       => $topModelos,
            'tendenciaReparaciones' => $tendenciaReparaciones,
            'valorInventario'  => $invStats['valor_total'],
            'itemsInventario'  => $invStats['items_totales'],
            'catInventario'    => $catInventario,
            'subCatInventario' => $subCatInventario,
            'totalSoporte'     => $this->soporteRepo->countSoporteTotal(),
            'soportePendiente'  => $this->soporteRepo->countSoportePendiente(),
            'trendSoporte'     => $trendSoporte,
            // Labels de tendencia en español (meses cortos)
            'tendenciaLabels'  => array_map(function ($m) {
                $mes = (int) substr($m['mes'], 5, 2);
                return self::MESES_CORTOS[$mes - 1] ?? '';
            }, $tendenciaReparaciones),
        ];
    }
}

