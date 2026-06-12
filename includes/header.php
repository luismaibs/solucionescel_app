<?php
/**
 * header.php
 * Sidebar fijo + topbar con búsqueda y menú de usuario
 */

// Determinar entorno y rutas de forma dinámica
$_request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$_request_path = preg_replace('#/+#', '/', str_replace('\\', '/', $_request_path));
$in_modules = strpos($_request_path, '/modules/') !== false;
$current_script = basename($_request_path);
$current_script = preg_replace('/\.php$/', '', $current_script) ?: 'index';
$_app_root = '/';
$_app_root_detected = false;
foreach (['/modules/', '/api/', '/cron/'] as $_segment) {
    $_pos = strpos($_request_path, $_segment);
    if ($_pos !== false) {
        $_app_root = rtrim(substr($_request_path, 0, $_pos), '/') . '/';
        $_app_root_detected = true;
        break;
    }
}
if (!$_app_root_detected && $_app_root === '/' && !preg_match('#^/(?:index|login|logout|api|bot)?(?:\.php)?/?$#', $_request_path)) {
    $_dir = rtrim(dirname($_request_path), '/\\');
    $_app_root = ($_dir === '' || $_dir === '.') ? '/' : $_dir . '/';
}
$base_path = $_app_root;

// Función helper para class active
if (!function_exists('activeClass')) {
    function activeClass($page, $current)
    {
        return ($page === $current) ? 'active' : '';
    }
}

// Iniciales del usuario para el avatar
$displayName = trim(getCurrentUsername() ?: getCurrentFullName());
if ($displayName !== '' && strpos($displayName, '@') !== false) {
    $displayName = explode('@', $displayName)[0];
}
if ($displayName !== '') {
    $parts = preg_split('/\s+/', $displayName);
    $first = substr($parts[0], 0, 1);
    $last = isset($parts[count($parts) - 1]) ? substr($parts[count($parts) - 1], 0, 1) : '';
    $userInitials = strtoupper($first . $last);
} else {
    $userInitials = 'SC';
}
?>
<script>
    /* Aplicar estado del sidebar ANTES del primer pintado */
    (function () {
        function init() {
            document.body.classList.add('with-sidebar');
            try {
                var s = localStorage.getItem('sc_sidebar');
                if (s === 'pinned') document.body.classList.add('sidebar-pinned');
                else if (s === 'collapsed') document.body.classList.add('sidebar-pinned-collapsed');
            } catch (e) {}
        }
        if (document.body) init();
        else document.addEventListener('DOMContentLoaded', init);
    })();
</script>

<!-- SIDEBAR -->
<aside class="app-sidebar" id="appSidebar">
    <div class="app-sidebar-header">
        <a href="<?= $base_path ?>index" class="app-sidebar-logo" title="SOLUCIONESCEL">
            <i class="bi bi-cpu-fill"></i>
        </a>
    </div>

    <nav class="app-sidebar-nav">
        <?php if (function_exists('puedeVerModulo') && puedeVerModulo('equipos')): ?>
        <a href="<?= $base_path ?>modules/panel"
            class="app-sidebar-link <?= activeClass('panel', $current_script) ?>"
            title="Equipos">
            <i class="bi bi-tools"></i>
            <span class="app-sidebar-link-label">Equipos</span>
        </a>
        <?php endif; ?>
        <?php if (function_exists('puedeVerModulo') && puedeVerModulo('clientes')): ?>
        <a href="<?= $base_path ?>modules/clientes"
            class="app-sidebar-link <?= (in_array($current_script, ['clientes', 'cliente_360'])) ? 'active' : '' ?>"
            title="Clientes">
            <i class="bi bi-person-lines-fill"></i>
            <span class="app-sidebar-link-label">Clientes</span>
        </a>
        <?php endif; ?>
        <?php if (function_exists('puedeVerModulo') && puedeVerModulo('inventario')): ?>
        <a href="<?= $base_path ?>modules/inventario"
            class="app-sidebar-link <?= activeClass('inventario', $current_script) ?>"
            title="Inventario">
            <i class="bi bi-box-seam"></i>
            <span class="app-sidebar-link-label">Inventario</span>
        </a>
        <?php endif; ?>
        <?php if (function_exists('puedeVerModulo') && puedeVerModulo('soporte')): ?>
        <a href="<?= $base_path ?>modules/soporte"
            class="app-sidebar-link <?= activeClass('soporte', $current_script) ?>"
            title="Soporte humano">
            <i class="bi bi-headset"></i>
            <span class="app-sidebar-link-label">Soporte</span>
        </a>
        <?php endif; ?>
        <?php if (function_exists('puedeVerModulo') && puedeVerModulo('mes_azul')): ?>
        <a href="<?= $base_path ?>modules/mes_azul"
            class="app-sidebar-link <?= activeClass('mes_azul', $current_script) ?>"
            title="Rastreo Mes Azul">
            <i class="bi bi-hourglass-split"></i>
            <span class="app-sidebar-link-label">Mes Azul</span>
        </a>
        <?php endif; ?>
        <?php if (function_exists('puedeVerModulo') && puedeVerModulo('analiticas')): ?>
        <a href="<?= $base_path ?>modules/analiticas"
            class="app-sidebar-link <?= (in_array($current_script, ['analiticas', 'asistente_ia'])) ? 'active' : '' ?>"
            title="Analíticas">
            <i class="bi bi-graph-up-arrow"></i>
            <span class="app-sidebar-link-label">Analíticas</span>
        </a>
        <?php endif; ?>
        <?php if (function_exists('isAdmin') && isAdmin()): ?>
        <a href="<?= $base_path ?>modules/plantillas"
            class="app-sidebar-link <?= activeClass('plantillas', $current_script) ?>"
            title="Plantillas">
            <i class="bi bi-chat-dots-fill"></i>
            <span class="app-sidebar-link-label">Plantillas</span>
        </a>
        <a href="<?= $base_path ?>modules/usuarios"
            class="app-sidebar-link <?= activeClass('usuarios', $current_script) ?>"
            title="Usuarios">
            <i class="bi bi-people-fill"></i>
            <span class="app-sidebar-link-label">Usuarios</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="app-sidebar-footer" id="sidebarFooter">
        <button type="button" class="app-sidebar-foot-btn" id="sidebarPinBtn" title="Fijar sidebar">
            <i class="bi bi-pin-angle"></i>
        </button>
        <button type="button" class="app-sidebar-foot-btn app-sidebar-arrow-btn" id="sidebarArrowBtn" title="Contraer">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>
</aside>

<script>
/* Sidebar: 2 botones (pin + flecha), 5 estados */
(function () {
    var KEY = 'sc_sidebar';
    var pinBtn = document.getElementById('sidebarPinBtn');
    var arrowBtn = document.getElementById('sidebarArrowBtn');
    var pinIcon = pinBtn ? pinBtn.querySelector('i') : null;
    var arrowIcon = arrowBtn ? arrowBtn.querySelector('i') : null;
    var CLASSES = ['sidebar-pinned', 'sidebar-pinned-collapsed'];

    function getState() {
        if (document.body.classList.contains('sidebar-pinned')) return 'pinned';
        if (document.body.classList.contains('sidebar-pinned-collapsed')) return 'collapsed';
        return 'auto';
    }

    function setState(s) {
        CLASSES.forEach(function (c) { document.body.classList.remove(c); });
        if (s === 'pinned') document.body.classList.add('sidebar-pinned');
        else if (s === 'collapsed') document.body.classList.add('sidebar-pinned-collapsed');
        try { localStorage.setItem(KEY, s === 'auto' ? '' : s); } catch (e) {}
        render(s);
    }

    function render(s) {
        /* Pin icon */
        if (pinIcon) {
            pinIcon.className = (s === 'pinned' || s === 'collapsed') ? 'bi bi-pin-fill' : 'bi bi-pin-angle';
        }
        if (pinBtn) {
            pinBtn.title = (s === 'pinned' || s === 'collapsed') ? 'Desfijar sidebar' : 'Fijar sidebar';
            pinBtn.classList.toggle('active', s === 'pinned' || s === 'collapsed');
        }
        /* Arrow: visible solo cuando fijado */
        if (arrowBtn) {
            var show = (s === 'pinned' || s === 'collapsed');
            arrowBtn.classList.toggle('is-visible', show);
        }
        if (arrowIcon) {
            arrowIcon.className = s === 'collapsed' ? 'bi bi-chevron-right' : 'bi bi-chevron-left';
        }
        if (arrowBtn) {
            arrowBtn.title = s === 'collapsed' ? 'Expandir' : 'Contraer';
        }
    }

    /* Pin: toggle fijado on/off */
    if (pinBtn) pinBtn.addEventListener('click', function () {
        var cur = getState();
        if (cur === 'auto') setState('pinned');
        else setState('auto');
    });

    /* Flecha: toggle expandido/contraido (solo cuando fijado) */
    if (arrowBtn) arrowBtn.addEventListener('click', function () {
        var cur = getState();
        if (cur === 'pinned') setState('collapsed');
        else if (cur === 'collapsed') setState('pinned');
    });

    render(getState());
})();
</script>

<!-- TOPBAR CON BÚSQUEDA UNIVERSAL Y MENÚ DE USUARIO -->
<header class="app-topbar">
    <button type="button" class="app-topbar-hamburger d-lg-none" id="appMobileMenuBtn" aria-label="Abrir menú">
        <i class="bi bi-list"></i>
    </button>
    <div class="app-topbar-search-wrap">
        <div class="app-topbar-search">
            <i class="bi bi-search app-topbar-search-icon"></i>
            <input id="globalSearchInput" class="app-topbar-search-input" type="search" autocomplete="off"
                placeholder="Buscar en SOLUCIONESCEL">
        </div>
        <div id="globalSearchResults" class="app-search-results d-none"></div>
    </div>

    <div class="app-topbar-actions">
        <!-- Toggle de tema claro/oscuro -->
        <button type="button" id="themeToggleBtn" class="app-topbar-icon-btn" title="Cambiar tema" aria-label="Cambiar tema">
            <i class="bi bi-sun-fill" id="themeIcon"></i>
        </button>

        <!-- Notification Center -->
        <div class="app-notif-wrapper">
            <button type="button" class="app-topbar-icon-btn position-relative" id="notifToggleBtn"
                title="Notificaciones" aria-label="Abrir notificaciones">
                <i class="bi bi-bell"></i>
                <span id="notifBadge" class="app-notif-badge d-none" aria-hidden="true">0</span>
            </button>
            <div class="app-notif-panel" id="notifPanel" role="dialog" aria-label="Notificaciones">
                <div class="app-notif-panel-header">
                    <h6 class="app-notif-panel-title"><i class="bi bi-bell me-2"></i>Notificaciones</h6>
                    <div class="app-notif-header-actions">
                        <button type="button" class="app-notif-mark-read d-none" id="notifMarkAllRead" title="Marcar todo como leído">
                            <i class="bi bi-check2-all"></i>
                        </button>
                        <button type="button" class="app-notif-panel-close" id="notifCloseBtn" aria-label="Cerrar">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="app-notif-panel-body" id="notifPanelBody">
                    <div class="app-notif-loading"><div class="app-notif-spinner"></div>Cargando...</div>
                </div>
                <div class="app-notif-panel-footer d-none" id="notifPanelFooter">
                    <span>Actualizado automáticamente</span>
                </div>
            </div>
        </div>

        <a href="<?= $base_path ?>logout?logout=true" class="app-topbar-icon-btn app-topbar-logout-btn"
            title="Cerrar sesión" aria-label="Cerrar sesión">
            <i class="bi bi-box-arrow-right"></i>
        </a>

        <!-- Píldora → vista perfil 360° (index) -->
        <a href="<?= $base_path ?>index" class="app-topbar-user-btn app-topbar-user-pill" title="Mi perfil 360°">
            <div class="avatar-circle app-topbar-user-avatar">
                <?= htmlspecialchars($userInitials) ?>
            </div>
            <span class="app-topbar-user-name"><?= htmlspecialchars($displayName) ?></span>
        </a>
    </div>
</header>

<!-- DRAWER DE NAVEGACIÓN MÓVIL (hamburguesa) -->
<div class="app-mobile-nav-overlay" id="appMobileNavOverlay" aria-hidden="true"></div>
<nav class="app-mobile-nav-drawer" id="appMobileNavDrawer" aria-label="Menú principal">
    <div class="app-mobile-nav-header">
        <span class="app-mobile-nav-title">SOLUCIONESCEL</span>
        <button type="button" class="app-mobile-nav-close" id="appMobileNavClose" aria-label="Cerrar menú">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="app-mobile-nav-links">
        <?php if (function_exists('puedeVerModulo') && puedeVerModulo('equipos')): ?>
        <a href="<?= $base_path ?>modules/panel" class="app-mobile-nav-link <?= activeClass('panel', $current_script) ?>">
            <i class="bi bi-tools"></i>
            <span>Equipos</span>
        </a>
        <?php endif; ?>
        <?php if (function_exists('puedeVerModulo') && puedeVerModulo('clientes')): ?>
        <a href="<?= $base_path ?>modules/clientes" class="app-mobile-nav-link <?= (in_array($current_script, ['clientes', 'cliente_360'])) ? 'active' : '' ?>">
            <i class="bi bi-person-lines-fill"></i>
            <span>Clientes</span>
        </a>
        <?php endif; ?>
        <?php if (function_exists('puedeVerModulo') && puedeVerModulo('inventario')): ?>
        <a href="<?= $base_path ?>modules/inventario" class="app-mobile-nav-link <?= activeClass('inventario', $current_script) ?>">
            <i class="bi bi-box-seam"></i>
            <span>Inventario</span>
        </a>
        <?php endif; ?>
        <?php if (function_exists('puedeVerModulo') && puedeVerModulo('soporte')): ?>
        <a href="<?= $base_path ?>modules/soporte" class="app-mobile-nav-link <?= activeClass('soporte', $current_script) ?>">
            <i class="bi bi-headset"></i>
            <span>Soporte</span>
        </a>
        <?php endif; ?>
        <?php if (function_exists('puedeVerModulo') && puedeVerModulo('mes_azul')): ?>
        <a href="<?= $base_path ?>modules/mes_azul" class="app-mobile-nav-link <?= activeClass('mes_azul', $current_script) ?>">
            <i class="bi bi-hourglass-split"></i>
            <span>Mes Azul</span>
        </a>
        <?php endif; ?>
        <?php if (function_exists('puedeVerModulo') && puedeVerModulo('analiticas')): ?>
        <a href="<?= $base_path ?>modules/analiticas" class="app-mobile-nav-link <?= (in_array($current_script, ['analiticas', 'asistente_ia'])) ? 'active' : '' ?>">
            <i class="bi bi-graph-up-arrow"></i>
            <span>Analíticas</span>
        </a>
        <?php endif; ?>
        <?php if (function_exists('isAdmin') && isAdmin()): ?>
        <a href="<?= $base_path ?>modules/plantillas" class="app-mobile-nav-link <?= activeClass('plantillas', $current_script) ?>">
            <i class="bi bi-chat-dots-fill"></i>
            <span>Plantillas</span>
        </a>
        <a href="<?= $base_path ?>modules/usuarios" class="app-mobile-nav-link <?= activeClass('usuarios', $current_script) ?>">
            <i class="bi bi-people-fill"></i>
            <span>Usuarios</span>
        </a>
        <?php endif; ?>
    </div>
</nav>

<script>
    (function () {
        var btn = document.getElementById('appMobileMenuBtn');
        var overlay = document.getElementById('appMobileNavOverlay');
        var drawer = document.getElementById('appMobileNavDrawer');
        var closeBtn = document.getElementById('appMobileNavClose');
        function openNav() {
            if (overlay) overlay.classList.add('is-open');
            if (drawer) drawer.classList.add('is-open');
            document.body.classList.add('app-mobile-nav-open');
        }
        function closeNav() {
            if (overlay) overlay.classList.remove('is-open');
            if (drawer) drawer.classList.remove('is-open');
            document.body.classList.remove('app-mobile-nav-open');
        }
        if (btn) btn.addEventListener('click', openNav);
        if (closeBtn) closeBtn.addEventListener('click', closeNav);
        if (overlay) overlay.addEventListener('click', closeNav);
        if (drawer) drawer.querySelectorAll('.app-mobile-nav-link').forEach(function (link) {
            link.addEventListener('click', closeNav);
        });
    })();
</script>

<?php
// Ruta absoluta desde la raíz web (funciona desde cualquier página, evita problemas con rutas relativas)
$_abs_root = $_app_root;
?>
<script>
    window.APP_BASE_PATH = <?= json_encode($_abs_root) ?>;
    window.APP_API_BASE  = <?= json_encode($_abs_root . 'api/') ?>;
</script>
<script>
(function () {
    /* ════════════════════════════════════════════════
       BÚSQUEDA GLOBAL
    ════════════════════════════════════════════════ */
    var searchResultsEl = document.getElementById('globalSearchResults');
    var searchInput = document.getElementById('globalSearchInput');
    var searchTimer = null;

    var escapeHtml = window.escapeHtml || function (s) {
        var d = document.createElement('div'); d.textContent = s; return d.innerHTML;
    };

    /* Resaltar término de búsqueda en texto */
    function highlightTerm(text, query) {
        if (!query || query.length < 3) return escapeHtml(text);
        var safe = escapeHtml(text);
        var re = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        return safe.replace(re, '<mark class="search-highlight">$1</mark>');
    }

    function positionSearch() {
        if (!searchResultsEl || !searchInput) return;
        var wrap = searchInput.closest('.app-topbar-search-wrap');
        if (!wrap) return;
        var rect = wrap.getBoundingClientRect();
        var maxW = window.innerWidth - rect.left - 12;
        var w = Math.min(Math.max(rect.width, 340), maxW);
        searchResultsEl.style.top = (rect.bottom + 6) + 'px';
        searchResultsEl.style.left = rect.left + 'px';
        searchResultsEl.style.width = w + 'px';
    }

    function showSearchResults() { searchResultsEl.classList.remove('d-none'); positionSearch(); }
    function hideSearchResults() { searchResultsEl.classList.add('d-none'); }

    function renderSearch(data) {
        var q = data.q || '';
        var results = data.results || {};
        var keys = Object.keys(results);

        if (!keys.length) {
            searchResultsEl.innerHTML = '<div class="app-search-empty"><i class="bi bi-search me-2"></i>Sin resultados para "<strong>' + escapeHtml(q) + '</strong>"</div>';
            showSearchResults();
            return;
        }

        var html = '';
        keys.forEach(function (key) {
            var cat = results[key];
            html += '<div class="app-search-section">';
            html += '<div class="app-search-section-header"><span><i class="bi ' + escapeHtml(cat.icon || 'bi-folder') + ' me-1"></i>' + escapeHtml(cat.label || key) + '</span><span class="app-search-section-count">' + (cat.count || 0) + '</span></div>';
            (cat.items || []).forEach(function (item) {
                html += '<a href="' + escapeHtml(item.url || '#') + '" class="app-search-item">';
                html += '<div class="app-search-item-title">' + highlightTerm(item.titulo || '', q) + '</div>';
                html += '<div class="app-search-item-sub">' + highlightTerm(item.subtitulo || '', q) + '</div>';
                html += '</a>';
            });
            if (cat.count > (cat.items || []).length) {
                html += '<a href="' + escapeHtml(cat.url || '#') + '" class="app-search-more">Ver todos los resultados de ' + escapeHtml(cat.label || key) + ' →</a>';
            }
            html += '</div>';
        });

        searchResultsEl.innerHTML = html;
        showSearchResults();
    }

    function doSearch() {
        var q = searchInput ? searchInput.value.trim() : '';
        if (q.length < 3) { hideSearchResults(); return; }

        /* Loading state */
        searchResultsEl.innerHTML = '<div class="app-search-loading"><div class="app-notif-spinner"></div>Buscando...</div>';
        showSearchResults();

        var apiBase = window.APP_API_BASE || '/api/';
        var baseParam = (window.APP_BASE_PATH || '/').replace(/\/?$/, '/');
        var url = apiBase + 'api_search?q=' + encodeURIComponent(q) + '&base_path=' + encodeURIComponent(baseParam);

        console.group('[Búsqueda Global]');
        console.log('Query:', q);
        console.log('API URL:', url);

        fetch(url)
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status + ' ' + r.statusText);
                return r.json();
            })
            .then(function (data) {
                console.log('Resultados:', data);
                console.groupEnd();
                renderSearch(data);
            })
            .catch(function (err) {
                console.error('Error:', err);
                console.groupEnd();
                searchResultsEl.innerHTML = '<div class="app-search-empty text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error al buscar<br><small style="opacity:0.7">Verifica la consola para más detalles</small></div>';
                showSearchResults();
            });
    }

    /* ════════════════════════════════════════════════
       NOTIFICATION CENTER
    ════════════════════════════════════════════════ */
    var notifBtn = document.getElementById('notifToggleBtn');
    var notifPanel = document.getElementById('notifPanel');
    var notifClose = document.getElementById('notifCloseBtn');
    var notifBody = document.getElementById('notifPanelBody');
    var notifBadge = document.getElementById('notifBadge');
    var notifOpen = false;
    var lastFilteredNotifData = null;

    var NOTIF_READ_KEY = 'sc_notif_read';

    function getNotifRead() {
        try {
            var raw = localStorage.getItem(NOTIF_READ_KEY);
            return raw ? JSON.parse(raw) : { notif_ids: [], vencidos_ids: [], soporte_ids: [], config_ids: [] };
        } catch (e) { return { notif_ids: [], vencidos_ids: [], soporte_ids: [], config_ids: [] }; }
    }

    function saveNotifRead(notifIds, vencidosIds, soporteIds, configIds) {
        try {
            localStorage.setItem(NOTIF_READ_KEY, JSON.stringify({
                notif_ids: notifIds || [],
                vencidos_ids: vencidosIds || [],
                soporte_ids: soporteIds || [],
                config_ids: configIds || []
            }));
        } catch (e) {}
    }

    function filterReadNotifications(data) {
        var read = getNotifRead();
        var readNotif = (read.notif_ids || []).map(Number);
        var readVencidos = (read.vencidos_ids || []).map(Number);
        var readSoporte = (read.soporte_ids || []).map(Number);
        var readConfig = (read.config_ids || []).map(String);
        var idIn = function (arr, id) { var n = Number(id); return arr.indexOf(n) !== -1; };
        var idInStr = function (arr, id) { return arr.indexOf(String(id)) !== -1; };
        var vencidos = (data.dispositivos_vencidos || []).filter(function (r) { return !idIn(readVencidos, r.id); });
        var soporteConv = (data.soporte_conversaciones || []).filter(function (c) { return !idIn(readSoporte, c.id); });
        var notifSist = (data.notificaciones_sistema || []).filter(function (n) { return !idIn(readNotif, n.id); });
        var notifConfig = (data.notificaciones_configurables || []).filter(function (n) { return !idInStr(readConfig, n.id); });
        var total = vencidos.length + soporteConv.length + notifSist.length + notifConfig.length;
        return {
            ok: true,
            dispositivos_vencidos: vencidos,
            soporte_pausadas_count: soporteConv.length,
            soporte_conversaciones: soporteConv,
            notificaciones_sistema: notifSist,
            notificaciones_configurables: notifConfig,
            total_alertas: total,
            base_path: data.base_path
        };
    }

    function toggleNotifPanel() {
        notifOpen = !notifOpen;
        notifPanel.classList.toggle('is-open', notifOpen);
        if (notifOpen) loadNotifications();
        else clearNotifSeenTimeout();
    }

    var notifSeenTimeout = null;
    function clearNotifSeenTimeout() { if (notifSeenTimeout) { clearTimeout(notifSeenTimeout); notifSeenTimeout = null; } }
    function scheduleMarkAsSeenOnView() {
        clearNotifSeenTimeout();
        notifSeenTimeout = setTimeout(function () {
            notifSeenTimeout = null;
            var d = lastFilteredNotifData;
            if (d && (d.total_alertas || 0) > 0) {
                var read = getNotifRead();
                var newNotif = (d.notificaciones_sistema || []).map(function (n) { return parseInt(n.id, 10); });
                var newVenc = (d.dispositivos_vencidos || []).map(function (r) { return parseInt(r.id, 10); });
                var newSop = (d.soporte_conversaciones || []).map(function (c) { return parseInt(c.id, 10); });
                var newConfig = (d.notificaciones_configurables || []).map(function (n) { return String(n.id); });
                var merge = function (a, b) { var s = {}; (a || []).concat(b || []).forEach(function (x) { s[Number(x)] = true; }); return Object.keys(s).map(Number); };
                var mergeStr = function (a, b) { var s = {}; (a || []).concat(b || []).forEach(function (x) { s[String(x)] = true; }); return Object.keys(s); };
                saveNotifRead(merge(read.notif_ids, newNotif), merge(read.vencidos_ids, newVenc), merge(read.soporte_ids, newSop), mergeStr(read.config_ids, newConfig));
                updateBadge(0);
            }
        }, 2500);
    }

    function closeNotifPanel() {
        notifOpen = false;
        notifPanel.classList.remove('is-open');
    }

    function updateBadge(total) {
        if (notifBadge) {
            if (total > 0) {
                notifBadge.textContent = total > 99 ? '99+' : total;
                notifBadge.classList.remove('d-none');
                if (notifBtn) notifBtn.classList.add('has-notif');
            } else {
                notifBadge.classList.add('d-none');
                if (notifBtn) notifBtn.classList.remove('has-notif');
            }
        }
    }

    var fmtDate = window.fmtDate
        ? function (s) { return window.fmtDate(s, 'relative'); }
        : function (s) {
            if (!s) return '';
            var d = new Date(s);
            if (isNaN(d.getTime())) return s;
            var now = new Date();
            if (d.toDateString() === now.toDateString()) return 'Hoy ' + d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
            var yesterday = new Date(now - 864e5);
            if (d.toDateString() === yesterday.toDateString()) return 'Ayer ' + d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
            return d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' }) + ' ' + d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
        };

    /* Mapeo de tipos → config visual */
    var tipoConfig = {
        success:  { icon: 'bi-check-circle-fill', color: '#4ade80', bg: 'rgba(34,197,94,0.1)', border: 'rgba(34,197,94,0.25)' },
        error:    { icon: 'bi-exclamation-octagon-fill', color: '#f87171', bg: 'rgba(239,68,68,0.1)', border: 'rgba(239,68,68,0.25)' },
        warning:  { icon: 'bi-exclamation-triangle-fill', color: '#fbbf24', bg: 'rgba(251,191,36,0.1)', border: 'rgba(251,191,36,0.25)' },
        info:     { icon: 'bi-info-circle-fill', color: '#60a5fa', bg: 'rgba(59,130,246,0.1)', border: 'rgba(59,130,246,0.25)' },
        sistema:  { icon: 'bi-bell-fill', color: '#a78bfa', bg: 'rgba(139,92,246,0.08)', border: 'rgba(139,92,246,0.2)' },
    };

    function getNotifStyle(tipo) {
        return tipoConfig[tipo] || tipoConfig['info'];
    }

    function loadNotifications() {
        notifBody.innerHTML = '<div class="app-notif-loading"><div class="app-notif-spinner"></div>Cargando...</div>';

        var apiUrl = window.APP_API_BASE + 'api_notificaciones_panel';
        console.log('[Notificaciones] Cargando desde:', apiUrl);

        fetch(apiUrl)
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                console.log('[Notificaciones] Datos recibidos:', data);
                if (!data.ok) {
                    notifBody.innerHTML = '<div class="app-notif-empty"><i class="bi bi-exclamation-triangle" style="font-size:1.5rem;color:#fbbf24"></i><span>Error al cargar notificaciones</span></div>';
                    return;
                }
                data = filterReadNotifications(data);
                lastFilteredNotifData = data;
                updateBadge(data.total_alertas || 0);

                var base = (window.APP_BASE_PATH || '/').replace(/\/?$/, '/');
                var html = '';
                var hasContent = false;

                /* ── Dispositivos vencidos ── */
                if (data.dispositivos_vencidos && data.dispositivos_vencidos.length) {
                    hasContent = true;
                    html += '<div class="app-notif-section">';
                    html += '<div class="app-notif-section-title"><i class="bi bi-exclamation-triangle-fill" style="color:#fbbf24"></i> Dispositivos vencidos <span class="app-notif-section-count">' + data.dispositivos_vencidos.length + '</span></div>';
                    data.dispositivos_vencidos.forEach(function (r) {
                        var s = getNotifStyle('warning');
                        html += '<a href="' + base + 'index" class="app-notif-card app-notif-card--warning" style="border-left-color:' + s.border + '">';
                        html += '<div class="app-notif-card-icon" style="color:' + s.color + ';background:' + s.bg + '"><i class="bi ' + s.icon + '"></i></div>';
                        html += '<div class="app-notif-card-content">';
                        html += '<div class="app-notif-card-title">' + escapeHtml((r.folio_publico || '') + ' – ' + (r.cliente_nombre || '')) + '</div>';
                        html += '<div class="app-notif-card-meta">' + escapeHtml((r.dias_en_taller || 0) + ' días listo sin recoger') + '</div>';
                        html += '<div class="app-notif-card-date"><i class="bi bi-clock me-1"></i>' + fmtDate(r.fecha_listo || r.fecha_ingreso) + '</div>';
                        html += '</div></a>';
                    });
                    html += '</div>';
                }

                /* ── Soporte pausadas ── */
                if (data.soporte_pausadas_count > 0) {
                    hasContent = true;
                    html += '<div class="app-notif-section">';
                    html += '<div class="app-notif-section-title"><i class="bi bi-headset" style="color:#60a5fa"></i> Soporte pausado <span class="app-notif-section-count">' + data.soporte_pausadas_count + '</span></div>';
                    (data.soporte_conversaciones || []).slice(0, 5).forEach(function (c) {
                        var s = getNotifStyle('info');
                        html += '<a href="' + base + 'modules/soporte" class="app-notif-card app-notif-card--info" style="border-left-color:' + s.border + '">';
                        html += '<div class="app-notif-card-icon" style="color:' + s.color + ';background:' + s.bg + '"><i class="bi bi-chat-dots-fill"></i></div>';
                        html += '<div class="app-notif-card-content">';
                        html += '<div class="app-notif-card-title">' + escapeHtml(c.nombre_cliente || '') + '</div>';
                        html += '<div class="app-notif-card-date"><i class="bi bi-clock me-1"></i>' + fmtDate(c.fecha_pausa) + '</div>';
                        html += '</div></a>';
                    });
                    if (data.soporte_pausadas_count > 5) {
                        html += '<a href="' + base + 'modules/soporte" class="app-notif-see-all">Ver todas las conversaciones <i class="bi bi-arrow-right"></i></a>';
                    }
                    html += '</div>';
                }

                /* ── Notificaciones del sistema ── */
                if (data.notificaciones_sistema && data.notificaciones_sistema.length) {
                    hasContent = true;
                    html += '<div class="app-notif-section">';
                    html += '<div class="app-notif-section-title"><i class="bi bi-bell-fill" style="color:#a78bfa"></i> Sistema <span class="app-notif-section-count">' + data.notificaciones_sistema.length + '</span></div>';
                    data.notificaciones_sistema.forEach(function (n) {
                        var tipo = n.tipo || 'info';
                        var s = getNotifStyle(tipo);
                        html += '<div class="app-notif-card app-notif-card--' + escapeHtml(tipo) + '" style="border-left-color:' + s.border + '">';
                        html += '<div class="app-notif-card-icon" style="color:' + s.color + ';background:' + s.bg + '"><i class="bi ' + s.icon + '"></i></div>';
                        html += '<div class="app-notif-card-content">';
                        html += '<div class="app-notif-card-title">' + escapeHtml(n.titulo || '') + '</div>';
                        var msg = n.mensaje || '';
                        html += '<div class="app-notif-card-meta">' + escapeHtml(msg.length > 100 ? msg.substring(0, 100) + '…' : msg) + '</div>';
                        html += '<div class="app-notif-card-date"><i class="bi bi-clock me-1"></i>' + fmtDate(n.creado_at) + '</div>';
                        html += '</div></div>';
                    });
                    html += '</div>';
                }

                /* ── Notificaciones configurables ── */
                if (data.notificaciones_configurables && data.notificaciones_configurables.length) {
                    hasContent = true;
                    html += '<div class="app-notif-section">';
                    html += '<div class="app-notif-section-title"><i class="bi bi-megaphone-fill" style="color:#f59e0b"></i> Configuradas <span class="app-notif-section-count">' + data.notificaciones_configurables.length + '</span></div>';
                    data.notificaciones_configurables.forEach(function (n) {
                        var tipo = n.tipo || 'info';
                        var icono = n.icono || 'bell-fill';
                        var s = getNotifStyle(tipo);
                        html += '<div class="app-notif-card app-notif-card--' + escapeHtml(tipo) + '" style="border-left-color:' + s.border + '">';
                        html += '<div class="app-notif-card-icon" style="color:' + s.color + ';background:' + s.bg + '"><i class="bi bi-' + escapeHtml(icono) + '"></i></div>';
                        html += '<div class="app-notif-card-content">';
                        html += '<div class="app-notif-card-title">' + escapeHtml(n.titulo || '') + '</div>';
                        var msg = n.mensaje || '';
                        html += '<div class="app-notif-card-meta">' + escapeHtml(msg.length > 100 ? msg.substring(0, 100) + '…' : msg) + '</div>';
                        html += '<div class="app-notif-card-date"><i class="bi bi-clock me-1"></i>' + fmtDate(n.creado_at) + '</div>';
                        html += '</div></div>';
                    });
                    html += '</div>';
                }

                if (!hasContent) {
                    html = '<div class="app-notif-empty"><i class="bi bi-bell-slash" style="font-size:2rem;opacity:0.25"></i><span>Sin notificaciones nuevas</span><small style="color:var(--text-muted);opacity:0.6;font-size:0.78rem">Todo está al día</small></div>';
                }

                notifBody.innerHTML = html;

                /* Mostrar/ocultar botón "marcar como leído" y footer */
                var markBtn = document.getElementById('notifMarkAllRead');
                var footer = document.getElementById('notifPanelFooter');
                if (markBtn) markBtn.classList.toggle('d-none', !hasContent);
                if (footer) footer.classList.toggle('d-none', !hasContent);
                /* Si el panel está abierto y hay contenido, marcar como vistas tras 2.5s */
                if (hasContent && notifOpen) scheduleMarkAsSeenOnView();
            })
            .catch(function (err) {
                console.error('[Notificaciones] Error:', err);
                notifBody.innerHTML = '<div class="app-notif-empty"><i class="bi bi-exclamation-triangle" style="font-size:1.5rem;color:#f87171"></i><span>Error al cargar notificaciones</span><small style="color:var(--text-muted);font-size:0.78rem">Intenta recargar la página</small></div>';
            });
    }

    if (!window.SCToast) {
        window.SCToast = {
            show: function (message, type) {
                type = type || 'info';
                var conf = tipoConfig[type] || tipoConfig['info'];
                var container = document.getElementById('sc-toast-container');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'sc-toast-container';
                    container.className = 'sc-toast-container';
                    document.body.appendChild(container);
                }
                var toast = document.createElement('div');
                toast.className = 'sc-toast sc-toast-' + type;
                toast.innerHTML = '<div class="sc-toast-icon" style="color:' + conf.color + '"><i class="bi ' + conf.icon + '"></i></div><div class="sc-toast-msg">' + escapeHtml(message) + '</div><button class="sc-toast-close" aria-label="Cerrar"><i class="bi bi-x"></i></button>';
                container.appendChild(toast);
                requestAnimationFrame(function () { toast.classList.add('is-visible'); });
                var dismiss = function () {
                    toast.classList.remove('is-visible');
                    toast.classList.add('is-hiding');
                    setTimeout(function () { toast.remove(); }, 300);
                };
                toast.querySelector('.sc-toast-close').addEventListener('click', dismiss);
                setTimeout(dismiss, 5000);
            }
        };
    }

    /* ════════════════════════════════════════════════
       EVENT LISTENERS
    ════════════════════════════════════════════════ */
    document.addEventListener('DOMContentLoaded', function () {
        /* Búsqueda */
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(doSearch, 300);
            });
            searchInput.addEventListener('focus', function () {
                if (searchInput.value.trim().length >= 3) doSearch();
            });
        }
        window.addEventListener('scroll', function () { if (searchResultsEl && !searchResultsEl.classList.contains('d-none')) positionSearch(); }, true);
        window.addEventListener('resize', function () { if (searchResultsEl && !searchResultsEl.classList.contains('d-none')) positionSearch(); });

        /* Cerrar búsqueda al click fuera */
        document.addEventListener('click', function (e) {
            if (searchResultsEl && !searchResultsEl.classList.contains('d-none') && !e.target.closest('.app-topbar-search-wrap')) hideSearchResults();
            /* Cerrar notificaciones al click fuera */
            if (notifOpen && !e.target.closest('.app-notif-wrapper')) closeNotifPanel();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { hideSearchResults(); closeNotifPanel(); }
        });

        /* Notificaciones */
        if (notifBtn) notifBtn.addEventListener('click', function (e) { e.stopPropagation(); toggleNotifPanel(); });
        if (notifClose) notifClose.addEventListener('click', closeNotifPanel);

        /* Marcar todo como leído (persiste en localStorage y limpia visualmente) */
        var markAllBtn = document.getElementById('notifMarkAllRead');
        if (markAllBtn) markAllBtn.addEventListener('click', function () {
            clearNotifSeenTimeout();
            var read = getNotifRead();
            var d = lastFilteredNotifData;
            if (d) {
                var newNotif = (d.notificaciones_sistema || []).map(function (n) { return parseInt(n.id, 10); });
                var newVenc = (d.dispositivos_vencidos || []).map(function (r) { return r.id; });
                var newSop = (d.soporte_conversaciones || []).map(function (c) { return c.id; });
                var newConfig = (d.notificaciones_configurables || []).map(function (n) { return String(n.id); });
                var merge = function (a, b) { var s = {}; (a || []).concat(b || []).forEach(function (x) { s[x] = true; }); return Object.keys(s).map(Number); };
                var mergeStr = function (a, b) { var s = {}; (a || []).concat(b || []).forEach(function (x) { s[String(x)] = true; }); return Object.keys(s); };
                saveNotifRead(merge(read.notif_ids, newNotif), merge(read.vencidos_ids, newVenc), merge(read.soporte_ids, newSop), mergeStr(read.config_ids, newConfig));
            }
            notifBody.innerHTML = '<div class="app-notif-empty"><i class="bi bi-check-circle" style="font-size:2rem;color:#4ade80;opacity:0.6"></i><span>Todo al día</span></div>';
            updateBadge(0);
            markAllBtn.classList.add('d-none');
            var footer = document.getElementById('notifPanelFooter');
            if (footer) footer.classList.add('d-none');
            if (window.SCToast) window.SCToast.show('Notificaciones marcadas como leídas', 'success');
        });

        /* Cargar conteo de notificaciones al iniciar */
        loadNotifications();

        /* Auto-refresh badge cada 60 segundos (usa datos filtrados por leídos) */
        setInterval(function () {
            if (notifOpen) return;
            fetch(window.APP_API_BASE + 'api_notificaciones_panel')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        data = filterReadNotifications(data);
                        updateBadge(data.total_alertas || 0);
                    }
                })
                .catch(function () { /* Fallo silencioso en polling */ });
        }, 60000);
    });
})();
</script>

<!-- MODAL INSTRUCCIONES PWA -->
<div class="modal fade" id="pwaInstallModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-glass border-0 shadow-lg">
            <div class="modal-header modal-glass-header border-bottom border-white border-opacity-10">
                <h5 class="modal-title fw-bold">Instalar Aplicación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body modal-glass-body p-4 text-center">
                <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3">
                    <i class="bi bi-phone fs-1 text-primary"></i>
                </div>
                <h5 class="fw-bold mb-3">Maximiza tu experiencia</h5>
                <p class="text-white-50 small mb-4">
                    Instala esta aplicación web en tu dispositivo para acceder más rápido y pantalla completa.
                </p>

                <div class="alert alert-dark border-secondary d-flex align-items-start text-start p-3 rounded-3 mb-3">
                    <i class="bi bi-android2 fs-4 me-3 text-success"></i>
                    <div>
                        <strong class="d-block text-white">Android (Chrome)</strong>
                        <small class="text-muted">Toca el menú <strong>( ⋮ )</strong> y selecciona <span
                                class="text-info">"Instalar aplicación"</span>.</small>
                    </div>
                </div>

                <div class="alert alert-dark border-secondary d-flex align-items-start text-start p-3 rounded-3">
                    <i class="bi bi-apple fs-4 me-3 text-white"></i>
                    <div>
                        <strong class="d-block text-white">iOS (Safari)</strong>
                        <small class="text-muted">Toca el botón <strong>Compartir</strong> <i
                                class="bi bi-box-arrow-up"></i> y selecciona <span class="text-info">"Agregar a
                                Inicio"</span>.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer modal-glass-footer border-top border-white border-opacity-10 justify-content-center">
                <button type="button" class="btn btn-primary rounded-pill px-5"
                    data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>

<script>
/* Toggle dark / light mode */
(function () {
    var btn = document.getElementById('themeToggleBtn');
    var icon = document.getElementById('themeIcon');

    function renderTheme(t) {
        document.documentElement.setAttribute('data-bs-theme', t);
        icon.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        btn.title = t === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
    }

    renderTheme(localStorage.getItem('sc_theme') || 'dark');

    btn.addEventListener('click', function () {
        var current = localStorage.getItem('sc_theme') || 'dark';
        var next = current === 'dark' ? 'light' : 'dark';
        localStorage.setItem('sc_theme', next);
        renderTheme(next);
    });
})();
</script>

<!-- Contenedor principal — el SPA router inyecta contenido de módulos aquí -->
<main id="app-content">
