<?php

namespace App\DataTables;

use App\Http\Requests\Reservations\ReservationFilterRequest;
use App\Models\EventSpaceReservation;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

/**
 * Event space reservations of the venues the user can see, filtered by venue, status and dates.
 */
class EventReservationDataTable extends BaseDataTable
{
    public function tableId(): string
    {
        return 'event-reservations-table';
    }

    public function dataTable(Builder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('reserved_on', fn (EventSpaceReservation $reservation) => $reservation->reserved_on->format('d/m/Y'))
            ->addColumn('schedule', fn (EventSpaceReservation $reservation) => $reservation->timeRange())
            ->addColumn('venue', fn (EventSpaceReservation $reservation) => e($reservation->space->court->name)
                .'<br><small class="text-muted">'.e($reservation->space->name).'</small>')
            ->filterColumn('venue', function (Builder $query, string $keyword): void {
                $query->whereHas('space', fn (Builder $space) => $space
                    ->where('name', 'ilike', "%{$keyword}%")
                    ->orWhereHas('court', fn (Builder $court) => $court->where('name', 'ilike', "%{$keyword}%")));
            })
            ->addColumn('event', fn (EventSpaceReservation $reservation) => e($reservation->event_type?->label() ?? '—')
                .'<br><small class="text-muted">'.$reservation->guests.' personas</small>')
            ->addColumn('customer', fn (EventSpaceReservation $reservation) => e($reservation->customerName())
                .($reservation->customerPhone() ? '<br><small class="text-muted">'.e($reservation->customerPhone()).'</small>' : ''))
            ->filterColumn('customer', function (Builder $query, string $keyword): void {
                $query->where(fn (Builder $customer) => $customer
                    ->where('customer_name', 'ilike', "%{$keyword}%")
                    ->orWhere('customer_phone', 'ilike', "%{$keyword}%")
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('name', 'ilike', "%{$keyword}%")
                        ->orWhere('email', 'ilike', "%{$keyword}%")));
            })
            ->editColumn('amount', fn (EventSpaceReservation $reservation) => 'Bs '.number_format((float) $reservation->amount, 2))
            ->addColumn('status_badge', fn (EventSpaceReservation $reservation) => view('event-reservations.partials.status', ['reservation' => $reservation])->render())
            ->editColumn('source', fn (EventSpaceReservation $reservation) => $reservation->source === 'admin' ? 'Panel' : 'App')
            ->addColumn('action', fn (EventSpaceReservation $reservation) => view('event-reservations.partials.actions', ['reservation' => $reservation])->render())
            ->orderColumn('reserved_on', fn (Builder $query, string $direction) => $this->latestFirst(
                $query->orderBy('event_space_reservations.reserved_on', $direction)->orderBy('event_space_reservations.starts_at', $direction)
            ))
            ->orderColumn('amount', fn (Builder $query, string $direction) => $this->latestFirst(
                $query->orderBy('event_space_reservations.amount', $direction)
            ))
            ->rawColumns(['venue', 'event', 'customer', 'status_badge', 'action']);
    }

    public function query(EventSpaceReservation $model, ReservationFilterRequest $request): Builder
    {
        $filters = $request->validated();

        $query = $model->newQuery()
            ->visibleTo($this->user())
            ->select('event_space_reservations.*')
            ->with(['space.court:id,name', 'user:id,name,email,phone'])
            ->when($filters['court_id'] ?? null, fn (Builder $query, $courtId) => $query
                ->whereHas('space', fn (Builder $space) => $space->where('court_id', $courtId)))
            ->when($filters['date_from'] ?? null, fn (Builder $query, $from) => $query->whereDate('reserved_on', '>=', $from))
            ->when($filters['date_to'] ?? null, fn (Builder $query, $to) => $query->whereDate('reserved_on', '<=', $to));
        $this->applyStatusFilter($query, $filters['status'] ?? null);

        return $query;
    }

    protected function filtersForm(): ?string
    {
        return 'event-reservation-filters';
    }

    protected function defaultOrder(): array
    {
        return [1, 'desc'];
    }

    protected function getColumns(): array
    {
        return [
            Column::make('code')->title('Código'),
            Column::make('reserved_on')->title('Fecha')->searchable(false),
            Column::make('schedule')->title('Horario')->orderable(false)->searchable(false),
            Column::make('venue')->title('Centro / espacio')->orderable(false),
            Column::make('event')->title('Evento')->orderable(false)->searchable(false),
            Column::make('customer')->title('Cliente')->orderable(false),
            Column::make('amount')->title('Monto')->searchable(false)->addClass('text-right'),
            Column::make('status_badge', 'status')->title('Estado')->orderable(false)->searchable(false),
            Column::make('source')->title('Origen')->searchable(false),
            $this->actionColumn(),
        ];
    }

    private function latestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('event_space_reservations.reserved_on')
            ->orderByDesc('event_space_reservations.starts_at')
            ->orderByDesc('event_space_reservations.id');
    }

    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        $expiredBefore = now()->subMinutes(EventSpaceReservation::PAYMENT_WINDOW_MINUTES);

        match ($status) {
            'active' => $query->blocking(),
            'pending_payment' => $query->where('status', EventSpaceReservation::STATUS_PENDING_PAYMENT)
                ->where('event_space_reservations.created_at', '>=', $expiredBefore),
            'expired' => $query->where('status', EventSpaceReservation::STATUS_PENDING_PAYMENT)
                ->where('event_space_reservations.created_at', '<', $expiredBefore),
            'refund_pending' => $query->where('status', EventSpaceReservation::STATUS_CANCELLED)
                ->whereNotNull('paid_at')->whereNull('refunded_at'),
            'confirmed', 'paid', 'cancelled' => $query->where('status', $status),
            default => null,
        };
    }
}
