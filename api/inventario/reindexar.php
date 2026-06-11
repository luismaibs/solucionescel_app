<?php
/**
 * API — Reindexar embeddings de inventario
 *
 * POST api/inventario/reindexar.php
 * Body: { "categoria": "accesorios|baterias|pantallas|servicios" }  (opcional, vacio = todas)
 *
 * Admin only. Regenera los embeddings de los productos.
 */
require_once __DIR__ . '/../../config/auth.php';
requireLogin();

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Solo administradores']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/Shared/TenantContext.php';
require_once __DIR__ . '/../../src/Inventario/InventarioEmbeddingRepository.php';
require_once __DIR__ . '/../../src/Inventario/InventarioEmbeddingService.php';

header('Content-Type: application/json; charset=utf-8');

$deepseekApiKey = getenv('DEEPSEEK_API_KEY');
if (empty($deepseekApiKey)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'API Key de IA no configurada']);
    exit;
}

$tenantId = TenantContext::requireTenant();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $categoria = $input['categoria'] ?? $_GET['categoria'] ?? '';
} else {
    $categoria = '';
}

$categoriasValidas = ['accesorios', 'baterias', 'pantallas', 'servicios'];

if ($categoria !== '' && !in_array($categoria, $categoriasValidas, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Categoria no valida: ' . $categoria]);
    exit;
}

$embeddingRepo = new InventarioEmbeddingRepository($supabase);
$embeddingService = new InventarioEmbeddingService($embeddingRepo, $deepseekApiKey);

if ($categoria !== '') {
    $resultados = [$embeddingService->reindexarCategoria($tenantId, $categoria)];
} else {
    $resultados = [];
    foreach ($categoriasValidas as $cat) {
        $resultados[] = $embeddingService->reindexarCategoria($tenantId, $cat);
    }
}

$totalIndexados = array_sum(array_column($resultados, 'indexados'));

echo json_encode([
    'ok' => true,
    'categorias' => $resultados,
    'total_indexados' => $totalIndexados,
], JSON_UNESCAPED_UNICODE);
