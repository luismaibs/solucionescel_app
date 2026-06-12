/**
 * Cliente Supabase para frontend (singleton).
 *
 * Expone window.SupabaseFrontend con:
 *   - getClient()      → cliente supabase-js o null
 *   - signIn(email,pw) → Promise<{ok, access_token, refresh_token, user}>
 *   - signOut()        → Promise<void>
 *   - getSession()     → Promise<session|null>
 *   - onAuthChange(fn) → unsubscribe function
 *
 * Requiere: window.SUPABASE_CONFIG + window.supabase (UMD cargado via CDN)
 */

(function () {
    'use strict';

    var __client = null;
    var __authListeners = [];
    var __authUnsub = null;

    function getClient() {
        if (__client) return __client;

        var config = window.SUPABASE_CONFIG;
        if (!config || !config.url || !config.anonKey) {
            console.warn('[Supabase] Config no disponible');
            return null;
        }

        if (typeof window.supabase === 'undefined' || typeof window.supabase.createClient !== 'function') {
            console.warn('[Supabase] SDK no cargado');
            return null;
        }

        __client = window.supabase.createClient(config.url, config.anonKey, {
            auth: {
                autoRefreshToken: true,
                persistSession: true,
                detectSessionInUrl: false,
            },
            realtime: {
                heartbeatIntervalMs: 30000,
            },
        });

        // Escuchar cambios de estado de autenticacion y notificar listeners
        if (__client && typeof __client.auth.onAuthStateChange === 'function') {
            __authUnsub = __client.auth.onAuthStateChange(function (event, session) {
                for (var i = 0; i < __authListeners.length; i++) {
                    try { __authListeners[i](event, session); } catch (e) {}
                }
                // Emitir evento global para otros modulos (ej. realtime.js)
                try {
                    window.dispatchEvent(new CustomEvent('supabase:auth_change', {
                        detail: { event: event, session: session }
                    }));
                } catch (e) {}
            });
        }

        return __client;
    }

    function apiBase() {
        var fallback = window.location.origin + '/api/';
        var raw = window.APP_API_BASE || fallback;

        try {
            var url = new URL(raw, window.location.origin);
            var path = url.pathname.replace(/\\/g, '/');

            if (url.protocol !== window.location.protocol || /\/var\/www\/html(?:\/|$)/.test(path)) {
                return fallback;
            }

            path = path.replace(/\/api\/api\/?$/, '/api/');
            if (!/\/api\/$/.test(path)) {
                path = path.replace(/\/?$/, '/') + 'api/';
            }

            return url.origin + path;
        } catch (e) {
            return fallback;
        }
    }

    /**
     * Sign in via Supabase Auth (client-side).
     * Al login exitoso, notifica al backend para establecer cookie HttpOnly.
     */
    async function signIn(email, password) {
        var client = getClient();
        if (!client) return { ok: false, error: 'Cliente Supabase no disponible' };

        try {
            var result = await client.auth.signInWithPassword({ email: email, password: password });
            if (result.error) return { ok: false, error: result.error.message };

            // Notificar al backend para establecer cookie HttpOnly
            var bridgeRes = await fetch(apiBase() + 'auth_bridge', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    access_token: result.data.session.access_token,
                    refresh_token: result.data.session.refresh_token,
                }),
            });
            var bridgeData = await bridgeRes.json().catch(function () { return { ok: false }; });
            if (!bridgeData.ok) {
                return { ok: false, error: 'Error al establecer sesion en el servidor' };
            }

            return {
                ok: true,
                access_token: result.data.session.access_token,
                refresh_token: result.data.session.refresh_token,
                user: result.data.user,
            };
        } catch (e) {
            return { ok: false, error: e.message };
        }
    }

    /**
     * Sign out (limpia sesion en Supabase y cookie backend).
     */
    async function signOut(redirectUrl) {
        var client = getClient();
        if (client) {
            try { await client.auth.signOut(); } catch (e) {}
        }

        try {
            await fetch(apiBase() + 'auth_bridge?action=logout', { method: 'POST' });
        } catch (e) {}

        __authListeners = [];

        var base = window.APP_BASE_PATH || './';
        window.location.href = redirectUrl || base + 'login';
    }

    /**
     * Devuelve la sesion actual de Supabase si existe.
     */
    async function getSession() {
        var client = getClient();
        if (!client) return null;
        try {
            var result = await client.auth.getSession();
            return result.data.session;
        } catch (e) {
            return null;
        }
    }

    function onAuthChange(fn) {
        __authListeners.push(fn);
        // Inicializar el cliente si no esta creado (necesario para que onAuthStateChange funcione)
        getClient();
        return function unsubscribe() {
            var idx = __authListeners.indexOf(fn);
            if (idx !== -1) __authListeners.splice(idx, 1);
        };
    }

    window.SupabaseFrontend = {
        getClient: getClient,
        signIn: signIn,
        signOut: signOut,
        getSession: getSession,
        onAuthChange: onAuthChange,
    };
})();
