/**
 * Panel Offcanvas - Formularios, búsqueda de clientes y offcanvas.
 * Requiere: panel-utils.js, Bootstrap 5, window.PANEL_DATA
 */
(function () {
    'use strict';

    const datosMarcas = (window.PANEL_DATA && window.PANEL_DATA.datosMarcas) ? window.PANEL_DATA.datosMarcas : {};
    const escapeHtml = window.escapeHtml || function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); };

    let activeOffcanvas = null;
    let clienteSearchTimer = null;
    let clienteSeleccionado = null;

    function poblarSelect(selectId, opciones, valorSeleccionado) {
        valorSeleccionado = valorSeleccionado || null;
        const sel = document.getElementById(selectId);
        if (!sel) return;
        const existingNewOption = sel.querySelector('option[value="__NEW__"]');
        const newOption = existingNewOption ? existingNewOption.cloneNode(true) : null;
        const valorExiste = valorSeleccionado && opciones.includes(valorSeleccionado);
        sel.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.disabled = true;
        placeholder.selected = !valorExiste;
        placeholder.innerText = 'Selecciona...';
        sel.appendChild(placeholder);
        opciones.forEach(function (opt) {
            const el = document.createElement('option');
            el.value = opt;
            el.innerText = opt;
            if (opt === valorSeleccionado) el.selected = true;
            sel.appendChild(el);
        });
        if (newOption) sel.appendChild(newOption);
    }

    function handleMarcaChange(select, prefix) {
        const val = select.value;
        const idField = document.getElementById(prefix + '_equipo_marca_id');
        const finalField = document.getElementById(prefix + '_marca_final');
        if (val === '__NEW__') {
            toggleInput(prefix, 'marca', true);
            toggleInput(prefix, 'modelo', true);
            if (idField) idField.value = '';
            if (finalField) finalField.value = '';
            const inp = document.getElementById(prefix + '_marca_input');
            if (inp) inp.focus();
        } else {
            toggleInput(prefix, 'marca', false);
            const opt = select.options[select.selectedIndex];
            const nombre = opt ? opt.textContent.trim() : '';
            if (idField) idField.value = val;
            if (finalField) finalField.value = nombre;
            const modelos = datosMarcas[nombre] || [];
            toggleInput(prefix, 'modelo', false);
            poblarSelect(prefix + '_modelo_select', modelos);
        }
    }

    function handleModeloChange(select, prefix) {
        const val = select.value;
        if (val === '__NEW__') toggleInput(prefix, 'modelo', true);
        else {
            toggleInput(prefix, 'modelo', false);
            const final = document.getElementById(prefix + '_modelo_final');
            if (final) final.value = val;
        }
    }

    function toggleInput(prefix, field, showInput) {
        const selCont = document.getElementById(prefix + '_' + field + '_select_container');
        const inpCont = document.getElementById(prefix + '_' + field + '_input_container');
        const inp = document.getElementById(prefix + '_' + field + '_input');
        const final = document.getElementById(prefix + '_' + field + '_final');
        if (showInput) {
            if (selCont) selCont.classList.add('d-none');
            if (inpCont) inpCont.classList.remove('d-none');
            if (inp) { inp.value = ''; inp.focus(); }
            if (final) final.value = '';
        } else {
            if (selCont) selCont.classList.remove('d-none');
            if (inpCont) inpCont.classList.add('d-none');
            if (inp) inp.value = '';
            if (final) final.value = '';
        }
    }

    function resetToSelect(prefix, field) {
        toggleInput(prefix, field, false);
        const sel = document.getElementById(prefix + '_' + field + '_select');
        const final = document.getElementById(prefix + '_' + field + '_final');
        if (sel) sel.selectedIndex = 0;
        if (final) final.value = '';
    }

    function prepareForm(prefix) {
        var idField = document.getElementById(prefix + '_equipo_marca_id');
        if (idField && idField.value) {
            var sel = document.getElementById(prefix + '_marca_select');
            if (sel && sel.value && sel.value !== '__NEW__') {
                var finalMarca = document.getElementById(prefix + '_marca_final');
                if (finalMarca) finalMarca.value = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].textContent.trim() : '';
            }
        }
        ['marca', 'modelo'].forEach(function (c) {
            const inpCont = document.getElementById(prefix + '_' + c + '_input_container');
            const isInput = inpCont && !inpCont.classList.contains('d-none');
            const final = document.getElementById(prefix + '_' + c + '_final');
            if (isInput) {
                const inp = document.getElementById(prefix + '_' + c + '_input');
                if (final && inp) final.value = inp.value;
            } else {
                const sel = document.getElementById(prefix + '_' + c + '_select');
                if (sel && sel.value && sel.value !== '__NEW__' && final) {
                    if (c === 'marca') {
                        final.value = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].textContent.trim() : sel.value;
                        if (idField) idField.value = sel.value;
                    } else {
                        final.value = sel.value;
                    }
                }
            }
        });
        return true;
    }

    function insertTemplateVar(badge, targetId) {
        const textarea = document.getElementById(targetId);
        if (!textarea) return;
        const tag = badge.innerText;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        textarea.value = text.substring(0, start) + ' ' + tag + ' ' + text.substring(end);
        textarea.focus();
        textarea.selectionEnd = start + tag.length + 2;
    }

    function insertTag(tag) {
        const textarea = document.getElementById('msg_texto');
        if (!textarea) return;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        textarea.value = text.substring(0, start) + tag + text.substring(end);
        textarea.focus();
        textarea.selectionEnd = start + tag.length;
    }

    function abrirOffcanvas(id) {
        const el = document.getElementById(id);
        if (!el) return;
        if (activeOffcanvas && activeOffcanvas !== el) {
            const existing = bootstrap.Offcanvas.getInstance(activeOffcanvas);
            if (existing) existing.hide();
        }
        const off = new bootstrap.Offcanvas(el);
        off.show();
        activeOffcanvas = el;
    }

    function setupClienteSearch() {
        const input = document.getElementById('create_cliente_search');
        const results = document.getElementById('create_cliente_results');
        if (!input || !results) return;

        input.addEventListener('input', function () {
            clearTimeout(clienteSearchTimer);
            var q = input.value.trim();
            var nombreField = document.getElementById('create_nombre');
            if (nombreField) nombreField.value = q.toLocaleUpperCase('es-MX');

            if (q.length < 2) {
                results.classList.add('d-none');
                return;
            }
            clienteSearchTimer = setTimeout(function () {
                fetch((window.APP_API_BASE || 'api/') + 'api_clientes.php?action=search&q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        const items = (data.ok && Array.isArray(data.items)) ? data.items : [];
                        let html = '';
                        if (items.length) {
                            html += items.map(function (c) {
                                var nom = escapeHtml(c.nombre || '').replace(/'/g, '&#39;');
                                var ape = escapeHtml(c.apellido || '').replace(/'/g, '&#39;');
                                var tel = escapeHtml(c.telefono || '').replace(/'/g, '&#39;');
                                return '<div class="p-2 px-3 d-flex align-items-center gap-2 cliente-search-item" style="cursor:pointer;border-bottom:1px solid rgba(255,255,255,0.05);" data-id="' + c.id + '" data-nombre="' + nom + '" data-apellido="' + ape + '" data-telefono="' + tel + '">'
                                    + '<div class="avatar-circle" style="width:30px;height:30px;font-size:0.6rem;">' + escapeHtml((c.nombre[0] || '') + (c.apellido[0] || '')) + '</div>'
                                    + '<div><div class="fw-bold text-white small">' + escapeHtml(c.nombre + ' ' + c.apellido) + '</div>'
                                    + '<div class="text-muted" style="font-size:0.75rem"><i class="bi bi-whatsapp me-1 text-success"></i>' + escapeHtml(c.telefono) + ' · ' + (c.total_equipos || 0) + ' equipos</div></div></div>';
                            }).join('');
                        }
                        var safeQForAttr = escapeHtml(q).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                        html += '<div class="p-2 px-3 text-info small cliente-search-new" style="cursor:pointer;" data-nombre="' + safeQForAttr + '">'
                             + '<i class="bi bi-person-plus me-2"></i>Crear nuevo cliente "' + escapeHtml(q) + '"'
                             + '</div>';
                        results.innerHTML = html;
                        results.classList.remove('d-none');
                    })
                    .catch(function () { results.classList.add('d-none'); });
            }, 250);
        });

        input.addEventListener('blur', function () {
            setTimeout(function () { results.classList.add('d-none'); }, 200);
        });
        input.addEventListener('focus', function () {
            if (input.value.trim().length >= 2) input.dispatchEvent(new Event('input'));
        });

        results.addEventListener('click', function (e) {
            var item = e.target.closest('.cliente-search-item');
            if (item) {
                seleccionarCliente(
                    parseInt(item.getAttribute('data-id'), 10),
                    item.getAttribute('data-nombre') || '',
                    item.getAttribute('data-apellido') || '',
                    item.getAttribute('data-telefono') || ''
                );
                return;
            }
            var newItem = e.target.closest('.cliente-search-new');
            if (newItem) {
                seleccionarNuevoCliente(newItem.getAttribute('data-nombre') || '');
            }
        });
    }

    function seleccionarCliente(id, nombre, apellido, telefono) {
        clienteSeleccionado = { id: id, nombre: nombre, apellido: apellido, telefono: telefono };
        document.getElementById('create_cliente_id').value = id;
        document.getElementById('create_cliente_selected_name').textContent = nombre + ' ' + apellido;
        document.getElementById('create_cliente_selected_phone').innerHTML = '<i class="bi bi-whatsapp me-1 text-success"></i>' + telefono;
        document.getElementById('create_cliente_search_wrap').classList.add('d-none');
        document.getElementById('create_cliente_selected').classList.remove('d-none');
        var tw = document.getElementById('create_telefono_wrap');
        if (tw) tw.classList.add('d-none');
        var nombreField = document.getElementById('create_nombre');
        var telefonoField = document.getElementById('create_telefono');
        if (nombreField) nombreField.value = (nombre + ' ' + apellido).toLocaleUpperCase('es-MX');
        if (telefonoField) {
            var cleanPhone = telefono.replace(/^52/, '').replace(/^\+?1/, '');
            telefonoField.value = cleanPhone.slice(-10);
        }
        document.getElementById('create_cliente_results').classList.add('d-none');
        document.getElementById('create_cliente_search').value = '';
    }

    function desvincularCliente() {
        clienteSeleccionado = null;
        document.getElementById('create_cliente_id').value = '';
        document.getElementById('create_cliente_selected').classList.add('d-none');
        document.getElementById('create_cliente_search_wrap').classList.remove('d-none');
        var tw = document.getElementById('create_telefono_wrap');
        if (tw) tw.classList.remove('d-none');
    }

    function resetClienteForm() {
        clienteSeleccionado = null;
        document.getElementById('create_cliente_id').value = '';
        document.getElementById('create_cliente_selected').classList.add('d-none');
        document.getElementById('create_cliente_search_wrap').classList.remove('d-none');
        document.getElementById('create_cliente_search').value = '';
        var tw = document.getElementById('create_telefono_wrap');
        if (tw) tw.classList.remove('d-none');
    }

    function seleccionarNuevoCliente(nombreCompleto) {
        clienteSeleccionado = null;
        document.getElementById('create_cliente_id').value = '';
        var results = document.getElementById('create_cliente_results');
        if (results) results.classList.add('d-none');
        document.getElementById('create_cliente_search').value = '';
        // Pre-llenar el modal con el nombre buscado
        var ncNombre = document.getElementById('nc_nombre');
        if (ncNombre) ncNombre.value = String(nombreCompleto).toLocaleUpperCase('es-MX');
        var ncApellido = document.getElementById('nc_apellido');
        if (ncApellido) ncApellido.value = '';
        var ncTelefono = document.getElementById('nc_telefono');
        if (ncTelefono) ncTelefono.value = '';
        var ncError = document.getElementById('nc_error');
        if (ncError) ncError.classList.add('d-none');
        // Abrir modal encima del offcanvas
        var modalEl = document.getElementById('modalNuevoCliente');
        if (modalEl) {
            var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();
        }
    }

    function guardarNuevoCliente() {
        var nombre = (document.getElementById('nc_nombre').value || '').trim();
        var apellido = (document.getElementById('nc_apellido').value || '').trim();
        var telefono = (document.getElementById('nc_telefono').value || '').trim();
        var lada = (document.getElementById('nc_lada').value || '52').trim();
        var btn = document.getElementById('nc_guardar_btn');
        var errorEl = document.getElementById('nc_error');

        if (!nombre || !apellido || !telefono) {
            if (errorEl) { errorEl.textContent = 'Nombre, apellido y teléfono son requeridos.'; errorEl.classList.remove('d-none'); }
            return;
        }
        if (telefono.length < 10) {
            if (errorEl) { errorEl.textContent = 'El teléfono debe tener al menos 10 dígitos.'; errorEl.classList.remove('d-none'); }
            return;
        }
        if (errorEl) errorEl.classList.add('d-none');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...'; }

        var fd = new FormData();
        fd.append('action', 'quick_create');
        fd.append('nombre', nombre);
        fd.append('apellido', apellido);
        fd.append('telefono', telefono);
        fd.append('lada', lada);
        fd.append('correo', '');

        fetch((window.APP_API_BASE || 'api/') + 'api_clientes.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    if (errorEl) { errorEl.textContent = data.message || 'Error al crear cliente'; errorEl.classList.remove('d-none'); }
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar y Vincular'; }
                    return;
                }
                // Cerrar modal y vincular al form principal
                var modalEl = document.getElementById('modalNuevoCliente');
                if (modalEl) { var m = bootstrap.Modal.getInstance(modalEl); if (m) m.hide(); }
                seleccionarCliente(
                    data.id,
                    nombre,
                    apellido,
                    lada + telefono
                );
            })
            .catch(function () {
                if (errorEl) { errorEl.textContent = 'Error de conexión. Intenta de nuevo.'; errorEl.classList.remove('d-none'); }
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar y Vincular'; }
            });
    }

    function abrirOffcanvasCrear() {
        var equiposMarcas = (window.PANEL_DATA && window.PANEL_DATA.equiposMarcas) || [];
        if (!equiposMarcas.length) {
            var marcas = Object.keys(datosMarcas).sort();
            poblarSelect('create_marca_select', marcas);
        }
        resetToSelect('create', 'marca');
        resetToSelect('create', 'modelo');
        var createIdField = document.getElementById('create_equipo_marca_id');
        if (createIdField) createIdField.value = '';
        resetClienteForm();
        abrirOffcanvas('offcanvasCrear');
    }

    function aplicarMarcaModeloEditar(marca, modelo, equipoMarcaId) {
        var setVal = function (id, v) { var el = document.getElementById(id); if (el) el.value = v == null ? '' : String(v); };
        var editMarcaSelect = document.getElementById('edit_marca_select');
        var equiposMarcas = (window.PANEL_DATA && window.PANEL_DATA.equiposMarcas) || [];
        setVal('edit_equipo_marca_id', equipoMarcaId || '');
        if (equipoMarcaId && editMarcaSelect) {
            var found = false;
            for (var i = 0; i < editMarcaSelect.options.length; i++) {
                if (editMarcaSelect.options[i].value === String(equipoMarcaId)) {
                    editMarcaSelect.selectedIndex = i;
                    found = true;
                    break;
                }
            }
            if (found) {
                toggleInput('edit', 'marca', false);
                setVal('edit_marca_final', marca || editMarcaSelect.options[editMarcaSelect.selectedIndex].textContent.trim());
                var modelos = datosMarcas[marca] || [];
                poblarSelect('edit_modelo_select', modelos, modelo);
                if (modelo && datosMarcas[marca] && datosMarcas[marca].includes(modelo)) {
                    toggleInput('edit', 'modelo', false);
                } else {
                    toggleInput('edit', 'modelo', true);
                    setVal('edit_modelo_input', modelo);
                }
                setVal('edit_modelo_final', modelo);
                return;
            }
        }
        var marcas = Object.keys(datosMarcas).sort();
        var marcaExiste = marca && marcas.includes(marca);
        var modeloExiste = marcaExiste && modelo && datosMarcas[marca] && datosMarcas[marca].includes(modelo);
        if (marcaExiste && editMarcaSelect) {
            for (var j = 0; j < editMarcaSelect.options.length; j++) {
                if (editMarcaSelect.options[j].textContent.trim() === marca) {
                    editMarcaSelect.selectedIndex = j;
                    setVal('edit_equipo_marca_id', editMarcaSelect.options[j].value);
                    break;
                }
            }
            toggleInput('edit', 'marca', false);
            if (modeloExiste) {
                poblarSelect('edit_modelo_select', datosMarcas[marca], modelo);
                toggleInput('edit', 'modelo', false);
            } else {
                toggleInput('edit', 'modelo', true);
                setVal('edit_modelo_input', modelo);
            }
        } else {
            toggleInput('edit', 'marca', true);
            toggleInput('edit', 'modelo', true);
            setVal('edit_marca_input', marca);
            setVal('edit_modelo_input', modelo);
        }
        setVal('edit_marca_final', marca);
        setVal('edit_modelo_final', modelo);
    }

    function abrirEditar(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_folio').value = data.folio_publico;
        document.getElementById('edit_ingresado_por').value = data.ingresado_por || '—';
        document.getElementById('edit_nombre').value = (data.cliente_nombre || '').toLocaleUpperCase('es-MX');
        document.getElementById('edit_falla').value = data.falla_reportada;
        var fullPhone = data.telefono || '';
        var ladaSelect = document.getElementById('edit_lada');
        var phoneInput = document.getElementById('edit_telefono');
        if (fullPhone.startsWith('+1')) { if (ladaSelect) ladaSelect.value = '+1'; if (phoneInput) phoneInput.value = fullPhone.substring(2); }
        else { if (ladaSelect) ladaSelect.value = '52'; if (phoneInput) phoneInput.value = fullPhone.replace('521', '').replace('52', '').replace('+', ''); }
        aplicarMarcaModeloEditar(data.equipo_marca || '', data.equipo_modelo || '', data.equipo_marca_id || null);
        abrirOffcanvas('offcanvasEditar');
    }

    function abrirMensaje(data) {
        document.getElementById('msg_id').value = data.id;
        document.getElementById('msg_folio').value = data.folio_publico;
        document.getElementById('msg_nombre').value = data.cliente_nombre;
        document.getElementById('msg_telefono').value = data.telefono;
        var mod = (data.equipo_marca ? data.equipo_marca + ' ' : '') + (data.equipo_modelo || '');
        document.getElementById('msg_modelo').value = mod;
        document.getElementById('msg_fecha').value = data.fecha_ingreso;
        document.getElementById('msg_texto').value = '';
        document.getElementById('disp_folio').innerText = '#' + data.folio_publico;
        document.getElementById('disp_nombre').innerText = data.cliente_nombre;
        document.getElementById('disp_telefono').innerText = data.telefono;
        document.getElementById('disp_modelo').innerText = mod;
        abrirOffcanvas('offcanvasMensaje');
    }

    function verHistorial(id, folio) {
        document.getElementById('hist_folio').innerText = folio;
        document.getElementById('loaderHistorial').style.display = 'block';
        document.getElementById('timelineContainer').innerHTML = '';
        abrirOffcanvas('offcanvasHistorial');
        fetch((window.APP_API_BASE || 'api/') + 'api_history.php?id=' + id)
            .then(function (response) { return response.json(); })
            .then(function (data) {
                document.getElementById('loaderHistorial').style.display = 'none';
                var container = document.getElementById('timelineContainer');
                var items = data.data || data;
                if (!items.length) { container.innerHTML = '<div class="text-center text-muted p-4"><i class="bi bi-chat-square-dots fs-1 mb-2 d-block opacity-25"></i>Sin historial</div>'; return; }
                var html = '';
                items.forEach(function (item) {
                    var detalleError = '';
                    if (item.estado_envio === 'fallido' && item.respuesta_api) detalleError = '<div class="mt-2 p-2 bg-danger bg-opacity-10 rounded small text-danger font-monospace text-break">' + item.respuesta_api + '</div>';
                    var detalleContenido = '';
                    if (item.contenido_mensaje) detalleContenido = '<div class="mt-2 p-3 rounded-3 small" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);"><div class="text-info fw-bold mb-1" style="font-size: 0.75rem;">MENSAJE ENVIADO:</div><div style="white-space: pre-wrap; color: #cbd5e1;">' + item.contenido_mensaje + '</div></div>';
                    var enviadoBadge = item.enviado_por ? '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="font-size: 0.7rem;"><i class="bi bi-person-fill me-1"></i>' + item.enviado_por + '</span>' : '<span class="badge bg-white bg-opacity-10 text-muted border border-secondary border-opacity-25" style="font-size: 0.7rem;"><i class="bi bi-hdd-rack me-1"></i>Auto</span>';
                    html += '<div class="history-item"><div class="history-icon ' + (item.badge_class ? item.badge_class.replace('bg-opacity-25', '') : '') + ' text-white shadow-sm"><i class="bi ' + (item.icon || '') + '"></i></div><div class="d-flex justify-content-between align-items-center mb-1"><span class="badge ' + (item.badge_class || '') + ' rounded-pill border-0">' + item.texto_estado + '</span><div class="d-flex align-items-center gap-2">' + enviadoBadge + '<small class="text-muted font-monospace" style="font-size: 0.7rem;">' + item.fecha_fmt + '</small></div></div><h6 class="mb-1 fw-bold text-light text-capitalize">' + (item.tipo_mensaje || '').replace(/_/g, ' ') + '</h6>' + detalleContenido + detalleError + '</div>';
                });
                container.innerHTML = html;
            })
            .catch(function () {
                document.getElementById('loaderHistorial').style.display = 'none';
                document.getElementById('timelineContainer').innerHTML = '<div class="text-danger text-center">Error de conexión</div>';
            });
    }

    window.poblarSelect = poblarSelect;
    window.handleMarcaChange = handleMarcaChange;
    window.handleModeloChange = handleModeloChange;
    window.toggleInput = toggleInput;
    window.resetToSelect = resetToSelect;
    window.prepareForm = prepareForm;
    window.insertTemplateVar = insertTemplateVar;
    window.insertTag = insertTag;
    window.abrirOffcanvas = abrirOffcanvas;
    window.abrirOffcanvasCrear = abrirOffcanvasCrear;
    window.abrirEditar = abrirEditar;
    window.abrirMensaje = abrirMensaje;
    window.verHistorial = verHistorial;
    window.setupClienteSearch = setupClienteSearch;
    window.seleccionarCliente = seleccionarCliente;
    window.desvincularCliente = desvincularCliente;
    window.seleccionarNuevoCliente = seleccionarNuevoCliente;
    window.guardarNuevoCliente = guardarNuevoCliente;
})();
