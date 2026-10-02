<?php

namespace App\DataTables;

use App\Models\User;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * Shared setup of the panel tables: server-side, rendered through AdminTable.init() (toolbar with
 * Export / Refresh / Print / Reset / Columns) and loaded from the same URL as the page
 * (DataTable::render() answers the AJAX request).
 *
 * Each table defines its query(), dataTable() columns and getColumns(); a page with filters
 * names its form in filtersForm() and the form values are sent with every request.
 */
abstract class BaseDataTable extends DataTable
{
    /**
     * HTML id of the <table>, also used by partials.table-toolbar.
     */
    abstract public function tableId(): string;

    /**
     * @return list<Column>
     */
    abstract protected function getColumns(): array;

    /**
     * [column index, direction] of the initial order.
     *
     * @return array{int, string}
     */
    protected function defaultOrder(): array
    {
        return [0, 'desc'];
    }

    /**
     * Id of a <form> whose non-empty fields are sent as filters, or null.
     */
    protected function filtersForm(): ?string
    {
        return null;
    }

    /**
     * Extra DataTables options for this table.
     *
     * @return array<string, mixed>
     */
    protected function parameters(): array
    {
        return [];
    }

    public function html(): HtmlBuilder
    {
        [$column, $direction] = $this->defaultOrder();
        $form = $this->filtersForm();
        $filters = $form === null ? null : '$("#'.$form.'").serializeArray().forEach(function (field) { if (field.value) data[field.name] = field.value; });';

        return $this->builder()
            ->setTableId($this->tableId())
            ->addTableClass('table table-hover w-100')
            ->columns($this->getColumns())
            // Without the page query string: the filters form sends the current values.
            ->minifiedAjax(url()->current(), $filters)
            ->orderBy($column, $direction)
            ->parameters([
                'processing' => true,
                'serverSide' => true,
                // Filtered lists start from the filters of the URL, not from a saved state.
                ...($form === null ? [] : ['stateSave' => false]),
                ...$this->parameters(),
            ])
            ->setTemplate('datatables.admin-script');
    }

    /**
     * Action buttons column: never sorted, searched, exported or hidden.
     */
    protected function actionColumn(string $class = ''): Column
    {
        return Column::computed('action', 'Acción')->addClass(trim('no-export no-colvis '.$class));
    }

    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    protected function filename(): string
    {
        return $this->tableId().'_'.now()->format('YmdHis');
    }
}
