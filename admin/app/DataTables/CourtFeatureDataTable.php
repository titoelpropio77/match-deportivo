<?php

namespace App\DataTables;

use App\Models\CourtFeature;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

class CourtFeatureDataTable extends BaseDataTable
{
    public function tableId(): string
    {
        return 'court-features-table';
    }

    public function dataTable(Builder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('name', fn (CourtFeature $feature) => ($feature->icon ? '<i class="'.e($feature->icon).' text-muted mr-1"></i>' : '').'<strong>'.e($feature->name).'</strong>')
            ->editColumn('key', fn (CourtFeature $feature) => '<code>'.e($feature->key).'</code>'.($feature->isSystem() ? ' <span class="badge badge-warning" title="Usada por los recargos de las reservas">recargo</span>' : ''))
            ->editColumn('fields_count', fn (CourtFeature $feature) => '<span class="badge badge-secondary">'.$feature->fields_count.'</span>')
            ->orderColumn('fields_count', 'fields_count $1')
            ->addColumn('action', fn (CourtFeature $feature) => view('court-features.partials.actions', ['feature' => $feature])->render())
            ->rawColumns(['name', 'key', 'fields_count', 'action']);
    }

    public function query(CourtFeature $model): Builder
    {
        return $model->newQuery()->withCount('fields');
    }

    protected function defaultOrder(): array
    {
        return [1, 'asc'];
    }

    protected function getColumns(): array
    {
        return [
            Column::make('id')->title('Id'),
            Column::make('name')->title('Característica'),
            Column::make('key')->title('Clave'),
            Column::make('fields_count')->title('Canchas')->searchable(false)->addClass('text-center'),
            $this->actionColumn('text-right'),
        ];
    }
}
