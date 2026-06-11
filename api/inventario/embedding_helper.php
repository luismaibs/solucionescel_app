<?php
/**
 * Helper para indexar/desindexar embeddings desde los endpoints CRUD de inventario.
 *
 * No se incluye en el flujo principal; es opcional.
 * Si falla la generacion del embedding, se ignora silenciosamente
 * para no interrumpir el CRUD.
 */

function _embeddingRepo(): InventarioEmbeddingRepository
{
    global $supabase;
    if (!isset($supabase)) {
        $supabase = new SupabaseClient();
    }
    if (!class_exists('InventarioEmbeddingRepository')) {
        require_once __DIR__ . '/../../src/Inventario/InventarioEmbeddingRepository.php';
    }
    return new InventarioEmbeddingRepository($supabase);
}

function _embeddingService(): ?InventarioEmbeddingService
{
    $key = getenv('DEEPSEEK_API_KEY');
    if (empty($key)) return null;

    if (!class_exists('InventarioEmbeddingService')) {
        require_once __DIR__ . '/../../src/Inventario/InventarioEmbeddingService.php';
    }
    return new InventarioEmbeddingService(_embeddingRepo(), $key);
}

function indexarEmbeddingSiDisponible(int $tenantId, string $categoria, int $productoId, array $datosExtras = []): void
{
    try {
        $service = _embeddingService();
        if (!$service) return;
        $service->indexarProductoPorId($tenantId, $categoria, $productoId);
    } catch (Throwable $e) {
        error_log('indexarEmbedding error: ' . $e->getMessage());
    }
}

function desindexarEmbeddingSiDisponible(int $tenantId, string $categoria, int $productoId): void
{
    try {
        if (!class_exists('InventarioEmbeddingRepository')) {
            require_once __DIR__ . '/../../src/Inventario/InventarioEmbeddingRepository.php';
        }
        _embeddingRepo()->deleteEmbedding($tenantId, $categoria, $productoId);
    } catch (Throwable $e) {
        error_log('desindexarEmbedding error: ' . $e->getMessage());
    }
}

function reindexarCategoriaBackground(int $tenantId, string $categoria): void
{
    try {
        $service = _embeddingService();
        if (!$service) return;

        $result = $service->reindexarCategoria($tenantId, $categoria);
        error_log('reindex ' . $categoria . ': ' . json_encode($result));
    } catch (Throwable $e) {
        error_log('reindexarCategoriaBackground error: ' . $e->getMessage());
    }
}
