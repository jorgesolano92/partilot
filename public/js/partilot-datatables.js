/**
 * Listados del panel: un solo scroll horizontal (contenedor .dataTables_wrapper).
 * Evita doble barra por scrollX + overflow en card-body/table-responsive.
 */
(function ($) {
    if (!$ || typeof $.fn.dataTable !== 'function') {
        return;
    }

    var PARTILOT_DT_LANG = {
        processing: 'Procesando...',
        lengthMenu: 'Mostrar _MENU_ registros',
        zeroRecords: 'No se encontraron resultados',
        emptyTable: 'Ningún dato disponible en esta tabla',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
        infoEmpty: 'Mostrando registros del 0 al 0 de un total de 0 registros',
        infoFiltered: '(filtrado de un total de _MAX_ registros)',
        search: 'Buscar:',
        loadingRecords: 'Cargando...',
        paginate: {
            first: 'Primero',
            last: 'Último',
            next: 'Siguiente',
            previous: 'Anterior',
        },
        aria: {
            sortAscending: ': Activar para ordenar la columna de manera ascendente',
            sortDescending: ': Activar para ordenar la columna de manera descendente',
        },
    };

    function adjustTableColumns(api) {
        if (!api || typeof api.columns !== 'function') {
            return;
        }
        api.columns.adjust();
        setTimeout(function () {
            api.columns.adjust();
        }, 50);
    }

    function normalizeListTableOptions(opts) {
        if (!opts || typeof opts !== 'object') {
            return opts;
        }
        var o = $.extend({}, opts);
        delete o.scrollX;
        delete o.scrollCollapse;
        delete o.scrollY;
        if (o.autoWidth === undefined) {
            o.autoWidth = false;
        }
        o.language = $.extend(true, {}, PARTILOT_DT_LANG, o.language || {});

        var userInitComplete = o.initComplete;
        o.initComplete = function (settings, json) {
            if (typeof userInitComplete === 'function') {
                userInitComplete.call(this, settings, json);
            }
            adjustTableColumns(this.api());
        };

        return o;
    }

    if ($.fn.dataTable.defaults) {
        $.extend(true, $.fn.dataTable.defaults, {
            language: PARTILOT_DT_LANG,
            autoWidth: false,
        });
    }

    var originalDataTable = $.fn.DataTable;

    $.fn.DataTable = function (opts) {
        if (typeof opts === 'object' && opts !== null && !(opts instanceof $)) {
            arguments[0] = normalizeListTableOptions(opts);
        }
        return originalDataTable.apply(this, arguments);
    };

    $.extend($.fn.DataTable, originalDataTable);
    $.fn.DataTable.Api = originalDataTable.Api;

    var resizeTimer;
    $(window).on('resize.partilotDatatables', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            $('.dataTable').each(function () {
                if ($.fn.DataTable.isDataTable(this)) {
                    adjustTableColumns($(this).DataTable());
                }
            });
        }, 120);
    });
})(typeof jQuery !== 'undefined' ? jQuery : null);
