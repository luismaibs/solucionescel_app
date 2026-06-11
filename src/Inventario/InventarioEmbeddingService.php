<?php

class InventarioEmbeddingService
{
    private InventarioEmbeddingRepository $repo;
    private string $apiKey;
    private string $embeddingUrl;
    private string $embeddingModel;
    private int $timeout;

    public function __construct(InventarioEmbeddingRepository $repo, string $apiKey)
    {
        $this->repo = $repo;
        $this->apiKey = $apiKey;
        $this->embeddingUrl = getenv('DEEPSEEK_EMBEDDING_URL') ?: 'https://api.deepseek.com/v1/embeddings';
        $this->embeddingModel = getenv('DEEPSEEK_EMBEDDING_MODEL') ?: 'deepseek-embedder';
        $this->timeout = (int)(getenv('DEEPSEEK_EMBEDDING_TIMEOUT') ?: 30);
    }

    public function generarEmbedding(string $texto): ?array
    {
        $payload = json_encode([
            'model' => $this->embeddingModel,
            'input' => $texto,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($this->embeddingUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);

        $responseRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('DeepSeek Embedding cURL Error: ' . $curlError);
            return null;
        }

        if ($httpCode !== 200) {
            error_log('DeepSeek Embedding HTTP ' . $httpCode . ': ' . $responseRaw);
            return null;
        }

        $data = json_decode($responseRaw, true);

        if (!isset($data['data'][0]['embedding'])) {
            error_log('DeepSeek Embedding respuesta inesperada: ' . $responseRaw);
            return null;
        }

        return $data['data'][0]['embedding'];
    }

    public function indexarProducto(int $tenantId, string $categoria, array $producto): bool
    {
        $texto = $this->construirTextoBusqueda($categoria, $producto);
        if ($texto === '') {
            return false;
        }

        $embedding = $this->generarEmbedding($texto);
        if ($embedding === null) {
            return false;
        }

        $productoId = (int) ($producto['id'] ?? 0);
        if ($productoId <= 0) {
            return false;
        }

        return $this->repo->upsertEmbedding($tenantId, $categoria, $productoId, $texto, $embedding);
    }

    public function indexarProductoPorId(int $tenantId, string $categoria, int $productoId): bool
    {
        $producto = $this->repo->obtenerProducto($tenantId, $categoria, $productoId);
        if (!$producto) {
            return false;
        }
        return $this->indexarProducto($tenantId, $categoria, $producto);
    }

    public function eliminarEmbedding(int $tenantId, string $categoria, int $productoId): bool
    {
        return $this->repo->deleteEmbedding($tenantId, $categoria, $productoId);
    }

    public function reindexarCategoria(int $tenantId, string $categoria): array
    {
        $ids = $this->repo->obtenerIdsActivos($tenantId, $categoria);
        $total = count($ids);
        $indexados = 0;
        $errores = [];

        foreach ($ids as $productoId) {
            try {
                $ok = $this->indexarProductoPorId($tenantId, $categoria, (int) $productoId);
                if ($ok) {
                    $indexados++;
                } else {
                    $errores[] = "ID {$productoId}: no se pudo indexar";
                }
            } catch (Exception $e) {
                $errores[] = "ID {$productoId}: " . $e->getMessage();
            }

            usleep(200000);
        }

        return [
            'categoria' => $categoria,
            'total' => $total,
            'indexados' => $indexados,
            'errores' => $errores,
        ];
    }

    public function buscar(int $tenantId, string $query, int $limit = 10): array
    {
        $queryEmbedding = $this->generarEmbedding($query);
        if ($queryEmbedding === null) {
            return ['ok' => false, 'error' => 'No se pudo generar el embedding de busqueda', 'resultados' => []];
        }

        $hits = $this->repo->buscarPorEmbedding($tenantId, $queryEmbedding, $limit);

        if (empty($hits)) {
            return ['ok' => true, 'resultados' => [], 'query' => $query];
        }

        $productos = $this->repo->obtenerProductosConCatalogosBatch($tenantId, $hits);

        $resultados = [];
        foreach ($hits as $hit) {
            $categoria = $hit['categoria'] ?? '';
            $productoId = (int)($hit['producto_id'] ?? 0);
            if ($categoria === '' || $productoId <= 0) continue;

            $key = $categoria . ':' . $productoId;
            $producto = $productos[$key] ?? null;
            if (!$producto) continue;

            $resultados[] = [
                'categoria'   => $categoria,
                'producto_id' => $productoId,
                'score'       => round((float)($hit['score'] ?? 0), 4),
                'producto'    => $producto,
            ];
        }

        return ['ok' => true, 'resultados' => $resultados, 'query' => $query];
    }

    public function construirTextoBusqueda(string $categoria, array $producto): string
    {
        switch ($categoria) {
            case 'accesorios':
                $subcat = $producto['_subcategoria'] ?? $producto['subcategoria_nombre'] ?? '';
                $marca = $producto['_marca'] ?? $producto['marca_nombre'] ?? '';
                $color = $producto['_color'] ?? $producto['color_nombre'] ?? '';
                $codigo = $producto['codigo'] ?? '';
                $nombre = $producto['nombre_producto'] ?? '';
                return trim("accesorio {$subcat} {$marca} {$nombre} {$codigo} color {$color}");

            case 'baterias':
                $marca = $producto['marca'] ?? '';
                $modelo = $producto['modelo_bateria'] ?? '';
                $calidad = $producto['calidad'] ?? '';
                $tipo = $producto['tipo'] ?? '';
                return trim("bateria {$marca} {$modelo} {$tipo} {$calidad}");

            case 'pantallas':
                $modelo = $producto['_modelo'] ?? $producto['modelo_nombre'] ?? '';
                $modeloTec = $producto['_modelo_tecnico'] ?? $producto['modelo_tecnico_nombre'] ?? '';
                $calidad = $producto['calidad'] ?? '';
                $tiempo = $producto['tiempo'] ?? '';
                return trim("pantalla mica {$modelo} {$modeloTec} calidad {$calidad} {$tiempo}");

            case 'servicios':
                $sub = $producto['subcategoria'] ?? '';
                $gama = $producto['gama'] ?? '';
                $so = $producto['sistemas_operativos'] ?? '';
                $acciones = is_array($producto['_acciones'] ?? null) ? implode(' ', $producto['_acciones']) : '';
                return trim("servicio {$sub} {$gama} {$so} {$acciones}");

            default:
                return '';
        }
    }
}
