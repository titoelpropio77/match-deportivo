<?php

namespace App\DataTables;

use App\Models\Court;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

/**
 * Sports centers the user can see (partners their own, managers the assigned ones).
 */
class CourtDataTable extends BaseDataTable
{
    public function tableId(): string
    {
        return 'courts-table';
    }

    public function dataTable(Builder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('owner', fn (Court $court) => e($court->owner?->name ?? '—'))
            ->filterColumn('owner', function (Builder $query, string $keyword): void {
                $query->whereHas('owner', fn (Builder $owner) => $owner->where('name', 'ilike', "%{$keyword}%"));
            })
            ->addColumn('city', fn (Court $court) => $court->city
                ? e($court->city->name).'<br><small class="text-muted">'.e($court->city->department).'</small>'
                : '<span class="text-muted">—</span>')
            ->filterColumn('city', function (Builder $query, string $keyword): void {
                $query->whereHas('city', fn (Builder $city) => $city
                    ->where('name', 'ilike', "%{$keyword}%")
                    ->orWhere('department', 'ilike', "%{$keyword}%"));
            })
            ->addColumn('sports', fn (Court $court) => $court->sports
                ->map(fn (Sport $sport) => '<span class="badge badge-info mr-1">'.e($sport->name).'</span>')
                ->implode(''))
            ->addColumn('schedule', fn (Court $court) => substr($court->opening_time, 0, 5).' – '.substr($court->closing_time, 0, 5))
            ->editColumn('updated_at', fn (Court $court) => $court->updated_at?->diffForHumans())
            ->addColumn('action', fn (Court $court) => view('courts.partials.actions', ['court' => $court])->render())
            ->rawColumns(['city', 'sports', 'action']);
    }

    public function query(Court $model): Builder
    {
        return $model->newQuery()
            ->visibleTo($this->user())
            ->select('courts.*')
            ->with(['owner:id,name', 'city:id,name,department', 'sports:id,name'])
            ->withCount('fields');
    }

    protected function defaultOrder(): array
    {
        return [1, 'asc'];
    }

    protected function getColumns(): array
    {
        return [
            Column::make('id')->title('Id'),
            Column::make('name')->title('Nombre'),
            Column::make('city')->title('Ciudad')->orderable(false),
            Column::make('address')->title('Dirección'),
            Column::make('owner')->title('Partner')->orderable(false),
            Column::make('sports')->title('Deportes')->orderable(false)->searchable(false),
            Column::make('fields_count')->title('Canchas físicas')->searchable(false)->addClass('text-center'),
            Column::make('schedule')->title('Horario')->orderable(false)->searchable(false),
            Column::make('updated_at')->title('Actualizado')->searchable(false),
            $this->actionColumn(),
        ];
    }
}
