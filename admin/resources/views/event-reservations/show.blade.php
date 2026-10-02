@extends('layouts.admin')

@php
    $space = $reservation->space;
    $court = $space->court;
    $bs = fn ($amount) => 'Bs '.number_format((float) $amount, 2);
@endphp

@section('title', 'Evento '.$reservation->code)
@section('page_title', 'Eventos')
@section('page_subtitle', 'Reserva '.$reservation->code)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('event-reservations.index') }}">Eventos</a></li>
    <li class="breadcrumb-item active">{{ $reservation->code }}</li>
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
            <div class="card card-warning card-outline">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="{{ $space->type->icon() }} mr-1"></i> {{ $reservation->code }}
                        <span class="ml-2">@include('event-reservations.partials.status')</span>
                    </h3>
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
                        <dt class="col-sm-4">Espacio</dt><dd class="col-sm-8">{{ $space->name }} <small class="text-muted">· {{ $space->type->label() }}</small></dd>
                        <dt class="col-sm-4">Evento</dt><dd class="col-sm-8">{{ $reservation->event_type?->label() ?? '—' }}</dd>
                        <dt class="col-sm-4">Personas</dt><dd class="col-sm-8">{{ $reservation->guests }} <small class="text-muted">(máx. {{ $space->capacity }})</small></dd>
                        <dt class="col-sm-4">Fecha</dt><dd class="col-sm-8">{{ ucfirst($reservation->reserved_on->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')) }}</dd>
                        <dt class="col-sm-4">Horario</dt><dd class="col-sm-8">{{ $reservation->timeRange() }} ({{ $reservation->hours }} {{ $reservation->hours === 1 ? 'hora' : 'horas' }})</dd>
                        <dt class="col-sm-4">Total</dt><dd class="col-sm-8"><strong>{{ $bs($reservation->amount) }}</strong></dd>
                        <dt class="col-sm-4">Origen</dt><dd class="col-sm-8">{{ \App\Models\CourtReservation::SOURCES[$reservation->source] ?? $reservation->source }}</dd>
                        @if ($reservation->notes)
                            <dt class="col-sm-4">Notas del cliente</dt><dd class="col-sm-8">{!! nl2br(e($reservation->notes)) !!}</dd>
                        @endif
                        @if ($reservation->status === \App\Models\EventSpaceReservation::STATUS_CANCELLED)
                            <dt class="col-sm-4 text-danger">Motivo de anulación</dt>
                            <dd class="col-sm-8">{{ $reservation->cancellation_reason ?: '—' }}</dd>
                        @endif
                    </dl>
                </div>
                @canany(['event_reservations.cancel', 'event_reservations.payments'])
                    @if ($reservation->canBeCancelled() || $reservation->canRegisterPayment() || $reservation->canBeRefunded())
                        <div class="card-footer text-right">
                            @can('event_reservations.payments')
                                @if ($reservation->canRegisterPayment())
                                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#payment-modal">
                                        <i class="fas fa-cash-register"></i> Registrar pago
                                    </button>
                                @endif
                                @if ($reservation->canBeRefunded())
                                    <form method="POST" action="{{ route('event-reservations.refund', $reservation) }}" class="d-inline"
                                          data-confirm="¿Confirmas que devolviste {{ $bs($reservation->amount) }} al cliente?">
                                        @csrf
                                        <button type="submit" class="btn btn-warning"><i class="fas fa-undo"></i> Marcar como reembolsada</button>
                                    </form>
                                @endif
                            @endcan
                            @can('event_reservations.cancel')
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
                    </dl>
                </div>
            </div>

            <div class="card card-success card-outline">
                <div class="card-header"><h3 class="card-title">Pago</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Estado</dt><dd class="col-sm-8">@include('event-reservations.partials.status')</dd>
                        <dt class="col-sm-4">Método</dt><dd class="col-sm-8">{{ $paymentMethods[$reservation->payment_method] ?? '—' }}</dd>
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
                                ({{ $paymentMethods[$reservation->payment_method] ?? '—' }})
                            </li>
                        @endif
                        @if ($reservation->isExpired())
                            <li class="mb-2">
                                <i class="fas fa-hourglass-end text-secondary mr-1"></i>
                                <strong>{{ $reservation->created_at->copy()->addMinutes(\App\Models\EventSpaceReservation::PAYMENT_WINDOW_MINUTES)->format('d/m/Y H:i') }}</strong> · Expiró sin pago, el horario se liberó
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

    @can('event_reservations.cancel')
        @if ($reservation->canBeCancelled())
            <div class="modal fade" id="cancel-modal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form method="POST" action="{{ route('event-reservations.cancel', $reservation) }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Anular reserva {{ $reservation->code }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            @include('partials.errors', ['bag' => 'cancel'])
                            <p class="mb-2">
                                {{ $space->name }} quedará libre de {{ $reservation->timeRange() }} el {{ $reservation->reserved_on->format('d/m/Y') }}.
                                @if ($reservation->paid_at)
                                    <br><strong class="text-danger">La reserva ya está pagada: deberás devolver {{ $bs($reservation->amount) }} al cliente.</strong>
                                @endif
                            </p>
                            <div class="form-group mb-0">
                                <label for="cancellation_reason">Motivo *</label>
                                <textarea id="cancellation_reason" name="cancellation_reason" rows="3" required minlength="5" maxlength="500"
                                          class="form-control @error('cancellation_reason', 'cancel') is-invalid @enderror"
                                          placeholder="Ej.: mantenimiento del espacio, solicitud del cliente...">{{ old('cancellation_reason') }}</textarea>
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

    @can('event_reservations.payments')
        @if ($reservation->canRegisterPayment())
            <div class="modal fade" id="payment-modal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-sm" role="document">
                    <form method="POST" action="{{ route('event-reservations.payment', $reservation) }}" class="modal-content">
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
