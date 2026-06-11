<?php
include '../config/auth.php';
requireLogin();
requireAdmin();
include_once '../includes/fragment_helper.php';
?>
<?php if (!$isFragment): ?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistente IA | SOLUCIONESCEL</title>
    <?php include '../includes/head_meta.php'; ?>
    <link rel="stylesheet" href="../assets/css/asistente-ia.css" data-module-css="asistente_ia">
</head>

<body>
    <?php include '../includes/header.php'; ?>
<?php else: ?>
    <link rel="stylesheet" href="<?= $fragment_asset_base ?>assets/css/asistente-ia.css" data-module-css="asistente_ia">
<?php endif; ?>
    <!-- Chart.js (lazy: carga dinámica sin bloquear parsing) -->
    <script>
    (function(){
        if(window.Chart) return;
        var s=document.createElement('script');
        s.src='https://cdn.jsdelivr.net/npm/chart.js';
        document.head.appendChild(s);
    })();
    </script>

    <div class="module-subheader">
        <div class="module-subheader-kpis">
            <span class="module-subheader-title"><i class="bi bi-stars text-primary me-1"></i>Asistente IA</span>
        </div>
        <div class="module-subheader-actions">
            <a href="analiticas" class="btn btn-outline-light" style="border-color: rgba(255,255,255,0.2);">
                <i class="bi bi-arrow-left"></i> Volver al Dashboard
            </a>
        </div>
    </div>

    <div class="ai-app">
        <!-- SIDEBAR DE HISTORIAL -->
        <aside class="ai-sidebar" id="aiSidebar">
            <div class="ai-sidebar-header">
                <span class="ai-sidebar-title"><i class="bi bi-clock-history me-2"></i>Historial</span>
                <button type="button" class="ai-msg-action-btn" id="aiSidebarCloseBtn" title="Cerrar historial">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="ai-sidebar-body" id="aiHistoryList">
                <div class="ai-history-empty">
                    <i class="bi bi-chat-square-text"></i>
                    <span>Tus análisis aparecerán aquí</span>
                </div>
            </div>
        </aside>

        <!-- ÁREA PRINCIPAL DEL CHAT -->
        <main class="ai-main">
            <button type="button" class="ai-sidebar-toggle visible" id="aiSidebarToggle" title="Mostrar historial">
                <i class="bi bi-layout-sidebar"></i>
            </button>

            <div class="ai-chat-area" id="aiChatArea">
                <div class="ai-chat-container" id="aiChatContainer">
                    <!-- Bienvenida -->
                    <div class="ai-welcome" id="aiWelcome">
                        <div class="ai-welcome-icon">
                            <i class="bi bi-stars"></i>
                        </div>
                        <h2>Asistente de Análisis IA</h2>
                        <p>Pregúntame cualquier cosa sobre tus datos. Analizo tu base de datos y genero visualizaciones inteligentes al instante.</p>

                        <div class="ai-suggestions" id="aiSuggestions">
                            <div class="ai-suggestion-chip" data-q="¿Cuántas reparaciones hay activas en el taller?">
                                <i class="bi bi-tools"></i>
                                Reparaciones activas
                            </div>
                            <div class="ai-suggestion-chip" data-q="¿Cuál es la marca de celular más reparada?">
                                <i class="bi bi-phone"></i>
                                Marca más popular
                            </div>
                            <div class="ai-suggestion-chip" data-q="Muéstrame la tendencia de reparaciones por mes en los últimos 6 meses">
                                <i class="bi bi-graph-up"></i>
                                Tendencia mensual
                            </div>
                            <div class="ai-suggestion-chip" data-q="¿Cuál es el valor total del inventario?">
                                <i class="bi bi-currency-dollar"></i>
                                Valor del inventario
                            </div>
                            <div class="ai-suggestion-chip" data-q="Top 10 modelos de celulares más reparados">
                                <i class="bi bi-trophy"></i>
                                Top modelos
                            </div>
                            <div class="ai-suggestion-chip" data-q="¿Cuál es la distribución de estados de las reparaciones?">
                                <i class="bi bi-pie-chart"></i>
                                Estados de reparación
                            </div>
                            <div class="ai-suggestion-chip" data-q="¿Cuántas conversaciones de soporte están pausadas esperando atención?">
                                <i class="bi bi-headset"></i>
                                Soporte pendiente
                            </div>
                            <div class="ai-suggestion-chip" data-q="¿Cuál es la tasa de éxito de las reparaciones?">
                                <i class="bi bi-lightning-charge"></i>
                                Tasa de éxito
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INPUT AREA -->
            <div class="ai-input-area">
                <div class="ai-input-wrap">
                    <!-- Toolbar: nueva conversación + toggle modelo -->
                    <div class="ai-input-toolbar">
                        <div class="ai-toolbar-left">
                            <button type="button" class="ai-new-chat-btn" id="aiNewChatBtn" title="Iniciar nueva conversación">
                                <i class="bi bi-plus-lg"></i>
                                <span>Nueva conversación</span>
                            </button>
                        </div>
                        <div class="ai-toolbar-right">
                            <div class="ai-model-toggle" id="aiModelToggle" title="Activar análisis profundo con DeepSeek Reasoner (más lento pero más inteligente)">
                                <i class="bi bi-lightbulb ai-model-icon"></i>
                                <span class="ai-model-label" id="aiModelLabel">Rápido</span>
                                <div class="ai-switch"></div>
                            </div>
                        </div>
                    </div>

                    <div class="ai-input-container">
                        <textarea class="ai-input" id="aiInput" rows="1"
                            placeholder="Pregúntame cualquier cosa sobre tus datos..."
                            maxlength="1000"></textarea>
                        <button type="button" class="ai-send-btn" id="aiSendBtn" disabled title="Enviar pregunta">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                    <div class="ai-input-hint">
                        <i class="bi bi-shield-lock"></i> Tus datos están protegidos · La IA corre en nuestros servidores privados, no exponemos tu información
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script defer src="../assets/js/asistente-ia.js"></script>

<?php if (!$isFragment): ?>
</main>
</body>

</html>
<?php endif; ?>
