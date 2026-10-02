@extends('layouts.admin')

@php
    $court = $reservation->field->court;
    $bs = fn ($amount) => 'Bs '.number_format((float) $amount, 2);
@endphp

@section('title', 'Reserva '.$reservation->reference())
@section('page_title', 'Reservas')
@section('page_subtitle', 'Reserva '.$reservation->reference())
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reservations.index') }}">Reservas</a></li>
    <li class="breadcrumb-item active">{{ $reservation->reference() }}</li>
@endsection

@section('content')
    @if ($reservation->canBeRefunded())
        <div class="alert alert-warning">
            <i class="fas fa-hand-holding-usd mr-1"></i>
            Esta reserva fue pagada y luego anulada: hay que devolver <strong>{{ $bs($reservation->amount) }}</strong> al cliente.
        </div>
    @endif

    <div class="row">
        <div class="col-lg-7">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">
                        {{ $reservation->reference() }}
                        <span class="ml-2">@include('reservations.partials.status')</span>
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('reservations.agenda', ['court_id' => $court->id, 'date' => $reservation->reserved_on->toDateString()]) }}" class="btn btn-sm btn-default">
                            <i class="far fa-calendar-alt"></i> Ver en la agenda
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Centro deportivo</dt>
                        <dd class="col-sm-8">
                            @can('courts.show')
                                <a href="{{ route('courts.show', $court) }}">{{ $court->name }}</a>
                            @else
                                {{ $court->name }}
                            @endcan
                            <br><small class="text-muted">{{ $court->address }}</small>
                        </dd>
                        <dt class="col-sm-4">Cancha</dt><dd class="col-sm-8">{{ $reservation->field->name }}</dd>
                        <dt class="col-sm-4">Deporte</dt><dd class="col-sm-8">{{ $reservation->sport?->name ?? '—' }}</dd>
                        <dt class="col-sm-4">Fecha</dt><dd class="col-sm-8">{{ ucfirst($reservation->reserved_on->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')) }}</dd>
                        <dt class="col-sm-4">Horario</dt><dd class="col-sm-8">{{ $reservation->timeRange() }} ({{ $reservation->hours }} {{ $reservation->hours === 1 ? 'hora' : 'horas' }})</dd>
                        <dt class="col-sm-4">Precio por hora</dt><dd class="col-sm-8">{{ $bs($reservation->field->price_per_hour) }}</dd>
                        @if ($reservation->items->isNotEmpty())
                            <dt class="col-sm-4">Cancha</dt><dd class="col-sm-8">{{ $bs($reservation->amount - $reservation->items_amount) }}</dd>
                            <dt class="col-sm-4">Artículos alquilados</dt>
                            <dd class="col-sm-8">
                                <ul class="list-unstyled mb-0">
                                    @foreach ($reservation->items as $item)
                                        <li>
                                            <i class="fas fa-volleyball-ball text-muted mr-1"></i>
                                            {{ $item->quantity }} × {{ $item->name }}
                                            <small class="text-muted">({{ $bs($item->unit_price) }}{{ $item->price_type === 'flat' ? ' c/u' : ' c/u por hora' }})</small>
                                            — {{ $bs($item->amount) }}
                                        </li>
                                    @endforeach
                                </ul>
                            </dd>
                        @endif
                        <dt class="col-sm-4">Total</dt><dd class="col-sm-8"><strong>{{ $bs($reservation->amount) }}</strong></dd>
                        @if ($reservation->booking_code)
                            <dt class="col-sm-4">Código de reserva</dt>
                            <dd class="col-sm-8"><code>{{ $reservation->booking_code }}</code> <small class="text-muted">(referencia del pago QR)</small></dd>
                        @endif
                        <dt class="col-sm-4">Origen</dt><dd class="col-sm-8">{{ \App\Models\CourtReservation::SOURCES[$reservation->source] ?? $reservation->source }}</dd>
                        @if ($reservation->notes)
                            <dt class="col-sm-4">Notas</dt><dd class="col-sm-8">{!! nl2br(e($reservation->notes)) !!}</dd>
                        @endif
                        @if ($reservation->status === \App\Models\CourtReservation::STATUS_CANCELLED)
                            <dt class="col-sm-4 text-danger">Motivo de anulación</dt>
                            <dd class="col-sm-8">{{ $reservation->cancellation_reason ?: '—' }}</dd>
                        @endif
                    </dl>
                </div>
                @canany(['reservations.cancel', 'reservations.payments'])
                    @if ($reservation->canBeCancelled() || $reservation->canRegisterPayment() || $reservation->canBeRefunded())
                        <div class="card-footer text-right">
                            @can('reservations.payments')
                                @if ($reservation->canRegisterPayment())
                                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#payment-modal">
                                        <i class="fas fa-cash-register"></i> Registrar pago
                                    </button>
                                @endif
                                @if ($reservation->canBeRefunded())
                                    <form method="POST" action="{{ route('reservations.refund', $reservation) }}" class="d-inline"
                                          data-confirm="¿Confirmas que devolviste {{ $bs($reservation->amount) }} al cliente?">
                                        @csrf
                                        <button type="submit" class="btn btn-warning"><i class="fas fa-undo"></i> Marcar como reembolsada</button>
                                    </form>
                                @endif
                            @endcan
                            @can('reservations.cancel')
                                @if ($reservation->canBeCancelled())
                                    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#cancel-modal">
                                        <i class="fas fa-ban"></i> Anular reserva
                                    </button>
                                @endif
                            @endcan
                        </div>
                    @endif
                @endcanany
            </div>
        </div>

        <div class="col-lg-5">
            @php($siblings = $reservation->siblings())
            @if ($siblings->isNotEmpty())
                <div class="card card-warning card-outline">
                    <div class="card-header"><h3 class="card-title">Reservado junto con</h3></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tbody>
                            @foreach ($siblings as $sibling)
                                <tr>
                                    <td><a href="{{ route('reservations.show', $sibling) }}">{{ $sibling->reference() }}</a></td>
                                    <td>{{ $sibling->reserved_on->format('d/m') }} · {{ $sibling->timeRange() }}</td>
                                    <td>{{ $sibling->field->name }}</td>
                                    <td>@include('reservations.partials.status', ['reservation' => $sibling])</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer small text-muted">
                        Se pagaron con un solo QR. Anular esta reserva no anula las demás.
                    </div>
                </div>
            @endif

            <div class="card card-info card-outline">
                <div class="card-header"><h3 class="card-title">Cliente</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nombre</dt><dd class="col-sm-8">{{ $reservation->customerName() }}</dd>
                        <dt class="col-sm-4">Teléfono</dt>
                        <dd class="col-sm-8">
                            @if ($phone = $reservation->customerPhone())
                                {{ $phone }}
                                <a href="https://wa.me/{{ preg_replace('/\D/', '', str_starts_with($phone, '+') ? $phone : '591'.$phone) }}" target="_blank" rel="noopener" class="ml-1 text-success" title="Escribir por WhatsApp"><i class="fab fa-whatsapp"></i></a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>
                        <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $reservation->user?->email ?? '—' }}</dd>
                        <dt class="col-sm-4">Cuenta</dt>
                        <dd class="col-sm-8">
                            @if ($reservation->user)
                                Usuario de la app
                                @can('users.show')
                                    · <a href="{{ route('users.show', $reservation->user) }}">ver perfil</a>
                                @endcan
                            @else
                                <span class="text-muted">Sin cuenta (registrada en el panel)</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card card-success card-outline">
                <div class="card-header"><h3 class="card-title">Pago</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Estado</dt><dd class="col-sm-8">@include('reservations.partials.status')</dd>
                        <dt class="col-sm-4">Método</dt><dd class="col-sm-8">{{ \App\Models\CourtReservation::PAYMENT_METHODS[$reservation->payment_method] ?? '—' }}</dd>
                        <dt class="col-sm-4">Pagado</dt><dd class="col-sm-8">{{ $reservation->paid_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        @if ($reservation->refunded_at)
                            <dt class="col-sm-4">Reembolsado</dt><dd class="col-sm-8">{{ $reservation->refunded_at->format('d/m/Y H:i') }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card card-secondary card-outline">
                <div class="card-header"><h3 class="card-title">Historial</h3></div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-plus-circle text-primary mr-1"></i>
                            <strong>{{ $reservation->created_at->format('d/m/Y H:i') }}</strong> · Creada
                            {{ $reservation->createdBy ? 'por '.$reservation->createdBy->name.' (panel)' : 'desde la app' }}
                        </li>
                        @if ($reservation->paid_at)
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success mr-1"></i>
                                <strong>{{ $reservation->paid_at->format('d/m/Y H:i') }}</strong> · Pago registrado
                                ({{ \App\Models\CourtReservation::PAYMENT_METHODS[$reservation->payment_method] ?? '—' }})
                            </li>
                        @endif
                        @if ($reservation->isExpired())
                            <li class="mb-2">
                                <i class="fas fa-hourglass-end text-secondary mr-1"></i>
                                <strong>{{ $reservation->created_at->copy()->addMinutes(\App\Models\CourtReservation::PAYMENT_WINDOW_MINUTES)->format('d/m/Y H:i') }}</strong> · Expiró sin pago, el horario se liberó
                            </li>
                        @endif
                        @if ($reservation->cancelled_at)
                            <li class="mb-2">
                                <i class="fas fa-ban text-danger mr-1"></i>
                                <strong>{{ $reservation->cancelled_at->format('d/m/Y H:i') }}</strong> · Anulada
                                {{ $reservation->cancelled_by === $reservation->user_id ? 'por el cliente' : 'por '.($reservation->cancelledBy?->name ?? 'el centro deportivo') }}
                            </li>
                        @endif
                        @if ($reservation->refunded_at)
                            <li class="mb-2">
                                <i class="fas fa-undo text-warning mr-1"></i>
                                <strong>{{ $reservation->refunded_at->format('d/m/Y H:i') }}</strong> · Dinero devuelto al cliente
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @can('reservations.cancel')
        @if ($reservation->canBeCancelled())
            <div class="modal fade" id="cancel-modal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form method="POST" action="{{ route('reservations.cancel', $reservation) }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Anular reserva {{ $reservation->reference() }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            @include('partials.errors', ['bag' => 'cancel'])
                            <p class="mb-2">
                                El horario {{ $reservation->timeRange() }} del {{ $reservation->reserved_on->format('d/m/Y') }} quedará libre.
                                @if ($reservation->paid_at)
                                    <br><strong class="text-danger">La reserva ya está pagada: deberás devolver {{ $bs($reservation->amount) }} al cliente.</strong>
                                @endif
                            </p>
                            <div class="form-group mb-0">
                                <label for="cancellation_reason">Motivo *</label>
                                <textarea id="cancellation_reason" name="cancellation_reason" rows="3" required minlength="5" maxlength="500"
                                          class="form-control @error('cancellation_reason', 'cancel') is-invalid @enderror"
                                          placeholder="Ej.: mantenimiento de la cancha, lluvia, solicitud del cliente...">{{ old('cancellation_reason') }}</textarea>
                                <small class="text-muted">El cliente verá este motivo en la app.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Volver</button>
                            <button type="submit" class="btn btn-danger"><i class="fas fa-ban"></i> Anular reserva</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan

    @can('reservations.payments')
        @if ($reservation->canRegisterPayment())
            <div class="modal fade" id="payment-modal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-sm" role="document">
                    <form method="POST" action="{{ route('reservations.payment', $reservation) }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Registrar pago</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            @include('partials.errors', ['bag' => 'payment'])
                            <p>Monto cobrado: <strong>{{ $bs($reservation->amount) }}</strong></p>
                            <div class="form-group mb-0">
                                <label for="payment_method">Método de pago *</label>
                                <select id="payment_method" name="payment_method" class="form-control" required>
                                    @foreach ($paymentMethods as $value => $label)
                                        <option value="{{ $value }}" @selected(old('payment_method', 'cash') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Volver</button>
                            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Registrar</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan
@endsection

@push('scripts')
    <script>
        @if ($errors->cancel->any()) $('#cancel-modal').modal('show'); @endif
        @if ($errors->payment->any()) $('#payment-modal').modal('show'); @endif
    </script>
@endpush
