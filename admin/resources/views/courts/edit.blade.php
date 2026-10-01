@extends('layouts.admin')

@section('title', 'Editar centro deportivo')
@section('page_title', 'Centros deportivos')
@section('page_subtitle', 'Editar '.$court->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('courts.index') }}">Centros deportivos</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-7">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Datos del complejo</h3></div>
                <form method="POST" action="{{ route('courts.update', $court) }}" enctype="multipart/form-data">
                    @method('PUT')
                    <div class="card-body">
                        @include('courts._form')
                    </div>
                    <div class="card-footer text-right">
                        <a href="{{ route('courts.index') }}" class="btn btn-default">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card card-success card-outline">
                <div class="card-header">
                    <h3 class="card-title">Canchas físicas</h3>
                    @can('court_fields.store')
                        <div class="card-tools">
                            <button type="button" class="btn btn-sm btn-success" data-add-field><i class="fas fa-plus"></i> Agregar</button>
                        </div>
                    @endcan
                </div>
                <div class="card-body p-0">
                    @include('courts.partials.fields-table', ['editable' => true])
                </div>
                <div class="card-footer text-muted small">
                    Cada cancha física se reserva por hora; una reserva la bloquea para todos sus deportes.
                </div>
            </div>
            @include('courts.partials.managers', ['editable' => true])
        </div>
    </div>

    @canany(['court_fields.store', 'court_fields.update'])
        <div class="modal fade" id="field-modal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="POST" class="modal-content" id="field-form" data-store-action="{{ route('courts.fields.store', $court) }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST" id="field-method">
                    <input type="hidden" name="_field_action" id="field-action" value="{{ old('_field_action') }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="field-modal-title">Agregar cancha física</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        @include('partials.errors', ['bag' => 'field'])
                        <div class="form-group">
                            <label for="field_name">Nombre *</label>
                            <input id="field_name" name="name" class="form-control" value="{{ $errors->field->any() ? old('name') : '' }}" placeholder="Ej: Cancha 1" required>
                        </div>
                        <div class="form-group">
                            <label for="field_price">Precio por hora (Bs) *</label>
                            <input id="field_price" name="price_per_hour" type="number" step="0.01" min="0" class="form-control" value="{{ $errors->field->any() ? old('price_per_hour') : '' }}" required>
                        </div>
                        <div class="form-group">
                            <label for="field_dimensions">Dimensiones</label>
                            <input id="field_dimensions" name="dimensions" maxlength="50" class="form-control" value="{{ $errors->field->any() ? old('dimensions') : '' }}" placeholder="Ej: 4x4, 40x20 m">
                        </div>
                        <div class="form-group">
                            <label for="field_description">Descripción</label>
                            <textarea id="field_description" name="description" rows="3" maxlength="2000" class="form-control" placeholder="Detalles de la cancha: tipo de piso, redes, estado, etc.">{{ $errors->field->any() ? old('description') : '' }}</textarea>
                        </div>
                        <label>Opciones adicionales</label>
                        @php($oldFieldFeatures = $errors->field->any() ? old('features', []) : [])
                        <div class="row mb-3">
                            @foreach (\App\Enums\CourtFieldFeature::cases() as $feature)
                                <div class="col-6">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input field-feature" id="field_feature_{{ $feature->value }}" name="features[]" value="{{ $feature->value }}" @checked(in_array($feature->value, $oldFieldFeatures, true))>
                                        <label class="custom-control-label font-weight-normal" for="field_feature_{{ $feature->value }}"><i class="{{ $feature->icon() }} text-muted mr-1"></i>{{ $feature->label() }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <label>Deportes *</label>
                        @php($oldFieldSports = $errors->field->any() ? array_map('intval', old('sports', [])) : [])
                        <div class="row">
                            @foreach ($sports as $sport)
                                <div class="col-6">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input field-sport" id="field_sport_{{ $sport->id }}" name="sports[]" value="{{ $sport->id }}" @checked(in_array($sport->id, $oldFieldSports, true))>
                                        <label class="custom-control-label font-weight-normal" for="field_sport_{{ $sport->id }}">{{ $sport->name }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endcanany
@endsection

@push('scripts')
    <script>
        (function () {
            const $modal = $('#field-modal');
            const $form = $('#field-form');

            function openModal(field) {
                const isEdit = Boolean(field && field.id);
                const action = isEdit ? field.action : $form.data('store-action');
                $form.attr('action', action);
                $('#field-action').val(isEdit ? action : '');
                $('#field-method').val(isEdit ? 'PUT' : 'POST');
                $('#field-modal-title').text(isEdit ? 'Editar ' + field.name : 'Agregar cancha física');
                if (field) {
                    $('#field_name').val(field.name);
                    $('#field_price').val(field.price_per_hour);
                    $('#field_dimensions').val(field.dimensions || '');
                    $('#field_description').val(field.description || '');
                    $('.field-feature').each(function () {
                        this.checked = (field.features || []).includes(this.value);
                    });
                    $('.field-sport').each(function () {
                        this.checked = field.sports.map(Number).includes(Number(this.value));
                    });
                }
                $modal.modal('show');
            }

            $('[data-add-field]').on('click', function () {
                $form.find('.alert').remove();
                openModal({ name: '', price_per_hour: '', dimensions: '', description: '', features: [], sports: [] });
            });

            $('[data-edit-field]').on('click', function () {
                $form.find('.alert').remove();
                openModal($(this).data('edit-field'));
            });

            @if ($errors->field->any())
                // Reopen the modal with the rejected input.
                const previousAction = $('#field-action').val();
                $form.attr('action', previousAction || $form.data('store-action'));
                $('#field-method').val(previousAction ? 'PUT' : 'POST');
                $modal.modal('show');
            @endif
        })();
    </script>
@endpush
