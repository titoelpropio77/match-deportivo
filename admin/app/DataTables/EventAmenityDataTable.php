<?php

namespace App\DataTables;

use App\Models\EventAmenity;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

class EventAmenityDataTable extends BaseDataTable
{
    public function tableId(): string
    {
        return 'event-amenities-table';
    }

    public function dataTable(Builder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('name', fn (EventAmenity $amenity) => ($amenity->icon ? '<i class="'.e($amenity->icon).' text-muted mr-1"></i>' : '').'<strong>'.e($amenity->name).'</strong>')
            ->editColumn('key', fn (EventAmenity $amenity) => '<code>'.e($amenity->key).'</code>')
            ->editColumn('spaces_count', fn (EventAmenity $amenity) => '<span class="badge badge-secondary">'.$amenity->spaces_count.'</span>')
            ->orderColumn('spaces_count', 'spaces_count $1')
            ->addColumn('action', fn (EventAmenity $amenity) => view('event-amenities.partials.actions', ['amenity' => $amenity])->render())
            ->rawColumns(['name', 'key', 'spaces_count', 'action']);
    }

    public function query(EventAmenity $model): Builder
    {
        return $model->newQuery()->withCount('spaces');
    }

    protected function defaultOrder(): array
    {
        return [1, 'asc'];
    }

    protected function getColumns(): array
    {
        return [
            Column::make('id')->title('Id'),
            Column::make('name')->title('Servicio'),
            Column::make('key')->title('Clave'),
            Column::make('spaces_count')->title('Espacios')->searchable(false)->addClass('text-center'),
            $this->actionColumn('text-right'),
        ];
    }
}
