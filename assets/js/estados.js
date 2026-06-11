/**
 * Estados - Configuración de Estados Dinámicos
 * Árbol jerárquico, CRUD, modales, integración con plantillas.
 */
(function () {
    'use strict';

    var basePath = (window.BASE_PATH || window.APP_BASE_PATH || '').replace(/\/$/, '') || '';
    var apiBase = window.APP_API_BASE || (basePath ? basePath + '/api/' : '/api/');
    var treeData = { primer_ingreso: [], re_ingreso: [] };
    var offcanvasEstado = null;
    var modalEliminar = null;
    var eliminarId = null;
    var toastSuccess = null;
    var toastError = null;

    // ═══════════════════════════════════════════════════════
    //  CARGA DE DATOS
    // ═══════════════════════════════════════════════════════

    function loadTree() {
        var tp = document.getElementById('tbodyPrimerIngreso');
        var tr = document.getElementById('tbodyReIngreso');
        if (tp) tp.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Cargando...</td></tr>';
        if (tr) tr.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Cargando...</td></tr>';

        fetch(apiBase + 'api_estados.php?action=tree')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) { showError(data.message || 'Error al cargar'); return; }
                treeData = data;
                renderTable('tbodyPrimerIngreso', data.primer_ingreso || []);
                renderTable('tbodyReIngreso', data.re_ingreso || []);

                var total = (data.primer_ingreso || []).length + (data.re_ingreso || []).length;
                (data.primer_ingreso || []).forEach(function (p) { total += (p.hijos || []).length; });
                (data.re_ingreso || []).forEach(function (p) { total += (p.hijos || []).length; });
                var kpi = document.getElementById('kpiTotalEstados');
                if (kpi) kpi.textContent = total;
            })
            .catch(function () { showError('Error de conexión'); });
    }

    function renderTable(tbodyId, padres) {
        var tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        if (!padres || padres.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Sin estados configurados</td></tr>';
            return;
        }
        var html = '';
        padres.forEach(function (p) {
            html += renderRow(p, 0);
            (p.hijos || []).forEach(function (h) {
                html += renderRow(h, 1, p.id);
            });
        });
        tbody.innerHTML = html;
    }

    function renderRow(e, nivel, parentId) {
        var indent = nivel > 0 ? 'ps-4' : '';
        var expandIcon = '';
        var isParent = (e.hijos && e.hijos.length > 0);
        var hasReingreso = e.habilitar_reingreso && !e.parent_id;
        var reingresoBadge = hasReingreso
            ? ' <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25 rounded-pill" style="font-size: 0.65rem;" title="Habilitado para reingreso"><i class="bi bi-arrow-repeat me-1"></i>Reingreso</span>'
            : '';
        var parentRowId = parentId ? ' data-parent="' + parentId + '"' : '';

        if (isParent) {
            expandIcon = '<button class="btn btn-sm btn-link text-muted p-0 me-1 toggle-hijos" data-id="' + e.id + '" title="Expandir/Colapsar subestados" style="text-decoration:none;line-height:1;">' +
                '<i class="bi bi-chevron-down" id="caret_' + e.id + '"></i></button>';
        }

        var tplBadge = e.plantilla_id
            ? '<a href="plantillas?highlight=' + e.plantilla_id + '" class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill text-decoration-none" style="font-size: 0.7rem;" title="Ver plantilla"><i class="bi bi-chat-dots me-1"></i>Ver</a>'
            : '<span class="badge bg-secondary bg-opacity-10 text-muted border border-secondary border-opacity-25 rounded-pill" style="font-size: 0.7rem;">Sin plantilla</span>';

        var isSub = nivel > 0;

        var html = '<tr class="estado-row' + (isSub ? ' estado-sub' : '') + (isSub && parentId ? ' sub-' + parentId : '') + '"' + parentRowId + '>';
        html += '<td class="' + indent + '">' + expandIcon + (isSub ? '<span class="text-muted small ms-2">└</span>' : '') + '</td>';
        html += '<td><span class="color-dot" style="background-color:' + escAttr(e.color) + ';" title="' + escAttr(e.color) + '"></span></td>';
        html += '<td>';
        html += '<div class="fw-medium text-white">' + escHtml(e.nombre) + reingresoBadge + '</div>';
        if (e.descripcion) html += '<div class="small text-muted">' + escHtml(e.descripcion) + '</div>';
        html += '</td>';
        html += '<td><code class="text-info small" style="font-size: 0.75rem;">' + escHtml(e.slug || '—') + '</code>' +
            '<button class="btn btn-sm btn-link text-muted p-0 ms-2 copy-slug" data-slug="' + escAttr(e.slug || '') + '" title="Copiar slug"><i class="bi bi-copy" style="font-size: 0.7rem;"></i></button></td>';
        html += '<td>' + tplBadge + '</td>';
        html += '<td>';
        html += '<div class="d-flex gap-1">';
        html += '<button class="btn btn-sm btn-action bg-primary bg-opacity-10 text-primary border-0 rounded-2" onclick="window._editEstado(\'' + e.id + '\')" title="Editar"><i class="bi bi-pencil"></i></button>';
        if (isParent) {
            html += '<button class="btn btn-sm btn-action bg-success bg-opacity-10 text-success border-0 rounded-2" onclick="window._openCreateModal(\'' + e.id + '\')" title="Agregar subestado"><i class="bi bi-plus-lg"></i></button>';
        }
        html += '<button class="btn btn-sm btn-action bg-danger bg-opacity-10 text-danger border-0 rounded-2" onclick="window._confirmDelete(\'' + e.id + '\',\'' + escAttr(e.nombre) + '\',\'' + escAttr(e.slug || '') + '\',' + (e.hijos ? e.hijos.length : 0) + ')" title="Eliminar"><i class="bi bi-trash"></i></button>';
        html += '</div>';
        html += '</td>';
        html += '</tr>';
        return html;
    }

    // ═══════════════════════════════════════════════════════
    //  MODAL CREAR / EDITAR
    // ═══════════════════════════════════════════════════════

    function openCreateModal(parentId) {
        parentId = parentId || null;
        document.getElementById('ef_id').value = '';
        document.getElementById('ef_parent_id').value = parentId || '';
        document.getElementById('modalEstadoTitle').innerHTML = parentId
            ? '<i class="bi bi-plus-circle me-2"></i>Nuevo Subestado'
            : '<i class="bi bi-plus-circle me-2"></i>Nuevo Estado';
        document.getElementById('ef_nombre').value = '';
        document.getElementById('ef_descripcion').value = '';
        document.getElementById('ef_color').value = '#3b82f6';
        document.getElementById('ef_color_hex').textContent = '#3b82f6';
        document.getElementById('ef_habilitar_reingreso').checked = false;
        document.getElementById('ef_seleccionable').checked = true;
        document.getElementById('ef_slug_wrap').style.display = 'none';
        document.getElementById('ef_error').classList.add('d-none');
        document.getElementById('ef_guardar_btn').innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar';
        document.getElementById('ef_guardar_btn').disabled = false;

        if (parentId) {
            document.getElementById('ef_tipo_wrap').style.display = 'none';
            document.getElementById('ef_color_wrap').style.display = 'none';
            document.getElementById('ef_reingreso_wrap').style.display = 'none';
            // Los subestados siempre son seleccionables (son la selección final)
            document.getElementById('ef_seleccionable_wrap').style.display = 'none';
            document.getElementById('ef_tipo_help').textContent = 'Hereda tipo y color del estado padre';
            document.getElementById('ef_tipo_help').style.display = 'block';
        } else {
            document.getElementById('ef_tipo_wrap').style.display = '';
            document.getElementById('ef_color_wrap').style.display = '';
            document.getElementById('ef_reingreso_wrap').style.display = '';
            document.getElementById('ef_seleccionable_wrap').style.display = '';
            document.getElementById('ef_tipo_help').style.display = 'none';
            onTipoChange();
        }

        loadTemplatesForSelect().then(function () {
            if (offcanvasEstado) offcanvasEstado.show();
        });
    }

    function editEstado(id) {
        fetch(apiBase + 'api_estados.php?action=get&id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) { showError(data.message); return; }
                var e = data.estado;
                var isSub = !!e.parent_id;

                document.getElementById('ef_id').value = e.id;
                document.getElementById('ef_parent_id').value = e.parent_id || '';
                document.getElementById('modalEstadoTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Editar Estado';
                document.getElementById('ef_nombre').value = e.nombre || '';
                document.getElementById('ef_descripcion').value = e.descripcion || '';
                document.getElementById('ef_color').value = e.color || '#94a3b8';
                document.getElementById('ef_color_hex').textContent = e.color || '#94a3b8';
                document.getElementById('ef_tipo').value = e.tipo || 'primer_ingreso';
                document.getElementById('ef_habilitar_reingreso').checked = !!e.habilitar_reingreso;
                // seleccionable: true por defecto si el campo no existe aún en la DB
                document.getElementById('ef_seleccionable').checked = (e.seleccionable !== false && e.seleccionable !== 0);
                document.getElementById('ef_plantilla_id').value = e.plantilla_id || '';
                document.getElementById('ef_error').classList.add('d-none');
                document.getElementById('ef_guardar_btn').innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar Cambios';
                document.getElementById('ef_guardar_btn').disabled = false;

                // Slug visible en edición
                document.getElementById('ef_slug_wrap').style.display = '';
                document.getElementById('ef_slug_display').textContent = e.slug || '—';

                if (isSub) {
                    document.getElementById('ef_tipo_wrap').style.display = 'none';
                    document.getElementById('ef_color_wrap').style.display = 'none';
                    document.getElementById('ef_reingreso_wrap').style.display = 'none';
                    // Los subestados siempre son seleccionables
                    document.getElementById('ef_seleccionable_wrap').style.display = 'none';
                } else {
                    document.getElementById('ef_tipo_wrap').style.display = '';
                    document.getElementById('ef_color_wrap').style.display = '';
                    document.getElementById('ef_reingreso_wrap').style.display = '';
                    document.getElementById('ef_seleccionable_wrap').style.display = '';
                    onTipoChange();
                }

                loadTemplatesForSelect(e.plantilla_id).then(function () {
                    if (offcanvasEstado) offcanvasEstado.show();
                });
            })
            .catch(function () { showError('Error de conexión'); });
    }

    function onTipoChange() {
        var tipo = document.getElementById('ef_tipo').value;
        var wrap = document.getElementById('ef_reingreso_wrap');
        if (tipo === 'primer_ingreso') {
            if (wrap) wrap.style.display = '';
        } else {
            if (wrap) wrap.style.display = 'none';
            document.getElementById('ef_habilitar_reingreso').checked = false;
        }
    }

    function loadTemplatesForSelect(selectedId) {
        selectedId = selectedId || null;
        return fetch(apiBase + 'api_estados.php?action=list_templates')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var sel = document.getElementById('ef_plantilla_id');
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

    function saveEstado(e) {
        e.preventDefault();
        var form = document.getElementById('formEstado');
        var fd = new FormData(form);
        fd.append('action', 'save');
        // Asegurar que seleccionable siempre se envíe (FormData no incluye checkboxes desmarcados)
        var chkSel = document.getElementById('ef_seleccionable');
        if (chkSel) {
            fd.set('seleccionable', chkSel.checked ? '1' : '0');
        }

        var btn = document.getElementById('ef_guardar_btn');
        var errorEl = document.getElementById('ef_error');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';
        errorEl.classList.add('d-none');

        fetch(apiBase + 'api_estados.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    errorEl.textContent = data.message || 'Error al guardar';
                    errorEl.classList.remove('d-none');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar';
                    return;
                }
                if (offcanvasEstado) offcanvasEstado.hide();
                showToast(data.message || 'Guardado correctamente');
                loadTree();
            })
            .catch(function () {
                errorEl.textContent = 'Error de conexión';
                errorEl.classList.remove('d-none');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar';
            });
    }

    // ═══════════════════════════════════════════════════════
    //  ELIMINAR
    // ═══════════════════════════════════════════════════════

    function confirmDelete(id, nombre, slug, subCount) {
        eliminarId = id;
        document.getElementById('del_nombre').textContent = nombre;
        document.getElementById('del_slug').textContent = slug;
        document.getElementById('del_subestados').textContent = subCount;
        if (modalEliminar) modalEliminar.show();
    }

    function executeDelete() {
        if (!eliminarId) return;
        var fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', eliminarId);

        fetch(apiBase + 'api_estados.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (modalEliminar) modalEliminar.hide();
                if (!data.ok) { showError(data.message || 'Error al eliminar'); return; }
                showToast('Estado eliminado correctamente');
                eliminarId = null;
                loadTree();
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

    function copiarSlug() {
        var slug = document.getElementById('ef_slug_display').textContent;
        if (!slug || slug === '—') return;
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
            // Toggle subestados
            var toggle = e.target.closest('.toggle-hijos');
            if (toggle) {
                var id = toggle.getAttribute('data-id');
                var caret = document.getElementById('caret_' + id);
                var subs = document.querySelectorAll('.sub-' + id);
                var expanded = caret && caret.classList.contains('bi-chevron-up');
                subs.forEach(function (row) {
                    row.style.display = expanded ? 'none' : '';
                });
                if (caret) {
                    caret.classList.toggle('bi-chevron-down', expanded);
                    caret.classList.toggle('bi-chevron-up', !expanded);
                }
                return;
            }

            // Copiar slug
            var copy = e.target.closest('.copy-slug');
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
        var offcanvasEl = document.getElementById('offcanvasEstado');
        var modalDelEl = document.getElementById('modalEliminar');
        if (offcanvasEl) offcanvasEstado = new bootstrap.Offcanvas(offcanvasEl);
        if (modalDelEl) modalEliminar = new bootstrap.Modal(modalDelEl);

        var form = document.getElementById('formEstado');
        if (form) form.addEventListener('submit', saveEstado);

        var btnDel = document.getElementById('btnConfirmarEliminar');
        if (btnDel) btnDel.addEventListener('click', executeDelete);

        var colorInput = document.getElementById('ef_color');
        if (colorInput) {
            colorInput.addEventListener('input', function () {
                document.getElementById('ef_color_hex').textContent = this.value;
            });
        }

        var toastEl = document.getElementById('liveToast');
        var errorToastEl = document.getElementById('errorToast');
        if (toastEl) toastSuccess = new bootstrap.Toast(toastEl, { delay: 3000 });
        if (errorToastEl) toastError = new bootstrap.Toast(errorToastEl, { delay: 4000 });

        setupTableEvents();
        loadTree();
    });

    // Exportar funciones para onclick inline
    window._editEstado = editEstado;
    window._openCreateModal = openCreateModal;
    window._confirmDelete = confirmDelete;

    window.openCreateModal = openCreateModal;
    window.copiarSlug = copiarSlug;
    window.onTipoChange = onTipoChange;
    window.loadTemplates = function () { loadTemplatesForSelect(null); };
})();
