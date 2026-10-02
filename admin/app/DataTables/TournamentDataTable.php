<?php

namespace App\DataTables;

use App\Http\Requests\Tournaments\TournamentFilterRequest;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

/**
 * Tournaments of the venues the user can see, filtered by venue, sport and status.
 */
class TournamentDataTable extends BaseDataTable
{
    public function tableId(): string
    {
        return 'tournaments-table';
    }

    public function dataTable(Builder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('name', fn (Tournament $tournament) => e($tournament->name)
                .'<br><small class="text-muted">'.e(Tournament::FORMATS[$tournament->format] ?? $tournament->format).'</small>')
            ->addColumn('venue', fn (Tournament $tournament) => e($tournament->court->name))
            ->filterColumn('venue', function (Builder $query, string $keyword): void {
                $query->whereHas('court', fn (Builder $court) => $court->where('name', 'ilike', "%{$keyword}%"));
            })
            ->addColumn('sport', fn (Tournament $tournament) => e($tournament->sport->name))
            ->editColumn('starts_on', fn (Tournament $tournament) => $tournament->starts_on->format('d/m/Y'))
            ->orderColumn('starts_on', fn (Builder $query, string $direction) => $query->orderBy('starts_on', $direction)->orderByDesc('tournaments.id'))
            ->addColumn('teams', fn (Tournament $tournament) => $tournament->confirmed_count.' / '.$tournament->max_teams)
            ->editColumn('entry_fee', fn (Tournament $tournament) => (float) $tournament->entry_fee > 0
                ? 'Bs '.number_format((float) $tournament->entry_fee, 2)
                : '<span class="badge badge-light">Gratis</span>')
            ->addColumn('status_badge', fn (Tournament $tournament) => '<span class="badge badge-'.$tournament->statusColor().'">'.e($tournament->statusLabel()).'</span>')
            ->addColumn('action', fn (Tournament $tournament) => view('tournaments.partials.actions', ['tournament' => $tournament])->render())
            ->rawColumns(['name', 'entry_fee', 'status_badge', 'action']);
    }

    public function query(Tournament $model, TournamentFilterRequest $request): Builder
    {
        $filters = $request->validated();

        return $model->newQuery()
            ->visibleTo($this->user())
            ->select('tournaments.*')
            ->with(['court:id,name', 'sport:id,name'])
            ->withCount([
                'registrations as confirmed_count' => fn (Builder $query) => $query->where('status', TournamentRegistration::STATUS_CONFIRMED),
            ])
            ->when($filters['court_id'] ?? null, fn (Builder $query, $id) => $query->where('court_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['sport_id'] ?? null, fn (Builder $query, $id) => $query->where('sport_id', $id));
    }

    protected function filtersForm(): ?string
    {
        return 'tournament-filters';
    }

    protected function defaultOrder(): array
    {
        return [4, 'desc'];
    }

    protected function getColumns(): array
    {
        return [
            Column::make('id')->title('Id'),
            Column::make('name')->title('Torneo'),
            Column::make('venue')->title('Centro deportivo')->orderable(false),
            Column::make('sport')->title('Deporte')->orderable(false)->searchable(false),
            Column::make('starts_on')->title('Inicio')->searchable(false),
            Column::make('teams')->title('Equipos')->orderable(false)->searchable(false)->addClass('text-center'),
            Column::make('entry_fee')->title('Inscripción')->searchable(false),
            Column::make('status_badge', 'status')->title('Estado')->orderable(false)->searchable(false),
            $this->actionColumn(),
        ];
    }
}
