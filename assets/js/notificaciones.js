/**
 * Notificaciones - Configuración de Notificaciones Dinámicas
 * CRUD de notificaciones y grupos, integración con plantillas.
 */
(function () {
    'use strict';

    var apiBase = (window.APP_API_BASE || 'api/');

    function apiUrl(endpoint) {
        var url = apiBase + endpoint;
        if (!url.endsWith('.php') && url.indexOf('?') === -1) {
            var match = url.match(/^(.+?)([\?#].*)?$/);
            if (match) {
                url = match[1] + '.php' + (match[2] || '');
            }
        } else if (!url.endsWith('.php') && url.indexOf('?') > -1) {
            url = url.replace('?', '.php?');
        }
        return url;
    }

    var notifList = [];
    var gruposList = [];
    var offcanvasNotif = null;
    var modalEliminar = null;
    var modalGrupo = null;
    var eliminarId = null;
    var toastSuccess = null;
    var toastError = null;

    // ═══════════════════════════════════════════════════════
    //  CARGA DE DATOS
    // ═══════════════════════════════════════════════════════

    function loadNotificaciones() {
        var tbody = document.getElementById('tbodyNotificaciones');
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Cargando...</td></tr>';

        fetch(apiUrl('api_notificaciones_config?action=list'))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) { showError(data.message || 'Error al cargar'); return; }
                notifList = data.notificaciones || [];
                renderTable(notifList);

                var kpi = document.getElementById('kpiTotalNotificaciones');
                if (kpi) kpi.textContent = notifList.length;
            })
            .catch(function () { showError('Error de conexión'); });
    }

    function loadGrupos() {
        fetch(apiUrl('api_notificaciones_config?action=list_grupos'))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                gruposList = data.grupos || [];
                renderGruposChips(gruposList);
                populateGrupoSelect(null);
            })
            .catch(function () {});
    }

    // ═══════════════════════════════════════════════════════
    //  RENDER TABLA
    // ═══════════════════════════════════════════════════════

    function renderTable(list) {
        var tbody = document.getElementById('tbodyNotificaciones');
        if (!tbody) return;
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Sin notificaciones configuradas</td></tr>';
            return;
        }
        var html = '';
        list.forEach(function (n) {
            html += renderRow(n);
        });
        tbody.innerHTML = html;
    }

    function renderRow(n) {
        var tipoCls = 'notif-type-badge--' + (n.tipo || 'info');
        var tipoLabel = (n.tipo || 'info').charAt(0).toUpperCase() + (n.tipo || 'info').slice(1);

        var grupoInfo = n.grupos_notificaciones;
        var grupoHtml = grupoInfo && grupoInfo.nombre
            ? '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill" style="font-size:0.7rem;">' + escHtml(grupoInfo.nombre) + '</span>'
            : '<span class="text-muted small">—</span>';

        var tplInfo = n.whatsapp_templates;
        var tplBadge = tplInfo && tplInfo.title
            ? '<a href="plantillas?highlight=' + (tplInfo.id || '') + '" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill text-decoration-none" style="font-size: 0.7rem;" title="Ver plantilla"><i class="bi bi-chat-dots me-1"></i>' + escHtml(tplInfo.title) + '</a>'
            : '<span class="badge bg-secondary bg-opacity-10 text-muted border border-secondary border-opacity-25 rounded-pill" style="font-size: 0.7rem;">Sin plantilla</span>';

        var iconHtml = n.icono ? '<i class="bi bi-' + escAttr(n.icono) + '"></i>' : '<i class="bi bi-bell-fill"></i>';

        var html = '<tr class="notif-row">';
        html += '<td class="notif-icon-cell text-white">' + iconHtml + '</td>';
        html += '<td>';
        html += '<div class="fw-medium text-white">' + escHtml(n.titulo || '') + '</div>';
        if (n.mensaje) html += '<div class="small text-muted" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' + escHtml(n.mensaje) + '</div>';
        html += '</td>';
        html += '<td><code class="text-info small" style="font-size: 0.75rem;">' + escHtml(n.slug || '-') + '</code>' +
            '<button class="btn btn-sm btn-link text-muted p-0 ms-2 copy-slug-notif" data-slug="' + escAttr(n.slug || '') + '" title="Copiar slug"><i class="bi bi-copy" style="font-size: 0.7rem;"></i></button></td>';
        html += '<td><span class="notif-type-badge ' + tipoCls + '">' + tipoLabel + '</span></td>';
        html += '<td>' + grupoHtml + '</td>';
        html += '<td>' + tplBadge + '</td>';
        html += '<td>';
        html += '<div class="d-flex gap-1">';
        html += '<button class="btn btn-sm btn-action bg-primary bg-opacity-10 text-primary border-0 rounded-2" onclick="window._editNotificacion(\'' + e(n.id) + '\')" title="Editar"><i class="bi bi-pencil"></i></button>';
        html += '<button class="btn btn-sm btn-action bg-danger bg-opacity-10 text-danger border-0 rounded-2" onclick="window._confirmDeleteNotif(\'' + e(n.id) + '\',\'' + escAttr(n.titulo || '') + '\',\'' + escAttr(n.slug || '') + '\')" title="Eliminar"><i class="bi bi-trash"></i></button>';
        html += '</div>';
        html += '</td>';
        html += '</tr>';
        return html;
    }

    // ═══════════════════════════════════════════════════════
    //  GRUPOS — CHIPS
    // ═══════════════════════════════════════════════════════

    function renderGruposChips(grupos) {
        var container = document.getElementById('gruposChipsContainer');
        if (!container) return;
        if (!grupos || grupos.length === 0) {
            container.innerHTML = '<span class="text-muted small">Sin grupos creados. Usa <strong>Nuevo Grupo</strong> para organizar tus notificaciones.</span>';
            return;
        }
        var html = '';
        grupos.forEach(function (g) {
            html += '<div class="d-inline-flex align-items-center gap-1 px-3 py-1 rounded-pill" style="background: rgba(99,179,237,0.1); border: 1px solid rgba(99,179,237,0.25);">'
                + '<i class="bi bi-collection text-info me-1" style="font-size: 0.8rem;"></i>'
                + '<span class="text-info fw-medium" style="font-size: 0.85rem;">' + escHtml(g.nombre) + '</span>'
                + '<button class="btn btn-sm p-0 ms-2 text-info opacity-50" onclick="window.openCreateGrupoModal(' + g.id + ')" title="Editar grupo" style="line-height:1;">'
                + '<i class="bi bi-pencil" style="font-size: 0.7rem;"></i></button>'
                + '</div>';
        });
        container.innerHTML = html;
    }

    // ═══════════════════════════════════════════════════════
    //  GRUPOS — MODAL CREAR / EDITAR
    // ═══════════════════════════════════════════════════════

    function openCreateGrupoModal(id) {
        var titleEl = document.getElementById('modalGrupoTitle');
        var gnId = document.getElementById('gn_id');
        var gnNombre = document.getElementById('gn_nombre');
        var gnOrden = document.getElementById('gn_orden');
        var gnError = document.getElementById('gn_error');
        var gnEliminarBtn = document.getElementById('gn_eliminar_btn');
        var gnGuardarBtn = document.getElementById('gn_guardar_btn');

        if (!gnId) return;

        gnError.classList.add('d-none');
        gnGuardarBtn.disabled = false;
        gnGuardarBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar';

        if (id) {
            var grupo = gruposList.find(function (g) { return g.id == id; });
            gnId.value = id;
            gnNombre.value = grupo ? grupo.nombre : '';
            gnOrden.value = grupo ? (grupo.orden || 0) : 0;
            if (titleEl) titleEl.innerHTML = '<i class="bi bi-collection me-2 text-info"></i>Editar Grupo';
            if (gnEliminarBtn) gnEliminarBtn.classList.remove('d-none');
        } else {
            gnId.value = '';
            gnNombre.value = '';
            gnOrden.value = '0';
            if (titleEl) titleEl.innerHTML = '<i class="bi bi-collection me-2 text-info"></i>Nuevo Grupo';
            if (gnEliminarBtn) gnEliminarBtn.classList.add('d-none');
        }

        if (modalGrupo) modalGrupo.show();
    }

    function saveGrupo(ev) {
        ev.preventDefault();
        var id = document.getElementById('gn_id').value;
        var nombre = document.getElementById('gn_nombre').value.trim();
        var orden = document.getElementById('gn_orden').value;
        var gnError = document.getElementById('gn_error');
        var gnGuardarBtn = document.getElementById('gn_guardar_btn');

        if (!nombre) {
            gnError.textContent = 'El nombre es requerido';
            gnError.classList.remove('d-none');
            return;
        }

        gnGuardarBtn.disabled = true;
        gnGuardarBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';
        gnError.classList.add('d-none');

        var fd = new FormData();
        fd.append('action', 'save_grupo');
        if (id) fd.append('id', id);
        fd.append('nombre', nombre);
        fd.append('orden', orden);

        fetch(apiUrl('api_notificaciones_config'), { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    gnError.textContent = data.message || 'Error al guardar';
                    gnError.classList.remove('d-none');
                    gnGuardarBtn.disabled = false;
                    gnGuardarBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar';
                    return;
                }
                if (modalGrupo) modalGrupo.hide();
                showToast(data.message || 'Grupo guardado');
                loadGrupos();
                loadNotificaciones();
            })
            .catch(function () {
                gnError.textContent = 'Error de conexión';
                gnError.classList.remove('d-none');
                gnGuardarBtn.disabled = false;
                gnGuardarBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar';
            });
    }

    function deleteGrupoFromModal() {
        var id = document.getElementById('gn_id').value;
        if (!id) return;
        if (!confirm('¿Eliminar este grupo? Las notificaciones quedarán sin grupo asignado.')) return;

        var fd = new FormData();
        fd.append('action', 'delete_grupo');
        fd.append('id', id);

        fetch(apiUrl('api_notificaciones_config'), { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (modalGrupo) modalGrupo.hide();
                if (!data.ok) { showError(data.message || 'Error al eliminar grupo'); return; }
                showToast('Grupo eliminado');
                loadGrupos();
                loadNotificaciones();
            })
            .catch(function () { showError('Error de conexión'); });
    }

    // ═══════════════════════════════════════════════════════
    //  SELECT DE GRUPOS EN EL FORMULARIO DE NOTIFICACIÓN
    // ═══════════════════════════════════════════════════════

    function populateGrupoSelect(selectedId) {
        var sel = document.getElementById('nf_grupo_id');
        if (!sel) return;
        sel.innerHTML = '<option value="">Sin grupo</option>';
        gruposList.forEach(function (g) {
            var opt = document.createElement('option');
            opt.value = g.id;
            opt.textContent = g.nombre;
            if (selectedId && Number(opt.value) === Number(selectedId)) opt.selected = true;
            sel.appendChild(opt);
        });
    }

    // ═══════════════════════════════════════════════════════
    //  MODAL CREAR / EDITAR NOTIFICACIÓN
    // ═══════════════════════════════════════════════════════

    function openCreateNotifModal() {
        document.getElementById('nf_id').value = '';
        document.getElementById('modalNotifTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Nueva Notificación';
        document.getElementById('nf_titulo').value = '';
        document.getElementById('nf_mensaje').value = '';
        document.getElementById('nf_tipo').value = 'info';
        document.getElementById('nf_icono').value = 'bell-fill';
        document.getElementById('nf_slug_wrap').style.display = 'none';
        document.getElementById('nf_error').classList.add('d-none');
        document.getElementById('nf_guardar_btn').innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar';
        document.getElementById('nf_guardar_btn').disabled = false;
        populateGrupoSelect(null);

        loadTemplatesForSelectNotif().then(function () {
            if (offcanvasNotif) offcanvasNotif.show();
        });
    }

    function editNotificacion(id) {
        fetch(apiUrl('api_notificaciones_config?action=get&id=' + encodeURIComponent(id)))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) { showError(data.message); return; }
                var n = data.notificacion;

                document.getElementById('nf_id').value = n.id;
                document.getElementById('modalNotifTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Editar Notificación';
                document.getElementById('nf_titulo').value = n.titulo || '';
                document.getElementById('nf_mensaje').value = n.mensaje || '';
                document.getElementById('nf_tipo').value = n.tipo || 'info';
                document.getElementById('nf_icono').value = n.icono || 'bell-fill';
                document.getElementById('nf_plantilla_id').value = n.plantilla_id || '';
                document.getElementById('nf_error').classList.add('d-none');
                document.getElementById('nf_guardar_btn').innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar Cambios';
                document.getElementById('nf_guardar_btn').disabled = false;

                document.getElementById('nf_slug_wrap').style.display = '';
                document.getElementById('nf_slug_display').textContent = n.slug || '-';

                populateGrupoSelect(n.grupo_id || null);

                loadTemplatesForSelectNotif(n.plantilla_id).then(function () {
                    if (offcanvasNotif) offcanvasNotif.show();
                });
            })
            .catch(function () { showError('Error de conexión'); });
    }

    function loadTemplatesForSelectNotif(selectedId) {
        selectedId = selectedId || null;
        return fetch(apiUrl('api_notificaciones_config?action=list_templates'))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var sel = document.getElementById('nf_plantilla_id');
                if (!sel) return;
                sel.innerHTML = '<option value="">Crear automáticamente al guardar</option>';
                var templates = (data.templates || []);
                if (!templates.length) {
                    var opt = document.createElement('option');
                    opt.value = '';
                    opt.disabled = true;
                    opt.textContent = 'No hay plantillas disponibles';
                    sel.appendChild(opt);
                    return;
                }
                templates.forEach(function (t) {
                    var opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = t.title || ('Plantilla #' + t.id);
                    if (selectedId && Number(opt.value) === Number(selectedId)) opt.selected = true;
                    sel.appendChild(opt);
                });
            })
            .catch(function () {});
    }

    function saveNotificacion(e) {
        e.preventDefault();
        var form = document.getElementById('formNotificacion');
        var fd = new FormData(form);
        fd.append('action', 'save');

        var btn = document.getElementById('nf_guardar_btn');
        var errorEl = document.getElementById('nf_error');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';
        errorEl.classList.add('d-none');

        fetch(apiUrl('api_notificaciones_config'), { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    errorEl.textContent = data.message || 'Error al guardar';
                    errorEl.classList.remove('d-none');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar';
                    return;
                }
                if (offcanvasNotif) offcanvasNotif.hide();
                showToast(data.message || 'Guardado correctamente');
                loadNotificaciones();
            })
            .catch(function () {
                errorEl.textContent = 'Error de conexión';
                errorEl.classList.remove('d-none');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar';
            });
    }

    // ═══════════════════════════════════════════════════════
    //  ELIMINAR NOTIFICACIÓN
    // ═══════════════════════════════════════════════════════

    function confirmDeleteNotif(id, titulo, slug) {
        eliminarId = id;
        document.getElementById('del_notif_nombre').textContent = titulo;
        document.getElementById('del_notif_slug').textContent = slug;
        if (modalEliminar) modalEliminar.show();
    }

    function executeDeleteNotif() {
        if (!eliminarId) return;
        var fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', eliminarId);

        fetch(apiUrl('api_notificaciones_config'), { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (modalEliminar) modalEliminar.hide();
                if (!data.ok) { showError(data.message || 'Error al eliminar'); return; }
                showToast('Notificación eliminada correctamente');
                eliminarId = null;
                loadNotificaciones();
            })
            .catch(function () { showError('Error de conexión'); });
    }

    // ═══════════════════════════════════════════════════════
    //  UTILIDADES
    // ═══════════════════════════════════════════════════════

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function escAttr(str) {
        return escHtml(str).replace(/"/g, '&quot;');
    }

    function e(val) {
        return val || '';
    }

    function showToast(msg) {
        var el = document.getElementById('toastMsg');
        if (el) el.textContent = msg;
        if (toastSuccess) toastSuccess.show();
    }

    function showError(msg) {
        var el = document.getElementById('errorMsg');
        if (el) el.textContent = msg;
        if (toastError) toastError.show();
    }

    function copiarSlugNotif() {
        var slug = document.getElementById('nf_slug_display').textContent;
        if (!slug || slug === '-') return;
        navigator.clipboard.writeText(slug).then(function () {
            showToast('Slug copiado: ' + slug);
        }).catch(function () {
            prompt('Copiar slug:', slug);
        });
    }

    // ═══════════════════════════════════════════════════════
    //  EVENTOS DE TABLA (delegados)
    // ═══════════════════════════════════════════════════════

    function setupTableEvents() {
        document.addEventListener('click', function (e) {
            var copy = e.target.closest('.copy-slug-notif');
            if (copy) {
                var slug = copy.getAttribute('data-slug');
                navigator.clipboard.writeText(slug).then(function () {
                    showToast('Slug copiado: ' + slug);
                }).catch(function () {});
                return;
            }
        });
    }

    // ═══════════════════════════════════════════════════════
    //  INICIALIZACION
    // ═══════════════════════════════════════════════════════

    document.addEventListener('DOMContentLoaded', function () {
        var offcanvasEl = document.getElementById('offcanvasNotificacion');
        var modalDelEl = document.getElementById('modalEliminarNotif');
        var modalGrupoEl = document.getElementById('modalGrupoNotif');
        if (offcanvasEl) offcanvasNotif = new bootstrap.Offcanvas(offcanvasEl);
        if (modalDelEl) modalEliminar = new bootstrap.Modal(modalDelEl);
        if (modalGrupoEl) modalGrupo = new bootstrap.Modal(modalGrupoEl);

        var form = document.getElementById('formNotificacion');
        if (form) form.addEventListener('submit', saveNotificacion);

        var formGrupo = document.getElementById('formGrupoNotif');
        if (formGrupo) formGrupo.addEventListener('submit', saveGrupo);

        var btnDel = document.getElementById('btnConfirmarEliminarNotif');
        if (btnDel) btnDel.addEventListener('click', executeDeleteNotif);

        var toastEl = document.getElementById('liveToast');
        var errorToastEl = document.getElementById('errorToast');
        if (toastEl) toastSuccess = new bootstrap.Toast(toastEl, { delay: 3000 });
        if (errorToastEl) toastError = new bootstrap.Toast(errorToastEl, { delay: 4000 });

        setupTableEvents();

        // Cargar si la sección está visible
        var section = document.getElementById('panelNotificacionesSection');
        if (section && section.style.display !== 'none') {
            loadGrupos();
            loadNotificaciones();
        }
    });

    // Exportar funciones para onclick inline
    window._editNotificacion = editNotificacion;
    window._confirmDeleteNotif = confirmDeleteNotif;

    window.openCreateNotifModal = openCreateNotifModal;
    window.openCreateGrupoModal = openCreateGrupoModal;
    window.deleteGrupoFromModal = deleteGrupoFromModal;
    window.loadNotificaciones = loadNotificaciones;
    window.loadGrupos = loadGrupos;
    window.copiarSlugNotif = copiarSlugNotif;
})();
