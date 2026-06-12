/**
 * SPA Router para SOLUCIONESCEL
 *
 * Intercepta navegación del sidebar/topbar y carga módulos via AJAX
 * sin recargar la página completa (shell + assets se mantienen).
 */
(function () {
    'use strict';

    // ── Config ──────────────────────────────────────────────
    var CONTENT_ID = 'app-content';
    var LOADING_BAR_ID = 'spa-loading-bar';
    var INTERNAL_PREFIXES = ['/modules/', '/index'];
    var EXCLUDED_PATHS = ['/logout', '/login', '/api/', '/bot'];

    // ── State ───────────────────────────────────────────────
    var isNavigating = false;
    var currentAbortController = null;
    var currentModuleCss = [];  // track loaded module CSS <link> elements

    // ── Loading bar ─────────────────────────────────────────
    function getOrCreateLoadingBar() {
        var bar = document.getElementById(LOADING_BAR_ID);
        if (!bar) {
            bar = document.createElement('div');
            bar.id = LOADING_BAR_ID;
            document.body.appendChild(bar);
        }
        return bar;
    }

    function showLoading() {
        var bar = getOrCreateLoadingBar();
        bar.classList.remove('spa-loading-done');
        bar.classList.add('spa-loading-active');
    }

    function hideLoading() {
        var bar = getOrCreateLoadingBar();
        bar.classList.remove('spa-loading-active');
        bar.classList.add('spa-loading-done');
        setTimeout(function () {
            bar.classList.remove('spa-loading-done');
        }, 400);
    }

    // ── Helpers ─────────────────────────────────────────────
    function isInternalLink(href) {
        if (!href) return false;
        try {
            var url = new URL(href, window.location.origin);
            if (url.origin !== window.location.origin) return false;
            var path = url.pathname;

            // Check excluded paths
            for (var i = 0; i < EXCLUDED_PATHS.length; i++) {
                if (path.indexOf(EXCLUDED_PATHS[i]) !== -1) return false;
            }

            // Check if it's an internal module/page path
            for (var j = 0; j < INTERNAL_PREFIXES.length; j++) {
                if (path.indexOf(INTERNAL_PREFIXES[j]) !== -1) return true;
            }
            // Root path is also internal (index/profile)
            if (path === '/' || path.match(/^\/index(\.\w+)?$/)) return true;
        } catch (e) {
            return false;
        }
        return false;
    }

    function getModuleName(path) {
        var match = path.match(/\/modules\/([^?#/]+)/);
        if (match) return match[1];
        if (path === '/' || path.match(/^\/index/)) return 'index';
        return null;
    }

    function updateActiveLinks(path) {
        var moduleName = getModuleName(path);
        if (!moduleName) return;

        // Sidebar links
        var sidebarLinks = document.querySelectorAll('.app-sidebar-link');
        for (var i = 0; i < sidebarLinks.length; i++) {
            var link = sidebarLinks[i];
            var linkModule = getModuleName(link.getAttribute('href') || '');
            // Special case: clientes sidebar includes cliente_360
            if (linkModule === 'clientes' && (moduleName === 'clientes' || moduleName === 'cliente_360')) {
                link.classList.add('active');
            } else if (linkModule === 'analiticas' && (moduleName === 'analiticas' || moduleName === 'asistente_ia')) {
                link.classList.add('active');
            } else if (linkModule === moduleName) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        }

        // Mobile drawer links
        var mobileLinks = document.querySelectorAll('.app-mobile-nav-link');
        for (var j = 0; j < mobileLinks.length; j++) {
            var mLink = mobileLinks[j];
            var mLinkModule = getModuleName(mLink.getAttribute('href') || '');
            if (mLinkModule === 'clientes' && (moduleName === 'clientes' || moduleName === 'cliente_360')) {
                mLink.classList.add('active');
            } else if (mLinkModule === 'analiticas' && (moduleName === 'analiticas' || moduleName === 'asistente_ia')) {
                mLink.classList.add('active');
            } else if (mLinkModule === moduleName) {
                mLink.classList.add('active');
            } else {
                mLink.classList.remove('active');
            }
        }
    }

    // ── Cleanup ─────────────────────────────────────────────
    function closeMobileDrawer() {
        var overlay = document.getElementById('appMobileNavOverlay');
        var drawer = document.getElementById('appMobileNavDrawer');
        if (overlay) overlay.classList.remove('open');
        if (drawer) drawer.classList.remove('open');
        document.body.classList.remove('mobile-nav-open');
    }

    function closeBootstrapModals() {
        // Close all open Bootstrap modals
        var modals = document.querySelectorAll('.modal.show');
        for (var i = 0; i < modals.length; i++) {
            var instance = bootstrap.Modal.getInstance(modals[i]);
            if (instance) instance.hide();
        }
        // Remove any leftover backdrops
        var backdrops = document.querySelectorAll('.modal-backdrop');
        for (var j = 0; j < backdrops.length; j++) {
            backdrops[j].remove();
        }
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');

        // Close offcanvas
        var offcanvases = document.querySelectorAll('.offcanvas.show');
        for (var k = 0; k < offcanvases.length; k++) {
            var ocInstance = bootstrap.Offcanvas.getInstance(offcanvases[k]);
            if (ocInstance) ocInstance.hide();
        }
    }

    function disconnectRealtime() {
        if (window.SupabaseRealtime && typeof window.SupabaseRealtime.disconnect === 'function') {
            try { window.SupabaseRealtime.disconnect(); } catch (e) {}
        }
        // Reset realtime config so modules can re-init
        window.REALTIME_CONFIG = null;
    }

    function cleanupModuleGlobals() {
        // Clean up known module globals
            // Los módulos registran sus propios globals en window.__spaModuleGlobals = [...]
        // antes de ejecutar su lógica. El router los limpia aquí al salir.
        var globals = window.__spaModuleGlobals || [];
        for (var i = 0; i < globals.length; i++) {
            try { delete window[globals[i]]; } catch (e) {
                window[globals[i]] = undefined;
            }
        }
        window.__spaModuleGlobals = [];
    }

    function removeModuleCss() {
        for (var i = 0; i < currentModuleCss.length; i++) {
            currentModuleCss[i].remove();
        }
        currentModuleCss = [];
    }

    function fullCleanup() {
        closeBootstrapModals();
        closeMobileDrawer();
        disconnectRealtime();
        cleanupModuleGlobals();
        removeModuleCss();
    }

    // ── Script execution ────────────────────────────────────
    function executeScripts(container) {
        var scripts = container.querySelectorAll('script');
        var promise = Promise.resolve();

        for (var i = 0; i < scripts.length; i++) {
            (function (oldScript) {
                promise = promise.then(function () {
                    return new Promise(function (resolve) {
                        var newScript = document.createElement('script');

                        // Copy attributes
                        for (var j = 0; j < oldScript.attributes.length; j++) {
                            var attr = oldScript.attributes[j];
                            newScript.setAttribute(attr.name, attr.value);
                        }

                        if (oldScript.src) {
                            // External script — wait for load
                            newScript.onload = resolve;
                            newScript.onerror = function () {
                                console.warn('[SPA] Failed to load script:', oldScript.src);
                                resolve();
                            };
                            oldScript.parentNode.replaceChild(newScript, oldScript);
                        } else {
                            // Inline script — execute immediately
                            newScript.textContent = oldScript.textContent;
                            oldScript.parentNode.replaceChild(newScript, oldScript);
                            resolve();
                        }
                    });
                });
            })(scripts[i]);
        }

        return promise;
    }

    // ── CSS handling ────────────────────────────────────────
    function loadModuleCss(container) {
        var links = container.querySelectorAll('link[rel="stylesheet"][data-module-css]');
        var styles = container.querySelectorAll('style[data-module-css]');

        for (var i = 0; i < links.length; i++) {
            var link = links[i];
            // Move to <head> instead of keeping in content
            var headLink = document.createElement('link');
            headLink.rel = 'stylesheet';
            headLink.href = link.href;
            headLink.setAttribute('data-module-css', link.getAttribute('data-module-css'));
            document.head.appendChild(headLink);
            currentModuleCss.push(headLink);
            link.remove();
        }

        for (var j = 0; j < styles.length; j++) {
            var style = styles[j];
            var headStyle = document.createElement('style');
            headStyle.setAttribute('data-module-css', style.getAttribute('data-module-css'));
            headStyle.textContent = style.textContent;
            document.head.appendChild(headStyle);
            currentModuleCss.push(headStyle);
            style.remove();
        }
    }

    // ── Navigation ──────────────────────────────────────────
    function navigateTo(url, pushHistory) {
        if (isNavigating) {
            // Abort previous navigation
            if (currentAbortController) {
                currentAbortController.abort();
            }
        }

        isNavigating = true;
        showLoading();

        currentAbortController = new AbortController();

        // Build fragment URL
        var fetchUrl = new URL(url, window.location.origin);
        fetchUrl.searchParams.set('fragment', '1');

        fetch(fetchUrl.toString(), {
            signal: currentAbortController.signal,
            headers: { 'X-Fragment': '1' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                // Handle auth failures
                if (response.status === 401) {
                    window.location.href = response.headers.get('X-Redirect-Url') || '/login';
                    return null;
                }
                // Handle redirects (e.g., admin-only modules)
                if (response.redirected) {
                    window.location.href = response.url;
                    return null;
                }
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function (html) {
                if (html === null) return; // redirected

                // Full cleanup of previous module
                fullCleanup();

                var container = document.getElementById(CONTENT_ID);
                if (!container) {
                    console.error('[SPA] #' + CONTENT_ID + ' not found, falling back to full reload');
                    window.location.href = url;
                    return;
                }

                // Inject new content
                container.innerHTML = html;

                // Load module CSS (move to head)
                loadModuleCss(container);

                // Actualizar URL ANTES de ejecutar scripts para que fetch() con rutas
                // relativas dentro del módulo resuelvan contra la URL destino
                if (pushHistory !== false) {
                    history.pushState({ spaUrl: url }, '', url);
                }
                updateActiveLinks(new URL(url, window.location.origin).pathname);

                // Execute scripts in order
                executeScripts(container).then(function () {
                    // Scroll to top
                    window.scrollTo(0, 0);

                    isNavigating = false;
                    hideLoading();

                    // Dispatch custom event para módulos que inicializan con onModuleReady
                    document.dispatchEvent(new CustomEvent('spa:navigated', {
                        detail: { url: url, module: getModuleName(new URL(url, window.location.origin).pathname) }
                    }));
                });
            })
            .catch(function (err) {
                if (err.name === 'AbortError') return; // navigation was cancelled
                console.error('[SPA] Navigation failed:', err);
                isNavigating = false;
                hideLoading();
                // Fallback: full page reload
                window.location.href = url;
            });
    }

    // ── Event listeners ─────────────────────────────────────

    // Intercept link clicks (delegation on document)
    document.addEventListener('click', function (e) {
        // Find closest <a> element
        var link = e.target.closest('a[href]');
        if (!link) return;

        var href = link.getAttribute('href');
        if (!href) return;

        // Skip if modifier keys (open in new tab)
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

        // Skip if target="_blank" or download
        if (link.target === '_blank' || link.hasAttribute('download')) return;

        // Skip if explicitly opted out
        if (link.hasAttribute('data-spa-ignore')) return;

        // Build full URL to check
        var fullUrl;
        try {
            fullUrl = new URL(href, window.location.origin).href;
        } catch (_e) {
            return;
        }

        if (isInternalLink(fullUrl)) {
            e.preventDefault();
            // Don't navigate to same page
            var target = new URL(fullUrl);
            var current = new URL(window.location.href);
            target.searchParams.delete('fragment');
            if (target.pathname === current.pathname && target.search === current.search) {
                closeMobileDrawer();
                return;
            }
            navigateTo(fullUrl, true);
        }
    });

    // Handle back/forward
    window.addEventListener('popstate', function (e) {
        if (e.state && e.state.spaUrl) {
            navigateTo(e.state.spaUrl, false);
        } else if (isInternalLink(window.location.href)) {
            navigateTo(window.location.href, false);
        }
    });

    // Replace initial history state
    history.replaceState({ spaUrl: window.location.href }, '', window.location.href);

    // ── Visibility: pausar animaciones en tab oculto ─────────
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            document.documentElement.classList.add('document-hidden');
        } else {
            document.documentElement.classList.remove('document-hidden');
        }
    });

    // ── Public API ──────────────────────────────────────────
    window.SpaRouter = {
        navigateTo: function (url) { navigateTo(url, true); },
        isNavigating: function () { return isNavigating; }
    };
})();
