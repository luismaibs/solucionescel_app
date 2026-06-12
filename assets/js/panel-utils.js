/**
 * Utilidades compartidas para el panel de reparaciones.
 * Usado por panel-offcanvas.js y panel-reparaciones.js.
 * Estados dinamicos cargados desde estados_config (api_estados?action=tree).
 */
(function () {
    'use strict';

    var escapeHtml = window.escapeHtml || function (str) {
        if (str == null) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    };

    function getInitials(nombre) {
        if (!nombre) return '';
        const parts = nombre.trim().split(/\s+/);
        const first = parts[0] ? parts[0][0] : '';
        const last = parts.length > 1 ? parts[parts.length - 1][0] : '';
        return (first + last).toUpperCase();
    }

    function calcularDias(fechaIngreso) {
        if (!fechaIngreso) return 0;
        const ingreso = new Date(fechaIngreso);
        if (isNaN(ingreso.getTime())) return 0;
        return Math.floor((new Date() - ingreso) / (1000 * 60 * 60 * 24));
    }

    // ── ESTADOS DINAMICOS ──

    var apiBase = (window.APP_API_BASE || 'api/');
    var estadosMap = null;
    var estadosReady = null;

    var legacyLabels = {
        en_taller: 'Laboratorio',
        listo: 'Listo',
        listo_sin_garantia: 'Listo',
        no_quedo: 'No Quedó',
        entregado: 'Entregado',
        garantia_activada: 'Proceso de revisión técnica',
        garantia_finalizada: 'Garantía exitosa',
        garantia_fallida: 'Garantía fallida',
        garantia_entregada: 'Garantía entregada',
        inactivo: 'Inactivo',
        ingresado: 'Laboratorio',
        diagnostico: 'Laboratorio',
        confirmacion_garantia: 'Proceso de revisión técnica'
    };

    var legacyColors = {
        en_taller: '#3b82f6',
        listo: '#4ade80',
        listo_sin_garantia: '#4ade80',
        no_quedo: '#f43f5e',
        entregado: '#475569',
        garantia_activada: '#0d9488',
        garantia_finalizada: '#10b981',
        garantia_fallida: '#dc2626',
        garantia_entregada: '#334155',
        inactivo: '#64748b',
        ingresado: '#3b82f6',
        diagnostico: '#3b82f6',
        confirmacion_garantia: '#0d9488'
    };

    function loadEstadosMap() {
        if (estadosReady) return estadosReady;
        estadosReady = new Promise(function (resolve) {
            fetch(apiBase + 'api_estados?action=tree')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        estadosReady = null;
                        resolve(null);
                        return;
                    }
                    estadosMap = {};
                    function walk(items) {
                        (items || []).forEach(function (p) {
                            var tieneHijos = p.hijos && p.hijos.length > 0;
                            estadosMap[p.slug] = {
                                id: p.id,
                                slug: p.slug,
                                nombre: p.nombre,
                                color: p.color || '#94a3b8',
                                tipo: p.tipo,
                                // Si tiene hijos: solo seleccionable si la DB lo dice explícitamente (true).
                                // Si no tiene hijos: seleccionable por defecto (a menos que la DB diga false).
                                seleccionable: tieneHijos
                                    ? (p.seleccionable === true)
                                    : (p.seleccionable !== false),
                                hijos: (p.hijos || []).map(function (h) {
                                    return {
                                        id: h.id,
                                        slug: h.slug,
                                        nombre: h.nombre,
                                        color: h.color || '#94a3b8'
                                    };
                                })
                            };
                            walk(p.hijos);
                        });
                    }
                    walk(data.primer_ingreso);
                    walk(data.re_ingreso);
                    estadosMap._primer_ingreso = (data.primer_ingreso || []).map(function (p) { return p.slug; });
                    estadosMap._re_ingreso = (data.re_ingreso || []).map(function (p) { return p.slug; });
                    resolve(estadosMap);
                })
                .catch(function () {
                    estadosReady = null;
                    resolve(null);
                });
        });
        return estadosReady;
    }

    function getEstadoLabel(estado, enGarantia, esMesAzulInactivado) {
        if (estado === 'inactivo' && esMesAzulInactivado) return 'Inactivo por Mes Azul';
        if (estado === 'inactivo') return 'Inactivo por entrega';
        if (estado === 'garantia_finalizada') return 'Garantía exitosa';

        if (estadosMap && estadosMap[estado]) {
            return estadosMap[estado].nombre;
        }
        return legacyLabels[estado] || estado;
    }

    function getEstadoColor(estado) {
        if (estadosMap && estadosMap[estado]) {
            return estadosMap[estado].color;
        }
        return legacyColors[estado] || '#94a3b8';
    }

    function getEstadoPadres(enGarantia) {
        if (!estadosMap) return [];
        var slugs = enGarantia ? estadosMap._re_ingreso : estadosMap._primer_ingreso;
        if (!slugs) return [];
        return slugs.map(function (s) { return estadosMap[s]; }).filter(Boolean);
    }

    function getEstadoHijos(parentSlug) {
        if (!estadosMap || !estadosMap[parentSlug]) return [];
        return estadosMap[parentSlug].hijos || [];
    }

    function getEstadoInfo(slug) {
        if (!estadosMap || !estadosMap[slug]) return null;
        return estadosMap[slug];
    }

    function getSubEstadosMap() {
        if (!estadosMap) return {};
        var map = {};
        Object.keys(estadosMap).forEach(function (slug) {
            if (slug.startsWith('_')) return;
            var entry = estadosMap[slug];
            if (entry.hijos && entry.hijos.length > 0) {
                map[slug] = entry.hijos;
            }
        });
        return map;
    }

    function getSubEstadosHtml(estadoSlug) {
        var hijos = getEstadoHijos(estadoSlug);
        if (!hijos.length) return '<span class="text-muted" style="font-size:0.75rem;">—</span>';
        return hijos.map(function (s) {
            return '<span class="badge rounded-pill px-2 py-0 me-1" style="background:' + (s.color || '#3b82f6') + '22;color:' + (s.color || '#3b82f6') + ';font-size:0.7rem;border:1px solid ' + (s.color || '#3b82f6') + '44;">' + window.escapeHtml(s.nombre) + '</span>';
        }).join(' ');
    }

    function actualizarModalSeleccionarHijo(parentSlug) {
        var titleEl = document.getElementById('modalHijoTitle');
        var subEl = document.getElementById('modalHijoSubtitle');
        var actionsEl = document.getElementById('modalHijoActions');
        if (!actionsEl) return;
        var padre = estadosMap && estadosMap[parentSlug];
        var padreNombre = padre ? padre.nombre : parentSlug;
        var hijos = getEstadoHijos(parentSlug);
        if (titleEl) titleEl.textContent = 'Seleccionar: ' + padreNombre;
        if (subEl) subEl.textContent = 'Elige una opcion para ' + padreNombre;
        actionsEl.innerHTML = hijos.map(function (h, i) {
            var extraClass = i === 0 ? ' modal-ios-row-selected' : '';
            var altClass = h.slug === 'sin_garantia' ? ' modal-ios-row-alt' : '';
            return '<button type="button" class="modal-ios-row' + extraClass + altClass + '" data-hijo="' + h.slug + '">' +
                '<span>' + window.escapeHtml(h.nombre) + '</span>' +
                '<i class="bi bi-check-circle-fill modal-ios-check"></i>' +
                '</button>';
        }).join('');
    }

    if (!window.escapeHtml) window.escapeHtml = escapeHtml;
    window.getInitials = getInitials;
    window.calcularDias = calcularDias;
    window.getEstadoLabelMobile = getEstadoLabel;
    window.getEstadoColorMobile = getEstadoColor;
    window.getEstadoLabel = getEstadoLabel;
    window.getEstadoColor = getEstadoColor;
    window.loadEstadosMap = loadEstadosMap;
    window.getEstadoInfo = getEstadoInfo;
    window.getEstadoPadres = getEstadoPadres;
    window.getEstadoHijos = getEstadoHijos;
    window.getSubEstadosMap = getSubEstadosMap;
    window.getSubEstadosHtml = getSubEstadosHtml;
    window.actualizarModalSeleccionarHijo = actualizarModalSeleccionarHijo;
})();
