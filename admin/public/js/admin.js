/* Shared behaviour for the admin panel: DataTables setup, delete confirmations, AJAX helpers. */
(function ($) {
    'use strict';

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            Accept: 'application/json',
        },
    });

    toastr.options = { positionClass: 'toast-top-right', timeOut: 3500, progressBar: true };

    const LANGUAGE_URL = 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json';

    function errorMessage(xhr, fallback) {
        return (xhr.responseJSON && xhr.responseJSON.message) || fallback || 'Ocurrió un error inesperado.';
    }

    /**
     * Creates a DataTable and mounts the toolbar (Export, Refresh, Print, Reset, Columns)
     * into the matching [data-toolbar-for] element.
     */
    function init(selector, options) {
        const $table = $(selector);
        const defaultOrder = options.order || [[0, 'desc']];
        const exportColumns = ':visible:not(.no-export)';

        const table = $table.DataTable($.extend(true, {
            language: { url: LANGUAGE_URL },
            responsive: true,
            autoWidth: false,
            stateSave: true,
            stateDuration: 0,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            order: defaultOrder,
            dom: "<'row mb-2'<'col-sm-6'l><'col-sm-6'f>>" +
                "<'row'<'col-12'tr>>" +
                "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>",
            initComplete: function () {
                const api = this.api();
                new $.fn.dataTable.Buttons(api, {
                    dom: { button: { className: 'btn btn-link btn-sm' } },
                    buttons: [
                        {
                            extend: 'collection',
                            text: '<i class="far fa-save"></i> Exportar',
                            autoClose: true,
                            buttons: [
                                { extend: 'copyHtml5', text: 'Copiar', exportOptions: { columns: exportColumns } },
                                { extend: 'csvHtml5', text: 'CSV', exportOptions: { columns: exportColumns } },
                                { extend: 'excelHtml5', text: 'Excel', exportOptions: { columns: exportColumns } },
                            ],
                        },
                        {
                            text: '<i class="fas fa-sync-alt"></i> Refrescar',
                            action: function () {
                                if (api.ajax.url()) {
                                    api.ajax.reload(null, false);
                                } else {
                                    window.location.reload();
                                }
                            },
                        },
                        { extend: 'print', text: '<i class="fas fa-print"></i> Imprimir', exportOptions: { columns: exportColumns } },
                        {
                            text: '<i class="fas fa-undo"></i> Reiniciar',
                            action: function () {
                                api.state.clear();
                                api.columns().visible(true);
                                api.search('').columns().search('');
                                api.order(defaultOrder).page.len(10).draw();
                            },
                        },
                        {
                            extend: 'colvis',
                            text: '<i class="fas fa-eye"></i> Columnas',
                            columns: ':not(.no-colvis)',
                        },
                    ],
                });
                const $toolbar = $('[data-toolbar-for="' + $table.attr('id') + '"]');
                api.buttons().container().appendTo($toolbar);

                if (typeof options.onInit === 'function') {
                    options.onInit(api);
                }
            },
        }, options));

        return table;
    }

    /**
     * Any element with [data-delete-url] asks for confirmation and sends DELETE via AJAX.
     * data-table: id of a DataTable to reload afterwards; otherwise the page reloads.
     */
    $(document).on('click', '[data-delete-url]', function (event) {
        event.preventDefault();
        const $button = $(this);

        Swal.fire({
            title: '¿Eliminar ' + ($button.data('name') || 'este registro') + '?',
            text: $button.data('warning') || 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({ url: $button.data('delete-url'), method: 'DELETE' })
                .done(function (response) {
                    toastr.success(response.message || 'Registro eliminado.');
                    const tableId = $button.data('table');
                    if (tableId && $.fn.dataTable.isDataTable('#' + tableId)) {
                        $('#' + tableId).DataTable().ajax.reload(null, false);
                    } else if ($button.data('redirect')) {
                        window.location.href = $button.data('redirect');
                    } else {
                        window.location.reload();
                    }
                })
                .fail(function (xhr) {
                    toastr.error(errorMessage(xhr, 'No se pudo eliminar.'));
                });
        });
    });

    /** Form-based deletes (non-AJAX), e.g. nested resources. */
    $(document).on('submit', 'form[data-confirm]', function (event) {
        const form = this;
        if (form.dataset.confirmed) {
            return;
        }
        event.preventDefault();

        Swal.fire({
            title: form.dataset.confirm,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
        }).then(function (result) {
            if (result.isConfirmed) {
                form.dataset.confirmed = '1';
                form.submit();
            }
        });
    });

    /** "Select all" checkbox for a permissions group. */
    $(document).on('change', '[data-check-group]', function () {
        const group = $(this).data('check-group');
        $('[data-group="' + group + '"]').prop('checked', this.checked);
    });

    window.AdminTable = { init: init, errorMessage: errorMessage };
})(jQuery);
