/**
 * Bottom sheet reutilizable para acciones móvil.
 * Uso: window.openBottomSheet({ title: 'Acciones', actions: [{ label: 'Editar', icon: 'bi-pencil', onClick: function() { ... } }] })
 */
(function () {
    var overlay = null;
    var panel = null;
    var bodyEl = null;
    var closeBtn = null;

    function getOrCreateSheet() {
        if (overlay && panel) return;
        overlay = document.createElement('div');
        overlay.className = 'bottom-sheet-overlay';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.id = 'globalBottomSheetOverlay';

        panel = document.createElement('div');
        panel.className = 'bottom-sheet';
        panel.id = 'globalBottomSheet';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        panel.setAttribute('aria-labelledby', 'globalBottomSheetTitle');

        panel.innerHTML =
            '<div class="bottom-sheet-header">' +
            '<h2 class="bottom-sheet-title" id="globalBottomSheetTitle">Acciones</h2>' +
            '<button type="button" class="bottom-sheet-close" aria-label="Cerrar" id="globalBottomSheetClose"><i class="bi bi-x-lg"></i></button>' +
            '</div>' +
            '<div class="bottom-sheet-body" id="globalBottomSheetBody"></div>';

        bodyEl = panel.querySelector('#globalBottomSheetBody');
        closeBtn = panel.querySelector('#globalBottomSheetClose');

        overlay.appendChild(panel);
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeBottomSheet();
        });
        if (closeBtn) closeBtn.addEventListener('click', closeBottomSheet);
    }

    function closeBottomSheet() {
        if (!overlay || !panel) return;
        overlay.classList.remove('is-open');
        panel.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    /**
     * @param {Object} opts
     * @param {string} opts.title - Título del panel
     * @param {Array<{label: string, icon: string, onClick: function, danger?: boolean}>} opts.actions - Lista de acciones
     */
    function openBottomSheet(opts) {
        if (!opts || !opts.actions || !opts.actions.length) return;
        getOrCreateSheet();

        var titleEl = panel.querySelector('#globalBottomSheetTitle');
        if (titleEl) titleEl.textContent = opts.title || 'Acciones';

        bodyEl.innerHTML = '';
        opts.actions.forEach(function (action) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'bottom-sheet-item' + (action.danger ? ' danger text-danger' : '');
            btn.innerHTML = (action.icon ? '<i class="bi ' + action.icon + '"></i>' : '') + '<span>' + (action.label || '') + '</span>';
            btn.addEventListener('click', function () {
                closeBottomSheet();
                if (typeof action.onClick === 'function') action.onClick();
            });
            bodyEl.appendChild(btn);
        });

        overlay.classList.add('is-open');
        panel.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        if (closeBtn) closeBtn.focus();
    }

    window.openBottomSheet = openBottomSheet;
    window.closeBottomSheet = closeBottomSheet;
})();
