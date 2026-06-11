<?php

/**
 * AIAnalyticsService
 *
 * Servicio de integración con DeepSeek para análisis inteligente de datos.
 * Implementa un flujo de 3 pasos:
 *   1. Generar SQL a partir de lenguaje natural
 *   2. Ejecutar la consulta de forma segura
 *   3. Analizar resultados y generar visualización
 */
class AIAnalyticsService
{
    /** @var AIAnalyticsRepository */
    private $repo;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $apiUrl;

    /** @var string */
    private $modelSQL;

    /** @var string */
    private $modelAnalisis;

    /** @var int */
    private $timeoutChat;

    /** @var int */
    private $timeoutReasoner;

    /** @var bool Si el usuario activó el modo razonador */
    private $useReasoner = false;

    /** @var string */
    private $schemaContext;

    /** @var SupabaseClient|null Cliente para logs (opcional) */
    private $supabaseForLogs;

    /** @var string|null Cache estática del schema_map */
    private static $schemaCached;

    public function __construct(AIAnalyticsRepository $repo, string $apiKey, ?SupabaseClient $supabaseForLogs = null)
    {
        $this->repo   = $repo;
        $this->apiKey = $apiKey;

        $this->apiUrl          = getenv('DEEPSEEK_API_URL') ?: 'https://api.deepseek.com/chat/completions';
        $this->modelSQL        = getenv('DEEPSEEK_MODEL_SQL') ?: 'deepseek-chat';
        $this->modelAnalisis   = getenv('DEEPSEEK_MODEL_ANALISIS') ?: 'deepseek-chat';
        $this->timeoutChat     = (int) (getenv('DEEPSEEK_TIMEOUT_CHAT') ?: 30);
        $this->timeoutReasoner = (int) (getenv('DEEPSEEK_TIMEOUT_REASONER') ?: 180);

        $this->supabaseForLogs = $supabaseForLogs;

        // Cargar schema_map una sola vez (cache estática)
        if (self::$schemaCached === null) {
            $schemaPath = dirname(__DIR__, 2) . '/database/schema_map.json';
            self::$schemaCached = file_exists($schemaPath) ? file_get_contents($schemaPath) : '{}';
        }
        $this->schemaContext = self::$schemaCached;
    }

    /**
     * Activa el modelo de razonamiento (deepseek-reasoner) para el análisis.
     * Más lento (~20-60s) pero genera insights más profundos.
     */
    public function activarRazonador(bool $activar = true): void
    {
        $this->useReasoner = $activar;
        $this->modelAnalisis = $activar ? 'deepseek-reasoner' : 'deepseek-chat';
    }

    /**
     * Indica si el modo razonador está activo.
     */
    public function isRazonadorActivo(): bool
    {
        return $this->useReasoner;
    }

    /**
     * Procesa una pregunta del usuario en 3 pasos:
     * 1. Generar SQL con DeepSeek
     * 2. Ejecutar SQL
     * 3. Analizar resultados con DeepSeek
     *
     * @param string $pregunta       Pregunta en lenguaje natural
     * @param string $username       Usuario que hace la pregunta
     * @param string $conversacionId UUID de la conversación activa
     * @return array Resultado completo del análisis
     */
    public function procesarPregunta(string $pregunta, string $username, string $conversacionId = ''): array
    {
        $inicio = microtime(true);

        // ═══════════════════════════════════════════
        // PASO 1: Generar SQL con DeepSeek
        // ═══════════════════════════════════════════
        $paso1 = $this->generarSQL($pregunta);

        if (!$paso1['success']) {
            return [
                'success' => false,
                'error'   => $paso1['error'],
                'paso'    => 'generacion_sql'
            ];
        }

        // Pregunta fuera de alcance: solo analíticas de este sistema
        if (!empty($paso1['out_of_scope'])) {
            return [
                'success'       => true,
                'answer'        => 'Lo siento, mi entrenamiento solo me permite responder sobre las analíticas de este sistema.',
                'out_of_scope'  => true,
                'chart_config'  => null,
                'data'          => [],
                'columns'       => [],
                'row_count'     => 0,
            ];
        }

        $sqlGenerado    = $paso1['sql'];
        $explicacion    = $paso1['explanation'];
        $vizSugerida    = $paso1['visualization'];

        // ═══════════════════════════════════════════
        // PASO 2: Ejecutar la consulta
        // ═══════════════════════════════════════════
        $paso2 = $this->repo->ejecutarConsultaSegura($sqlGenerado);

        if (!$paso2['success']) {
            // Si falla la primera consulta, intentar pedir a DeepSeek que corrija
            $paso1Retry = $this->generarSQL($pregunta, $paso2['error']);

            if ($paso1Retry['success']) {
                $sqlGenerado = $paso1Retry['sql'];
                $explicacion = $paso1Retry['explanation'];
                $vizSugerida = $paso1Retry['visualization'];
                $paso2 = $this->repo->ejecutarConsultaSegura($sqlGenerado);
            }

            if (!$paso2['success']) {
                return [
                    'success' => false,
                    'error'   => 'No se pudo ejecutar la consulta: ' . $paso2['error'],
                    'sql'     => $sqlGenerado,
                    'paso'    => 'ejecucion_sql'
                ];
            }
        }

        // ═══════════════════════════════════════════
        // PASO 3: Analizar resultados con DeepSeek
        // ═══════════════════════════════════════════
        $paso3 = $this->analizarResultados(
            $pregunta,
            $sqlGenerado,
            $paso2['data'],
            $paso2['columns'],
            $vizSugerida
        );

        $tiempoTotal = round((microtime(true) - $inicio) * 1000);

        // Guardar en historial
        $idHistorial = null;
        try {
            $idHistorial = $this->repo->guardarAnalisis([
                'username'        => $username,
                'conversacion_id' => $conversacionId,
                'titulo'          => $this->generarTitulo($pregunta),
                'pregunta'        => $pregunta,
                'respuesta'       => $paso3['answer'] ?? '',
                'sql_generado'    => $sqlGenerado,
                'datos_resultado' => json_encode($paso2['data'], JSON_UNESCAPED_UNICODE),
                'visualizacion'   => json_encode($paso3['chart_config'] ?? null, JSON_UNESCAPED_UNICODE),
                'guardado'        => 0,
            ]);
        } catch (Exception $e) {
            error_log('AIAnalytics guardar historial: ' . $e->getMessage());
        }

        return [
            'success'          => true,
            'answer'           => $paso3['answer'] ?? 'Análisis completado.',
            'sql_used'         => $sqlGenerado,
            'explanation'      => $explicacion,
            'data'             => $paso2['data'],
            'columns'          => $paso2['columns'],
            'row_count'        => $paso2['row_count'],
            'chart_config'     => $paso3['chart_config'] ?? null,
            'historial_id'     => $idHistorial,
            'conversacion_id'  => $conversacionId,
            'tiempo_ms'        => $tiempoTotal,
        ];
    }

    /**
     * PASO 1: Enviar pregunta + esquema a DeepSeek para generar SQL.
     * Usa el modelo rápido (deepseek-chat) para respuesta estructurada en ~2-5s.
     */
    private function generarSQL(string $pregunta, string $errorPrevio = null): array
    {
        $prompt = $this->construirPromptSQL($pregunta, $errorPrevio);

        $response = $this->llamarDeepSeek($prompt, $this->modelSQL);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        // Parsear la respuesta JSON
        $parsed = $this->extraerJSON($response['content']);

        if ($parsed === null) {
            return [
                'success' => false,
                'error'   => 'No se pudo interpretar la respuesta de la IA para generar SQL'
            ];
        }

        // Pregunta fuera de alcance: usuario preguntó algo no relacionado con analíticas del sistema
        if (!empty($parsed['out_of_scope'])) {
            return [
                'success'      => true,
                'out_of_scope' => true,
                'sql'          => null,
                'explanation'  => $parsed['explanation'] ?? '',
                'visualization' => ['type' => 'none'],
            ];
        }

        if (empty($parsed['sql'])) {
            return [
                'success' => false,
                'error'   => 'La IA no generó una consulta SQL válida'
            ];
        }

        return [
            'success'       => true,
            'sql'           => $parsed['sql'],
            'explanation'   => $parsed['explanation'] ?? '',
            'visualization' => $parsed['visualization'] ?? ['type' => 'table'],
        ];
    }

    /**
     * PASO 3: Analizar los resultados y generar configuración de gráfica.
     * Usa el modelo de razonamiento (deepseek-reasoner) para insights profundos.
     */
    private function analizarResultados(
        string $pregunta,
        string $sql,
        array $datos,
        array $columnas,
        array $vizSugerida
    ): array {
        $prompt = $this->construirPromptAnalisis($pregunta, $sql, $datos, $columnas, $vizSugerida);

        $response = $this->llamarDeepSeek($prompt, $this->modelAnalisis);

        if (!$response['success']) {
            // Si falla el análisis, generar respuesta básica
            return $this->generarAnalisisBasico($datos, $columnas, $vizSugerida);
        }

        $parsed = $this->extraerJSON($response['content']);

        if ($parsed === null) {
            // Usar la respuesta como texto plano
            return [
                'answer'       => $response['content'],
                'chart_config' => $this->generarChartBasico($datos, $columnas, $vizSugerida),
            ];
        }

        return [
            'answer'       => $parsed['answer'] ?? 'Análisis completado.',
            'chart_config' => $parsed['chart_config'] ?? null,
        ];
    }

    // ═══════════════════════════════════════════════
    // CONSTRUCCIÓN DE PROMPTS
    // ═══════════════════════════════════════════════

    /**
     * Construye el prompt para generación de SQL.
     */
    private function construirPromptSQL(string $pregunta, string $errorPrevio = null): string
    {
        $prompt = "Eres un analista de datos experto en talleres de reparación de celulares (SOLUCIONESCEL).\n";
        $prompt .= "Genera SOLO consultas SELECT de PostgreSQL basadas en el siguiente esquema:\n\n";
        $prompt .= $this->schemaContext . "\n\n";

        if ($errorPrevio) {
            $prompt .= "⚠️ Corrige este error de la consulta anterior:\n{$errorPrevio}\n\n";
        }

        $prompt .= "Pregunta del usuario: \"{$pregunta}\"\n\n";
        $prompt .= "Reglas:\n";
        $prompt .= "- OUT_OF_SCOPE (devolver out_of_scope:true) SOLO si la pregunta es claramente ajena (deportes, recetas, otras empresas, política).\n";
        $prompt .= "- Si habla del taller, reparaciones, inventario, datos → out_of_scope:false. Sé laxo.\n";
        $prompt .= "- SOLO SELECT. PROHIBIDO: INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE, CREATE.\n";
        $prompt .= "- Usa SOLO tablas del esquema. No existe 'servicios', usa 'inv_servicios_generales'.\n";
        $prompt .= "- FILTRA deleted_at IS NULL en tablas que lo tengan.\n";
        $prompt .= "- LIMIT máximo 100.\n";
        $prompt .= "- ALIAS en español para columnas.\n";
        $prompt .= "- Timezone: America/Mexico_City.\n\n";
        $prompt .= "Responde SOLO este JSON (sin markdown):\n";
        $prompt .= '{"out_of_scope":false,"sql":"SELECT ...","explanation":"Breve explicación",';
        $prompt .= '"visualization":{"type":"bar|line|doughnut|table|kpi|area|none","title":"Título","x_label":"Eje X","y_label":"Eje Y"}}';

        return $prompt;
    }

    /**
     * Construye el prompt para análisis de resultados.
     */
    private function construirPromptAnalisis(
        string $pregunta,
        string $sql,
        array $datos,
        array $columnas,
        array $vizSugerida
    ): string {
        $datosLimitados = array_slice($datos, 0, 50);
        $datosJSON = json_encode($datosLimitados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = "Eres un analista de negocio experto en talleres de reparación (SOLUCIONESCEL). Da insights claros en ESPAÑOL.\n\n";
        $prompt .= "Pregunta: \"{$pregunta}\"\n";
        $prompt .= "SQL ejecutado: {$sql}\n";
        $prompt .= "Columnas: " . implode(', ', $columnas) . " | Total filas: " . count($datos) . "\n\n";
        $prompt .= "Datos (JSON):\n{$datosJSON}\n\n";
        $prompt .= "Visualización sugerida: " . ($vizSugerida['type'] ?? 'table') . "\n\n";
        $prompt .= "Instrucciones:\n";
        $prompt .= "1. Analiza los datos, responde en ESPAÑOL con insights útiles.\n";
        $prompt .= "2. Usa **negrita** para números clave, • para listas.\n";
        $prompt .= "3. Genera config completa de Chart.js v3+ con colores para tema oscuro (fondo #0f172a).\n";
        $prompt .= "4. Pocos datos o 1 número → type 'kpi'. Tablas → type 'table'.\n\n";
        $prompt .= "Responde SOLO este JSON (sin markdown):\n";
        $prompt .= '{"answer":"Análisis en español...","chart_config":{"type":"bar|line|doughnut|pie|table|kpi|area",';
        $prompt .= '"data":{"labels":["..."],"datasets":[{"label":"...","data":[...],"backgroundColor":[...]}]},';
        $prompt .= '"options":{...},"kpi_value":"...","kpi_label":"...","table_data":{"headers":[...],"rows":[[...]]}}}';

        return $prompt;
    }

    // ═══════════════════════════════════════════════
    // COMUNICACIÓN CON DEEPSEEK API
    // ═══════════════════════════════════════════════

    /**
     * Llama a la API de DeepSeek.
     *
     * @param string $prompt  El prompt a enviar
     * @param string $modelo  El modelo a usar (deepseek-chat o deepseek-reasoner)
     */
    private function llamarDeepSeek(string $prompt, string $modelo = null): array
    {
        $startTime = microtime(true);

        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'API Key de DeepSeek no configurada', 'content' => ''];
        }

        $modelo = $modelo ?: $this->modelSQL;

        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];

        $payload = [
            'model'    => $modelo,
            'messages' => $messages,
        ];

        // deepseek-chat soporta parámetros adicionales para respuestas más precisas
        if ($modelo === 'deepseek-chat') {
            $payload['temperature'] = 0.1;
            $payload['max_tokens']  = 4096;
        }

        // Timeout adaptativo: chat es rápido (~5s), reasoner es lento (~60s)
        $timeout = ($modelo === 'deepseek-reasoner') ? $this->timeoutReasoner : $this->timeoutChat;

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);

        $responseRaw = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('DeepSeek cURL Error: ' . $curlError);
            $this->logExternalCall('deepseek', $this->apiUrl, 'POST', 0, false, (int) round((microtime(true) - $startTime) * 1000), $modelo, $curlError, null);
            return [
                'success' => false,
                'error'   => 'Error de conexión con DeepSeek: ' . $curlError,
                'content' => ''
            ];
        }

        if ($httpCode !== 200) {
            error_log('DeepSeek HTTP ' . $httpCode . ': ' . $responseRaw);
            $errorMsg = 'Error de DeepSeek (HTTP ' . $httpCode . ')';

            // Intentar extraer mensaje de error
            $errorData = json_decode($responseRaw, true);
            if (isset($errorData['error']['message'])) {
                $errorMsg .= ': ' . $errorData['error']['message'];
            }

            $this->logExternalCall('deepseek', $this->apiUrl, 'POST', $httpCode, false, (int) round((microtime(true) - $startTime) * 1000), $modelo, $errorMsg, null);
            return [
                'success' => false,
                'error'   => $errorMsg,
                'content' => ''
            ];
        }

        $data = json_decode($responseRaw, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            error_log('DeepSeek respuesta inesperada: ' . $responseRaw);
            $this->logExternalCall('deepseek', $this->apiUrl, 'POST', $httpCode, false, (int) round((microtime(true) - $startTime) * 1000), $modelo, 'Respuesta inesperada', null);
            return [
                'success' => false,
                'error'   => 'Respuesta inesperada de DeepSeek',
                'content' => ''
            ];
        }

        $content = $data['choices'][0]['message']['content'];

        $this->logExternalCall('deepseek', $this->apiUrl, 'POST', $httpCode, true, (int) round((microtime(true) - $startTime) * 1000), $modelo, null);

        return [
            'success' => true,
            'content' => $content,
            'error'   => null,
        ];
    }

    /**
     * Registra una llamada a API externa en Supabase para monitoreo.
     * Opcional: si falla la conexion, se ignora silenciosamente.
     */
    private function logExternalCall(
        string $service,
        string $endpoint,
        string $method,
        int $statusCode,
        bool $ok,
        int $responseTimeMs = 0,
        ?string $model = null,
        ?string $errorMessage = null,
        ?int $tokensUsed = null
    ): void {
        if (!$this->supabaseForLogs) return;

        try {
            $tenantId = class_exists('TenantContext') ? TenantContext::getTenantId() : null;
            $this->supabaseForLogs->post('external_api_logs', [
                'tenant_id'       => $tenantId,
                'service'         => $service,
                'endpoint'         => $endpoint,
                'method'           => $method,
                'status_code'      => $statusCode,
                'ok'               => $ok,
                'response_time_ms' => $responseTimeMs,
                'tokens_used'      => $tokensUsed,
                'model'            => $model,
                'error_message'    => $errorMessage,
            ]);
        } catch (\Throwable $e) {
            error_log('AIAnalytics logExternalCall: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════
    // UTILIDADES
    // ═══════════════════════════════════════════════

    /**
     * Extrae un objeto JSON de una respuesta que puede venir envuelta en markdown.
     */
    private function extraerJSON(string $texto): ?array
    {
        $texto = trim($texto);

        // Intentar parsear directamente
        $decoded = json_decode($texto, true);
        if ($decoded !== null && is_array($decoded)) {
            return $decoded;
        }

        // Buscar JSON dentro de bloques de código markdown ```json ... ```
        if (preg_match('/```(?:json)?\s*\n?([\s\S]*?)\n?\s*```/', $texto, $matches)) {
            $decoded = json_decode(trim($matches[1]), true);
            if ($decoded !== null && is_array($decoded)) {
                return $decoded;
            }
        }

        // Buscar el primer { ... } en el texto
        $start = strpos($texto, '{');
        $end   = strrpos($texto, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $jsonStr = substr($texto, $start, $end - $start + 1);
            $decoded = json_decode($jsonStr, true);
            if ($decoded !== null && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Genera un título corto a partir de la pregunta.
     */
    private function generarTitulo(string $pregunta): string
    {
        // Truncar a 80 caracteres
        $titulo = mb_substr(trim($pregunta), 0, 80);
        if (mb_strlen($pregunta) > 80) {
            $titulo .= '...';
        }
        return $titulo;
    }

    /**
     * Genera un análisis básico cuando DeepSeek falla.
     */
    private function generarAnalisisBasico(array $datos, array $columnas, array $vizSugerida): array
    {
        $count = count($datos);

        if ($count === 0) {
            return [
                'answer'       => 'La consulta no retornó resultados. Intenta reformular tu pregunta.',
                'chart_config' => null,
            ];
        }

        if ($count === 1 && count($columnas) <= 2) {
            // Es un KPI
            $valor = reset($datos[0]);
            $label = $columnas[0] ?? 'Resultado';
            return [
                'answer'       => "**Resultado:** $valor",
                'chart_config' => [
                    'type'      => 'kpi',
                    'kpi_value' => $valor,
                    'kpi_label' => $label,
                ],
            ];
        }

        // Generar chart básico
        return [
            'answer'       => "Se encontraron **$count** registros.",
            'chart_config' => $this->generarChartBasico($datos, $columnas, $vizSugerida),
        ];
    }

    /**
     * Genera una configuración de Chart.js básica a partir de los datos.
     */
    private function generarChartBasico(array $datos, array $columnas, array $vizSugerida): ?array
    {
        if (empty($datos) || count($columnas) < 2) {
            return null;
        }

        $type = $vizSugerida['type'] ?? 'bar';

        if ($type === 'table' || $type === 'none') {
            return [
                'type'       => 'table',
                'table_data' => [
                    'headers' => $columnas,
                    'rows'    => array_map('array_values', $datos),
                ],
            ];
        }

        if ($type === 'kpi' && count($datos) === 1) {
            $valor = reset($datos[0]);
            return [
                'type'      => 'kpi',
                'kpi_value' => $valor,
                'kpi_label' => $vizSugerida['title'] ?? $columnas[0],
            ];
        }

        // Para gráficas: primera columna = labels, segunda = datos
        $labels   = array_column($datos, $columnas[0]);
        $values   = array_column($datos, $columnas[1]);
        $colores  = $this->generarPaleta(count($labels));

        return [
            'type' => $type,
            'data' => [
                'labels'   => $labels,
                'datasets' => [[
                    'label'           => $vizSugerida['title'] ?? $columnas[1],
                    'data'            => array_map(function ($v) {
                        return is_numeric($v) ? (float) $v : $v;
                    }, $values),
                    'backgroundColor' => ($type === 'doughnut' || $type === 'pie') ? $colores : $colores[0],
                    'borderColor'     => ($type === 'line' || $type === 'area') ? $colores[0] : 'transparent',
                    'borderWidth'     => ($type === 'line' || $type === 'area') ? 3 : 0,
                    'borderRadius'    => ($type === 'bar') ? 6 : 0,
                    'fill'            => ($type === 'area'),
                    'tension'         => 0.4,
                ]],
            ],
            'options' => [
                'responsive'          => true,
                'maintainAspectRatio' => false,
                'plugins'             => [
                    'legend' => ['display' => ($type === 'doughnut' || $type === 'pie')],
                ],
            ],
        ];
    }

    /**
     * Genera una paleta de colores para gráficas.
     */
    private function generarPaleta(int $cantidad): array
    {
        $colores = [
            'rgba(59, 130, 246, 0.8)',   // Azul
            'rgba(16, 185, 129, 0.8)',   // Verde
            'rgba(245, 158, 11, 0.8)',   // Naranja
            'rgba(239, 68, 68, 0.8)',    // Rojo
            'rgba(139, 92, 246, 0.8)',   // Púrpura
            'rgba(6, 182, 212, 0.8)',    // Cyan
            'rgba(236, 72, 153, 0.8)',   // Rosa
            'rgba(234, 179, 8, 0.8)',    // Amarillo
            'rgba(168, 162, 158, 0.8)',  // Gris
            'rgba(34, 211, 238, 0.8)',   // Turquesa
        ];

        $resultado = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $resultado[] = $colores[$i % count($colores)];
        }

        return $resultado;
    }
}
