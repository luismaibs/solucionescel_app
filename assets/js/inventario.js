        // ================================================================
        // HELPERS
        // ================================================================
        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function getAccesorioSubcategoria(p) {
            var nombre = p && p.subcategoria_nombre !== undefined && p.subcategoria_nombre !== null
                ? String(p.subcategoria_nombre).trim()
                : '';
            return nombre || 'Sin subcategoría';
        }

        function compareInvText(a, b) {
            return String(a || '').localeCompare(String(b || ''), 'es', {
                sensitivity: 'base',
                numeric: true
            });
        }

        function sortAccesoriosBySubcategoria(items) {
            return (items || []).slice().sort(function (a, b) {
                return compareInvText(getAccesorioSubcategoria(a), getAccesorioSubcategoria(b)) ||
                    compareInvText(a.marca_nombre, b.marca_nombre) ||
                    compareInvText(a.nombre_producto, b.nombre_producto) ||
                    compareInvText(a.codigo, b.codigo);
            });
        }

        function getStableSubcategoriaStyle(nombre) {
            var hash = 0;
            var text = String(nombre || 'Sin subcategoría');
            for (var i = 0; i < text.length; i++) {
                hash = ((hash << 5) - hash) + text.charCodeAt(i);
                hash |= 0;
            }
            var hue = Math.abs(hash) % 360;
            return '--inv-subcat-hue:' + hue + ';' +
                '--inv-subcat-bg:hsla(' + hue + ',82%,62%,0.13);' +
                '--inv-subcat-bg-strong:hsla(' + hue + ',82%,62%,0.24);' +
                '--inv-subcat-border:hsla(' + hue + ',82%,62%,0.38);' +
                '--inv-subcat-text:hsl(' + hue + ',88%,80%);' +
                '--inv-subcat-text-light:hsl(' + hue + ',68%,34%);';
        }

        function getSubcategoriaCounts(items) {
            var counts = {};
            (items || []).forEach(function (p) {
                var sub = getAccesorioSubcategoria(p);
                counts[sub] = (counts[sub] || 0) + 1;
            });
            return counts;
        }

        function renderSubcategoriaLabel(nombre, count) {
            var total = parseInt(count || 0, 10);
            return '<div class="inv-subcat-heading">' +
                '<span class="inv-subcat-dot"></span>' +
                '<span class="inv-subcat-title">' + escapeHtml(nombre) + '</span>' +
                '<span class="inv-subcat-count">' + total + (total === 1 ? ' accesorio' : ' accesorios') + '</span>' +
                '</div>';
        }

        // Toasts
        const toastSuccess = new bootstrap.Toast(document.getElementById('liveToast'));
        const toastError = new bootstrap.Toast(document.getElementById('errorToast'));

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg')) {
            document.getElementById('toastMsg').innerText = "Operación realizada con éxito";
            toastSuccess.show();
            window.history.replaceState({}, document.title, window.location.pathname);
        }
        if (urlParams.has('error')) {
            document.getElementById('errorMsg').innerText = "Error en la operación.";
            toastError.show();
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        // Sistema Confirmación
        let confirmCallback = null;
        const modalConfirmacion = new bootstrap.Modal(document.getElementById('modalConfirmacion'));
        function showConfirm(title, message, callback) {
            document.getElementById('confirmTitle').innerText = title;
            document.getElementById('confirmMessage').innerHTML = message;
            confirmCallback = callback;
            modalConfirmacion.show();
        }
        document.getElementById('btnConfirmAction').addEventListener('click', function () {
            if (confirmCallback) confirmCallback();
            modalConfirmacion.hide();
        });
        // ================================================================
        // ESTADO GLOBAL DEL MÃ“DULO
        // ================================================================
        let categoriaActiva = 'accesorios';
        let invCurrentPage = 1;
        const invPerPage = 50;
        let invTotalItems = 0;
        let invLastItems = [];
        let invAllItems = [];
        let invFilteredItems = [];
        let filtrosActivos = { marcas: [], subcategorias: [], colores: [], stock: 'todos', precioMin: '', precioMax: '' };
        let vistaActual = 'lista';
        let invExcelTable = null;
        let invExcelCat = null;
        let suppressCellEdited = false;

        // ================================================================
        // DEFINICIONES DE COLUMNAS POR CATEGORÍA
        // ================================================================
        const columnDefs = {
            servicios: {
                thead: ['Subcategoría', 'Acciones', 'Gama', 'Sist. Operativos', 'Garantía', 'Tiempo', 'Precio', ''],
                row: function (p) {
                    var acciones = (p.acciones_lista || '').split('||').filter(Boolean).map(function(a) { return '<span class="badge bg-white bg-opacity-10 text-white fw-normal me-1 mb-1" style="font-size:0.75rem">' + escapeHtml(a) + '</span>'; }).join('');
                    var garantia = p.garantia === 'SI'
                        ? '<span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25"><i class="bi bi-shield-check me-1"></i>Sí</span>'
                        : '<span class="badge bg-white bg-opacity-10 text-muted border border-secondary border-opacity-25">NO</span>';
                    return '<td class="ps-4"><span class="badge-cat cat-servicios">' + escapeHtml(p.subcategoria) + '</span></td>' +
                        '<td><div class="d-flex flex-wrap" style="max-width:200px">' + (acciones || '<span class="text-muted small">—</span>') + '</div></td>' +
                        '<td><span class="text-info fw-semibold" style="text-transform:capitalize">' + escapeHtml(p.gama) + '</span></td>' +
                        '<td><span class="text-muted small">' + escapeHtml((p.sistemas_operativos || '').replace(/,/g, ', ')) + '</span></td>' +
                        '<td>' + garantia + '</td>' +
                        '<td class="text-muted small">' + escapeHtml(p.tiempo_entrega || '—') + '</td>' +
                        '<td><span class="price-tag">$' + parseFloat(p.precio || 0).toFixed(2) + '</span></td>';
                },
                card: function (p) {
                    return '<div class="app-mobile-card-meta mb-1"><span class="badge-cat cat-servicios">' + escapeHtml(p.subcategoria) + '</span></div>' +
                        '<div class="app-mobile-card-title">' + escapeHtml(p.gama) + ' · $' + parseFloat(p.precio || 0).toFixed(2) + '</div>' +
                        '<div class="app-mobile-card-subtitle">' + escapeHtml((p.sistemas_operativos || '').replace(/,/g, ', ')) + '</div>';
                }
            },
            baterias: {
                thead: ['Marca', 'Modelo', 'Calidad', 'Tipo', 'Tiempo Entrega', 'Notas', ''],
                row: function (p) {
                    return '<td class="ps-4 fw-semibold text-white">' + escapeHtml(p.marca) + '</td>' +
                        '<td class="text-info">' + escapeHtml(p.modelo_bateria) + '</td>' +
                        '<td><span class="text-muted small">' + escapeHtml((p.calidad || '').replace(/,/g, ', ')) + '</span></td>' +
                        '<td><span class="text-muted small">' + escapeHtml((p.tipo || '').replace(/,/g, ', ')) + '</span></td>' +
                        '<td><span class="text-muted small">' + escapeHtml((p.tiempo || '').replace(/,/g, ', ')) + '</span></td>' +
                        '<td class="text-muted small" style="max-width:150px">' + escapeHtml(p.notas || '—') + '</td>';
                },
                card: function (p) {
                    return '<div class="app-mobile-card-meta mb-1"><span class="badge-cat cat-baterias">' + escapeHtml(p.marca) + '</span></div>' +
                        '<div class="app-mobile-card-title">' + escapeHtml(p.modelo_bateria) + '</div>' +
                        '<div class="app-mobile-card-subtitle">' + escapeHtml((p.calidad || '').replace(/,/g, ', ')) + ' · ' + escapeHtml((p.tipo || '').replace(/,/g, ', ')) + '</div>';
                }
            },
            pantallas: {
                thead: ['Modelo', 'Modelo Técnico', 'Calidad', 'Precio', 'Tiempo Entrega', 'Nota', ''],
                row: function (p) {
                    var calidadClass = p.calidad === 'Original' ? 'text-success' : (p.calidad === 'Intermedio' ? 'text-info' : 'text-warning');
                    return '<td class="ps-4 fw-semibold text-white">' + escapeHtml(p.modelo_nombre || '—') + '</td>' +
                        '<td class="text-info">' + escapeHtml(p.modelo_tecnico_nombre || '—') + '</td>' +
                        '<td><span class="' + calidadClass + ' fw-semibold">' + escapeHtml(p.calidad) + '</span></td>' +
                        '<td><span class="price-tag">$' + parseFloat(p.precio || 0).toFixed(2) + '</span></td>' +
                        '<td class="text-muted small">' + escapeHtml(p.tiempo || '—') + '</td>' +
                        '<td class="text-muted small" style="max-width:150px">' + escapeHtml(p.nota || '—') + '</td>';
                },
                card: function (p) {
                    return '<div class="app-mobile-card-meta mb-1"><span class="badge-cat cat-pantallas">' + escapeHtml(p.calidad) + '</span></div>' +
                        '<div class="app-mobile-card-title">' + escapeHtml(p.modelo_nombre || '—') + '</div>' +
                        '<div class="app-mobile-card-subtitle">' + escapeHtml(p.modelo_tecnico_nombre || '') + ' · $' + parseFloat(p.precio || 0).toFixed(2) + '</div>';
                }
            },
            accesorios: {
                thead: ['Marca', 'Producto', 'Stock', 'Precio', ''],
                row: function (p) {
                    var stock = parseInt(p.stock || 0, 10);
                    var stockClass = stock < 3 ? 'text-danger fw-bold' : 'text-success fw-semibold';
                    var color = escapeHtml(p.color_nombre || '—');
                    return '<td class="ps-4 inv-marca-cell">' +
                                '<div class="inv-marca-name">' + escapeHtml(p.marca_nombre || '—') + '</div>' +
                            '</td>' +
                            '<td class="inv-producto-cell">' +
                                '<div class="inv-producto-name">' + escapeHtml(p.nombre_producto) + '</div>' +
                                '<span class="inv-meta-line">' + escapeHtml(p.codigo) + (color !== '—' ? ' · ' + color : '') + '</span>' +
                            '</td>' +
                            '<td><span class="' + stockClass + '">' + stock + ' u.</span></td>' +
                            '<td><span class="price-tag">$' + parseFloat(p.precio || 0).toFixed(2) + '</span></td>';
                },
                card: function (p) {
                    var stock = parseInt(p.stock || 0, 10);
                    var stockClass = stock < 3 ? 'text-danger' : 'text-success';
                    return '<div class="app-mobile-card-meta mb-1"><span class="app-mobile-card-subtitle">' + escapeHtml(p.marca_nombre || '') + '</span></div>' +
                        '<div class="app-mobile-card-title">' + escapeHtml(p.nombre_producto) + '</div>' +
                        '<div class="app-mobile-card-subtitle">' + escapeHtml(p.codigo) + ' · $' + parseFloat(p.precio || 0).toFixed(2) + ' · <span class="' + stockClass + '">' + stock + ' u.</span></div>';
                }
            }
        };

        // ================================================================
        // KPIs DINÁMICOS
        // ================================================================
        const kpiColorMap = {
            primary: { bg: 'bg-primary', text: 'text-primary' },
            success: { bg: 'bg-success', text: 'text-success' },
            info:    { bg: 'bg-info',    text: 'text-info' },
            warning: { bg: 'bg-warning', text: 'text-warning' },
            purple:  { bg: 'bg-purple',  text: 'text-purple' },
        };

        async function loadKpis(cat) {
            var container = document.getElementById('kpiCards');
            container.style.opacity = '0.4';
            container.style.transition = 'opacity 0.2s ease';

            try {
                var resp = await fetch('../api/inventario/kpis?categoria=' + cat);
                var data = await resp.json();

                if (!data.ok || !data.kpis) throw new Error('KPI error');

                var html = data.kpis.map(function (k) {
                    var colors = kpiColorMap[k.color] || kpiColorMap.primary;
                    return '<span class="module-kpi-chip">' +
                        '<i class="bi ' + escapeHtml(k.icon) + ' kpi-icon ' + colors.text + '"></i>' +
                        '<span class="kpi-value">' + escapeHtml(String(k.value)) + '</span>' +
                        '<span class="kpi-label">' + escapeHtml(k.label).toUpperCase() + '</span>' +
                        '</span>';
                }).join('');

                container.innerHTML = html;
                setTimeout(function () { container.style.opacity = '1'; }, 50);
            } catch (e) {
                container.innerHTML = '<span class="module-kpi-chip"><i class="bi bi-exclamation-triangle kpi-icon text-danger"></i><span class="kpi-label">Error al cargar KPIs</span></span>';
                container.style.opacity = '1';
            }
        }

        // ================================================================
        // RENDERIZADO TABLA DINÁMICA POR CATEGORÍA
        // ================================================================
        function renderTableHead(cat) {
            var def = columnDefs[cat];
            if (!def) return;
            var ths = def.thead.map(function (h, i) {
                if (i === 0) return '<th class="ps-4">' + escapeHtml(h) + '</th>';
                if (i === def.thead.length - 1) return '<th class="text-end pe-4">Acciones</th>';
                return '<th>' + escapeHtml(h) + '</th>';
            }).join('');
            document.getElementById('invTableHead').innerHTML = '<tr>' + ths + '</tr>';
        }

        function renderTableRows(cat, items) {
            var tbody = document.getElementById('invTableBody');
            if (!tbody) return;
            var def = columnDefs[cat];
            if (!def) return;

            if (!items || items.length === 0) {
                var colSpan = def.thead.length;
                tbody.innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center py-5 text-muted">Sin registros para mostrar.</td></tr>';
                return;
            }

            var rows = '';
            var lastSubcategoria = null;
            var countSource = cat === 'accesorios' && invFilteredItems.length ? invFilteredItems : items;
            var subcategoriaCounts = cat === 'accesorios' ? getSubcategoriaCounts(countSource) : {};

            items.forEach(function (p) {
                var editBtn = cat === 'accesorios'
                    ? '<button class="btn-action bg-primary bg-opacity-10 text-primary border-0" onclick="abrirEditarAccesorio(' + p.id + ')" title="Editar"><i class="bi bi-pencil"></i></button>'
                    : '';
                var rowClass = '';
                var rowStyle = '';

                if (cat === 'accesorios') {
                    var subcategoria = getAccesorioSubcategoria(p);
                    rowStyle = getStableSubcategoriaStyle(subcategoria);
                    rowClass = ' class="inv-subcat-item" style="' + rowStyle + '"';

                    if (subcategoria !== lastSubcategoria) {
                        rows += '<tr class="inv-subcat-group" style="' + rowStyle + '">' +
                            '<td colspan="' + def.thead.length + '">' +
                            renderSubcategoriaLabel(subcategoria, subcategoriaCounts[subcategoria]) +
                            '</td></tr>';
                        lastSubcategoria = subcategoria;
                    }
                }

                rows += '<tr' + rowClass + '>' + def.row(p) +
                    '<td class="text-end pe-4"><div class="d-flex justify-content-end gap-2">' +
                    editBtn +
                    '<button class="btn-action bg-danger bg-opacity-10 text-danger border-0" onclick="confirmDeleteCat(' + p.id + ')" title="Eliminar"><i class="bi bi-trash"></i></button>' +
                    '</div></td></tr>';
            });

            tbody.innerHTML = rows;
        }

        function renderMobileCards(cat, items) {
            var container = document.getElementById('invCardsContainer');
            if (!container) return;
            var def = columnDefs[cat];
            if (!def) return;

            if (!items || items.length === 0) {
                container.innerHTML = '<div class="text-center py-5 text-muted">Sin registros para mostrar.</div>';
                return;
            }

            if (cat === 'accesorios') {
                var groupedCards = '';
                var lastSubcategoria = null;
                var countSource = invFilteredItems.length ? invFilteredItems : items;
                var subcategoriaCounts = getSubcategoriaCounts(countSource);

                items.forEach(function (p) {
                    var subcategoria = getAccesorioSubcategoria(p);
                    var cardStyle = getStableSubcategoriaStyle(subcategoria);

                    if (subcategoria !== lastSubcategoria) {
                        groupedCards += '<div class="inv-mobile-subcat-group" style="' + cardStyle + '">' +
                            renderSubcategoriaLabel(subcategoria, subcategoriaCounts[subcategoria]) +
                            '</div>';
                        lastSubcategoria = subcategoria;
                    }

                    groupedCards += '<div class="app-mobile-card inv-subcat-card d-flex align-items-start justify-content-between gap-2" style="' + cardStyle + '" data-inv-id="' + p.id + '" role="button" tabindex="0">' +
                        '<div class="flex-grow-1 min-w-0">' + def.card(p) + '</div>' +
                        '<button type="button" class="app-mobile-card-more flex-shrink-0" onclick="event.preventDefault();event.stopPropagation();openInvActionsSheet(' + p.id + ')" aria-label="Mas acciones"><i class="bi bi-three-dots-vertical"></i></button>' +
                        '</div>';
                });

                container.innerHTML = groupedCards;
                return;
            }

            var cards = items.map(function (p) {
                return '<div class="app-mobile-card d-flex align-items-start justify-content-between gap-2" data-inv-id="' + p.id + '" role="button" tabindex="0">' +
                    '<div class="flex-grow-1 min-w-0">' + def.card(p) + '</div>' +
                    '<button type="button" class="app-mobile-card-more flex-shrink-0" onclick="event.preventDefault();event.stopPropagation();openInvActionsSheet(' + p.id + ')" aria-label="Más acciones"><i class="bi bi-three-dots-vertical"></i></button>' +
                    '</div>';
            }).join('');

            container.innerHTML = cards;
        }

        // ================================================================
        // PAGINACIÃ“N
        // ================================================================
        function updateInvPaginationInfo() {
            var infoEl = document.getElementById('invPaginationInfo');
            var btnPrev = document.getElementById('btnPrevPage');
            var btnNext = document.getElementById('btnNextPage');
            if (!infoEl || !btnPrev || !btnNext) return;

            if (invTotalItems === 0) {
                infoEl.textContent = 'Sin registros en esta categoría.';
                btnPrev.disabled = true;
                btnNext.disabled = true;
                return;
            }

            var totalPages = Math.ceil(invTotalItems / invPerPage);
            var start = (invCurrentPage - 1) * invPerPage + 1;
            var end = Math.min(invCurrentPage * invPerPage, invTotalItems);
            infoEl.textContent = 'Mostrando ' + start + '-' + end + ' de ' + invTotalItems + ' registros';
            btnPrev.disabled = invCurrentPage <= 1;
            btnNext.disabled = invCurrentPage >= totalPages;
        }

        function changeInvPage(delta) {
            var totalPages = Math.ceil(invTotalItems / invPerPage) || 1;
            var target = invCurrentPage + delta;
            if (target < 1) target = 1;
            if (target > totalPages) target = totalPages;
            if (target === invCurrentPage) return;
            invCurrentPage = target;
            invLastItems = invFilteredItems.slice((invCurrentPage - 1) * invPerPage, invCurrentPage * invPerPage);
            if (window.innerWidth < 992) {
                renderMobileCards(categoriaActiva, invLastItems);
            } else {
                renderTableRows(categoriaActiva, invLastItems);
            }
            updateInvPaginationInfo();
        }

        // ================================================================
        // CARGA PRINCIPAL POR CATEGORÍA
        // ================================================================
        async function loadCategoria(cat) {
            var tbody = document.getElementById('invTableBody');
            var cardsContainer = document.getElementById('invCardsContainer');
            var isMobile = window.innerWidth < 992;

            renderTableHead(cat);
            var colSpan = (columnDefs[cat] || { thead: [] }).thead.length;
            if (tbody && !isMobile) {
                tbody.innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Cargando...</td></tr>';
            }
            if (cardsContainer && isMobile) {
                cardsContainer.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Cargando...</div>';
            }

            try {
                var resp = await fetch('../api/inventario/categoria?categoria=' + cat + '&page=1&per_page=9999');
                var data = await resp.json();

                if (!data.ok) throw new Error(data.message || 'Error');

                invAllItems = data.items || [];
                var searchEl = document.getElementById('searchInput');
                if (searchEl) searchEl.value = '';
                applyLocalFilter('');
            } catch (err) {
                if (tbody && !isMobile) {
                    tbody.innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center py-5 text-danger">Error al cargar datos.</td></tr>';
                }
                if (cardsContainer && isMobile) {
                    cardsContainer.innerHTML = '<div class="text-center py-5 text-danger">Error al cargar datos.</div>';
                }
                document.getElementById('invPaginationInfo').textContent = 'No se pudo cargar el inventario.';
            }
        }

        function applyLocalFilter(query) {
            var q = (query || '').toLowerCase().trim();
            var f = filtrosActivos;
            var cat = categoriaActiva;

            invFilteredItems = invAllItems.filter(function(p) {
                // Texto
                if (q && getItemSearchText(p, cat).indexOf(q) === -1) return false;
                // Marca
                if (f.marcas.length) {
                    var marca = cat === 'baterias' ? p.marca : p.marca_nombre;
                    if (f.marcas.indexOf(marca) === -1) return false;
                }
                // Subcategoría / calidad
                if (f.subcategorias.length) {
                    var sub = cat === 'accesorios' ? p.subcategoria_nombre
                            : cat === 'pantallas'  ? p.calidad
                            : cat === 'servicios'  ? p.subcategoria
                            : p.calidad;
                    if (f.subcategorias.indexOf(sub) === -1) return false;
                }
                // Color (solo accesorios)
                if (f.colores.length && cat === 'accesorios') {
                    if (f.colores.indexOf(p.color_nombre) === -1) return false;
                }
                // Stock
                if (f.stock !== 'todos') {
                    var stock = parseInt(p.stock || 0, 10);
                    if (f.stock === 'disponible' && stock <= 0) return false;
                    if (f.stock === 'bajo' && stock >= 3) return false;
                }
                // Precio
                if (f.precioMin !== '' && parseFloat(p.precio || 0) < parseFloat(f.precioMin)) return false;
                if (f.precioMax !== '' && parseFloat(p.precio || 0) > parseFloat(f.precioMax)) return false;
                return true;
            });

            if (cat === 'accesorios') {
                invFilteredItems = sortAccesoriosBySubcategoria(invFilteredItems);
            }

            invTotalItems = invFilteredItems.length;
            invCurrentPage = 1;
            invLastItems = invFilteredItems.slice(0, invPerPage);

            // Vista tabla (hoja de cálculo): delegar render a Tabulator
            if (vistaActual === 'tabla') {
                renderTablaExcel();
                updateInvPaginationInfo();
                return;
            }

            var isMobile = window.innerWidth < 992;
            var tbody = document.getElementById('invTableBody');
            var cardsEl = document.getElementById('invCardsContainer');

            if (isMobile) {
                renderMobileCards(cat, invLastItems);
                if (tbody) tbody.innerHTML = '';
            } else {
                renderTableRows(cat, invLastItems);
                if (cardsEl) cardsEl.innerHTML = '';
            }

            updateInvPaginationInfo();
            var hayFiltroTexto = q;
            var hayFiltros = f.marcas.length || f.subcategorias.length || f.colores.length || f.stock !== 'todos' || f.precioMin !== '' || f.precioMax !== '';
            if ((hayFiltroTexto || hayFiltros) && invFilteredItems.length !== invAllItems.length) {
                var paginationInfo = document.getElementById('invPaginationInfo');
                if (paginationInfo) paginationInfo.textContent = invFilteredItems.length + ' resultado(s)' + (q ? ' para "' + query + '"' : '');
            }
        }

        function getItemSearchText(p, cat) {
            var fields;
            switch (cat) {
                case 'accesorios':
                    fields = [p.nombre_producto, p.codigo, p.marca_nombre, p.subcategoria_nombre, p.color_nombre];
                    break;
                case 'baterias':
                    fields = [p.marca, p.modelo_bateria, p.calidad, p.tipo];
                    break;
                case 'pantallas':
                    fields = [p.modelo_nombre, p.modelo_tecnico_nombre, p.calidad];
                    break;
                case 'servicios':
                    fields = [p.subcategoria, p.gama, p.sistemas_operativos || '', (p.acciones_lista || '').replace(/\|\|/g, ' ')];
                    break;
                default:
                    return JSON.stringify(p).toLowerCase();
            }
            return fields.filter(Boolean).join(' ').toLowerCase();
        }

        // ================================================================
        // FILTROS AVANZADOS
        // ================================================================
        var panelFiltrosOpen = false;

        function toggleFiltros(e) {
            if (e) e.stopPropagation();
            panelFiltrosOpen = !panelFiltrosOpen;
            var panel = document.getElementById('panelFiltros');
            var btn = document.getElementById('btnFiltros');
            if (panelFiltrosOpen) {
                renderFiltrosPanel();
                panel.classList.remove('d-none');
                btn.classList.add('active');
            } else {
                panel.classList.add('d-none');
                btn.classList.remove('active');
            }
        }

        document.addEventListener('click', function(e) {
            if (!panelFiltrosOpen) return;
            var panel = document.getElementById('panelFiltros');
            var btn = document.getElementById('btnFiltros');
            if (!panel.contains(e.target) && !btn.contains(e.target)) {
                panelFiltrosOpen = false;
                panel.classList.add('d-none');
                btn.classList.remove('active');
            }
        });

        function renderFiltrosPanel() {
            var cat = categoriaActiva;
            var content = document.getElementById('panelFiltrosContent');
            if (!content) return;
            var html = '';

            if (cat === 'accesorios') {
                var marcas = uniqueSorted(invAllItems.map(function(p) { return p.marca_nombre; }));
                if (marcas.length) html += renderFiltroSeccion('Marca', 'marca', marcas, filtrosActivos.marcas);
                var subs = uniqueSorted(invAllItems.map(function(p) { return p.subcategoria_nombre; }));
                if (subs.length) html += renderFiltroSeccion('Subcategoría', 'subcategoria', subs, filtrosActivos.subcategorias);
                var colores = uniqueSorted(invAllItems.map(function(p) { return p.color_nombre; }));
                if (colores.length) html += renderFiltroSeccion('Color', 'color', colores, filtrosActivos.colores);
            } else if (cat === 'baterias') {
                var marcas = uniqueSorted(invAllItems.map(function(p) { return p.marca; }));
                if (marcas.length) html += renderFiltroSeccion('Marca', 'marca', marcas, filtrosActivos.marcas);
                var calidades = uniqueSorted(invAllItems.map(function(p) { return p.calidad; }));
                if (calidades.length) html += renderFiltroSeccion('Calidad', 'subcategoria', calidades, filtrosActivos.subcategorias);
            } else if (cat === 'pantallas') {
                var calidades = uniqueSorted(invAllItems.map(function(p) { return p.calidad; }));
                if (calidades.length) html += renderFiltroSeccion('Calidad', 'subcategoria', calidades, filtrosActivos.subcategorias);
            } else if (cat === 'servicios') {
                var subcats = uniqueSorted(invAllItems.map(function(p) { return p.subcategoria; }));
                if (subcats.length) html += renderFiltroSeccion('Subcategoría', 'subcategoria', subcats, filtrosActivos.subcategorias);
                var gamas = uniqueSorted(invAllItems.map(function(p) { return p.gama; }));
                if (gamas.length) html += renderFiltroSeccion('Gama', 'marca', gamas, filtrosActivos.marcas);
            }

            // Stock (solo categorías con campo stock)
            if (cat === 'accesorios' || cat === 'baterias') {
                var stockOpts = [
                    { val: 'todos', label: 'Todos' },
                    { val: 'disponible', label: 'En stock (≥1)' },
                    { val: 'bajo', label: 'Stock bajo (<3)' }
                ];
                var stockChips = stockOpts.map(function(o) {
                    var sel = filtrosActivos.stock === o.val ? ' selected' : '';
                    return '<span class="inv-filter-chip' + sel + '" data-tipo="stock" data-val="' + o.val + '" onclick="selectStockFiltro(this)">' + o.label + '</span>';
                }).join('');
                html += '<div class="inv-filter-seccion"><div class="inv-filter-seccion-titulo">Stock</div><div class="inv-filter-chips">' + stockChips + '</div></div>';
            }

            // Precio
            html += '<div class="inv-filter-seccion">' +
                '<div class="inv-filter-seccion-titulo">Precio</div>' +
                '<div class="inv-filter-precio-row">' +
                '<input type="number" id="filtroPrecioMin" class="form-control form-control-sm" placeholder="Mín $" min="0" value="' + escapeHtml(filtrosActivos.precioMin) + '">' +
                '<span class="text-muted small">—</span>' +
                '<input type="number" id="filtroPrecioMax" class="form-control form-control-sm" placeholder="Máx $" min="0" value="' + escapeHtml(filtrosActivos.precioMax) + '">' +
                '</div></div>';

            content.innerHTML = html;
        }

        function uniqueSorted(arr) {
            return arr.filter(Boolean).filter(function(v, i, a) { return a.indexOf(v) === i; }).sort();
        }

        function renderFiltroSeccion(label, tipo, opciones, activas) {
            var chips = opciones.map(function(op) {
                var sel = activas.indexOf(op) !== -1 ? ' selected' : '';
                return '<span class="inv-filter-chip' + sel + '" data-tipo="' + tipo + '" data-val="' + escapeHtml(op) + '" onclick="toggleFiltroChip(this)">' + escapeHtml(op) + '</span>';
            }).join('');
            return '<div class="inv-filter-seccion"><div class="inv-filter-seccion-titulo">' + label + '</div><div class="inv-filter-chips">' + chips + '</div></div>';
        }

        function toggleFiltroChip(el) {
            el.classList.toggle('selected');
        }

        function selectStockFiltro(el) {
            el.closest('.inv-filter-chips').querySelectorAll('.inv-filter-chip').forEach(function(c) { c.classList.remove('selected'); });
            el.classList.add('selected');
        }

        function aplicarFiltros() {
            var content = document.getElementById('panelFiltrosContent');
            if (!content) return;
            filtrosActivos.marcas = [];
            filtrosActivos.subcategorias = [];
            filtrosActivos.colores = [];
            content.querySelectorAll('.inv-filter-chip.selected').forEach(function(chip) {
                var tipo = chip.getAttribute('data-tipo');
                var val = chip.getAttribute('data-val');
                if (tipo === 'marca') filtrosActivos.marcas.push(val);
                else if (tipo === 'subcategoria') filtrosActivos.subcategorias.push(val);
                else if (tipo === 'color') filtrosActivos.colores.push(val);
                else if (tipo === 'stock') filtrosActivos.stock = val;
            });
            var minEl = document.getElementById('filtroPrecioMin');
            var maxEl = document.getElementById('filtroPrecioMax');
            filtrosActivos.precioMin = minEl ? minEl.value : '';
            filtrosActivos.precioMax = maxEl ? maxEl.value : '';
            actualizarBadgeFiltros();
            applyLocalFilter(document.getElementById('searchInput').value.trim());
            // Cerrar panel
            panelFiltrosOpen = false;
            document.getElementById('panelFiltros').classList.add('d-none');
        }

        function limpiarFiltros() {
            filtrosActivos = { marcas: [], subcategorias: [], colores: [], stock: 'todos', precioMin: '', precioMax: '' };
            renderFiltrosPanel();
            actualizarBadgeFiltros();
            applyLocalFilter(document.getElementById('searchInput').value.trim());
        }

        function actualizarBadgeFiltros() {
            var badge = document.getElementById('filtrosBadge');
            var btn = document.getElementById('btnFiltros');
            if (!badge || !btn) return;
            var f = filtrosActivos;
            var count = f.marcas.length + f.subcategorias.length + f.colores.length +
                (f.stock !== 'todos' ? 1 : 0) +
                (f.precioMin !== '' ? 1 : 0) +
                (f.precioMax !== '' ? 1 : 0);
            if (count > 0) {
                badge.textContent = count;
                badge.classList.remove('d-none');
                btn.classList.add('has-filters');
            } else {
                badge.classList.add('d-none');
                btn.classList.remove('has-filters');
            }
        }

        // ================================================================
        // VISTA TABLA (hoja de cálculo tipo Excel — Tabulator)
        // ================================================================
        var moneyFmt = { symbol: '$', precision: 2, thousand: ',' };

        var excelColumnDefs = {
            accesorios: [
                { title: 'Subcategoría', field: 'subcategoria_nombre', width: 150, headerFilter: 'input' },
                { title: 'Marca', field: 'marca_nombre', width: 120, headerFilter: 'input' },
                { title: 'Código', field: 'codigo', width: 130, editor: 'input', cssClass: 'excel-editable', headerFilter: 'input' },
                { title: 'Producto', field: 'nombre_producto', minWidth: 220, editor: 'input', cssClass: 'excel-editable', headerFilter: 'input' },
                { title: 'Stock', field: 'stock', width: 90, hozAlign: 'right', editor: 'number', editorParams: { min: 0 }, cssClass: 'excel-editable' },
                { title: 'Precio', field: 'precio', width: 110, hozAlign: 'right', editor: 'number', editorParams: { min: 0, step: 0.01 }, formatter: 'money', formatterParams: moneyFmt, cssClass: 'excel-editable' },
                { title: 'Color', field: 'color_nombre', width: 110, headerFilter: 'input' }
            ],
            baterias: [
                { title: 'Marca', field: 'marca', width: 130, editor: 'input', cssClass: 'excel-editable', headerFilter: 'input' },
                { title: 'Modelo', field: 'modelo_bateria', width: 140, editor: 'input', cssClass: 'excel-editable', headerFilter: 'input' },
                { title: 'Código', field: 'codigo', width: 120, editor: 'input', cssClass: 'excel-editable' },
                { title: 'Calidad', field: 'calidad', width: 150, editor: 'input', cssClass: 'excel-editable' },
                { title: 'Tipo', field: 'tipo', width: 130, editor: 'input', cssClass: 'excel-editable' },
                { title: 'Tiempo', field: 'tiempo', width: 150, editor: 'input', cssClass: 'excel-editable' },
                { title: 'Stock', field: 'stock', width: 90, hozAlign: 'right', editor: 'number', editorParams: { min: 0 }, cssClass: 'excel-editable' },
                { title: 'Precio', field: 'precio', width: 110, hozAlign: 'right', editor: 'number', editorParams: { min: 0, step: 0.01 }, formatter: 'money', formatterParams: moneyFmt, cssClass: 'excel-editable' },
                { title: 'Notas', field: 'notas', minWidth: 180, editor: 'textarea', cssClass: 'excel-editable' }
            ],
            pantallas: [
                { title: 'Modelo', field: 'modelo_nombre', width: 150, headerFilter: 'input' },
                { title: 'Modelo Técnico', field: 'modelo_tecnico_nombre', width: 150, headerFilter: 'input' },
                { title: 'Calidad', field: 'calidad', width: 130, editor: 'list', editorParams: { values: ['Original', 'Intermedio', 'Genérico'] }, cssClass: 'excel-editable' },
                { title: 'Precio', field: 'precio', width: 120, hozAlign: 'right', editor: 'number', editorParams: { min: 0, step: 0.01 }, formatter: 'money', formatterParams: moneyFmt, cssClass: 'excel-editable' },
                { title: 'Tiempo', field: 'tiempo', width: 170, editor: 'input', cssClass: 'excel-editable' },
                { title: 'Nota', field: 'nota', minWidth: 180, editor: 'textarea', cssClass: 'excel-editable' }
            ],
            servicios: [
                { title: 'Subcategoría', field: 'subcategoria', width: 140, editor: 'input', cssClass: 'excel-editable', headerFilter: 'input' },
                { title: 'Gama', field: 'gama', width: 120, editor: 'input', cssClass: 'excel-editable' },
                { title: 'Sist. Operativos', field: 'sistemas_operativos', width: 170, editor: 'input', cssClass: 'excel-editable' },
                { title: 'Garantía', field: 'garantia', width: 100, editor: 'list', editorParams: { values: ['SI', 'NO'] }, cssClass: 'excel-editable' },
                { title: 'Tiempo', field: 'tiempo_entrega', width: 150, editor: 'input', cssClass: 'excel-editable' },
                { title: 'Precio', field: 'precio', width: 110, hozAlign: 'right', editor: 'number', editorParams: { min: 0, step: 0.01 }, formatter: 'money', formatterParams: moneyFmt, cssClass: 'excel-editable' },
                { title: 'Nota', field: 'nota', minWidth: 180, editor: 'textarea', cssClass: 'excel-editable' }
            ]
        };

        function cambiarVista(vista, btn) {
            if (vista === vistaActual) return;
            vistaActual = vista;

            document.querySelectorAll('.inv-view-opt').forEach(function (c) { c.classList.remove('active'); });
            if (btn) btn.classList.add('active');

            var tableWrap = document.querySelector('.table-container .app-table-wrap');
            var cardsWrap = document.getElementById('invCardsContainer');
            var excelWrap = document.getElementById('invExcelContainer');
            var paginationRow = document.getElementById('invPaginationRow');

            if (vista === 'tabla') {
                if (tableWrap) tableWrap.classList.add('d-none');
                if (cardsWrap) cardsWrap.classList.add('d-none');
                if (excelWrap) excelWrap.classList.remove('d-none');
                if (paginationRow) paginationRow.classList.add('d-none');
                renderTablaExcel();
            } else {
                if (excelWrap) excelWrap.classList.add('d-none');
                if (tableWrap) tableWrap.classList.remove('d-none');
                if (cardsWrap) cardsWrap.classList.remove('d-none');
                if (paginationRow) paginationRow.classList.remove('d-none');
                applyLocalFilter(document.getElementById('searchInput').value.trim());
            }
        }

        function renderTablaExcel() {
            if (typeof Tabulator === 'undefined') return;
            var cols = excelColumnDefs[categoriaActiva];
            if (!cols) return;

            // Misma categoría → solo refrescar datos
            if (invExcelTable && invExcelCat === categoriaActiva) {
                invExcelTable.replaceData(invFilteredItems.slice());
                return;
            }

            // Reconstruir (cambió de categoría o primera vez)
            if (invExcelTable) {
                try { invExcelTable.destroy(); } catch (e) {}
                invExcelTable = null;
            }
            invExcelCat = categoriaActiva;

            invExcelTable = new Tabulator('#invExcelContainer', {
                data: invFilteredItems.slice(),
                index: 'id',
                layout: 'fitDataStretch',
                height: '64vh',
                movableColumns: true,
                resizableColumns: true,
                placeholder: 'Sin registros para mostrar.',
                columnDefaults: { resizable: true, headerSortTristate: true },
                columns: cols
            });

            invExcelTable.on('cellEdited', function (cell) {
                if (suppressCellEdited) return;
                guardarCeldaExcel(cell);
            });
        }

        function guardarCeldaExcel(cell) {
            var rowData = cell.getRow().getData();
            var campo = cell.getField();
            var valor = cell.getValue();
            var id = rowData.id;

            fetch('../api/inventario/actualizar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ categoria: categoriaActiva, id: id, campo: campo, valor: valor })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        // Reflejar valor normalizado del servidor sin re-disparar edición
                        if (data.valor !== undefined && data.valor !== null && String(data.valor) !== String(valor)) {
                            suppressCellEdited = true;
                            cell.setValue(data.valor, true);
                            suppressCellEdited = false;
                        }
                        syncItemLocal(id, campo, data.valor !== undefined ? data.valor : valor);
                        cell.getElement().classList.add('excel-saved');
                        setTimeout(function () { cell.getElement().classList.remove('excel-saved'); }, 900);
                    } else {
                        cell.restoreOldValue();
                        document.getElementById('errorMsg').innerText = data.message || 'No se pudo guardar.';
                        toastError.show();
                    }
                })
                .catch(function () {
                    cell.restoreOldValue();
                    document.getElementById('errorMsg').innerText = 'Error de conexión al guardar.';
                    toastError.show();
                });
        }

        function syncItemLocal(id, campo, valor) {
            [invAllItems, invFilteredItems].forEach(function (arr) {
                var it = arr.find(function (x) { return x.id == id; });
                if (it) it[campo] = valor;
            });
        }

        // ================================================================
        // CAMBIAR CATEGORÍA (función principal)
        // ================================================================
        function cambiarCategoria(cat, btn) {
            if (cat === categoriaActiva && invAllItems.length > 0) return;
            categoriaActiva = cat;
            filtrosActivos = { marcas: [], subcategorias: [], colores: [], stock: 'todos', precioMin: '', precioMax: '' };
            actualizarBadgeFiltros();

            // Activar chip visual
            document.querySelectorAll('.filter-chip').forEach(function (c) { c.classList.remove('active'); });
            if (btn) btn.classList.add('active');

            // Recargar KPIs y tabla en paralelo
            loadKpis(cat);
            loadCategoria(cat);
        }

        // ================================================================
        // DELETE POR CATEGORÍA
        // ================================================================
        function confirmDeleteCat(id) {
            showConfirm('Eliminar', '¿Eliminar este registro permanentemente?', function () {
                modalConfirmacion.hide();
                var fd = new FormData();
                fd.append('action', 'delete');
                fd.append('categoria', categoriaActiva);
                fd.append('id', id);
                fetch('../api/inventario/categoria', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok) {
                            loadCategoria(categoriaActiva);
                            loadKpis(categoriaActiva);
                            document.getElementById('toastMsg').innerText = data.message || 'Registro eliminado';
                            toastSuccess.show();
                        } else {
                            document.getElementById('errorMsg').innerText = data.message || 'Error al eliminar';
                            toastError.show();
                        }
                    })
                    .catch(function () {
                        document.getElementById('errorMsg').innerText = 'Error de conexión';
                        toastError.show();
                    });
            });
        }

        // ================================================================
        // BOTTOM SHEET MOBILE
        // ================================================================
        function openInvActionsSheet(prodId) {
            var p = invLastItems.find(function (item) { return item.id == prodId; });
            if (!p || typeof window.openBottomSheet !== 'function') return;
            var actions = [];
            if (categoriaActiva === 'accesorios') {
                actions.push({ label: 'Editar', icon: 'bi-pencil', onClick: function () { abrirEditarAccesorio(p.id); } });
            }
            actions.push({ label: 'Eliminar', icon: 'bi-trash', onClick: function () { confirmDeleteCat(p.id); }, danger: true });
            window.openBottomSheet({ title: 'Registro', actions: actions });
        }

        // ================================================================
        // BÚSQUEDA SEMÁNTICA RAG
        // ================================================================
        var searchDebounceTimer = null;

        document.getElementById('searchInput').addEventListener('input', function () {
            var query = this.value.trim();
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(function () {
                applyLocalFilter(query);
            }, 200);
        });

        function resetToCategoryView() {
            var searchEl = document.getElementById('searchInput');
            if (searchEl) searchEl.value = '';
            applyLocalFilter('');
        }

        // ================================================================
        // ABRIR PANELES DE CREACIÃ“N
        // ================================================================
        const modalCategoria = new bootstrap.Modal(document.getElementById('modalSeleccionCategoria'));

        function abrirOffcanvasCrear() {
            modalCategoria.show();
        }

        function abrirFormServiciosGenerales() {
            modalCategoria.hide();
            document.getElementById('formServicioGeneral').reset();
            document.getElementById('accionesContainer').innerHTML = '';
            accionesArr = [];
            document.querySelectorAll('#offcanvasServicioGeneral .gama-option').forEach(function (el) { el.classList.remove('active'); });
            document.getElementById('gamaHidden').value = '';
            document.getElementById('garantiaSwitch').checked = false;
            document.getElementById('garantiaHidden').value = 'NO';
            document.getElementById('garantiaLabel').textContent = 'NO';
            document.getElementById('garantiaLabel').style.color = 'rgba(248,250,252,0.6)';
            offcanvasServicioGeneral.show();
        }

        // ================================================================
        // RESIZE HANDLER
        // ================================================================
        var _resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(_resizeTimer);
            _resizeTimer = setTimeout(function () {
                if (vistaActual === 'tabla') return;
                if (invLastItems.length === 0) return;
                var tbody = document.getElementById('invTableBody');
                var cardsEl = document.getElementById('invCardsContainer');
                if (window.innerWidth >= 992) {
                    renderTableRows(categoriaActiva, invLastItems);
                    if (cardsEl) cardsEl.innerHTML = '';
                } else {
                    renderMobileCards(categoriaActiva, invLastItems);
                    if (tbody) tbody.innerHTML = '';
                }
            }, 250);
        });

        // Mobile card click delegation
        onModuleReady(function () {
            var invCardsContainer = document.getElementById('invCardsContainer');
            if (invCardsContainer) {
                invCardsContainer.addEventListener('click', function (e) {
                    if (e.target.closest('.app-mobile-card-more')) return;
                    var card = e.target.closest('.app-mobile-card[data-inv-id]');
                    if (!card) return;
                    var id = parseInt(card.getAttribute('data-inv-id'), 10);
                    var p = invLastItems.find(function (i) { return i.id === id; });
                    // No editar al click en mobile por ahora (cada categoría tiene campos distintos)
                });
            }

            // Carga inicial: Accesorios activo por defecto (primera categoría alfabéticamente)
            cambiarCategoria('accesorios', document.querySelector('.filter-chip[data-cat="accesorios"]'));
        });

        // ================================================================
        // FORMULARIO SERVICIOS GENERALES
        // ================================================================
        const offcanvasServicioGeneral = new bootstrap.Offcanvas(document.getElementById('offcanvasServicioGeneral'));

        // --- Acciones dinámicas (chips) ---
        let accionesArr = [];

        function renderAccionChips() {
            const container = document.getElementById('accionesContainer');
            container.innerHTML = accionesArr.map((a, i) =>
                '<span class="accion-chip">' +
                    escapeHtml(a) +
                    ' <span class="chip-remove" data-idx="' + i + '"><i class="bi bi-x"></i></span>' +
                '</span>'
            ).join('');
        }

        document.getElementById('btnAddAccion').addEventListener('click', function () {
            const input = document.getElementById('sgAccionInput');
            const val = input.value.trim();
            if (val === '') return;
            accionesArr.push(val);
            input.value = '';
            renderAccionChips();
            input.focus();
        });

        // Enter en input de acción
        document.getElementById('sgAccionInput').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('btnAddAccion').click();
            }
        });

        // Eliminar chip
        document.getElementById('accionesContainer').addEventListener('click', function (e) {
            const removeBtn = e.target.closest('.chip-remove');
            if (!removeBtn) return;
            const idx = parseInt(removeBtn.getAttribute('data-idx'), 10);
            accionesArr.splice(idx, 1);
            renderAccionChips();
        });

        // --- Selector de Gama ---
        document.querySelectorAll('.gama-option').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.gama-option').forEach(function (el) { el.classList.remove('active'); });
                btn.classList.add('active');
                document.getElementById('gamaHidden').value = btn.getAttribute('data-value');
            });
        });

        // --- Toggle Garantía ---
        document.getElementById('garantiaSwitch').addEventListener('change', function () {
            const label = document.getElementById('garantiaLabel');
            const hidden = document.getElementById('garantiaHidden');
            if (this.checked) {
                hidden.value = 'SI';
                label.textContent = 'Sí';
                label.style.color = '#4ade80';
            } else {
                hidden.value = 'NO';
                label.textContent = 'NO';
                label.style.color = 'rgba(248,250,252,0.6)';
            }
        });

        // --- Feedback helper ---
        function showSgFeedback(type, message) {
            const el = document.getElementById('sgFeedback');
            el.className = type === 'error' ? 'sg-feedback-error mb-3' : 'sg-feedback-success mb-3';
            el.textContent = message;
            el.classList.remove('d-none');
        }

        function hideSgFeedback() {
            const el = document.getElementById('sgFeedback');
            el.className = 'd-none mb-3';
            el.textContent = '';
        }

        // --- Submit del formulario Servicios Generales ---
        document.getElementById('formServicioGeneral').addEventListener('submit', function (e) {
            e.preventDefault();
            hideSgFeedback();

            const subcategoria = document.getElementById('sgSubcategoria').value;
            const gama = document.getElementById('gamaHidden').value;
            const precio = document.getElementById('sgPrecio').value;

            if (!subcategoria) { showSgFeedback('error', 'Selecciona una subcategoría.'); return; }
            if (!gama) { showSgFeedback('error', 'Selecciona una gama.'); return; }

            const sistemasChecked = document.querySelectorAll('input[name="sistemas_operativos[]"]:checked');
            if (sistemasChecked.length === 0) { showSgFeedback('error', 'Selecciona al menos un sistema operativo.'); return; }
            if (!precio || parseFloat(precio) <= 0) { showSgFeedback('error', 'El precio debe ser mayor a 0.'); return; }

            const fd = new FormData();
            fd.append('action', 'create');
            fd.append('subcategoria', subcategoria);
            fd.append('gama', gama);
            fd.append('garantia', document.getElementById('garantiaHidden').value);
            fd.append('tiempo_entrega', document.getElementById('sgTiempoEntrega').value);
            fd.append('precio', precio);
            fd.append('nota', document.getElementById('sgNota').value);
            fd.append('sistemas_operativos', Array.from(sistemasChecked).map(function (cb) { return cb.value; }).join(','));
            fd.append('acciones', JSON.stringify(accionesArr));

            submitFormAjax(fd, '../api/inventario/servicios_generales', 'btnGuardarServicio', 'sgFeedback', 'Servicio creado correctamente.', function () {
                offcanvasServicioGeneral.hide();
            });
        });

        // ================================================================
        // HELPERS GENÉRICOS PARA SUBMIT Y FEEDBACK
        // ================================================================

        function showFeedback(elId, type, msg) {
            var el = document.getElementById(elId);
            el.className = (type === 'error' ? 'sg-feedback-error' : 'sg-feedback-success') + ' mb-3';
            el.textContent = msg;
        }

        function hideFeedback(elId) {
            var el = document.getElementById(elId);
            el.className = 'd-none mb-3';
            el.textContent = '';
        }

        function submitFormAjax(fd, url, btnId, feedbackId, successMsg, onSuccess) {
            hideFeedback(feedbackId);
            var btn = document.getElementById(btnId);
            var originalHTML = btn.innerHTML;
            btn.innerHTML = '<span class="btn-loading" style="display:inline-block;width:1rem;height:1rem;"></span> Guardando...';
            btn.disabled = true;

            fetch(url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                    if (data.ok) {
                        showFeedback(feedbackId, 'success', successMsg);
                        setTimeout(function () {
                            hideFeedback(feedbackId);
                            if (onSuccess) onSuccess();
                            // Recargar tabla y KPIs de la categoría activa
                            loadCategoria(categoriaActiva);
                            loadKpis(categoriaActiva);
                            document.getElementById('toastMsg').innerText = successMsg;
                            toastSuccess.show();
                        }, 800);
                    } else {
                        showFeedback(feedbackId, 'error', data.message || 'Error al guardar.');
                    }
                })
                .catch(function (err) {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                    showFeedback(feedbackId, 'error', 'Error de conexión. Intenta de nuevo.');
                    console.error(err);
                });
        }

        // ================================================================
        // FORMULARIO BATERÍAS
        // ================================================================

        var offcanvasBateria = new bootstrap.Offcanvas(document.getElementById('offcanvasBateria'));

        function abrirFormBaterias() {
            modalCategoria.hide();
            document.getElementById('formBateria').reset();
            hideFeedback('batFeedback');
            offcanvasBateria.show();
        }

        document.getElementById('formBateria').addEventListener('submit', function (e) {
            e.preventDefault();
            hideFeedback('batFeedback');

            var marca = document.getElementById('batMarca').value.trim();
            var modelo = document.getElementById('batModelo').value.trim();
            if (!marca) { showFeedback('batFeedback', 'error', 'La marca es requerida.'); return; }
            if (!modelo) { showFeedback('batFeedback', 'error', 'El modelo de batería es requerido.'); return; }

            var calChecked = document.querySelectorAll('input[name="bat_calidad[]"]:checked');
            if (calChecked.length === 0) { showFeedback('batFeedback', 'error', 'Selecciona al menos una calidad.'); return; }

            var tipoChecked = document.querySelectorAll('input[name="bat_tipo[]"]:checked');
            if (tipoChecked.length === 0) { showFeedback('batFeedback', 'error', 'Selecciona al menos un tipo.'); return; }

            var tiempoChecked = document.querySelectorAll('input[name="bat_tiempo[]"]:checked');
            if (tiempoChecked.length === 0) { showFeedback('batFeedback', 'error', 'Selecciona al menos un tiempo.'); return; }

            var fd = new FormData();
            fd.append('marca', marca);
            fd.append('modelo_bateria', modelo);
            fd.append('calidad', Array.from(calChecked).map(function (c) { return c.value; }).join(','));
            fd.append('tipo', Array.from(tipoChecked).map(function (c) { return c.value; }).join(','));
            fd.append('tiempo', Array.from(tiempoChecked).map(function (c) { return c.value; }).join(','));
            fd.append('notas', document.getElementById('batNotas').value);
            fd.append('precio', (document.getElementById('batPrecio') && document.getElementById('batPrecio').value) || '0');
            fd.append('stock', (document.getElementById('batStock') && document.getElementById('batStock').value) || '0');
            fd.append('codigo', (document.getElementById('batCodigo') && document.getElementById('batCodigo').value) || '');

            submitFormAjax(fd, '../api/inventario/crear_bateria', 'btnGuardarBateria', 'batFeedback', 'Batería creada correctamente.', function () {
                offcanvasBateria.hide();
            });
        });

        // ================================================================
        // FORMULARIO ACCESORIOS
        // ================================================================

        var offcanvasAccesorio = new bootstrap.Offcanvas(document.getElementById('offcanvasAccesorio'));

        function abrirFormAccesorios() {
            modalCategoria.hide();
            document.getElementById('formAccesorio').reset();
            document.getElementById('accId').value = '';
            document.getElementById('offcanvasAccesorioTitle').textContent = 'Nuevo Accesorio';
            document.getElementById('btnGuardarAccesorioLabel').textContent = 'Guardar Producto';
            hideFeedback('accFeedback');
            cargarCatalogo('../api/catalogos?tipo=subcategorias&action=listar', 'accSubcategoria');
            cargarCatalogo('../api/catalogos?tipo=marcas&action=listar', 'accMarca');
            cargarCatalogo('../api/catalogos?tipo=colores&action=listar', 'accColor');
            offcanvasAccesorio.show();
        }

        function abrirEditarAccesorio(id) {
            var p = invAllItems.find(function (item) { return item.id == id; });
            if (!p) return;

            document.getElementById('formAccesorio').reset();
            document.getElementById('accId').value = p.id;
            document.getElementById('offcanvasAccesorioTitle').textContent = 'Editar Accesorio';
            document.getElementById('btnGuardarAccesorioLabel').textContent = 'Guardar Cambios';
            hideFeedback('accFeedback');

            // Carga los selects y luego pre-selecciona los valores del accesorio
            function preselect(selectId, val) {
                var sel = document.getElementById(selectId);
                function trySet() {
                    var opt = sel.querySelector('option[value="' + val + '"]');
                    if (opt) { sel.value = val; }
                    else { setTimeout(trySet, 100); }
                }
                trySet();
            }

            cargarCatalogo('../api/catalogos?tipo=subcategorias&action=listar', 'accSubcategoria');
            cargarCatalogo('../api/catalogos?tipo=marcas&action=listar', 'accMarca');
            cargarCatalogo('../api/catalogos?tipo=colores&action=listar', 'accColor');

            preselect('accSubcategoria', p.subcategoria_id);
            preselect('accMarca', p.marca_id);
            preselect('accColor', p.color_id);

            document.getElementById('accCodigo').value = p.codigo || '';
            document.getElementById('accNombre').value = p.nombre_producto || '';
            document.getElementById('accStock').value = p.stock || 0;
            document.getElementById('accPrecio').value = parseFloat(p.precio || 0).toFixed(2);

            offcanvasAccesorio.show();
        }

        document.getElementById('formAccesorio').addEventListener('submit', function (e) {
            e.preventDefault();
            hideFeedback('accFeedback');

            var editId = document.getElementById('accId').value;
            var subId = document.getElementById('accSubcategoria').value;
            var marcaId = document.getElementById('accMarca').value;
            var colorId = document.getElementById('accColor').value;
            var codigo = document.getElementById('accCodigo').value.trim();
            var nombre = document.getElementById('accNombre').value.trim();
            var stock = document.getElementById('accStock').value;
            var precio = document.getElementById('accPrecio').value;

            if (!subId) { showFeedback('accFeedback', 'error', 'Selecciona una subcategoría.'); return; }
            if (!marcaId) { showFeedback('accFeedback', 'error', 'Selecciona una marca.'); return; }
            if (!codigo) { showFeedback('accFeedback', 'error', 'El código es requerido.'); return; }
            if (!nombre) { showFeedback('accFeedback', 'error', 'El nombre del producto es requerido.'); return; }
            if (!precio || parseFloat(precio) <= 0) { showFeedback('accFeedback', 'error', 'El precio debe ser mayor a 0.'); return; }
            if (!colorId) { showFeedback('accFeedback', 'error', 'Selecciona un color.'); return; }

            var fd = new FormData();
            fd.append('subcategoria_id', subId);
            fd.append('marca_id', marcaId);
            fd.append('codigo', codigo);
            fd.append('nombre_producto', nombre);
            fd.append('stock', stock);
            fd.append('precio', precio);
            fd.append('color_id', colorId);

            if (editId) {
                fd.append('id', editId);
                submitFormAjax(fd, '../api/inventario/actualizar_accesorio', 'btnGuardarAccesorio', 'accFeedback', 'Accesorio actualizado correctamente.', function () {
                    offcanvasAccesorio.hide();
                });
            } else {
                submitFormAjax(fd, '../api/inventario/crear_accesorio', 'btnGuardarAccesorio', 'accFeedback', 'Accesorio creado correctamente.', function () {
                    offcanvasAccesorio.hide();
                });
            }
        });

        // ================================================================
        // FORMULARIO PANTALLAS
        // ================================================================

        var offcanvasPantalla = new bootstrap.Offcanvas(document.getElementById('offcanvasPantalla'));

        function abrirFormPantallas() {
            modalCategoria.hide();
            document.getElementById('formPantalla').reset();
            document.getElementById('panCalidadHidden').value = '';
            document.getElementById('panTiempoHidden').value = '';
            document.querySelectorAll('.pan-calidad-opt').forEach(function (el) { el.classList.remove('active'); });
            document.querySelectorAll('.pan-tiempo-opt').forEach(function (el) { el.classList.remove('active'); });
            hideFeedback('panFeedback');
            cargarCatalogo('../api/catalogos?tipo=modelos&action=listar', 'panModelo');
            cargarCatalogo('../api/catalogos?tipo=modelos_tecnicos&action=listar', 'panModeloTecnico');
            offcanvasPantalla.show();
        }

        // Calidad — selección única
        document.querySelectorAll('.pan-calidad-opt').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.pan-calidad-opt').forEach(function (el) { el.classList.remove('active'); });
                btn.classList.add('active');
                document.getElementById('panCalidadHidden').value = btn.getAttribute('data-value');
            });
        });

        // Tiempo — selección única
        document.querySelectorAll('.pan-tiempo-opt').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.pan-tiempo-opt').forEach(function (el) { el.classList.remove('active'); });
                btn.classList.add('active');
                document.getElementById('panTiempoHidden').value = btn.getAttribute('data-value');
            });
        });

        document.getElementById('formPantalla').addEventListener('submit', function (e) {
            e.preventDefault();
            hideFeedback('panFeedback');

            var modeloId = document.getElementById('panModelo').value;
            var mtecnicoId = document.getElementById('panModeloTecnico').value;
            var calidad = document.getElementById('panCalidadHidden').value;
            var precio = document.getElementById('panPrecio').value;
            var tiempo = document.getElementById('panTiempoHidden').value;

            if (!modeloId) { showFeedback('panFeedback', 'error', 'Selecciona un modelo.'); return; }
            if (!mtecnicoId) { showFeedback('panFeedback', 'error', 'Selecciona un modelo técnico.'); return; }
            if (!calidad) { showFeedback('panFeedback', 'error', 'Selecciona una calidad.'); return; }
            if (!precio || parseFloat(precio) <= 0) { showFeedback('panFeedback', 'error', 'El precio debe ser mayor a 0.'); return; }
            if (!tiempo) { showFeedback('panFeedback', 'error', 'Selecciona un tiempo de entrega.'); return; }

            var fd = new FormData();
            fd.append('modelo_id', modeloId);
            fd.append('modelo_tecnico_id', mtecnicoId);
            fd.append('calidad', calidad);
            fd.append('precio', precio);
            fd.append('tiempo', tiempo);
            fd.append('nota', document.getElementById('panNota').value);

            submitFormAjax(fd, '../api/inventario/crear_pantalla', 'btnGuardarPantalla', 'panFeedback', 'Pantalla creada correctamente.', function () {
                offcanvasPantalla.hide();
            });
        });

        // ================================================================
        // CATÁLOGOS DINÁMICOS (sistema reutilizable)
        // ================================================================

        // Mapeo de tipos de catálogo â†’ URL y select target
        var catalogConfig = {
            'acc_subcategoria':    { listUrl: '../api/catalogos?tipo=subcategorias&action=listar',    addTipo: 'subcategorias',    selectId: 'accSubcategoria' },
            'acc_marca':           { listUrl: '../api/catalogos?tipo=marcas&action=listar',           addTipo: 'marcas',           selectId: 'accMarca' },
            'acc_color':           { listUrl: '../api/catalogos?tipo=colores&action=listar',          addTipo: 'colores',          selectId: 'accColor' },
            'pan_modelo':          { listUrl: '../api/catalogos?tipo=modelos&action=listar',           addTipo: 'modelos',           selectId: 'panModelo' },
            'pan_modelo_tecnico':  { listUrl: '../api/catalogos?tipo=modelos_tecnicos&action=listar',  addTipo: 'modelos_tecnicos',  selectId: 'panModeloTecnico' },
        };

        var catalogoActual = null;
        var modalCatalogo = null;

        // Carga genérica de opciones en un <select>
        function cargarCatalogo(url, selectId) {
            var sel = document.getElementById(selectId);
            sel.innerHTML = '<option value="" disabled selected>Cargando...</option>';
            fetch(url)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok && data.items) {
                        sel.innerHTML = '<option value="" disabled selected>Selecciona...</option>';
                        data.items.forEach(function (item) {
                            var opt = document.createElement('option');
                            opt.value = item.id;
                            opt.textContent = item.nombre;
                            sel.appendChild(opt);
                        });
                    }
                })
                .catch(function () {
                    sel.innerHTML = '<option value="" disabled selected>Error al cargar</option>';
                });
        }

        // Abrir modal para agregar nuevo valor
        function abrirModalCatalogo(tipo, label) {
            catalogoActual = tipo;
            if (!modalCatalogo) {
                modalCatalogo = new bootstrap.Modal(document.getElementById('modalAgregarCatalogo'));
            }
            document.getElementById('modalCatalogoTitle').textContent = 'Agregar ' + label;
            document.getElementById('catalogoNombreInput').value = '';
            document.getElementById('catalogoNombreInput').placeholder = 'Nombre de ' + label + '...';
            var fb = document.getElementById('catalogoFeedback');
            fb.className = 'd-none mb-3';
            fb.textContent = '';
            modalCatalogo.show();
            setTimeout(function () { document.getElementById('catalogoNombreInput').focus(); }, 300);
        }

        // Enter en modal
        document.getElementById('catalogoNombreInput').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('btnGuardarCatalogo').click();
            }
        });

        // Guardar valor de catálogo
        document.getElementById('btnGuardarCatalogo').addEventListener('click', function () {
            var nombre = document.getElementById('catalogoNombreInput').value.trim();
            var fb = document.getElementById('catalogoFeedback');

            if (!nombre) {
                fb.className = 'sg-feedback-error mb-3';
                fb.textContent = 'El nombre es requerido.';
                return;
            }

            if (!catalogoActual || !catalogConfig[catalogoActual]) return;

            var config = catalogConfig[catalogoActual];
            var btn = document.getElementById('btnGuardarCatalogo');
            var originalHTML = btn.innerHTML;
            btn.innerHTML = '<span class="btn-loading" style="display:inline-block;width:1rem;height:1rem;"></span>';
            btn.disabled = true;

            var fd = new FormData();
            fd.append('action', 'agregar');
            fd.append('tipo', config.addTipo);
            fd.append('nombre', nombre);

            fetch('../api/catalogos', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;

                    if (data.ok) {
                        // Agregar al select y seleccionar
                        var sel = document.getElementById(config.selectId);
                        var opt = document.createElement('option');
                        opt.value = data.id;
                        opt.textContent = data.nombre;
                        sel.appendChild(opt);
                        sel.value = data.id;

                        fb.className = 'sg-feedback-success mb-3';
                        fb.textContent = data.message || 'Agregado correctamente.';

                        setTimeout(function () { modalCatalogo.hide(); }, 600);
                    } else {
                        fb.className = 'sg-feedback-error mb-3';
                        fb.textContent = data.message || 'Error al guardar.';
                    }
                })
                .catch(function () {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                    fb.className = 'sg-feedback-error mb-3';
                    fb.textContent = 'Error de conexión.';
                });
        });

        // ================================================================
        // IMPORTAR INVENTARIO (modal 3 pasos)
        // ================================================================
        var importStep = 1;
        var importCategoriaSeleccionada = null;
        var importParsedRows = [];      // filas ya validadas para enviar
        var importInvalidRows = [];     // filas con errores (para mostrar antes de confirmar)

        var importColumnConfig = {
            servicios: {
                headers: ['Subcategoría', 'Gama', 'Sistemas operativos', 'Garantía', 'Tiempo entrega', 'Precio', 'Nota', 'Acciones'],
                keys: ['subcategoria', 'gama', 'sistemas_operativos', 'garantia', 'tiempo_entrega', 'precio', 'nota', 'acciones'],
                required: ['subcategoria', 'gama', 'sistemas_operativos', 'precio'],
                sample: ['desbloqueo', 'media', 'Android,iPhone OS', 'SI', '24 horas', '350.00', 'Incluye respaldo', 'Desbloquear patrón|Respaldar datos']
            },
            baterias: {
                headers: ['Marca', 'Calidad', 'Tipo', 'Modelo batería', 'Tiempo', 'Notas'],
                keys: ['marca', 'calidad', 'tipo', 'modelo_bateria', 'tiempo', 'notas'],
                required: ['marca', 'calidad', 'tipo', 'modelo_bateria', 'tiempo'],
                sample: ['Samsung', 'Genérico,Original', 'Interna', 'BLP927', '2-3 días full', 'Para Galaxy S10']
            },
            pantallas: {
                headers: ['Modelo', 'Modelo técnico', 'Calidad', 'Precio', 'Tiempo', 'Nota'],
                keys: ['modelo', 'modelo_tecnico', 'calidad', 'precio', 'tiempo', 'nota'],
                required: ['modelo', 'modelo_tecnico', 'calidad', 'precio', 'tiempo'],
                sample: ['iPhone 12', 'DNP', 'Original', '1200.00', 'Instalación inmediata 4hrs', 'En stock']
            },
            accesorios: {
                headers: ['Subcategoría', 'Marca', 'Color', 'Código', 'Nombre producto', 'Stock', 'Precio'],
                keys: ['subcategoria', 'marca', 'color', 'codigo', 'nombre_producto', 'stock', 'precio'],
                required: ['subcategoria', 'marca', 'color', 'codigo', 'nombre_producto', 'stock', 'precio'],
                sample: ['Fundas', 'Apple', 'Negro', 'F-APP-01', 'Funda iPhone 12', '10', '199.00']
            }
        };

        var modalImportar = null;
        function abrirModalImportar() {
            if (!modalImportar) modalImportar = new bootstrap.Modal(document.getElementById('modalImportarInventario'));
            importStep = 1;
            importCategoriaSeleccionada = null;
            importParsedRows = [];
            importInvalidRows = [];
            document.querySelectorAll('#modalImportarInventario .cat-selector-card').forEach(function (b) { b.classList.remove('border-primary'); b.style.borderWidth = '1.5px'; });
            document.getElementById('btnImportPaso1Siguiente').disabled = true;
            document.getElementById('importFileInput').value = '';
            document.getElementById('importValidacionAlert').classList.add('d-none');
            document.getElementById('importFilasInvalidas').classList.add('d-none');
            document.getElementById('importProgress').classList.add('d-none');
            document.getElementById('importResultado').classList.add('d-none');
            document.getElementById('btnImportarEnviar').disabled = true;
            importarIrPaso(1);
            modalImportar.show();
        }

        function seleccionarCategoriaImportar(cat, btn) {
            importCategoriaSeleccionada = cat;
            document.querySelectorAll('#modalImportarInventario .cat-selector-card').forEach(function (b) {
                b.classList.remove('border-primary');
                b.style.borderWidth = '1.5px';
            });
            if (btn) {
                btn.classList.add('border-primary');
                btn.style.borderWidth = '2px';
            }
            document.getElementById('btnImportPaso1Siguiente').disabled = false;
            var nombres = { accesorios: 'Accesorios', baterias: 'Baterías', pantallas: 'Pantallas', servicios: 'Servicios Generales' };
            document.getElementById('importCategoriaNombre').textContent = 'Categoría: ' + (nombres[cat] || cat);
        }

        function importarIrPaso(paso) {
            importStep = paso;
            document.querySelectorAll('.import-paso').forEach(function (el) { el.classList.add('d-none'); });
            document.getElementById('importPaso' + paso).classList.remove('d-none');
            document.querySelectorAll('#modalImportarInventario .import-step-dot').forEach(function (dot, i) {
                var n = i + 1;
                dot.classList.remove('active');
                dot.style.background = n <= paso ? 'var(--primary)' : 'rgba(255,255,255,0.1)';
                dot.style.color = n <= paso ? '#fff' : '#94a3b8';
            });
            if (paso === 3) {
                document.getElementById('importFileInput').value = '';
                document.getElementById('btnImportarEnviar').disabled = true;
            }
        }

        function descargarPlantillaImportar() {
            if (!importCategoriaSeleccionada || typeof XLSX === 'undefined') return;
            var cfg = importColumnConfig[importCategoriaSeleccionada];
            if (!cfg) return;
            var wb = XLSX.utils.book_new();
            var wsData = [cfg.headers, cfg.sample];
            var ws = XLSX.utils.aoa_to_sheet(wsData);
            XLSX.utils.book_append_sheet(wb, ws, 'Plantilla');
            var nombre = 'plantilla_inventario_' + importCategoriaSeleccionada + '.xlsx';
            XLSX.writeFile(wb, nombre);
        }

        function normalizarHeader(h) {
            if (typeof h !== 'string') return '';
            return h.replace(/\s+/g, ' ').trim().toLowerCase();
        }

        function mapRowToKeys(headers, keys, rowArr) {
            var obj = {};
            var normHeaders = headers.map(function (h) { return normalizarHeader(h); });
            for (var i = 0; i < keys.length; i++) {
                var val = rowArr[i];
                if (val !== undefined && val !== null) val = String(val).trim();
                else val = '';
                obj[keys[i]] = val;
            }
            return obj;
        }

        function validarColumnasArchivo(headers, cfg) {
            var expected = cfg.headers.map(function (h) { return normalizarHeader(h); });
            var actual = headers.map(function (h) { return normalizarHeader(h); });
            var missing = [];
            for (var i = 0; i < expected.length; i++) {
                if (actual.indexOf(expected[i]) === -1) missing.push(cfg.headers[i]);
            }
            return missing;
        }

        document.getElementById('importFileInput').addEventListener('change', function () {
            var file = this.files[0];
            var cat = importCategoriaSeleccionada;
            var alertEl = document.getElementById('importValidacionAlert');
            var filasInvalidasEl = document.getElementById('importFilasInvalidas');
            var listaInvalidas = document.getElementById('importFilasInvalidasLista');
            var resultadoEl = document.getElementById('importResultado');
            var progressEl = document.getElementById('importProgress');
            var btnEnviar = document.getElementById('btnImportarEnviar');

            alertEl.classList.add('d-none');
            filasInvalidasEl.classList.add('d-none');
            resultadoEl.classList.add('d-none');
            progressEl.classList.add('d-none');
            importParsedRows = [];
            importInvalidRows = [];

            if (!file || !cat) return;
            var cfg = importColumnConfig[cat];
            if (!cfg) return;

            var reader = new FileReader();
            var isCsv = file.name.toLowerCase().endsWith('.csv');
            reader.onload = function (e) {
                try {
                    var data = e.target.result;
                    var wb = isCsv
                        ? XLSX.read(data, { type: 'string', raw: true })
                        : XLSX.read(new Uint8Array(data), { type: 'array', cellDates: true });
                    var firstSheet = wb.SheetNames[0];
                    var ws = wb.Sheets[firstSheet];
                    var json = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });
                    if (json.length < 2) {
                        alertEl.className = 'alert alert-danger';
                        alertEl.textContent = 'El archivo debe tener al menos una fila de encabezados y una fila de datos.';
                        alertEl.classList.remove('d-none');
                        btnEnviar.disabled = true;
                        return;
                    }
                    var headerRow = json[0].map(function (c) { return c !== null && c !== undefined ? String(c) : ''; });
                    var missingCols = validarColumnasArchivo(headerRow, cfg);
                    if (missingCols.length > 0) {
                        alertEl.className = 'alert alert-danger';
                        alertEl.innerHTML = 'Columnas requeridas no encontradas o con nombre incorrecto: <strong>' + missingCols.join(', ') + '</strong>. Usa la plantilla de esta categoría.';
                        alertEl.classList.remove('d-none');
                        btnEnviar.disabled = true;
                        return;
                    }
                    var headerNorm = headerRow.map(function (h) { return normalizarHeader(h); });
                    var keyIdx = [];
                    for (var idx = 0; idx < cfg.keys.length; idx++) {
                        var expectNorm = normalizarHeader(cfg.headers[idx]);
                        var colIndex = headerNorm.indexOf(expectNorm);
                        keyIdx[idx] = colIndex >= 0 ? colIndex : idx;
                    }
                    var validRows = [];
                    var invalidRows = [];
                    for (var r = 1; r < json.length; r++) {
                        var rowArr = json[r];
                        if (!Array.isArray(rowArr)) rowArr = [];
                        var obj = {};
                        var rowIsEmpty = true;
                        for (var k = 0; k < cfg.keys.length; k++) {
                            var colIdx = keyIdx[k] !== undefined ? keyIdx[k] : k;
                            var val = rowArr[colIdx];
                            if (val !== undefined && val !== null) val = String(val).trim();
                            else val = '';
                            if (val !== '') rowIsEmpty = false;
                            obj[cfg.keys[k]] = val;
                        }
                        if (rowIsEmpty) continue;
                        var faltan = cfg.required.filter(function (req) {
                            var v = obj[req];
                            return v === '' || v === null || v === undefined;
                        });
                        if (faltan.length > 0) {
                            invalidRows.push({ row: r + 1, obj: obj, missing: faltan });
                        } else {
                            validRows.push(obj);
                        }
                    }
                    importParsedRows = validRows;
                    importInvalidRows = invalidRows;
                    if (invalidRows.length > 0) {
                        filasInvalidasEl.classList.remove('d-none');
                        listaInvalidas.innerHTML = invalidRows.map(function (x) {
                            return '<li>Fila ' + x.row + ': faltan ' + x.missing.join(', ') + '</li>';
                        }).join('');
                    }
                    if (validRows.length === 0) {
                        alertEl.className = 'alert alert-warning';
                        alertEl.textContent = 'No hay filas válidas para importar. Todas tienen campos obligatorios incompletos.';
                        alertEl.classList.remove('d-none');
                        btnEnviar.disabled = true;
                    } else {
                        alertEl.className = 'alert alert-success';
                        alertEl.textContent = 'Se encontraron ' + validRows.length + ' fila(s) válida(s).' + (invalidRows.length > 0 ? ' Hay ' + invalidRows.length + ' fila(s) con datos incompletos.' : '');
                        alertEl.classList.remove('d-none');
                        btnEnviar.disabled = false;
                    }
                } catch (err) {
                    alertEl.className = 'alert alert-danger';
                    alertEl.textContent = 'Error al leer el archivo: ' + (err.message || 'formato no válido');
                    alertEl.classList.remove('d-none');
                    btnEnviar.disabled = true;
                }
            };
            if (isCsv) {
                reader.readAsText(file, 'UTF-8');
            } else {
                reader.readAsArrayBuffer(file);
            }
        });

        function enviarImportacion() {
            if (importParsedRows.length === 0 || !importCategoriaSeleccionada) return;
            var btn = document.getElementById('btnImportarEnviar');
            var resultadoEl = document.getElementById('importResultado');
            var progressEl = document.getElementById('importProgress');
            var progressBar = document.getElementById('importProgressBar');
            var progressPercent = document.getElementById('importProgressPercent');
            var progressDetail = document.getElementById('importProgressDetail');
            var CHUNK_SIZE = 25;

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Importando...';
            resultadoEl.classList.add('d-none');
            progressEl.classList.remove('d-none');
            progressBar.style.width = '0%';
            progressPercent.textContent = '0%';
            progressDetail.textContent = 'Preparando...';

            var total = importParsedRows.length;
            var totalImported = 0;
            var allErrors = [];
            var chunks = [];

            for (var i = 0; i < total; i += CHUNK_SIZE) {
                chunks.push(importParsedRows.slice(i, i + CHUNK_SIZE));
            }

            var completed = 0;
            var cancelled = false;
            var chunkRetries = {};

            function updateProgress() {
                var pct = total > 0 ? Math.round((completed / total) * 100) : 0;
                progressBar.style.width = pct + '%';
                progressBar.setAttribute('aria-valuenow', pct);
                progressPercent.textContent = pct + '%';
                progressDetail.textContent = 'Procesados ' + completed + ' de ' + total + ' registros...';
            }

            function sendChunk(index) {
                if (cancelled || index >= chunks.length) {
                    finishImport();
                    return;
                }

                if (chunkRetries[index] === undefined) chunkRetries[index] = 0;

                var chunk = chunks[index];
                console.log('[Importar] Lote ' + (index + 1) + '/' + chunks.length + ' — ' + chunk.length + ' filas — intento #' + (chunkRetries[index] + 1));

                var controller = new AbortController();
                var timeoutId = setTimeout(function () {
                    console.warn('[Importar] Lote ' + (index + 1) + ' — TIMEOUT (>30s), abortando...');
                    controller.abort();
                }, 30000);

                fetch('../api/inventario/importar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ categoria: importCategoriaSeleccionada, rows: chunk }),
                    signal: controller.signal
                })
                .then(function (r) {
                    clearTimeout(timeoutId);
                    console.log('[Importar] Lote ' + (index + 1) + ' — HTTP ' + r.status);

                    if (!r.ok) {
                        return r.text().then(function (txt) {
                            console.error('[Importar] Lote ' + (index + 1) + ' — Error HTTP ' + r.status + ': ' + txt.substring(0, 500));
                            throw new Error('HTTP ' + r.status + ' — ' + txt.substring(0, 200));
                        });
                    }

                    var ct = r.headers.get('content-type') || '';
                    if (ct.indexOf('application/json') === -1) {
                        console.error('[Importar] Lote ' + (index + 1) + ' — Content-Type inesperado: ' + ct);
                        return r.text().then(function (txt) {
                            throw new Error('El servidor no devolvio JSON: ' + txt.substring(0, 200));
                        });
                    }

                    return r.json();
                })
                .then(function (data) {
                    console.log('[Importar] Lote ' + (index + 1) + ' — ok=' + data.ok + ', imported=' + (data.imported || 0) + ', errors=' + (data.errors ? data.errors.length : 0));
                    chunkRetries[index] = 0;

                    if (data.ok) {
                        totalImported += (data.imported || 0);
                        if (data.errors && data.errors.length) {
                            allErrors = allErrors.concat(data.errors);
                        }
                    } else {
                        allErrors.push(data.message || 'Error en lote ' + (index + 1));
                        if (data.errors && data.errors.length) {
                            allErrors = allErrors.concat(data.errors);
                        }
                    }
                    completed += chunk.length;
                    updateProgress();
                    sendChunk(index + 1);
                })
                .catch(function (err) {
                    clearTimeout(timeoutId);
                    var retryCount = chunkRetries[index] || 0;
                    var maxRetries = 3;
                    var isAbort = (err && err.name === 'AbortError');

                    console.error('[Importar] Lote ' + (index + 1) + ' — FALLO' + (isAbort ? ' (timeout/abort)' : '') + ': ' + (err && err.message ? err.message : err) + ' — reintento ' + (retryCount + 1) + '/' + maxRetries);

                    if (retryCount < maxRetries) {
                        chunkRetries[index] = retryCount + 1;
                        allErrors.push('Error de conexion en lote ' + (index + 1) + '. Reintentando (' + (retryCount + 1) + '/' + maxRetries + ')...');
                        setTimeout(function () { sendChunk(index); }, 1000);
                    } else {
                        allErrors.push('Error de conexion en lote ' + (index + 1) + ' — ABANDONADO tras ' + maxRetries + ' reintentos.');
                        console.error('[Importar] Lote ' + (index + 1) + ' — ABANDONADO tras ' + maxRetries + ' reintentos.');
                        completed += chunk.length;
                        updateProgress();
                        sendChunk(index + 1);
                    }
                });
            }

            function finishImport() {
                progressBar.classList.remove('progress-bar-animated');
                if (totalImported > 0) {
                    progressBar.style.background = '#22c55e';
                }
                progressDetail.textContent = totalImported + ' de ' + total + ' registros importados.';
                resultadoEl.classList.remove('d-none');

                if (totalImported > 0) {
                    resultadoEl.className = 'alert alert-success';
                    resultadoEl.innerHTML = 'Se importaron <strong>' + totalImported + '</strong> de <strong>' + total + '</strong> registro(s).';
                    if (allErrors.length > 0) {
                        resultadoEl.innerHTML += '<ul class="mb-0 mt-2 small">' + allErrors.map(function (e) { return '<li>' + escapeHtml(e); }).join('') + '</ul>';
                    }
                    loadCategoria(categoriaActiva);
                    loadKpis(categoriaActiva);
                    if (document.getElementById('toastMsg')) {
                        document.getElementById('toastMsg').innerText = 'Importacion completada: ' + totalImported + '/' + total + ' registros.';
                        toastSuccess.show();
                    }
                } else {
                    resultadoEl.className = 'alert alert-danger';
                    resultadoEl.textContent = 'No se pudo importar ningun registro.';
                    if (allErrors.length > 0) {
                        resultadoEl.innerHTML += '<ul class="mb-0 mt-2 small">' + allErrors.map(function (e) { return '<li>' + escapeHtml(e); }).join('') + '</ul>';
                    }
                }

                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-upload me-1"></i> Importar';
            }

            updateProgress();
            sendChunk(0);
        }

        function reindexarInventario() {
            if (!confirm('Esto regenerara los indices de busqueda IA para todo el inventario. Puede tardar varios minutos. Continuar?')) return;

            showConfirm('Reindexar', 'Regenerar indices de busqueda semantica?', function () {
                modalConfirmacion.hide();
                document.getElementById('toastMsg').innerText = 'Reindexacion iniciada...';
                toastSuccess.show();

                fetch('../api/inventario/reindexar', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok) {
                            document.getElementById('toastMsg').innerText = 'Reindexacion completada: ' + data.total_indexados + ' productos indexados.';
                            toastSuccess.show();
                        } else {
                            document.getElementById('errorMsg').innerText = data.error || 'Error al reindexar';
                            toastError.show();
                        }
                    })
                    .catch(function () {
                        document.getElementById('errorMsg').innerText = 'Error de conexion';
                        toastError.show();
                    });
            });
        }

        // ================================================================
        // REALTIME — Túnel global único (compartido cross-módulo)
        // Escucha cambios en tablas de inventario y refresca la vista.
        // ================================================================

        var tableToCat = {
            'inv_accesorios': 'accesorios',
            'inv_baterias': 'baterias',
            'inv_pantallas': 'pantallas',
            'inv_servicios_generales': 'servicios'
        };
        var rtRefreshTimer = null;

        window.addEventListener('realtime:change', function (e) {
            var table = e.detail.table;
            var cat = tableToCat[table];
            if (!cat) return;
            if (cat !== categoriaActiva) return;

            // Debounce: agrupar cambios rapidos en una sola recarga
            if (rtRefreshTimer) clearTimeout(rtRefreshTimer);
            rtRefreshTimer = setTimeout(function () {
                rtRefreshTimer = null;
                loadCategoria(categoriaActiva);
                loadKpis(categoriaActiva);
            }, 500);
        });
