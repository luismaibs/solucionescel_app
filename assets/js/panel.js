/**
 * Panel de Taller (index) - Inicialización y wiring.
 * Requiere: panel-utils.js, panel-offcanvas.js, panel-reparaciones.js, Bootstrap 5
 */
(function () {
    'use strict';

    const datosMarcas = (window.PANEL_DATA && window.PANEL_DATA.datosMarcas) ? window.PANEL_DATA.datosMarcas : {};

    onModuleReady(function () {
        var pr = window.PanelReparaciones;
        if (!pr) return;

        var modalEl = document.getElementById('modalConfirmacion');
        var modalTipoListoEl = document.getElementById('modalSeleccionarTipoListo');
        var modalNuevoClienteEl = document.getElementById('modalNuevoCliente');
        if (modalEl) pr.modalConfirmacion = new bootstrap.Modal(modalEl);
        if (modalTipoListoEl) pr.modalSeleccionarTipoListo = new bootstrap.Modal(modalTipoListoEl);
        pr.modalSeleccionarTipoListoEstado = null;

        document.getElementById('modalHijoActions').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-hijo]');
            if (!btn) return;
            if (pr.modalSeleccionarTipoListoId === null) {
                if (pr.modalSeleccionarTipoListo) pr.modalSeleccionarTipoListo.hide();
                return;
            }
            var hijoSlug = btn.getAttribute('data-hijo');
            var id = pr.modalSeleccionarTipoListoId;
            var padreSlug = pr.modalSeleccionarTipoListoEstado || 'listo';
            pr.modalSeleccionarTipoListoId = null;
            pr.modalSeleccionarTipoListoEstado = null;
            if (pr.modalSeleccionarTipoListo) pr.modalSeleccionarTipoListo.hide();
            if (window.updateStatusConHijo) window.updateStatusConHijo(id, padreSlug, hijoSlug);
        });
        if (modalNuevoClienteEl) pr.modalNuevoCliente = new bootstrap.Modal(modalNuevoClienteEl);

        var formNuevoCliente = document.getElementById('formNuevoCliente');
        if (formNuevoCliente) {
            formNuevoCliente.addEventListener('submit', function (e) {
                e.preventDefault();
                if (window.guardarNuevoCliente) window.guardarNuevoCliente();
            });
        }

        var btnConfirm = document.getElementById('btnConfirmAction');
        if (btnConfirm) {
            btnConfirm.addEventListener('click', function () {
                if (pr.confirmCallback) pr.confirmCallback();
                if (pr.modalConfirmacion) pr.modalConfirmacion.hide();
            });
        }

        if (window.setupClienteSearch) window.setupClienteSearch();
        if (window.initPipelineToggle) window.initPipelineToggle();
        if (window.loadSubEstadosMap) window.loadSubEstadosMap();

        var formCrear = document.getElementById('formCrear');
        if (formCrear) {
            formCrear.addEventListener('submit', function (e) {
                if (window.prepareForm) window.prepareForm('create');
                var clienteIdField = document.getElementById('create_cliente_id');
                if (clienteIdField && clienteIdField.value) return;

                var nombreCompleto = (document.getElementById('create_nombre').value || '').trim();
                var telefono = (document.getElementById('create_telefono').value || '').trim();
                var lada = (document.getElementById('create_lada').value || '').trim();

                // Si hay telefono manualmente, crear cliente rapido
                if (nombreCompleto && telefono && telefono.length >= 10) {
                    e.preventDefault();
                    var partes = nombreCompleto.split(/\s+/);
                    var nombre = partes.shift() || '';
                    var apellido = partes.join(' ') || '';
                    var fd = new FormData();
                    fd.append('action', 'quick_create');
                    fd.append('nombre', nombre);
                    fd.append('apellido', apellido || nombre);
                    fd.append('telefono', telefono);
                    fd.append('lada', lada || '52');
                    fd.append('correo', '');
                    fetch((window.APP_API_BASE || 'api/') + 'api_clientes', { method: 'POST', body: fd })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (!data.ok) {
                                if (window.SCToast) window.SCToast.show(data.message || 'Error al crear cliente', 'error');
                                return;
                            }
                            if (clienteIdField) clienteIdField.value = data.id;
                            formCrear.submit();
                        })
                        .catch(function () {
                            if (window.SCToast) window.SCToast.show('Error de conexión', 'error');
                        });
                    return;
                }

                // Sin cliente ni telefono: mostrar advertencia
                e.preventDefault();
                if (window.SCToast) window.SCToast.show('Busca o crea un cliente antes de registrar el ingreso', 'error');
                var searchInput = document.getElementById('create_cliente_search');
                if (searchInput) searchInput.focus();
            });
        }

        var formEditar = document.getElementById('formEditar');
        if (formEditar) {
            formEditar.addEventListener('submit', function () {
                if (window.prepareForm) window.prepareForm('edit');
            });
        }

        var equiposMarcas = (window.PANEL_DATA && window.PANEL_DATA.equiposMarcas) || [];
        var createSel = document.getElementById('create_marca_select');
        if (createSel && equiposMarcas.length) {
            createSel.innerHTML = '';
            var ph = document.createElement('option');
            ph.value = '';
            ph.disabled = true;
            ph.selected = true;
            ph.textContent = 'Selecciona Marca...';
            createSel.appendChild(ph);
            equiposMarcas.forEach(function (em) {
                var opt = document.createElement('option');
                opt.value = em.id;
                opt.textContent = em.nombre;
                createSel.appendChild(opt);
            });
            var newOpt = document.createElement('option');
            newOpt.value = '__NEW__';
            newOpt.className = 'fw-bold text-info';
            newOpt.textContent = '+ Agregar nueva...';
            createSel.appendChild(newOpt);
        } else if (window.poblarSelect) {
            var marcas = Object.keys(datosMarcas).sort();
            window.poblarSelect('create_marca_select', marcas);
        }
        var editSel = document.getElementById('edit_marca_select');
        if (editSel && equiposMarcas.length) {
            editSel.innerHTML = '';
            var ph2 = document.createElement('option');
            ph2.value = '';
            ph2.disabled = true;
            ph2.selected = true;
            ph2.textContent = 'Selecciona Marca...';
            editSel.appendChild(ph2);
            equiposMarcas.forEach(function (em) {
                var opt = document.createElement('option');
                opt.value = em.id;
                opt.textContent = em.nombre;
                editSel.appendChild(opt);
            });
            var newOpt2 = document.createElement('option');
            newOpt2.value = '__NEW__';
            newOpt2.className = 'fw-bold text-info';
            newOpt2.textContent = '+ Agregar nueva...';
            editSel.appendChild(newOpt2);
        }

        if (window.setupSearchInputHandler) window.setupSearchInputHandler();
        if (window.setupCardsContainerClick) window.setupCardsContainerClick();
        if (window.setupResizeHandler) window.setupResizeHandler();

        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        dropdownElementList.forEach(function (el) { new bootstrap.Dropdown(el); });

        var liveToastEl = document.getElementById('liveToast');
        var errorToastEl = document.getElementById('errorToast');
        if (liveToastEl) window.toastSuccess = new bootstrap.Toast(liveToastEl);
        if (errorToastEl) window.toastError = new bootstrap.Toast(errorToastEl);

        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg') && window.toastSuccess) {
            var msg = 'Operación realizada correctamente';
            var msgs = { creado: 'Equipo ingresado exitosamente', editado: 'Información actualizada', estado_actualizado: 'Estado cambiado correctamente', inactivado: 'Inactivado', notificado_extra: 'Notificación enviada al cliente', mensaje_enviado: 'Mensaje personalizado enviado', eliminado: 'Registro eliminado correctamente', templates_saved: 'Plantillas actualizadas con éxito' };
            if (msgs[urlParams.get('msg')]) msg = msgs[urlParams.get('msg')];
            var msgEl = document.getElementById('toastMsg');
            if (msgEl) msgEl.innerText = msg;
            window.toastSuccess.show();
            window.history.replaceState({}, document.title, window.location.pathname);
        }
        if (urlParams.has('error') && window.toastError) {
            var errorMsg = urlParams.get('error') === 'duplicado' ? '⚠️ El Folio ya existe. Verifica los datos.' : (urlParams.get('error') === 'db_error' ? 'Error de conexión con la base de datos.' : 'Error desconocido');
            var errEl = document.getElementById('errorMsg');
            if (errEl) errEl.innerText = errorMsg;
            window.toastError.show();
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        if (window.loadReparaciones) window.loadReparaciones(1);
        if (window.loadNotifConfigCache) window.loadNotifConfigCache();
    });

    window.confirmLogout = function () {
        if (window.showConfirm) {
            window.showConfirm('Cerrar Sesión', '¿Estás seguro de que deseas salir?', function () {
                window.location.href = 'logout?logout=true';
            });
        } else {
            if (confirm('¿Estás seguro de que deseas salir?')) window.location.href = 'logout?logout=true';
        }
    };
})();
