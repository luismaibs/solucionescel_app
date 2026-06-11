/**
 * Supabase Realtime — Canal Global Único
 *
 * Arquitectura: UN solo canal WebSocket para toda la app (todas las tablas).
 * - El canal se crea UNA vez y se reutiliza en reconexiones.
 * - Múltiples tablas comparten el mismo túnel; cambios se despachan via eventos.
 * - Backoff exponencial con jitter (max 30 s, 10 intentos).
 * - Polling fallback cada 15 s cuando se exceden reintentos.
 * - Ignora cambios propios durante 2 s post-accion.
 * - Limpieza garantizada en logout y navegacion (beforeunload).
 *
 * Requiere: window.SupabaseFrontend (supabase-client.js)
 *           window.REALTIME_CONFIG  { tenantId, tables: [...] }
 *
 * Eventos:
 *   window.addEventListener('realtime:change', function(e) {
 *     // e.detail = { table: 'inv_accesorios', eventType: 'UPDATE', payload: {...} }
 *   });
 */
(function () {
    'use strict';

    // Guard: si ya existe una instancia (re-carga via SPA), desconectar antes de re-init
    if (window.SupabaseRealtime && typeof window.SupabaseRealtime.disconnect === 'function') {
        try { window.SupabaseRealtime.disconnect(); } catch (_e) {}
    }

    var DEBUG = window.APP_DEBUG === true;

    var RECONNECT_BASE_MS      = 1000;
    var RECONNECT_CAP_MS       = 30000;
    var RECONNECT_MAX_ATTEMPTS = 10;
    var JITTER_MAX_MS          = 1000;
    var HEARTBEAT_TIMEOUT_MS   = 45000;
    var HEARTBEAT_CHECK_MS     = 5000;
    var POLLING_INTERVAL_MS    = 15000;
    var DEBOUNCE_MS            = 800;
    var DISCONNECT_DEBOUNCE_MS = 300;
    var SKIP_WINDOW_MS         = 2000;
    var SESSION_RETRY_MAX     = 3;
    var SESSION_RETRY_DELAY   = 300;

    // Estado interno — un solo canal global
    var _client            = null;
    var _channel           = null;
    var _config            = null;
    var _subscribedTables  = [];

    var _state             = 'disconnected';
    var _reconnectAttempts = 0;
    var _reconnectTimer    = null;
    var _heartbeatTimer    = null;
    var _lastHeartbeat     = 0;
    var _pollingTimer      = null;
    var _debounceTimer     = null;
    var _disconnectTimer   = null;
    var _authUnsub         = null;
    var _initPending       = false;

    // ─── Logging ────────────────────────────────────

    function log(level, msg, obj) {
        if (level === 'debug' && !DEBUG) return;
        var prefix = '[Realtime]';
        switch (level) {
            case 'error': console.error(prefix, msg, obj || ''); break;
            case 'warn':  console.warn(prefix, msg, obj || '');  break;
            case 'info':  console.log(prefix, msg, obj || '');   break;
            case 'debug': console.log(prefix, msg, obj || '');   break;
        }
    }

    // ─── Maquina de estados ─────────────────────────

    function setState(newState, reason) {
        var prev = _state;
        if (prev === newState) return;
        _state = newState;
        log('debug', prev + ' → ' + newState + (reason ? ' (' + reason + ')' : ''));
        updateIndicatorDOM(newState);
    }

    // ─── Indicador visual ───────────────────────────

    function ensureIndicator() {
        if (document.getElementById('realtimeIndicator')) return;
        var container = document.querySelector('.app-topbar-actions');
        if (!container) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', ensureIndicator);
            }
            return;
        }
        var dot = document.createElement('span');
        dot.id = 'realtimeIndicator';
        dot.className = 'rt-indicator rt-disconnected';
        dot.setAttribute('role', 'status');
        dot.setAttribute('aria-label', 'Tiempo real desconectado');
        dot.title = 'Tiempo real desconectado';
        var themeBtn = document.getElementById('themeToggleBtn');
        if (themeBtn) {
            container.insertBefore(dot, themeBtn);
        } else {
            container.insertBefore(dot, container.firstChild);
        }
    }

    function updateIndicatorDOM(state) {
        var dot = document.getElementById('realtimeIndicator');
        if (!dot) { ensureIndicator(); dot = document.getElementById('realtimeIndicator'); if (!dot) return; }
        dot.classList.remove('rt-connected', 'rt-connecting', 'rt-reconnecting', 'rt-disconnected', 'rt-fallback');
        var label = '';
        switch (state) {
            case 'connected':
                dot.classList.add('rt-connected');
                label = 'Tiempo real activo';
                break;
            case 'connecting':
                dot.classList.add('rt-connecting');
                label = 'Conectando tiempo real…';
                break;
            case 'reconnecting':
                dot.classList.add('rt-reconnecting');
                label = 'Reconectando (intento ' + _reconnectAttempts + ')';
                break;
            case 'fallback_polling':
                dot.classList.add('rt-fallback');
                label = 'Actualizacion por sondeo (15 s) — WebSocket caido';
                break;
            default:
                dot.classList.add('rt-disconnected');
                label = 'Tiempo real desconectado';
                break;
        }
        dot.title = label;
        dot.setAttribute('aria-label', label);
    }

    // ─── Heartbeat watchdog ─────────────────────────

    function startHeartbeatWatchdog() {
        stopHeartbeatWatchdog();
        _lastHeartbeat = Date.now();
        _heartbeatTimer = setInterval(function () {
            if (Date.now() - _lastHeartbeat > HEARTBEAT_TIMEOUT_MS) {
                log('warn', 'Heartbeat perdido — reconectando');
                handleDisconnect('heartbeat_lost');
            }
        }, HEARTBEAT_CHECK_MS);
    }

    function stopHeartbeatWatchdog() {
        if (_heartbeatTimer) { clearInterval(_heartbeatTimer); _heartbeatTimer = null; }
    }

    function recordHeartbeat() { _lastHeartbeat = Date.now(); }

    // ─── Polling fallback ───────────────────────────

    function startPolling() {
        stopPolling();
        log('info', 'Polling fallback cada ' + (POLLING_INTERVAL_MS / 1000) + 's');
        _pollingTimer = setInterval(forceRefresh, POLLING_INTERVAL_MS);
    }

    function stopPolling() {
        if (_pollingTimer) { clearInterval(_pollingTimer); _pollingTimer = null; }
    }

    // ─── Reconexion con backoff exponencial + jitter ─

    function scheduleReconnect() {
        stopReconnect();
        _reconnectAttempts++;

        if (_reconnectAttempts > RECONNECT_MAX_ATTEMPTS) {
            log('error', 'Maximos reintentos (' + RECONNECT_MAX_ATTEMPTS + ') — fallback a polling');
            setState('fallback_polling', 'max_attempts');
            disconnectChannel();
            startPolling();
            return;
        }

        var delay = Math.min(RECONNECT_CAP_MS, RECONNECT_BASE_MS * Math.pow(2, _reconnectAttempts - 1));
        delay = Math.round(delay + Math.random() * JITTER_MAX_MS);

        log('info', 'Reconexion en ' + (delay / 1000).toFixed(1) + 's (intento ' + _reconnectAttempts + ')');
        setState('reconnecting');

        _reconnectTimer = setTimeout(function () {
            _reconnectTimer = null;
            initRealtime(_config);
        }, delay);
    }

    function stopReconnect() {
        if (_reconnectTimer) { clearTimeout(_reconnectTimer); _reconnectTimer = null; }
    }

    function handleDisconnect(reason) {
        if (_disconnectTimer) return;
        log('warn', 'Desconexion detectada: ' + reason);
        stopHeartbeatWatchdog();

        _disconnectTimer = setTimeout(function () {
            _disconnectTimer = null;
            scheduleReconnect();
        }, DISCONNECT_DEBOUNCE_MS);
    }

    // ─── Skip de cambios propios ────────────────────

    function skipOwnChanges() {
        try { return window.__REALTIME_SKIP && Date.now() < window.__REALTIME_SKIP; } catch (e) { return false; }
    }

    // ─── Refresh global (polling fallback) ──────────
    //  Solo dispara reparaciones (backward compat). Los modulos que
    //  usen el evento 'realtime:change' se refrescan por esa via.

    function forceRefresh() {
        try { if (window.loadReparaciones) window.loadReparaciones(); } catch (e) {}
        try {
            var pipeline = document.getElementById('viewPipelineContainer');
            if (pipeline && pipeline.style.display !== 'none' && window.loadReparacionesPipeline) {
                window.loadReparacionesPipeline();
            }
        } catch (e) {}
    }

    // ─── Dispatch de cambios por tabla ──────────────

    /**
     * Handler generico para postgres_changes de cualquier tabla.
     * 1. Emite evento 'realtime:change' para modulos que escuchen (inventario, etc.)
     * 2. Backward compat: si la tabla es reparaciones, ejecuta los handlers legacy.
     */
    function makeTableHandler(table) {
        return function (payload) {
            if (skipOwnChanges()) {
                log('debug', 'Cambio propio ignorado [' + table + ']');
                return;
            }
            recordHeartbeat();
            log('debug', 'postgres_changes [' + table + ']: ' + payload.eventType + ' id=' + ((payload.new || payload.old || {}).id));

            // 1. Emitir evento generico para cualquier modulo
            try {
                window.dispatchEvent(new CustomEvent('realtime:change', {
                    detail: { table: table, eventType: payload.eventType, payload: payload }
                }));
            } catch (e) {}

            // 2. Backward compat: reparaciones → debounced refresh legacy
            if (table === 'reparaciones') {
                if (_debounceTimer) clearTimeout(_debounceTimer);
                _debounceTimer = setTimeout(function () {
                    forceRefresh();
                    _debounceTimer = null;
                }, DEBOUNCE_MS);
            }
        };
    }

    // ─── Canal unico global ─────────────────────────
    //  Garantiza: solo UN canal activo a la vez.
    //  Todas las tablas comparten el mismo tunel WebSocket.

    function initChannel(client, config) {
        disconnectChannel();

        _client = client;
        _subscribedTables = config.tables || ['reparaciones'];

        try {
            _channel = client.channel('global-panel', {
                config: { broadcast: { self: false } }
            });

            var tenantFilter = 'tenant_id=eq.' + (config.tenantId || 1);

            for (var i = 0; i < _subscribedTables.length; i++) {
                var table = _subscribedTables[i];
                _channel.on('postgres_changes', {
                    event: '*',
                    schema: 'public',
                    table: table,
                    filter: tenantFilter,
                }, makeTableHandler(table));
            }

            _channel.subscribe(function (status, err) {
                switch (status) {
                    case 'SUBSCRIBED':
                        log('info', 'Canal global suscrito [' + _subscribedTables.join(', ') + ']');
                        setState('connected');
                        startHeartbeatWatchdog();
                        stopPolling();
                        _reconnectAttempts = 0;
                        _initPending = false;
                        break;
                    case 'CHANNEL_ERROR':
                        log('error', 'Error de canal', err);
                        _initPending = false;
                        break;
                    case 'CLOSED':
                    case 'TIMED_OUT':
                        log('warn', 'Canal cerrado (' + status + ')');
                        _initPending = false;
                        handleDisconnect('channel_' + status.toLowerCase());
                        break;
                }
            });
        } catch (e) {
            log('error', 'Excepcion al crear canal: ' + e.message);
            _initPending = false;
            handleDisconnect('channel_exception');
        }
    }

    function disconnectChannel() {
        if (_channel) {
            try {
                _channel.unsubscribe();
                if (_client && typeof _client.removeChannel === 'function') {
                    _client.removeChannel(_channel);
                }
            } catch (e) { /* ignore */ }
            _channel = null;
        }
    }

    function destroy() {
        stopHeartbeatWatchdog();
        stopPolling();
        stopReconnect();
        if (_disconnectTimer) { clearTimeout(_disconnectTimer); _disconnectTimer = null; }
        if (_debounceTimer) { clearTimeout(_debounceTimer); _debounceTimer = null; }
        disconnectChannel();
        _client = null;
        _subscribedTables = [];
        _reconnectAttempts = 0;
        _initPending = false;
        setState('disconnected');
    }

    // ─── Inicializacion con guard anti-concurrencia ──

    function initRealtime(config, retryCount) {
        if (_initPending) {
            log('debug', 'Inicializacion ya en curso — ignorando llamada duplicada');
            return;
        }

        retryCount = retryCount || 0;
        stopReconnect();
        _config = config || _config;

        if (!_config) {
            log('error', 'Sin configuracion de realtime');
            setState('disconnected');
            return;
        }

        _initPending = true;
        setState('connecting');

        if (!window.SupabaseFrontend || typeof window.SupabaseFrontend.getClient !== 'function') {
            log('warn', 'SupabaseFrontend no disponible');
            setState('disconnected');
            _initPending = false;
            return;
        }

        var client = window.SupabaseFrontend.getClient();
        if (!client) {
            log('warn', 'Cliente Supabase no disponible');
            setState('disconnected');
            _initPending = false;
            return;
        }

        client.auth.getSession().then(function (result) {
            var session = result.data && result.data.session;
            if (!session) {
                if (retryCount < SESSION_RETRY_MAX) {
                    log('info', 'Sesion no disponible (intento ' + (retryCount + 1) + '/' + SESSION_RETRY_MAX + ') — reintentando en ' + SESSION_RETRY_DELAY + 'ms');
                    _initPending = false;
                    setState('reconnecting');
                    setTimeout(function () { initRealtime(_config, retryCount + 1); }, SESSION_RETRY_DELAY);
                    return;
                }
                log('info', 'Sin sesion activa');
                setState('disconnected');
                _initPending = false;
                return;
            }
            initChannel(client, _config);
        }).catch(function (err) {
            log('error', 'Error al obtener sesion: ' + (err.message || err));
            _initPending = false;
            setState('disconnected');
            handleDisconnect('session_error');
        });
    }

    // ─── Auth listener ──────────────────────────────

    function setupAuthListener() {
        if (_authUnsub) return;
        if (!window.SupabaseFrontend || typeof window.SupabaseFrontend.onAuthChange !== 'function') return;

        _authUnsub = window.SupabaseFrontend.onAuthChange(function (event, session) {
            log('debug', 'Auth change: ' + event);
            switch (event) {
                case 'SIGNED_OUT':
                    log('info', 'Sesion cerrada — destruyendo canal');
                    destroy();
                    if (_authUnsub) { _authUnsub(); _authUnsub = null; }
                    break;
                case 'TOKEN_REFRESHED':
                    recordHeartbeat();
                    break;
                case 'SIGNED_IN':
                    log('info', 'Nueva sesion');
                    _reconnectAttempts = 0;
                    stopPolling();
                    initRealtime(_config);
                    break;
                case 'INITIAL_SESSION':
                    if (session && _state === 'disconnected') {
                        log('info', 'Sesion inicial detectada — iniciando canal');
                        _reconnectAttempts = 0;
                        stopPolling();
                        initRealtime(_config);
                    }
                    break;
            }
        });
    }

    // ─── API publica ────────────────────────────────

    window.SupabaseRealtime = {
        init: initRealtime,
        disconnect: destroy,
        getState: function () { return _state; },
        getSubscribedTables: function () { return _subscribedTables.slice(); },
        forceReconnect: function () {
            log('info', 'Reconexion forzada');
            _reconnectAttempts = 0;
            _initPending = false;
            stopPolling();
            initRealtime(_config);
        }
    };

    // ─── Cleanup en navegacion ──────────────────────

    window.addEventListener('beforeunload', function () {
        destroy();
    });

    // ─── Auto-inicializacion ────────────────────────

    var initialConfig = window.REALTIME_CONFIG;
    if (initialConfig) {
        ensureIndicator();
        setupAuthListener();

        function boot() {
            setTimeout(function () { initRealtime(initialConfig); }, 600);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }
    }
})();
