'use strict';

(function () {
    // Estado de paginación / listado
    let repCurrentPage = 1;
    const repPerPage = 50;
    let repTotalItems = 0;
    let repLastItems = [];
    let repPipelineItems = [];
    var basePath = (window.BASE_PATH || window.APP_BASE_PATH || '').replace(/\/$/, '') || '';
    var apiBase = window.APP_API_BASE || (basePath ? basePath + '/api/' : '/api/');
    const PIPELINE_STORAGE_KEY = 'solucionescel_view_mode';

    // Cache de notificaciones configuradas para el dropdown de campana
    var _notifConfigCache = [];

    function loadNotifConfigCache() {
        fetch(apiBase + 'api_notificaciones_config.php?action=list')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) _notifConfigCache = data.notificaciones || [];
            })
            .catch(function () {});
    }

    function escHtmlNotif(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function escAttrNotif(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // Genera items del dropdown de campana para notificaciones configurables.
    // stopProp=true añade event.stopPropagation() (necesario en pipeline).
    function buildNotifConfigDropdownItems(repairId, stopProp) {
        if (!_notifConfigCache.length) return '';
        var grupos = {};
        _notifConfigCache.forEach(function (n) {
            var gNombre = (n.grupos_notificaciones && n.grupos_notificaciones.nombre) ? n.grupos_notificaciones.nombre : 'Notificaciones';
            if (!grupos[gNombre]) grupos[gNombre] = [];
            grupos[gNombre].push(n);
        });
        var stopStr = stopProp ? 'event.stopPropagation();' : '';
        var html = '<li><hr class="dropdown-divider bg-white opacity-10 my-1"></li>';
        Object.keys(grupos).forEach(function (gNombre) {
            html += '<li><small class="dropdown-header text-uppercase opacity-50 ps-2" style="font-size:0.65rem;">' + escHtmlNotif(gNombre) + '</small></li>';
            grupos[gNombre].forEach(function (n) {
                html += '<li><button class="dropdown-item rounded-2 small mb-1" type="button" onclick="' + stopStr + 'enviarNotificacionConfig(' + repairId + ',\'' + escAttrNotif(n.id) + '\',\'' + escAttrNotif(n.titulo) + '\')">'
                    + '<i class="bi bi-' + escAttrNotif(n.icono || 'bell-fill') + ' me-2 text-warning"></i>'
                    + escHtmlNotif(n.titulo) + '</button></li>';
            });
        });
        return html;
    }

    function enviarNotificacionConfig(repairId, notifId, titulo) {
        showConfirm('Enviar Notificación', '¿Enviar "' + escHtmlNotif(titulo) + '" al cliente por WhatsApp?', function () {
            const panel = window.PanelReparaciones;
            if (panel && panel.modalConfirmacion) panel.modalConfirmacion.hide();
            var fd = new FormData();
            fd.append('action', 'enviar');
            fd.append('repair_id', repairId);
            fd.append('notif_id', notifId);
            fetch((window.APP_API_BASE || 'api/') + 'api_notificaciones_config.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var toastEl = document.getElementById('liveToast');
                    var toastErrEl = document.getElementById('errorToast');
                    if (data.ok) {
                        var msgEl = document.getElementById('toastMsg');
                        if (msgEl) msgEl.textContent = data.message || 'Notificación enviada';
                        if (toastEl && window.bootstrap) new bootstrap.Toast(toastEl, { delay: 3000 }).show();
                    } else {
                        var errEl = document.getElementById('errorMsg');
                        if (errEl) errEl.textContent = data.message || 'Error al enviar';
                        if (toastErrEl && window.bootstrap) new bootstrap.Toast(toastErrEl, { delay: 4000 }).show();
                    }
                })
                .catch(function () {
                    var errEl = document.getElementById('errorMsg');
                    if (errEl) errEl.textContent = 'Error de conexión';
                    var toastErrEl = document.getElementById('errorToast');
                    if (toastErrEl && window.bootstrap) new bootstrap.Toast(toastErrEl, { delay: 4000 }).show();
                });
        });
    }
    const PIPELINE_LOCK_KEY = 'solucionescel_pipeline_lock';

    // Estados dinamicos cargados desde estados_config
    var estadosListos = false;

    function loadSubEstadosMap() {
        if (window.getSubEstadosMap && window.loadEstadosMap) {
            window.loadEstadosMap().then(function () {
                estadosListos = true;
            });
        }
    }

    function getSubEstadosHtml(estadoSlug) {
        if (window.getSubEstadosHtml) {
            return window.getSubEstadosHtml(estadoSlug);
        }
        return '<span class="text-muted" style="font-size:0.75rem;">—</span>';
    }

    /**
     * Muestra solo el sub-estado seleccionado para una reparación concreta.
     * Si el estado tiene hijos pero ninguno fue elegido → "Sin seleccionar"
     * Si clickable=true, envuelve en un botón para abrir el modal de selección.
     */
    function getSelectedSubEstadoHtml(estadoSlug, tipoGarantia, repId, clickable) {
        var hijos = (window.getEstadoHijos && window.getEstadoHijos(estadoSlug)) || [];
        if (!hijos.length) {
            return '<span class="text-muted" style="font-size:0.75rem;">—</span>';
        }
        var inner;
        if (!tipoGarantia) {
            inner = '<span class="text-warning fst-italic" style="font-size:0.72rem;cursor:pointer;">' +
                '<i class="bi bi-pencil-fill me-1" style="font-size:0.65rem;"></i>Sin seleccionar</span>';
        } else {
            var found = hijos.find(function (h) { return h.slug === tipoGarantia; });
            var nombre = found ? found.nombre : tipoGarantia.replace(/_/g, ' ');
            var color = found ? (found.color || '#3b82f6') : '#3b82f6';
            inner = '<span class="badge rounded-pill px-2 py-0" style="background:' + color + '22;color:' + color +
                ';font-size:0.7rem;border:1px solid ' + color + '44;cursor:pointer;" title="Cambiar sub-estado">' +
                window.escapeHtml(nombre) + ' <i class="bi bi-pencil-fill ms-1" style="font-size:0.6rem;opacity:0.6;"></i></span>';
        }
        if (clickable && repId) {
            return '<button type="button" class="btn p-0 border-0 bg-transparent" style="line-height:1;" ' +
                'onclick="openSubEstadoModal(' + repId + ',\'' + estadoSlug + '\')" title="Seleccionar sub-estado">' +
                inner + '</button>';
        }
        return inner;
    }

    // Referencias de modales compartidas con panel.js
    window.PanelReparaciones = window.PanelReparaciones || {
        modalConfirmacion: null,
        modalActivarGarantia: null,
        modalSeleccionarTipoListo: null,
        modalActivarGarantiaId: null,
        modalSeleccionarTipoListoId: null,
        modalSeleccionarTipoListoEstado: null,
        confirmCallback: null
    };

    function getRepLastItems() {
        return repLastItems.slice();
    }
    function setRepLastItems(items) {
        repLastItems = items;
        if (window.innerWidth < 992) {
            renderRepairsCards(items);
        } else {
            renderRepairsRows(items);
        }
        updateRepPaginationInfo();
    }

    function updateRepPaginationInfo() {
        const infoEl = document.getElementById('repPaginationInfo');
        const btnPrev = document.getElementById('btnRepPrevPage');
        const btnNext = document.getElementById('btnRepNextPage');
        if (!infoEl || !btnPrev || !btnNext) return;
        if (repTotalItems === 0) {
            infoEl.textContent = 'Sin reparaciones para mostrar.';
            btnPrev.disabled = true;
            btnNext.disabled = true;
            return;
        }
        const totalPages = Math.ceil(repTotalItems / repPerPage);
        const start = (repCurrentPage - 1) * repPerPage + 1;
        const end = Math.min(repCurrentPage * repPerPage, repTotalItems);
        infoEl.textContent = `Mostrando ${start}-${end} de ${repTotalItems} equipos`;
        btnPrev.disabled = repCurrentPage <= 1;
        btnNext.disabled = repCurrentPage >= totalPages;
    }

    function buildEstadosDropdownHtml(id, enGarantia, estadoActual) {
        var padres = (window.getEstadoPadres && window.getEstadoPadres(enGarantia)) || [];
        if (!padres.length) return '';
        var items = '';
        padres.forEach(function (p) {
            if (p.slug === estadoActual) return;
            var dot = '<span class="status-dot" style="background-color:' + (p.color || '#94a3b8') + ';"></span>';
            if (p.hijos && p.hijos.length > 0) {
                if (p.seleccionable !== false) {
                    // Seleccionable directamente: clic en padre → updateStatus
                    // También muestra flecha + submenu para elegir sub-estado
                    items += '<li class="dropdown-submenu position-relative">' +
                        '<button class="dropdown-item rounded-2 small mb-1 d-flex align-items-center justify-content-between" onclick="updateStatus(' + id + ',\'' + p.slug + '\')">' +
                        '<span>' + dot + window.escapeHtml(p.nombre) + '</span>' +
                        '<i class="bi bi-chevron-right small ms-2 opacity-50"></i></button>' +
                        '<ul class="dropdown-menu dropdown-menu-dark shadow-lg border-secondary p-1 rounded-3 submenu-garantia" style="background: #1e293b; position: absolute; left: 100%; top: 0; margin-left: 2px;">';
                    p.hijos.forEach(function (h) {
                        items += '<li><button class="dropdown-item rounded-2 small mb-1" onclick="updateStatusConHijo(' + id + ',\'' + p.slug + '\',\'' + h.slug + '\')"><span class="status-dot" style="background-color:' + (h.color || '#94a3b8') + ';"></span>' + window.escapeHtml(h.nombre) + '</button></li>';
                    });
                    items += '</ul></li>';
                } else {
                    // NO seleccionable: clic en padre abre modal obligatorio de sub-estado
                    items += '<li><button class="dropdown-item rounded-2 small mb-1 d-flex align-items-center justify-content-between" onclick="openSubEstadoModal(' + id + ',\'' + p.slug + '\')">' +
                        '<span>' + dot + window.escapeHtml(p.nombre) + '</span>' +
                        '<i class="bi bi-chevron-right small ms-2 opacity-50"></i></button></li>';
                }
            } else {
                items += '<li><button class="dropdown-item rounded-2 small mb-1" onclick="updateStatus(' + id + ',\'' + p.slug + '\')"><span class="status-dot" style="background-color:' + (p.color || '#94a3b8') + ';"></span>' + window.escapeHtml(p.nombre) + '</button></li>';
            }
        });
        return items;
    }

    // Abre el modal iOS de selección obligatoria de sub-estado
    function openSubEstadoModal(id, padreSlug) {
        var panel = window.PanelReparaciones;
        panel.modalSeleccionarTipoListoId = id;
        panel.modalSeleccionarTipoListoEstado = padreSlug;
        if (panel.modalSeleccionarTipoListo) {
            if (typeof window.actualizarModalSeleccionarHijo === 'function') {
                window.actualizarModalSeleccionarHijo(padreSlug);
            }
            panel.modalSeleccionarTipoListo.show();
        }
    }

    var tipoGarantiaLabelsCache = null;

    function getTipoGarantiaLabel(tipoGarantia) {
        if (!tipoGarantia) return '';
        if (tipoGarantiaLabelsCache === null) {
            tipoGarantiaLabelsCache = Object.assign({}, window.TIPO_GARANTIA_LABELS || {});
        }
        var label = tipoGarantiaLabelsCache[tipoGarantia];
        if (label) return label;
        if (window.getEstadoLabel) {
            label = window.getEstadoLabel(tipoGarantia);
            if (label && label !== tipoGarantia) return label;
        }
        return tipoGarantia.replace(/_/g, ' ');
    }

    function renderRepairsRows(items) {
        const tbody = document.getElementById('repairsTableBody');
        if (!tbody) return;
        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">Sin reparaciones para mostrar.</td></tr>';
            return;
        }
        const rowsHtml = items.map(function (r) {
            const estado = r.estado || '';
            const enGarantia = !!(r.inicio_garantia_reactivado);
            const esMesAzulInactivado = (r.mes_azul_estado || '') === 'inactivado';
            const bgStatus = (window.getEstadoColorMobile || function () { return '#94a3b8'; })(estado);
            const labelEstado = (window.getEstadoLabelMobile || function (e) { return e; })(estado, enGarantia, esMesAzulInactivado);
            const tipoGarantiaLabel = (estado === 'listo' || estado === 'listo_sin_garantia')
                ? getTipoGarantiaLabel(r.tipo_garantia)
                : '';
            const estadoConGarantia = window.escapeHtml(labelEstado);
            const esListo = (estado === 'listo' || estado === 'listo_sin_garantia');
            const diasParaVencido = esListo && r.fecha_listo ? window.calcularDias(r.fecha_listo) : 0;
            const dias = window.calcularDias(r.fecha_ingreso);
            const isOld = esListo && diasParaVencido >= 90;
            const claseFila = (estado === 'entregado' || estado === 'garantia_entregada' || estado === 'inactivo') ? 'row-entregado' : '';
            const claseOpaco = esMesAzulInactivado ? ' dispositivo-opaco' : '';
            const modeloCompleto = ((r.equipo_marca || '') ? (r.equipo_marca + ' ') : '') + (r.equipo_modelo || '');
            const initials = window.getInitials(r.cliente_nombre || '');
            const entregadoFlag = (estado === 'entregado' || estado === 'garantia_entregada' || estado === 'inactivo') ? 'true' : 'false';
            const fechaObj = r.fecha_ingreso ? new Date(r.fecha_ingreso) : null;
            const fechaFmt = fechaObj && !isNaN(fechaObj.getTime())
                ? fechaObj.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' })
                : '';
            const payload = JSON.stringify(r).replace(/</g, '\\u003c');
            const dataDaysVencido = esListo ? diasParaVencido : 0;
            const menuEstadoGarantia = buildEstadosDropdownHtml(r.id, true, estado) + '<li><hr class="dropdown-divider bg-white opacity-10 my-1"></li>';
            const menuEstadoNormal = buildEstadosDropdownHtml(r.id, false, estado) + '<li><hr class="dropdown-divider bg-white opacity-10 my-1"></li>';
            const puedeInactivar = (estado === 'garantia_activada' || !r.inicio_garantia_reactivado);
            return `
                <tr class="${claseFila}${claseOpaco}" data-status="${window.escapeHtml(estado)}" data-days="${dataDaysVencido}" data-entregado="${entregadoFlag}" data-mes-azul="${esMesAzulInactivado ? 'inactivado' : ''}">
                    <td class="ps-4"><div class="d-flex flex-column"><a href="modules/equipo_360?id=${r.id}" class="font-monospace fw-bold text-info text-decoration-none" style="cursor:pointer" title="Ver Vista 360°">#${window.escapeHtml(r.folio_publico || '')}</a><span class="text-muted small mt-1">${window.escapeHtml(fechaFmt)}</span></div></td>
                    <td><div class="d-flex align-items-center gap-2"><div class="avatar-circle flex-shrink-0">${window.escapeHtml(initials)}</div><div style="min-width: 0;"><div class="fw-bold text-truncate text-white">${window.escapeHtml(r.cliente_nombre || '')}</div><div class="small text-muted d-flex align-items-center"><i class="bi bi-whatsapp me-1 text-success"></i>${window.escapeHtml(r.telefono || '')}</div></div></div></td>
                    <td><div class="fw-medium text-light">${window.escapeHtml(modeloCompleto)}</div>${esMesAzulInactivado ? '<span class="etiqueta-mes-azul badge bg-secondary bg-opacity-25 text-info border border-info border-opacity-25 rounded-pill px-2 py-0 mt-1" style="font-size: 10px;"><i class="bi bi-hourglass-split me-1"></i>Dispositivo inactivado por Mes Azul</span>' : ''}<div class="small text-muted text-truncate" style="max-width: 180px;">${window.escapeHtml(r.falla_reportada || '')}</div>${isOld && !esMesAzulInactivado ? `<span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-25 rounded-pill px-2 py-0 mt-1" style="font-size: 10px;"><i class="bi bi-clock-history me-1"></i>${diasParaVencido} días</span>` : ''}</td>
                    <td>${(estado === 'entregado' || estado === 'garantia_entregada' || estado === 'inactivo') ? `<div class="d-flex align-items-center opacity-90"><span class="status-dot" style="background-color: ${bgStatus}; box-shadow: 0 0 8px ${bgStatus};"></span><span class="ms-2">${window.escapeHtml(labelEstado)}</span></div>` : `<div class="dropdown"><button class="btn btn-sm dropdown-toggle btn-status-drop d-flex align-items-center text-white" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport"><div class="d-flex align-items-center"><span class="status-dot" style="background-color: ${bgStatus}; box-shadow: 0 0 8px ${bgStatus};"></span>${estadoConGarantia}</div></button><ul class="dropdown-menu dropdown-menu-dark shadow-lg border-secondary p-1 rounded-3" style="background: #1e293b;">${enGarantia ? menuEstadoGarantia : menuEstadoNormal}</ul></div>`}</td>
                    <td>${getSelectedSubEstadoHtml(estado, r.tipo_garantia, r.id, !(estado === 'entregado' || estado === 'garantia_entregada' || estado === 'inactivo'))}</td>
                    <td class="pe-4 text-end"><div class="d-flex justify-content-end gap-2">${(estado === 'entregado' || estado === 'garantia_entregada' || estado === 'inactivo') ? (estado === 'inactivo' ? `<button class="btn-action text-success border-0" onclick="reactivarInactivo(${r.id})" title="Reactivar"><i class="bi bi-arrow-repeat"></i></button>` : `<button class="btn-action text-success border-0" onclick="abrirModalActivarGarantia(${r.id})" title="Reactivar"><i class="bi bi-arrow-repeat"></i></button>`) : `<div class="dropdown dropstart"><button class="btn-action text-success border-0" type="button" data-bs-toggle="dropdown" title="Notificar"><i class="bi bi-bell-fill"></i></button><ul class="dropdown-menu dropdown-menu-dark shadow-lg border-secondary p-1 rounded-3" style="background: #1e293b; min-width: 200px;"><li><small class="dropdown-header text-uppercase opacity-50 ps-2" style="font-size: 0.65rem;">Operativo</small></li><li><button class="dropdown-item rounded-2 small mb-1" onclick="enviarExtra(${r.id}, 'piezas_pedido')"><i class="bi bi-box-seam me-2 text-info"></i>Pieza Llegó</button></li><li><hr class="dropdown-divider bg-white opacity-10 my-1"></li><li><small class="dropdown-header text-uppercase opacity-50 ps-2" style="font-size: 0.65rem;">Ciclo</small></li><li><button class="dropdown-item rounded-2 small mb-1" onclick="enviarExtra(${r.id}, 'mes_azul_inicio')"><i class="bi bi-hourglass-split me-2"></i>Mes Azul (Inicio)</button></li><li><button class="dropdown-item rounded-2 small mb-1" onclick="enviarExtra(${r.id}, 'mes_azul_final')"><i class="bi bi-calendar-x me-2"></i>Mes Azul (Final)</button></li>${puedeInactivar ? `<li><button class="dropdown-item rounded-2 small" onclick="inactivarEquipo(${r.id})"><i class="bi bi-pause-circle me-2"></i>Inactivar</button></li>` : ''}${buildNotifConfigDropdownItems(r.id, false)}</ul></div>`}<button class="btn-action text-warning border-0" onclick="verHistorial(${r.id}, '#${window.escapeHtml(r.folio_publico || '')}')" title="Historial"><i class="bi bi-clock-history"></i></button><button class="btn-action text-info border-0" onclick='abrirMensaje(${payload})' title="Mensaje"><i class="bi bi-chat-text-fill"></i></button><button class="btn-action text-light border-0" onclick='abrirEditar(${payload})' title="Editar"><i class="bi bi-pencil-fill"></i></button></div></td>
                </tr>`;
        }).join('');
        tbody.innerHTML = rowsHtml;
    }

    function renderRepairsCards(items) {
        const container = document.getElementById('repairsCardsContainer');
        if (!container) return;
        if (!items || items.length === 0) {
            container.innerHTML = '<div class="text-center py-5 text-muted">Sin reparaciones para mostrar.</div>';
            return;
        }
        const cardsHtml = items.map(function (r) {
            const estado = r.estado || '';
            const enGarantia = !!(r.inicio_garantia_reactivado);
            const esMesAzulInactivado = (r.mes_azul_estado || '') === 'inactivado';
            const labelEstado = window.getEstadoLabelMobile(estado, enGarantia, esMesAzulInactivado);
            const colorEstado = window.getEstadoColorMobile(estado);
            const esListoCard = (estado === 'listo' || estado === 'listo_sin_garantia');
            const diasParaVencidoCard = esListoCard && r.fecha_listo ? window.calcularDias(r.fecha_listo) : 0;
            const isOld = esListoCard && diasParaVencidoCard >= 90;
            const modeloCompleto = ((r.equipo_marca || '') ? (r.equipo_marca + ' ') : '') + (r.equipo_modelo || '');
            const fallaShort = (r.falla_reportada || '').substring(0, 50) + ((r.falla_reportada || '').length > 50 ? '…' : '');
            const id = r.id;
            const tipoGarantiaLabel = (estado === 'listo' || estado === 'listo_sin_garantia') && r.tipo_garantia
                ? getTipoGarantiaLabel(r.tipo_garantia)
                : '';
            const garantiaBadge = tipoGarantiaLabel
                ? '<span class="app-mobile-card-badge bg-success bg-opacity-25 text-success border border-success border-opacity-25" style="margin-left:4px;"><i class="bi bi-shield-fill-check me-1"></i>' + window.escapeHtml(tipoGarantiaLabel) + '</span>'
                : '';
            return '<div class="app-mobile-card d-flex align-items-start justify-content-between gap-2" data-repair-id="' + id + '" role="button" tabindex="0"><div class="flex-grow-1 min-w-0"><div class="app-mobile-card-title font-monospace text-info">#' + window.escapeHtml(r.folio_publico || '') + '</div><div class="app-mobile-card-subtitle fw-medium text-white">' + window.escapeHtml(r.cliente_nombre || '') + '</div><div class="app-mobile-card-subtitle">' + window.escapeHtml(modeloCompleto) + (fallaShort ? ' · ' + window.escapeHtml(fallaShort) : '') + '</div><div class="app-mobile-card-meta"><span class="app-mobile-card-badge" style="background:' + colorEstado + '22;color:' + colorEstado + '">' + window.escapeHtml(labelEstado) + '</span>' + garantiaBadge + (isOld ? '<span class="app-mobile-card-badge bg-danger bg-opacity-25 text-danger"><i class="bi bi-clock-history me-1"></i>' + diasParaVencidoCard + ' días</span>' : '') + '</div></div><button type="button" class="app-mobile-card-more flex-shrink-0" onclick="event.preventDefault();event.stopPropagation();openRepairActionsSheet(' + id + ')" aria-label="Más acciones"><i class="bi bi-three-dots-vertical"></i></button></div>';
        }).join('');
        container.innerHTML = cardsHtml;
    }

    function openRepairActionsSheet(repairId) {
        const r = repLastItems.find(function (item) { return item.id == repairId; });
        if (!r || typeof window.openBottomSheet !== 'function') return;
        const estado = r.estado || '';
        const entregadoOInactivo = estado === 'entregado' || estado === 'garantia_entregada' || estado === 'inactivo';
        const puedeInactivar = (estado === 'garantia_activada' || !r.inicio_garantia_reactivado);
        const actions = [
            { label: 'Editar', icon: 'bi-pencil', onClick: function () { window.abrirEditar(r); window.abrirOffcanvas('offcanvasEditar'); } },
            { label: 'Historial', icon: 'bi-clock-history', onClick: function () { window.verHistorial(r.id, '#' + (r.folio_publico || '')); window.abrirOffcanvas('offcanvasHistorial'); } },
            { label: 'Enviar mensaje', icon: 'bi-chat-text', onClick: function () { window.abrirMensaje(r); window.abrirOffcanvas('offcanvasMensaje'); } }
        ];
        if (!entregadoOInactivo) {
            var padresMovil = (window.getEstadoPadres && window.getEstadoPadres(!!r.inicio_garantia_reactivado)) || [];
            padresMovil.forEach(function (p) {
                if (p.slug === estado) return;
                if (p.hijos && p.hijos.length > 0) {
                    if (p.seleccionable !== false) {
                        // Seleccionable directo: aparece como opción directa
                        var pSlug = p.slug;
                        actions.push({ label: p.nombre, icon: 'bi-check-circle', onClick: function () { updateStatus(r.id, pSlug); } });
                    } else {
                        // No seleccionable: abre modal para elegir sub-estado obligatorio
                        var pSlug = p.slug;
                        actions.push({ label: p.nombre + '…', icon: 'bi-list-check', onClick: function () { openSubEstadoModal(r.id, pSlug); } });
                    }
                } else {
                    var icon = p.nombre.toLowerCase().indexOf('pendiente') >= 0 || p.nombre.toLowerCase().indexOf('laboratorio') >= 0 || p.nombre.toLowerCase().indexOf('taller') >= 0 ? 'bi-tools' : 'bi-check-circle';
                    var pSlug = p.slug;
                    actions.push({ label: p.nombre, icon: icon, onClick: function () { updateStatus(r.id, pSlug); } });
                }
            });
            actions.push({ label: 'Pieza llegó', icon: 'bi-box-seam', onClick: function () { enviarExtra(r.id, 'piezas_pedido'); } });
            if (puedeInactivar) actions.push({ label: 'Inactivar', icon: 'bi-pause-circle', onClick: function () { inactivarEquipo(r.id); }, danger: true });
        } else {
            actions.push({
                label: 'Reactivar',
                icon: 'bi-arrow-repeat',
                onClick: function () { (estado === 'inactivo' ? reactivarInactivo : abrirModalActivarGarantia)(r.id); }
            });
        }
        window.openBottomSheet({ title: 'Acciones', actions: actions });
    }

    async function loadReparaciones(page) {
        page = page || repCurrentPage || 1;
        const tbody = document.getElementById('repairsTableBody');
        const cardsContainer = document.getElementById('repairsCardsContainer');
        const isMobile = window.innerWidth < 992;
        if (tbody && !isMobile) tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">Cargando reparaciones...</td></tr>';
        if (cardsContainer && isMobile) cardsContainer.innerHTML = '<div class="text-center py-5 text-muted">Cargando reparaciones...</div>';
        try {
            // Cargar el mapa de estados y las reparaciones en paralelo
            const [resp] = await Promise.all([
                fetch(apiBase + 'api_reparaciones_list.php?page=' + page + '&per_page=' + repPerPage),
                window.loadEstadosMap ? window.loadEstadosMap() : Promise.resolve(),
            ]);
            const data = await resp.json();
            if (!data.ok) throw new Error(data.message || 'Error al cargar reparaciones');
            repCurrentPage = data.page || 1;
            repTotalItems = data.total || 0;
            repLastItems = data.items || [];
            if (window.innerWidth < 992) {
                renderRepairsCards(data.items || []);
                if (tbody) tbody.innerHTML = '';
            } else {
                renderRepairsRows(data.items || []);
                if (cardsContainer) cardsContainer.innerHTML = '';
            }
            updateRepPaginationInfo();
        } catch (err) {
            if (tbody && !isMobile) tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-danger">Error al cargar reparaciones.</td></tr>';
            if (cardsContainer && isMobile) cardsContainer.innerHTML = '<div class="text-center py-5 text-danger">Error al cargar reparaciones.</div>';
            const infoEl = document.getElementById('repPaginationInfo');
            if (infoEl) infoEl.textContent = 'No se pudo cargar el listado.';
        }
    }

    function changeRepPage(delta) {
        const totalPages = Math.ceil(repTotalItems / repPerPage) || 1;
        let target = repCurrentPage + delta;
        if (target < 1) target = 1;
        if (target > totalPages) target = totalPages;
        if (target === repCurrentPage) return;
        loadReparaciones(target);
    }

    function showConfirm(title, message, callback) {
        const panel = window.PanelReparaciones;
        const titleEl = document.getElementById('confirmTitle');
        const msgEl = document.getElementById('confirmMessage');
        if (titleEl) titleEl.innerText = title;
        if (msgEl) msgEl.innerHTML = message;
        panel.confirmCallback = callback;
        if (panel.modalConfirmacion) panel.modalConfirmacion.show();
    }

    function postReparacionAjax(formData, successMsg) {
        window.__REALTIME_SKIP = Date.now() + 2000;
        fetch((window.APP_API_BASE || 'api/') + 'api_reparaciones.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    loadReparaciones(repCurrentPage);
                    var pipelineEl = document.getElementById('viewPipelineContainer');
                    if (pipelineEl && pipelineEl.style.display !== 'none') loadReparacionesPipeline();
                    const msgEl = document.getElementById('toastMsg');
                    if (msgEl) msgEl.innerText = successMsg || data.message;
                    if (window.toastSuccess) window.toastSuccess.show();
                } else {
                    const errEl = document.getElementById('errorMsg');
                    if (errEl) errEl.innerText = data.message || 'Error';
                    if (window.toastError) window.toastError.show();
                }
            })
            .catch(function () {
                const errEl = document.getElementById('errorMsg');
                if (errEl) errEl.innerText = 'Error de conexión';
                if (window.toastError) window.toastError.show();
            });
    }

    function inactivarEquipo(id) {
        showConfirm('Inactivar equipo', '¿Inactivar este equipo? Pasará a estado Inactivo. No se envía ninguna notificación.', function () {
            const panel = window.PanelReparaciones;
            if (panel.modalConfirmacion) panel.modalConfirmacion.hide();
            const fd = new FormData();
            fd.append('action', 'inactivar_garantia');
            fd.append('id', id);
            postReparacionAjax(fd, 'Equipo inactivado');
        });
    }

    function updateStatus(id, nuevoEstado) {
        showConfirm('Actualizar Estado', 'Se enviará una notificación automática según tu plantilla configurada.', function () {
            const panel = window.PanelReparaciones;
            if (panel.modalConfirmacion) panel.modalConfirmacion.hide();
            const fd = new FormData();
            fd.append('action', 'update_status');
            fd.append('id', id);
            fd.append('nuevo_estado', nuevoEstado);
            postReparacionAjax(fd, 'Estado actualizado');
        });
    }

    function updateStatusConGarantia(id, nuevoEstado, tipoGarantia) {
        updateStatusConHijo(id, nuevoEstado, tipoGarantia);
    }

    function updateStatusConHijo(id, padreSlug, hijoSlug) {
        var hijoNombre = hijoSlug;
        if (window.getEstadoHijos) {
            var hijos = window.getEstadoHijos(padreSlug);
            var found = hijos.find(function (h) { return h.slug === hijoSlug; });
            if (found) hijoNombre = found.nombre;
        }
        showConfirm(
            'Actualizar Estado',
            'Se marcará el equipo con: <strong>' + window.escapeHtml(hijoNombre) + '</strong>.<br><br>Se enviará una notificación automática al cliente.',
            function () {
                const panel = window.PanelReparaciones;
                if (panel.modalConfirmacion) panel.modalConfirmacion.hide();
                const fd = new FormData();
                fd.append('action', 'update_status');
                fd.append('id', id);
                fd.append('nuevo_estado', padreSlug);
                fd.append('tipo_garantia', hijoSlug);
                postReparacionAjax(fd, 'Estado actualizado');
            }
        );
    }

    function enviarExtra(id, tipo) {
        showConfirm('Notificación Extra', '¿Enviar el aviso predefinido para esta acción?', function () {
            const panel = window.PanelReparaciones;
            if (panel.modalConfirmacion) panel.modalConfirmacion.hide();
            const fd = new FormData();
            fd.append('action', 'notify_extra');
            fd.append('id', id);
            fd.append('tipo_extra', tipo);
            postReparacionAjax(fd, 'Notificación enviada');
        });
    }

    function filterTable(criteria, element) {
        document.querySelectorAll('.filter-chip').forEach(function (el) {
            el.classList.remove('active', 'active-danger');
        });
        if (element) element.classList.add(criteria === 'old' ? 'active-danger' : 'active');
        document.querySelectorAll('#repairsTable tbody tr').forEach(function (row) {
            const status = row.getAttribute('data-status');
            const days = parseInt(row.getAttribute('data-days'), 10);
            const entregado = row.getAttribute('data-entregado') === 'true';
            var show;
            if (criteria === 'all') {
                show = true;
            } else if (criteria === 'old') {
                show = days >= 90 && !entregado;
            } else if (criteria === 'listo') {
                show = status === 'listo' || status === 'listo_sin_garantia';
            } else if (criteria === 'en_taller') {
                show = status === 'en_taller' || status === 'ingresado' || status === 'diagnostico';
            } else if (criteria === 'inactivo') {
                show = status === 'inactivo' || row.getAttribute('data-mes-azul') === 'inactivado';
            } else {
                show = status === criteria;
            }
            row.style.display = show ? '' : 'none';
        });
    }

    // ═══════════════════════════════════════════
    // VISTA PIPELINE / KANBAN
    // ═══════════════════════════════════════════
    var pipelineEstados = (window.PIPELINE_ESTADOS || []);
    var pipelinePuedeCambiarEstado = !!(window.PIPELINE_PUEDE_CAMBIAR_ESTADO);
    var pipelineDragLocked = false;
    var pipelineDragSourceCard = null;
    var pipelineDragSourceEstado = null;

    function getPipelineSearchFilter() {
        var inp = document.getElementById('searchInput');
        return inp ? inp.value.toLowerCase().trim() : '';
    }

    function pipelineCardMatchesSearch(r, filter) {
        if (!filter) return true;
        var text = [
            r.cliente_nombre || '',
            r.folio_publico || '',
            r.equipo_marca || '',
            r.equipo_modelo || '',
            r.falla_reportada || ''
        ].join(' ').toLowerCase();
        return text.indexOf(filter) !== -1;
    }

    function renderPipeline(items, searchFilter) {
        var board = document.getElementById('pipelineBoard');
        if (!board) return;
        var filtered = searchFilter ? items.filter(function (r) { return pipelineCardMatchesSearch(r, searchFilter); }) : items;
        pipelineEstados.forEach(function (est) {
            var col = board.querySelector('.pipeline-column[data-estado="' + est.slug + '"]');
            if (!col) return;
            var zone = col.querySelector('.pipeline-column-cards');
            var countEl = col.querySelector('.pipeline-column-count');
            if (!zone || !countEl) return;
            var inState = filtered.filter(function (r) {
                var efectivo = ((r.mes_azul_estado || '') === 'inactivado') ? 'inactivo' : (r.estado || '');
                // Mapear slugs legacy a slugs dinamicos de estados_config
                var legacyMap = { ingresado: 'en_taller', diagnostico: 'en_taller', listo_sin_garantia: 'listo', confirmacion_garantia: 'garantia_activada' };
                if (legacyMap[efectivo]) efectivo = legacyMap[efectivo];
                return efectivo === est.slug;
            });
            countEl.textContent = inState.length;
            var dragEnabled = pipelinePuedeCambiarEstado && !pipelineDragLocked;
            var dragAttr = dragEnabled ? 'draggable="true"' : '';
            var dragClass = dragEnabled ? '' : ' no-drag';
            zone.innerHTML = inState.map(function (r) {
                var modeloCompleto = ((r.equipo_marca || '') ? (r.equipo_marca + ' ') : '') + (r.equipo_modelo || '');
                var fechaObj = r.fecha_ingreso ? new Date(r.fecha_ingreso) : null;
                var fechaFmt = fechaObj && !isNaN(fechaObj.getTime())
                    ? fechaObj.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: '2-digit' })
                    : '';
                var garantia = !!(r.inicio_garantia_reactivado) &&
                    ['listo', 'en_taller', 'no_quedo', 'garantia_fallida', 'entregado', 'garantia_entregada'].indexOf(r.estado) !== -1;
                var tipoGarantia = r.tipo_garantia || '';
                var tipoGarantiaLabel = getTipoGarantiaLabel(tipoGarantia);
                var modulesPath = window.APP_BASE_PATH ? window.APP_BASE_PATH + 'modules/' : '/modules/';
                var clienteUrl = (r.cliente_id ? (modulesPath + 'cliente_360?id=' + r.cliente_id) : '#');
                var equipoUrl = modulesPath + 'equipo_360?id=' + r.id;
                var estado = r.estado || '';
                var esMesAzulInactivadoCard = (r.mes_azul_estado || '') === 'inactivado';
                var esInactivo = estado === 'inactivo' || esMesAzulInactivadoCard;
                var entregadoOInactivo = estado === 'entregado' || estado === 'garantia_entregada' || esInactivo;
                var puedeInactivar = (estado === 'garantia_activada' || !r.inicio_garantia_reactivado);
                var payload = JSON.stringify(r).replace(/</g, '\\u003c');
                var avisosHtml = entregadoOInactivo ? '' :
                    '<div class="dropdown"><button type="button" class="btn btn-sm pipeline-card-btn pipeline-card-avisos" data-bs-toggle="dropdown" data-bs-boundary="viewport" onclick="event.stopPropagation();" title="Avisos"><i class="bi bi-bell-fill text-warning me-1"></i>Avisos</button><ul class="dropdown-menu dropdown-menu-dark shadow-lg border-secondary p-1 rounded-3" style="background:#1e293b;min-width:180px;"><li><small class="dropdown-header text-uppercase opacity-50 ps-2" style="font-size:0.65rem;">Operativo</small></li><li><button class="dropdown-item rounded-2 small mb-1" type="button" onclick="event.stopPropagation();enviarExtra(' + r.id + ',\'piezas_pedido\')"><i class="bi bi-box-seam me-2 text-info"></i>Pieza Llegó</button></li><li><hr class="dropdown-divider bg-white opacity-10 my-1"></li><li><small class="dropdown-header text-uppercase opacity-50 ps-2" style="font-size:0.65rem;">Ciclo</small></li><li><button class="dropdown-item rounded-2 small mb-1" type="button" onclick="event.stopPropagation();enviarExtra(' + r.id + ',\'mes_azul_inicio\')"><i class="bi bi-hourglass-split me-2"></i>Mes Azul (Inicio)</button></li><li><button class="dropdown-item rounded-2 small mb-1" type="button" onclick="event.stopPropagation();enviarExtra(' + r.id + ',\'mes_azul_final\')"><i class="bi bi-calendar-x me-2"></i>Mes Azul (Final)</button></li>' + (puedeInactivar ? '<li><button class="dropdown-item rounded-2 small" type="button" onclick="event.stopPropagation();inactivarEquipo(' + r.id + ')"><i class="bi bi-pause-circle me-2"></i>Inactivar</button></li>' : '') + buildNotifConfigDropdownItems(r.id, true) + '</ul></div>';
                var menuEstados = '';
                if (!entregadoOInactivo) {
                    menuEstados = buildEstadosDropdownHtml(r.id, garantia, estado);
                    if (puedeInactivar) {
                        menuEstados += '<li><hr class="dropdown-divider bg-white opacity-10 my-1"></li><li><button class="dropdown-item rounded-2 small text-danger" type="button" onclick="event.stopPropagation();inactivarEquipo(' + r.id + ')"><i class="bi bi-pause-circle me-2"></i>Inactivar</button></li>';
                    }
                } else {
                    menuEstados = (estado === 'inactivo' || esMesAzulInactivadoCard)
                        ? '<li><button class="dropdown-item rounded-2 small" type="button" onclick="event.stopPropagation();reactivarInactivo(' + r.id + ')"><i class="bi bi-arrow-repeat me-2"></i>Reactivar</button></li>'
                        : '<li><button class="dropdown-item rounded-2 small" type="button" onclick="event.stopPropagation();abrirModalActivarGarantia(' + r.id + ')"><i class="bi bi-arrow-repeat me-2"></i>Reactivar</button></li>';
                }
                var masHtml = '<div class="dropdown"><button type="button" class="btn btn-sm pipeline-card-btn pipeline-card-more" data-bs-toggle="dropdown" data-bs-boundary="viewport" onclick="event.stopPropagation();" title="Más acciones"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-dark shadow-lg border-secondary p-1 rounded-3" style="background:#1e293b;min-width:200px;"><li><button class="dropdown-item rounded-2 small mb-1" type="button" onclick="event.stopPropagation();abrirEditar(' + payload + ');abrirOffcanvas(\'offcanvasEditar\')"><i class="bi bi-pencil me-2"></i>Editar</button></li><li><button class="dropdown-item rounded-2 small mb-1" type="button" onclick="event.stopPropagation();verHistorial(' + r.id + ',\'#' + window.escapeHtml(r.folio_publico || '') + '\');abrirOffcanvas(\'offcanvasHistorial\')"><i class="bi bi-clock-history me-2"></i>Historial</button></li><li><button class="dropdown-item rounded-2 small mb-1" type="button" onclick="event.stopPropagation();abrirMensaje(' + payload + ');abrirOffcanvas(\'offcanvasMensaje\')"><i class="bi bi-chat-text me-2"></i>Enviar mensaje</button></li><li><hr class="dropdown-divider bg-white opacity-10 my-1"></li>' + menuEstados + '</ul></div>';
                var cardOpacoClass = esMesAzulInactivadoCard ? ' dispositivo-opaco' : '';
                var inactivoBadge = '';
                if (esInactivo) {
                    inactivoBadge = esMesAzulInactivadoCard
                        ? '<div class="mt-1"><span class="pipeline-badge-inactivo pipeline-badge-mesazul"><i class="bi bi-hourglass-split me-1"></i>Inactivo por Mes Azul</span></div>'
                        : '<div class="mt-1"><span class="pipeline-badge-inactivo pipeline-badge-entrega"><i class="bi bi-box-seam me-1"></i>Inactivo por entrega</span></div>';
                }
                var barraGarantia = (estado === 'listo' || estado === 'listo_sin_garantia') && tipoGarantiaLabel
                    ? '<div class="pipeline-badge-listo mt-2"><i class="bi bi-shield-fill-check me-2"></i>' + window.escapeHtml(tipoGarantiaLabel) + '</div>'
                    : '';
                return '<div class="pipeline-card' + dragClass + cardOpacoClass + '" data-repair-id="' + r.id + '" data-estado="' + window.escapeHtml(r.estado || '') + '" data-href="' + equipoUrl + '" role="button" tabindex="0" ' + dragAttr + '>' +
                    '<div class="pipeline-card-body">' +
                    '<div class="pipeline-card-title">' + window.escapeHtml(modeloCompleto) + '</div>' +
                    (r.cliente_id
                        ? '<a href="' + clienteUrl + '" class="pipeline-card-cliente" onclick="event.stopPropagation()"><i class="bi bi-person me-1"></i>' + window.escapeHtml(r.cliente_nombre || '') + '</a>'
                        : '<span class="pipeline-card-cliente text-muted">' + window.escapeHtml(r.cliente_nombre || '') + '</span>') +
                    '<div class="pipeline-card-meta"><span><i class="bi bi-calendar3 me-1"></i>' + window.escapeHtml(fechaFmt) + '</span>' + (garantia ? '<span class="pipeline-card-garantia"><i class="bi bi-shield-check me-1"></i>Garantía</span>' : '') + '</div>' +
                    barraGarantia +
                    inactivoBadge +
                    '</div>' +
                    '<div class="pipeline-card-actions">' + avisosHtml + masHtml + '</div>' +
                    '</div>';
            }).join('');
        });
        syncPipelineScrollBar();
        setupPipelineDragDrop();
        setupPipelineCardClick();
        setupPipelineDropdowns();
    }

    function syncPipelineScrollBar() {
        var board = document.getElementById('pipelineBoard');
        var track = document.getElementById('pipelineScrollTrack');
        var thumb = document.getElementById('pipelineScrollThumb');
        if (!board || !track || !thumb) return;
        var maxScroll = Math.max(0, board.scrollWidth - board.clientWidth);
        var trackWidth = track.offsetWidth;
        var ratio = maxScroll > 0 ? board.clientWidth / board.scrollWidth : 1;
        var thumbWidth = Math.max(40, Math.round(ratio * trackWidth));
        var thumbMaxLeft = trackWidth - thumbWidth;
        thumb.style.width = thumbWidth + 'px';
        if (maxScroll === 0) {
            thumb.style.left = '0';
        } else {
            var left = (board.scrollLeft / maxScroll) * thumbMaxLeft;
            thumb.style.left = Math.round(left) + 'px';
        }
    }

    function setupPipelineCardClick() {
        var board = document.getElementById('pipelineBoard');
        if (!board) return;
        board.addEventListener('click', function (e) {
            var card = e.target.closest('.pipeline-card');
            if (!card) return;
            if (e.target.closest('.pipeline-card-actions') || e.target.closest('a.pipeline-card-cliente')) return;
            var href = card.getAttribute('data-href');
            if (href) window.location.href = href;
        });
    }

    function setupPipelineDropdowns() {
        document.querySelectorAll('.pipeline-column-cards .dropdown-toggle').forEach(function (el) {
            new bootstrap.Dropdown(el);
        });
    }

    function setupPipelineDragDrop() {
        if (!pipelinePuedeCambiarEstado) return;
        var cards = document.querySelectorAll('.pipeline-card:not(.no-drag)');
        var columns = document.querySelectorAll('.pipeline-column');
        cards.forEach(function (card) {
            card.ondragstart = function (e) {
                pipelineDragSourceCard = card;
                pipelineDragSourceEstado = card.getAttribute('data-estado');
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', card.getAttribute('data-repair-id'));
            };
            card.ondragend = function () {
                card.classList.remove('dragging');
                columns.forEach(function (c) { c.classList.remove('drag-over'); });
                pipelineDragSourceCard = null;
                pipelineDragSourceEstado = null;
            };
        });
        columns.forEach(function (col) {
            col.ondragover = function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                col.classList.add('drag-over');
            };
            col.ondragleave = function () { col.classList.remove('drag-over'); };
            col.ondrop = function (e) {
                e.preventDefault();
                col.classList.remove('drag-over');
                var targetEstado = col.getAttribute('data-estado');
                if (pipelineDragSourceEstado === targetEstado) return;
                var id = parseInt(e.dataTransfer.getData('text/plain'), 10);
                if (!id) return;
                var r = repPipelineItems.find(function (i) { return i.id === id; });
                if (!r) return;
                pipelineConfirmMoveCard(r.id, pipelineDragSourceEstado, targetEstado);
            };
        });
    }

    function pipelineConfirmMoveCard(id, estadoAnterior, estadoNuevo) {
        var r = repPipelineItems.find(function (i) { return i.id === id; });
        if (!r) return;
        const panel = window.PanelReparaciones;
        var hijos = (window.getEstadoHijos && window.getEstadoHijos(estadoNuevo)) || [];
        var tieneHijos = hijos.length > 0;
        var estadoInfo = window.getEstadoInfo ? window.getEstadoInfo(estadoNuevo) : null;
        var esSeleccionable = estadoInfo ? estadoInfo.seleccionable !== false : true;

        // Si tiene hijos y NO es seleccionable directamente → modal obligatorio
        // Si tiene hijos pero SÍ es seleccionable → confirmar directo (sin sub-estado)
        if (tieneHijos && !esSeleccionable) {
            openSubEstadoModal(id, estadoNuevo);
            return;
        }
        if (estadoNuevo === 'inactivo') {
            showConfirm('Inactivar equipo', '¿Inactivar este equipo? Pasará a estado Inactivo. No se envía ninguna notificación.', function () {
                if (panel.modalConfirmacion) panel.modalConfirmacion.hide();
                var fd = new FormData();
                fd.append('action', 'inactivar_garantia');
                fd.append('id', id);
                postReparacionAjax(fd, 'Equipo inactivado');
            });
            return;
        }
        showConfirm('Actualizar Estado', 'Se enviará una notificación automática según tu plantilla configurada.', function () {
            if (panel.modalConfirmacion) panel.modalConfirmacion.hide();
            var fd = new FormData();
            fd.append('action', 'update_status');
            fd.append('id', id);
            fd.append('nuevo_estado', estadoNuevo);
            postReparacionAjax(fd, 'Estado actualizado');
        });
    }

    async function loadReparacionesPipeline() {
        var board = document.getElementById('pipelineBoard');
        if (!board) return;
        try {
            var resp = await fetch(apiBase + 'api_reparaciones_list.php?page=1&per_page=500');
            var data = await resp.json();
            if (!data.ok) throw new Error(data.message || 'Error al cargar');
            repPipelineItems = data.items || [];
            renderPipeline(repPipelineItems, getPipelineSearchFilter());
        } catch (err) {
            repPipelineItems = [];
            renderPipeline([], getPipelineSearchFilter());
        }
    }

    function switchViewMode(mode) {
        var tablaContainer = document.getElementById('viewTablaContainer');
        var pipelineContainer = document.getElementById('viewPipelineContainer');
        var filtrosContainer = document.getElementById('filtrosEstadosContainer');
        var viewTabla = document.getElementById('viewTabla');
        var viewPipeline = document.getElementById('viewPipeline');
        if (!tablaContainer || !pipelineContainer) return;
        if (mode === 'pipeline') {
            tablaContainer.style.display = 'none';
            pipelineContainer.style.display = 'block';
            if (filtrosContainer) filtrosContainer.style.display = 'none';
            loadReparacionesPipeline();
            setTimeout(function () { syncPipelineScrollBar(); }, 100);
        } else {
            tablaContainer.style.display = 'block';
            pipelineContainer.style.display = 'none';
            if (filtrosContainer) filtrosContainer.style.display = 'flex';
            loadReparaciones(repCurrentPage);
        }
        try { localStorage.setItem(PIPELINE_STORAGE_KEY, mode); } catch (e) { }
    }

    function initPipelineToolbar() {
        var board = document.getElementById('pipelineBoard');
        var track = document.getElementById('pipelineScrollTrack');
        var thumb = document.getElementById('pipelineScrollThumb');
        var lockBtn = document.getElementById('pipelineLockDrag');
        var lockIcon = document.getElementById('pipelineLockIcon');
        if (!lockBtn || !lockIcon) return;
        try {
            var savedLock = localStorage.getItem(PIPELINE_LOCK_KEY);
            pipelineDragLocked = (savedLock === 'true');
        } catch (e) { }
        lockBtn.setAttribute('aria-pressed', pipelineDragLocked ? 'true' : 'false');
        lockBtn.title = pipelineDragLocked ? 'Desbloquear arrastre de tarjetas' : 'Bloquear arrastre de tarjetas';
        lockIcon.className = pipelineDragLocked ? 'bi bi-lock-fill' : 'bi bi-unlock-fill';

        if (board && track && thumb) {
            board.addEventListener('scroll', function () { syncPipelineScrollBar(); });
            (function () {
                var trackEl = document.getElementById('pipelineScrollTrack');
                var thumbEl = document.getElementById('pipelineScrollThumb');
                var boardEl = document.getElementById('pipelineBoard');
                function scrollFromThumbPosition(thumbLeftPx) {
                    var tw = trackEl.offsetWidth;
                    var thw = thumbEl.offsetWidth;
                    var maxLeft = tw - thw;
                    if (maxLeft <= 0) return;
                    var maxScroll = boardEl.scrollWidth - boardEl.clientWidth;
                    if (maxScroll <= 0) return;
                    var pct = Math.max(0, Math.min(1, thumbLeftPx / maxLeft));
                    boardEl.scrollLeft = pct * maxScroll;
                }
                thumbEl.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    var startX = e.clientX;
                    var startScroll = boardEl.scrollLeft;
                    function onMove(e2) {
                        var maxScroll = boardEl.scrollWidth - boardEl.clientWidth;
                        var tw = trackEl.offsetWidth;
                        var thw = thumbEl.offsetWidth;
                        var maxLeft = tw - thw;
                        if (maxLeft <= 0 || maxScroll <= 0) return;
                        var deltaX = e2.clientX - startX;
                        var scrollPerPx = maxScroll / maxLeft;
                        boardEl.scrollLeft = Math.max(0, Math.min(maxScroll, startScroll + deltaX * scrollPerPx));
                    }
                    function onUp() {
                        document.removeEventListener('mousemove', onMove);
                        document.removeEventListener('mouseup', onUp);
                    }
                    document.addEventListener('mousemove', onMove);
                    document.addEventListener('mouseup', onUp);
                });
                trackEl.addEventListener('click', function (e) {
                    if (e.target === thumbEl) return;
                    var rect = trackEl.getBoundingClientRect();
                    var clickX = e.clientX - rect.left;
                    var thw = thumbEl.offsetWidth;
                    var tw = trackEl.offsetWidth;
                    var maxLeft = tw - thw;
                    if (maxLeft <= 0) return;
                    var thumbCenter = clickX - thw / 2;
                    scrollFromThumbPosition(thumbCenter);
                });
            })();
            syncPipelineScrollBar();
        }

        lockBtn.addEventListener('click', function () {
            pipelineDragLocked = !pipelineDragLocked;
            lockBtn.setAttribute('aria-pressed', pipelineDragLocked ? 'true' : 'false');
            lockBtn.title = pipelineDragLocked ? 'Desbloquear arrastre de tarjetas' : 'Bloquear arrastre de tarjetas';
            lockIcon.className = pipelineDragLocked ? 'bi bi-lock-fill' : 'bi bi-unlock-fill';
            try { localStorage.setItem(PIPELINE_LOCK_KEY, pipelineDragLocked ? 'true' : 'false'); } catch (e) { }
            renderPipeline(repPipelineItems, getPipelineSearchFilter());
        });
    }

    function initPipelineToggle() {
        var viewTabla = document.getElementById('viewTabla');
        var viewPipeline = document.getElementById('viewPipeline');
        if (!viewTabla || !viewPipeline) return;
        initPipelineToolbar();
        var saved = '';
        try { saved = localStorage.getItem(PIPELINE_STORAGE_KEY) || ''; } catch (e) { }
        var initialMode = (saved === 'pipeline') ? 'pipeline' : 'tabla';
        if (initialMode === 'pipeline') {
            viewPipeline.checked = true;
            switchViewMode('pipeline');
        } else {
            viewTabla.checked = true;
        }
        viewTabla.addEventListener('change', function () { if (viewTabla.checked) switchViewMode('tabla'); });
        viewPipeline.addEventListener('change', function () { if (viewPipeline.checked) switchViewMode('pipeline'); });
    }

    function abrirModalActivarGarantia(id) {
        showConfirm('Reactivar dispositivo', '¿Seguro que quieres enviar "Proceso de revisión técnica"?', function () {
            const panel = window.PanelReparaciones;
            if (panel.modalConfirmacion) panel.modalConfirmacion.hide();
            var fd = new FormData();
            fd.append('action', 'iniciar_garantia');
            fd.append('id', id);
            postReparacionAjax(fd, 'Garantía iniciada — Proceso de revisión técnica');
        });
    }

    function reactivarInactivo(id) {
        showConfirm('Reactivar dispositivo', '¿Seguro que quieres enviar "Proceso de revisión técnica"?', function () {
            const panel = window.PanelReparaciones;
            if (panel.modalConfirmacion) panel.modalConfirmacion.hide();
            var fd = new FormData();
            fd.append('action', 'reactivar_inactivo');
            fd.append('id', id);
            postReparacionAjax(fd, 'Dispositivo reactivado');
        });
    }

    function setupSearchInputHandler() {
        var searchInputEl = document.getElementById('searchInput');
        if (!searchInputEl) return;
        searchInputEl.addEventListener('keyup', function () {
            var pipelineContainer = document.getElementById('viewPipelineContainer');
            if (pipelineContainer && pipelineContainer.style.display !== 'none') {
                renderPipeline(repPipelineItems, getPipelineSearchFilter());
            } else {
                var filter = this.value.toLowerCase();
                document.querySelectorAll('#repairsTable tbody tr').forEach(function (row) {
                    row.style.display = row.innerText.toLowerCase().indexOf(filter) !== -1 ? '' : 'none';
                });
            }
        });
    }

    function setupCardsContainerClick() {
        var cardsContainer = document.getElementById('repairsCardsContainer');
        if (!cardsContainer) return;
        cardsContainer.addEventListener('click', function (e) {
            if (e.target.closest('.app-mobile-card-more')) return;
            var card = e.target.closest('.app-mobile-card[data-repair-id]');
            if (!card) return;
            var id = parseInt(card.getAttribute('data-repair-id'), 10);
            var r = repLastItems.find(function (i) { return i.id === id; });
            if (r && window.abrirEditar && window.abrirOffcanvas) {
                window.abrirEditar(r);
                window.abrirOffcanvas('offcanvasEditar');
            }
        });
    }

    function setupResizeHandler() {
        window.addEventListener('resize', function () {
            if (repLastItems.length === 0) return;
            var tbody = document.getElementById('repairsTableBody');
            var cardsEl = document.getElementById('repairsCardsContainer');
            if (window.innerWidth >= 992) {
                renderRepairsRows(repLastItems);
                if (cardsEl) cardsEl.innerHTML = '';
            } else {
                renderRepairsCards(repLastItems);
                if (tbody) tbody.innerHTML = '';
            }
        });
    }

    // Exponer API pública en window
    window.filterTable = filterTable;
    window.changeRepPage = changeRepPage;
    window.updateStatus = updateStatus;
    window.updateStatusConGarantia = updateStatusConGarantia;
    window.updateStatusConHijo = updateStatusConHijo;
    window.openSubEstadoModal = openSubEstadoModal;
    window.enviarExtra = enviarExtra;
    window.enviarNotificacionConfig = enviarNotificacionConfig;
    window.loadNotifConfigCache = loadNotifConfigCache;
    window.openRepairActionsSheet = openRepairActionsSheet;
    if (typeof abrirEditar !== 'undefined') window.abrirEditar = abrirEditar;
    if (typeof abrirMensaje !== 'undefined') window.abrirMensaje = abrirMensaje;
    if (typeof verHistorial !== 'undefined') window.verHistorial = verHistorial;
    window.abrirModalActivarGarantia = abrirModalActivarGarantia;
    window.reactivarInactivo = reactivarInactivo;
    window.inactivarEquipo = inactivarEquipo;
    if (typeof insertTemplateVar !== 'undefined') window.insertTemplateVar = insertTemplateVar;
    if (typeof insertTag !== 'undefined') window.insertTag = insertTag;
    if (typeof resetToSelect !== 'undefined') window.resetToSelect = resetToSelect;
    if (typeof handleMarcaChange !== 'undefined') window.handleMarcaChange = handleMarcaChange;
    if (typeof handleModeloChange !== 'undefined') window.handleModeloChange = handleModeloChange;
    if (typeof abrirOffcanvas !== 'undefined') window.abrirOffcanvas = abrirOffcanvas;
    window.loadReparaciones = loadReparaciones;
    window.loadSubEstadosMap = loadSubEstadosMap;
    window.loadReparacionesPipeline = loadReparacionesPipeline;
    window.getRepLastItems = getRepLastItems;
    window.setRepLastItems = setRepLastItems;
    window.initPipelineToggle = initPipelineToggle;
    window.setupSearchInputHandler = setupSearchInputHandler;
    window.setupCardsContainerClick = setupCardsContainerClick;
    window.setupResizeHandler = setupResizeHandler;
    window.postReparacionAjax = postReparacionAjax;
    window.showConfirm = showConfirm;

})();
