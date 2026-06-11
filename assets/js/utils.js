/**
 * Utilidades compartidas para SolucionesCel.
 * escapeHtml, fmtDate, getEstadoColor, getEstadoLabel, SCToast
 */
(function () {
    'use strict';

    function escapeHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function fmtDate(d, format) {
        if (!d) return '';
        var dt = new Date(d);
        if (isNaN(dt.getTime())) return String(d);
        format = format || 'short';
        if (format === 'date') {
            return dt.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
        }
        if (format === 'datetime') {
            return dt.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + dt.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
        }
        if (format === 'relative') {
            var now = new Date();
            if (dt.toDateString() === now.toDateString()) return 'Hoy ' + dt.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
            var yesterday = new Date(now - 864e5);
            if (dt.toDateString() === yesterday.toDateString()) return 'Ayer ' + dt.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
            return dt.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' }) + ' ' + dt.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
        }
        return dt.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    var estadoColorMap = {
        en_taller: '#3b82f6',
        listo: '#4ade80', listo_sin_garantia: '#4ade80',
        no_quedo: '#f43f5e',
        entregado: '#475569',
        garantia_activada: '#0d9488',
        garantia_finalizada: '#10b981',
        garantia_fallida: '#dc2626',
        garantia_entregada: '#334155',
        inactivo: '#64748b',
        ingresado: '#3b82f6', diagnostico: '#3b82f6',
        confirmacion_garantia: '#0d9488'
    };

    var estadoLabelMap = {
        en_taller: 'Laboratorio',
        listo: 'Listo', listo_sin_garantia: 'Listo',
        no_quedo: 'No Quedó',
        entregado: 'Entregado',
        garantia_activada: 'Proceso de revisión técnica',
        garantia_finalizada: 'Garantía exitosa',
        garantia_fallida: 'Garantía fallida',
        garantia_entregada: 'Garantía entregada',
        inactivo: 'Inactivo',
        ingresado: 'Laboratorio', diagnostico: 'Laboratorio',
        confirmacion_garantia: 'Proceso de revisión técnica'
    };

    function getEstadoColor(estado) {
        return estadoColorMap[estado] || '#94a3b8';
    }

    function getEstadoLabel(estado, enGarantia) {
        if (estado === 'en_taller' && enGarantia) return 'Garantía en laboratorio';
        return estadoLabelMap[estado] || estado;
    }

    var tipoConfig = {
        success: { icon: 'bi-check-circle-fill', color: '#4ade80' },
        error: { icon: 'bi-exclamation-octagon-fill', color: '#f87171' },
        warning: { icon: 'bi-exclamation-triangle-fill', color: '#fbbf24' },
        info: { icon: 'bi-info-circle-fill', color: '#60a5fa' },
        sistema: { icon: 'bi-bell-fill', color: '#a78bfa' }
    };

    window.escapeHtml = escapeHtml;
    window.fmtDate = fmtDate;
    window.getEstadoColor = getEstadoColor;
    window.getEstadoLabel = getEstadoLabel;

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
})();
