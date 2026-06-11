(function () {
        'use strict';

        // ═══════════════════════════════════════
        // CONFIGURACIÓN Y ESTADO
        // ═══════════════════════════════════════
        const API_URL = (window.APP_API_BASE || '../api/') + 'api_ai_query';
        let chartCounter = 0;
        let isProcessing = false;
        let useReasoner = false;
        let currentConversacionId = generarUUID(); // Cada sesión nueva empieza con un UUID fresco

        // Elementos del DOM
        const chatArea      = document.getElementById('aiChatArea');
        const chatContainer = document.getElementById('aiChatContainer');
        const input         = document.getElementById('aiInput');
        const sendBtn       = document.getElementById('aiSendBtn');
        const sidebar       = document.getElementById('aiSidebar');
        const historyList   = document.getElementById('aiHistoryList');
        const sidebarToggle = document.getElementById('aiSidebarToggle');
        const sidebarClose  = document.getElementById('aiSidebarCloseBtn');
        const newChatBtn    = document.getElementById('aiNewChatBtn');
        const modelToggle   = document.getElementById('aiModelToggle');
        const modelLabel    = document.getElementById('aiModelLabel');

        /**
         * Genera un UUID v4 en el cliente.
         */
        function generarUUID() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = Math.random() * 16 | 0;
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            });
        }

        /**
         * HTML de la pantalla de bienvenida (reutilizable).
         */
        var welcomeHTML = ''
            + '<div class="ai-welcome" id="aiWelcome">'
            + '  <div class="ai-welcome-icon"><i class="bi bi-stars"></i></div>'
            + '  <h2>Asistente de Análisis IA</h2>'
            + '  <p>Pregúntame cualquier cosa sobre tus datos. Analizo tu base de datos y genero visualizaciones inteligentes al instante.</p>'
            + '  <div class="ai-suggestions" id="aiSuggestions">'
            + '    <div class="ai-suggestion-chip" data-q="¿Cuántas reparaciones hay activas en el taller?"><i class="bi bi-tools"></i> Reparaciones activas</div>'
            + '    <div class="ai-suggestion-chip" data-q="¿Cuál es la marca de celular más reparada?"><i class="bi bi-phone"></i> Marca más popular</div>'
            + '    <div class="ai-suggestion-chip" data-q="Muéstrame la tendencia de reparaciones por mes en los últimos 6 meses"><i class="bi bi-graph-up"></i> Tendencia mensual</div>'
            + '    <div class="ai-suggestion-chip" data-q="¿Cuál es el valor total del inventario?"><i class="bi bi-currency-dollar"></i> Valor del inventario</div>'
            + '    <div class="ai-suggestion-chip" data-q="Top 10 modelos de celulares más reparados"><i class="bi bi-trophy"></i> Top modelos</div>'
            + '    <div class="ai-suggestion-chip" data-q="¿Cuál es la distribución de estados de las reparaciones?"><i class="bi bi-pie-chart"></i> Estados de reparación</div>'
            + '    <div class="ai-suggestion-chip" data-q="¿Cuántas conversaciones de soporte están pausadas esperando atención?"><i class="bi bi-headset"></i> Soporte pendiente</div>'
            + '    <div class="ai-suggestion-chip" data-q="¿Cuál es la tasa de éxito de las reparaciones?"><i class="bi bi-lightning-charge"></i> Tasa de éxito</div>'
            + '  </div>'
            + '</div>';

        // ═══════════════════════════════════════
        // SIDEBAR
        // ═══════════════════════════════════════
        function toggleSidebar() {
            sidebar.classList.toggle('collapsed');
        }

        sidebarToggle.addEventListener('click', toggleSidebar);
        sidebarClose.addEventListener('click', function () {
            sidebar.classList.add('collapsed');
        });

        // ═══════════════════════════════════════
        // NUEVA CONVERSACIÓN
        // ═══════════════════════════════════════
        newChatBtn.addEventListener('click', function () {
            iniciarNuevaConversacion();
            if (window.SCToast) SCToast.show('Nueva conversación iniciada', 'info');
        });

        function iniciarNuevaConversacion() {
            currentConversacionId = generarUUID();
            chatContainer.innerHTML = welcomeHTML;
            inicializarSugerencias();
            chartCounter = 0;
            isProcessing = false;
            sendBtn.disabled = true;
            input.value = '';
            input.style.height = 'auto';
            input.focus();

            // Desmarcar conversación activa en sidebar
            var items = historyList.querySelectorAll('.ai-history-item');
            items.forEach(function (el) { el.classList.remove('active'); });
        }

        // ═══════════════════════════════════════
        // TOGGLE MODELO IA
        // ═══════════════════════════════════════
        modelToggle.addEventListener('click', function () {
            useReasoner = !useReasoner;
            modelToggle.classList.toggle('active', useReasoner);
            modelLabel.textContent = useReasoner ? 'Profundo' : 'Rápido';

            if (useReasoner && window.SCToast) {
                SCToast.show('Modo Razonador activado — Análisis más profundos pero más lentos (20-60s)', 'info');
            }
        });

        // ═══════════════════════════════════════
        // INPUT AUTO-RESIZE
        // ═══════════════════════════════════════
        input.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            sendBtn.disabled = this.value.trim().length === 0 || isProcessing;
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (!sendBtn.disabled) enviarPregunta();
            }
        });

        sendBtn.addEventListener('click', enviarPregunta);

        // ═══════════════════════════════════════
        // SUGERENCIAS
        // ═══════════════════════════════════════
        function inicializarSugerencias() {
            var chips = chatContainer.querySelectorAll('.ai-suggestion-chip');
            chips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    var q = this.getAttribute('data-q');
                    input.value = q;
                    input.dispatchEvent(new Event('input'));
                    enviarPregunta();
                });
            });
        }
        inicializarSugerencias();

        // ═══════════════════════════════════════
        // ENVIAR PREGUNTA
        // ═══════════════════════════════════════
        function enviarPregunta() {
            var pregunta = input.value.trim();
            if (!pregunta || isProcessing) return;

            isProcessing = true;
            sendBtn.disabled = true;
            input.value = '';
            input.style.height = 'auto';

            // Ocultar bienvenida
            var welcomeEl = document.getElementById('aiWelcome');
            if (welcomeEl) welcomeEl.style.display = 'none';

            // Agregar mensaje del usuario
            agregarMensajeUsuario(pregunta);

            // Mostrar indicador de escritura con info del modelo
            var typing = mostrarTyping();

            // Llamar al API con la preferencia del modelo y la conversación activa
            fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'query',
                    question: pregunta,
                    use_reasoner: useReasoner,
                    conversacion_id: currentConversacionId
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                typing.remove();

                if (data.success) {
                    agregarMensajeIA(data);
                } else {
                    agregarMensajeError(data.error || 'Error desconocido');
                }
            })
            .catch(function (err) {
                typing.remove();
                agregarMensajeError('Error de conexión: ' + err.message);
            })
            .finally(function () {
                isProcessing = false;
                sendBtn.disabled = input.value.trim().length === 0;
                cargarHistorial();
            });
        }

        // ═══════════════════════════════════════
        // CREAR MENSAJES
        // ═══════════════════════════════════════
        function agregarMensajeUsuario(texto) {
            var html = '<div class="ai-message user">'
                + '<div class="ai-msg-avatar"><i class="bi bi-person-fill"></i></div>'
                + '<div class="ai-msg-body">'
                + '<div class="ai-msg-bubble">' + escapeHtml(texto) + '</div>'
                + '<div class="ai-msg-meta"><span class="ai-msg-time">' + horaActual() + '</span></div>'
                + '</div></div>';

            chatContainer.insertAdjacentHTML('beforeend', html);
            scrollToBottom();
        }

        function agregarMensajeIA(data) {
            var msgId = 'ai-msg-' + Date.now();
            var chartId = 'chart-' + (++chartCounter);

            var html = '<div class="ai-message assistant" id="' + msgId + '">'
                + '<div class="ai-msg-avatar"><i class="bi bi-stars"></i></div>'
                + '<div class="ai-msg-body">'
                + '<div class="ai-msg-bubble">' + formatearRespuesta(data.answer || '') + '</div>';

            // Visualización
            var chartConfig = data.chart_config;
            if (chartConfig) {
                if (chartConfig.type === 'kpi') {
                    html += renderKPI(chartConfig);
                } else if (chartConfig.type === 'table') {
                    html += renderTabla(chartConfig);
                } else if (chartConfig.type && chartConfig.data) {
                    html += renderChart(chartId, chartConfig);
                }
            }

            // SQL colapsable
            if (data.sql_used) {
                html += '<div class="ai-msg-sql">'
                    + '<span class="ai-msg-sql-toggle" onclick="this.nextElementSibling.classList.toggle(\'show\')">'
                    + '<i class="bi bi-code-slash"></i> Ver SQL generado'
                    + '</span>'
                    + '<div class="ai-msg-sql-code">' + escapeHtml(data.sql_used) + '</div>'
                    + '</div>';
            }

            // Metadatos
            html += '<div class="ai-msg-meta">'
                + '<span class="ai-msg-time">' + horaActual();

            if (data.tiempo_ms) {
                html += ' · ' + (data.tiempo_ms / 1000).toFixed(1) + 's';
            }
            if (data.row_count !== undefined && data.row_count !== null) {
                html += ' · ' + data.row_count + ' filas';
            }
            if (data.model_used) {
                var modelIcon = data.model_used === 'deepseek-reasoner' ? '<i class="bi bi-lightbulb" style="color:#a78bfa"></i> ' : '';
                var modelName = data.model_used === 'deepseek-reasoner' ? 'Razonador' : 'Rápido';
                html += ' · ' + modelIcon + modelName;
            }

            html += '</span>'
                + '<div class="ai-msg-actions">';

            // Botón guardar: guarda toda la conversación
            html += '<button class="ai-msg-action-btn js-save-conv" data-conv-id="' + escapeHtml(currentConversacionId) + '" title="Guardar conversación">'
                + '<i class="bi bi-bookmark"></i> Guardar'
                + '</button>';

            html += '</div></div>';
            html += '</div></div>';

            chatContainer.insertAdjacentHTML('beforeend', html);
            scrollToBottom();

            // Renderizar Chart.js si hay gráfica
            if (chartConfig && chartConfig.type && chartConfig.data
                && chartConfig.type !== 'kpi' && chartConfig.type !== 'table') {
                requestAnimationFrame(function () {
                    inicializarChart(chartId, chartConfig);
                });
            }
        }

        function agregarMensajeError(error) {
            var html = '<div class="ai-message assistant">'
                + '<div class="ai-msg-avatar"><i class="bi bi-stars"></i></div>'
                + '<div class="ai-msg-body">'
                + '<div class="ai-msg-error">'
                + '<i class="bi bi-exclamation-triangle-fill"></i>'
                + '<div>' + escapeHtml(error) + '</div>'
                + '</div>'
                + '<div class="ai-msg-meta"><span class="ai-msg-time">' + horaActual() + '</span></div>'
                + '</div></div>';

            chatContainer.insertAdjacentHTML('beforeend', html);
            scrollToBottom();
        }

        function mostrarTyping() {
            var modoTxt = useReasoner
                ? '<i class="bi bi-lightbulb" style="color:#a78bfa"></i> Razonando en profundidad... (puede tardar 20-60s)'
                : '<i class="bi bi-arrow-repeat"></i> Analizando tus datos...';

            var html = '<div class="ai-typing" id="aiTyping">'
                + '<div class="ai-msg-avatar" style="background:linear-gradient(135deg,rgba(139,92,246,0.3),rgba(59,130,246,0.3));color:#a78bfa;border:1px solid rgba(139,92,246,0.2);width:36px;height:36px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:0.85rem;">'
                + '<i class="bi bi-stars"></i></div>'
                + '<div>'
                + '<div class="ai-typing-dots">'
                + '<div class="ai-typing-dot"></div>'
                + '<div class="ai-typing-dot"></div>'
                + '<div class="ai-typing-dot"></div>'
                + '</div>'
                + '<div class="ai-typing-label">' + modoTxt + '</div>'
                + '</div></div>';

            chatContainer.insertAdjacentHTML('beforeend', html);
            scrollToBottom();

            return document.getElementById('aiTyping');
        }

        // ═══════════════════════════════════════
        // RENDERIZAR VISUALIZACIONES
        // ═══════════════════════════════════════
        function renderKPI(config) {
            var value = config.kpi_value !== undefined ? config.kpi_value : (config.value || '-');
            var label = config.kpi_label || config.label || '';

            return '<div class="ai-msg-kpi">'
                + '<div class="ai-kpi-value">' + escapeHtml(String(value)) + '</div>'
                + '<div class="ai-kpi-label">' + escapeHtml(label) + '</div>'
                + '</div>';
        }

        function renderTabla(config) {
            var td = config.table_data;
            if (!td || !td.headers || !td.rows) return '';

            var html = '<div class="ai-msg-table"><table><thead><tr>';
            td.headers.forEach(function (h) {
                html += '<th>' + escapeHtml(String(h)) + '</th>';
            });
            html += '</tr></thead><tbody>';

            td.rows.forEach(function (row) {
                html += '<tr>';
                row.forEach(function (cell) {
                    html += '<td>' + escapeHtml(String(cell !== null && cell !== undefined ? cell : '-')) + '</td>';
                });
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            return html;
        }

        function renderChart(chartId, config) {
            var title = '';
            if (config.options && config.options.plugins && config.options.plugins.title && config.options.plugins.title.text) {
                title = config.options.plugins.title.text;
            }

            return '<div class="ai-msg-chart">'
                + (title ? '<div class="ai-msg-chart-title">' + escapeHtml(title) + '</div>' : '')
                + '<div class="ai-chart-canvas-wrap">'
                + '<canvas id="' + chartId + '"></canvas>'
                + '</div></div>';
        }

        function inicializarChart(chartId, config) {
            var canvas = document.getElementById(chartId);
            if (!canvas) return;

            var ctx = canvas.getContext('2d');
            var type = config.type || 'bar';

            // Mapear 'area' a 'line' con fill
            if (type === 'area') {
                type = 'line';
                if (config.data && config.data.datasets) {
                    config.data.datasets.forEach(function (ds) {
                        ds.fill = true;
                        ds.tension = ds.tension || 0.4;
                    });
                }
            }

            // Opciones por defecto para tema oscuro
            var defaultOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: (type === 'doughnut' || type === 'pie'),
                        labels: { color: '#94a3b8', font: { family: "'Inter', sans-serif", size: 11 }, boxWidth: 12, padding: 15 }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#cbd5e1',
                        borderColor: 'rgba(255,255,255,0.1)',
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 8,
                    }
                },
                scales: {}
            };

            // Agregar escalas para tipos que las necesitan
            if (type !== 'doughnut' && type !== 'pie') {
                defaultOptions.scales = {
                    x: {
                        grid: { color: 'rgba(255,255,255,0.04)', display: true },
                        ticks: { color: '#64748b', font: { size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255,255,255,0.04)', borderDash: [4, 4] },
                        ticks: { color: '#64748b', font: { size: 11 } }
                    }
                };
            }

            // Merge con opciones del API
            var options = Object.assign({}, defaultOptions);
            if (config.options) {
                // Merge superficial de plugins
                if (config.options.plugins) {
                    Object.assign(options.plugins, config.options.plugins);
                }
                if (config.options.scales) {
                    Object.assign(options.scales, config.options.scales);
                }
                if (config.options.indexAxis) {
                    options.indexAxis = config.options.indexAxis;
                }
            }

            // Asegurar que los datasets tienen colores
            if (config.data && config.data.datasets) {
                var palette = [
                    'rgba(59, 130, 246, 0.8)', 'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)', 'rgba(239, 68, 68, 0.8)',
                    'rgba(139, 92, 246, 0.8)', 'rgba(6, 182, 212, 0.8)',
                    'rgba(236, 72, 153, 0.8)', 'rgba(234, 179, 8, 0.8)'
                ];

                config.data.datasets.forEach(function (ds, idx) {
                    if (!ds.backgroundColor) {
                        if (type === 'doughnut' || type === 'pie') {
                            ds.backgroundColor = palette.slice(0, (config.data.labels || []).length);
                        } else {
                            ds.backgroundColor = palette[idx % palette.length];
                        }
                    }
                    if ((type === 'line') && !ds.borderColor) {
                        ds.borderColor = palette[idx % palette.length];
                        ds.borderWidth = ds.borderWidth || 3;
                        ds.pointBackgroundColor = '#0f172a';
                        ds.pointBorderColor = palette[idx % palette.length];
                        ds.pointBorderWidth = 2;
                        ds.pointRadius = 4;
                    }
                    if (type === 'bar' && !ds.borderRadius) {
                        ds.borderRadius = 6;
                    }
                });
            }

            try {
                new Chart(ctx, {
                    type: type,
                    data: config.data || { labels: [], datasets: [] },
                    options: options
                });
            } catch (e) {
                console.error('Error al crear gráfica:', e);
            }
        }

        // ═══════════════════════════════════════
        // EVENT DELEGATION (sin handlers globales)
        // ═══════════════════════════════════════
        chatContainer.addEventListener('click', function (e) {
            var btn = e.target.closest('.js-save-conv');
            if (btn) {
                guardarConversacion(btn.getAttribute('data-conv-id'));
            }
        });

        historyList.addEventListener('click', function (e) {
            var item = e.target.closest('.ai-history-item');
            if (item) {
                cargarConversacionPrevia(item.getAttribute('data-conv-id'));
            }
        });

        var historialDebounceTimer = null;
        function cargarHistorial() {
            clearTimeout(historialDebounceTimer);
            historialDebounceTimer = setTimeout(function () {
                fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_history', limit: 30 })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.historial) {
                    renderHistorial(data.historial);
                }
            })
            .catch(function () {});
            }, 300);
        }

        function renderHistorial(items) {
            if (!items.length) {
                historyList.innerHTML = '<div class="ai-history-empty">'
                    + '<i class="bi bi-chat-square-text"></i>'
                    + '<span>Tus conversaciones aparecerán aquí</span>'
                    + '</div>';
                return;
            }

            var html = '';
            items.forEach(function (item) {
                var convId = item.conversacion_id;
                var icon = item.guardado == 1 ? 'bi-bookmark-fill' : 'bi-chat-square-text';
                var cls = item.guardado == 1 ? ' saved' : '';
                var isActive = (convId === currentConversacionId) ? ' active' : '';
                var titulo = item.titulo || item.pregunta || 'Sin título';
                if (titulo.length > 55) titulo = titulo.substring(0, 55) + '...';

                var msgCount = item.msg_count || 1;
                var fecha = formatearFechaCorta(item.last_activity || item.created_at);

                html += '<div class="ai-history-item' + cls + isActive + '" data-conv-id="' + escapeHtml(convId) + '">'
                    + '<div class="ai-history-icon"><i class="bi ' + icon + '"></i></div>'
                    + '<div class="ai-history-text">'
                    + '<div class="ai-history-title">' + escapeHtml(titulo) + '</div>'
                    + '<div class="ai-history-date">' + escapeHtml(fecha) + (msgCount > 1 ? ' · ' + msgCount + ' msgs' : '') + '</div>'
                    + '</div></div>';
            });

            historyList.innerHTML = html;
        }

        // ═══════════════════════════════════════
        // GUARDAR CONVERSACIÓN
        // ═══════════════════════════════════════
        function guardarConversacion(convId) {
            fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'save_analysis', conversacion_id: convId })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    var btns = document.querySelectorAll('.js-save-conv[data-conv-id="' + convId + '"]');
                    btns.forEach(function (btn) {
                        btn.classList.add('saved');
                        btn.innerHTML = '<i class="bi bi-bookmark-fill"></i> Guardado';
                    });
                    if (window.SCToast) SCToast.show('Conversación guardada', 'success');
                    cargarHistorial();
                }
            })
            .catch(function () {
                if (window.SCToast) SCToast.show('Error al guardar', 'error');
            });
        }

        // ═══════════════════════════════════════
        // CARGAR CONVERSACIÓN COMPLETA
        // ═══════════════════════════════════════
        function cargarConversacionPrevia(convId) {
            if (convId === currentConversacionId) return;

            fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_conversation', conversacion_id: convId })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.mensajes && data.mensajes.length) {
                    currentConversacionId = convId;
                    chatContainer.innerHTML = '';
                    chartCounter = 0;

                    data.mensajes.forEach(function (msg) {
                        agregarMensajeUsuario(msg.pregunta);

                        var viz = null;
                        try { viz = JSON.parse(msg.visualizacion); } catch (e) {}

                        agregarMensajeIA({
                            answer: msg.respuesta,
                            sql_used: msg.sql_generado,
                            chart_config: viz,
                            historial_id: msg.id,
                            conversacion_id: convId,
                            row_count: null,
                            tiempo_ms: null,
                            model_used: null
                        });
                    });

                    var items = historyList.querySelectorAll('.ai-history-item');
                    items.forEach(function (el) {
                        el.classList.toggle('active', el.getAttribute('data-conv-id') === convId);
                    });

                    scrollToTop();
                }
            })
            .catch(function () {
                if (window.SCToast) SCToast.show('Error al cargar conversación', 'error');
            });
        }

        // ═══════════════════════════════════════
        // UTILIDADES (usa window.escapeHtml de utils.js)
        // ═══════════════════════════════════════
        var escapeHtml = window.escapeHtml || function (s) {
            if (s == null) return '';
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
        };

        function formatearRespuesta(text) {
            if (!text) return '';

            // Escapar HTML primero
            var safe = escapeHtml(text);

            // Convertir **negrita**
            safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

            // Convertir *cursiva*
            safe = safe.replace(/\*([^*]+)\*/g, '<em>$1</em>');

            // Convertir listas con •
            safe = safe.replace(/^[•\-]\s+(.+)$/gm, '<li>$1</li>');
            safe = safe.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');

            // Convertir saltos de línea
            safe = safe.replace(/\n/g, '<br>');

            return safe;
        }

        function horaActual() {
            var d = new Date();
            return d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
        }

        function formatearFechaCorta(str) {
            if (!str) return '';
            var d = new Date(str);
            if (isNaN(d.getTime())) return str;

            var now = new Date();
            if (d.toDateString() === now.toDateString()) {
                return 'Hoy ' + d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
            }

            var yesterday = new Date(now - 864e5);
            if (d.toDateString() === yesterday.toDateString()) {
                return 'Ayer ' + d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
            }

            return d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' });
        }

        function scrollToBottom() {
            requestAnimationFrame(function () {
                chatArea.scrollTop = chatArea.scrollHeight;
            });
        }

        function scrollToTop() {
            requestAnimationFrame(function () {
                chatArea.scrollTop = 0;
            });
        }

        // ═══════════════════════════════════════
        // INICIALIZACIÓN
        // ═══════════════════════════════════════
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
        Chart.defaults.font.family = "'Inter', sans-serif";

        // Cargar historial al inicio
        cargarHistorial();

        // Focus en el input
        input.focus();

        // Sidebar colapsado en móvil por defecto
        if (window.innerWidth < 992) {
            sidebar.classList.add('collapsed');
        }

    })();