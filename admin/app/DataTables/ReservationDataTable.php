<?php

namespace App\DataTables;

use App\Http\Requests\Reservations\ReservationFilterRequest;
use App\Models\CourtReservation;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

/**
 * Court reservations of the venues the user can see, filtered by venue, status, source and dates.
 */
class ReservationDataTable extends BaseDataTable
{
    /**
     * Filters of the list => label. Some are derived (expired, refund_pending), see applyStatusFilter().
     */
    public const STATUS_FILTERS = [
        'active' => 'Activas (ocupan horario)',
        'pending_payment' => 'Pago pendiente',
        'confirmed' => 'Confirmadas (pagan en el local)',
        'paid' => 'Pagadas',
        'cancelled' => 'Anuladas',
        'refund_pending' => 'Devolución pendiente',
        'expired' => 'Expiradas (sin pago)',
    ];

    public function tableId(): string
    {
        return 'reservations-table';
    }

    public function dataTable(Builder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('reference', fn (CourtReservation $reservation) => $reservation->reference())
            ->filterColumn('reference', function (Builder $query, string $keyword): void {
                $id = (int) preg_replace('/\D/', '', $keyword);
                if ($id > 0) {
                    $query->where('court_reservations.id', $id);
                }
            })
            ->editColumn('reserved_on', fn (CourtReservation $reservation) => $reservation->reserved_on->format('d/m/Y'))
            ->addColumn('schedule', fn (CourtReservation $reservation) => $reservation->timeRange())
            ->addColumn('venue', fn (CourtReservation $reservation) => e($reservation->field->court->name)
                .'<br><small class="text-muted">'.e($reservation->field->name).'</small>')
            ->filterColumn('venue', function (Builder $query, string $keyword): void {
                $query->whereHas('field', fn (Builder $field) => $field
                    ->where('name', 'ilike', "%{$keyword}%")
                    ->orWhereHas('court', fn (Builder $court) => $court->where('name', 'ilike', "%{$keyword}%")));
            })
            ->addColumn('sport', fn (CourtReservation $reservation) => e($reservation->sport?->name ?? '—'))
            ->addColumn('customer', fn (CourtReservation $reservation) => e($reservation->customerName())
                .($reservation->customerPhone() ? '<br><small class="text-muted">'.e($reservation->customerPhone()).'</small>' : ''))
            ->filterColumn('customer', function (Builder $query, string $keyword): void {
                $query->where(fn (Builder $customer) => $customer
                    ->where('customer_name', 'ilike', "%{$keyword}%")
                    ->orWhere('customer_phone', 'ilike', "%{$keyword}%")
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('name', 'ilike', "%{$keyword}%")
                        ->orWhere('email', 'ilike', "%{$keyword}%")));
            })
            ->editColumn('amount', fn (CourtReservation $reservation) => 'Bs '.number_format((float) $reservation->amount, 2))
            ->addColumn('status_badge', fn (CourtReservation $reservation) => view('reservations.partials.status', ['reservation' => $reservation])->render())
            ->editColumn('source', fn (CourtReservation $reservation) => $reservation->source === 'admin' ? 'Panel' : 'App')
            ->addColumn('action', fn (CourtReservation $reservation) => view('reservations.partials.actions', ['reservation' => $reservation])->render())
            // Each sortable column falls back to date and time descending, so ties keep the latest first.
            ->orderColumn('reserved_on', fn (Builder $query, string $direction) => $this->latestFirst(
                $query->orderBy('court_reservations.reserved_on', $direction)->orderBy('court_reservations.starts_at', $direction)
            ))
            ->orderColumn('schedule', fn (Builder $query, string $direction) => $this->latestFirst(
                $query->orderBy('court_reservations.starts_at', $direction)
            ))
            ->orderColumn('amount', fn (Builder $query, string $direction) => $this->latestFirst(
                $query->orderBy('court_reservations.amount', $direction)
            ))
            ->orderColumn('source', fn (Builder $query, string $direction) => $this->latestFirst(
                $query->orderBy('court_reservations.source', $direction)
            ))
            ->rawColumns(['venue', 'customer', 'status_badge', 'action']);
    }

    public function query(CourtReservation $model, ReservationFilterRequest $request): Builder
    {
        $filters = $request->validated();

        $query = $model->newQuery()
            ->visibleTo($this->user())
            ->select('court_reservations.*')
            ->with(['field.court:id,name', 'sport:id,name', 'user:id,name,email,phone'])
            ->when($filters['court_id'] ?? null, fn (Builder $query, $courtId) => $query
                ->whereHas('field', fn (Builder $field) => $field->where('court_id', $courtId)))
            ->when($filters['source'] ?? null, fn (Builder $query, $source) => $query->where('source', $source))
            ->when($filters['date_from'] ?? null, fn (Builder $query, $from) => $query->whereDate('reserved_on', '>=', $from))
            ->when($filters['date_to'] ?? null, fn (Builder $query, $to) => $query->whereDate('reserved_on', '<=', $to));
        $this->applyStatusFilter($query, $filters['status'] ?? null);

        return $query;
    }

    protected function filtersForm(): ?string
    {
        return 'reservation-filters';
    }

    protected function defaultOrder(): array
    {
        return [1, 'desc'];
    }

    protected function getColumns(): array
    {
        return [
            Column::make('reference')->title('Referencia')->orderable(false),
            Column::make('reserved_on')->title('Fecha')->searchable(false),
            Column::make('schedule')->title('Horario')->searchable(false),
            Column::make('venue')->title('Centro / cancha')->orderable(false),
            Column::make('sport')->title('Deporte')->orderable(false)->searchable(false),
            Column::make('customer')->title('Cliente')->orderable(false),
            Column::make('amount')->title('Monto')->searchable(false)->addClass('text-right'),
            Column::make('status_badge', 'status')->title('Estado')->orderable(false)->searchable(false),
            Column::make('source')->title('Origen')->searchable(false),
            $this->actionColumn(),
        ];
    }

    private function latestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('court_reservations.reserved_on')
            ->orderByDesc('court_reservations.starts_at')
            ->orderByDesc('court_reservations.id');
    }

    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        $expiredBefore = now()->subMinutes(CourtReservation::PAYMENT_WINDOW_MINUTES);

        match ($status) {
            'active' => $query->blocking(),
            'pending_payment' => $query->where('status', CourtReservation::STATUS_PENDING_PAYMENT)
                ->where('court_reservations.created_at', '>=', $expiredBefore),
            'expired' => $query->where('status', CourtReservation::STATUS_PENDING_PAYMENT)
                ->where('court_reservations.created_at', '<', $expiredBefore),
            'refund_pending' => $query->where('status', CourtReservation::STATUS_CANCELLED)
                ->whereNotNull('paid_at')->whereNull('refunded_at'),
            'confirmed', 'paid', 'cancelled' => $query->where('status', $status),
            default => null,
        };
    }
}
