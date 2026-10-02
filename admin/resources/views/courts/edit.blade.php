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
            <div class="card card-info card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-volleyball-ball mr-1"></i> Artículos en alquiler</h3>
                    @can('rental_items.store')
                        <div class="card-tools">
                            <button type="button" class="btn btn-sm btn-info" data-add-rental><i class="fas fa-plus"></i> Agregar</button>
                        </div>
                    @endcan
                </div>
                <div class="card-body p-0">
                    @include('courts.partials.rental-items-table', ['editable' => true])
                </div>
                <div class="card-footer text-muted small">
                    Se ofrecen al reservar una cancha del mismo deporte y se suman al total. "Cantidad" limita cuántos se alquilan a la vez (vacío = sin límite).
                </div>
            </div>
            <div class="card card-warning card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-glass-cheers mr-1"></i> Espacios para eventos</h3>
                    @can('event_spaces.store')
                        <div class="card-tools">
                            <button type="button" class="btn btn-sm btn-warning" data-add-space><i class="fas fa-plus"></i> Agregar</button>
                        </div>
                    @endcan
                </div>
                <div class="card-body p-0">
                    @include('courts.partials.event-spaces-table', ['editable' => true])
                </div>
                <div class="card-footer text-muted small">
                    Parrilleros, quinchos y salones que se alquilan por hora para reuniones y fiestas. Se muestran en la app junto a las canchas del centro.
                </div>
            </div>
            @include('courts.partials.managers', ['editable' => true])
        </div>
    </div>

    @canany(['rental_items.store', 'rental_items.update'])
        @php($oldRental = $errors->rental->any())
        <div class="modal fade" id="rental-modal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="POST" class="modal-content" id="rental-form" data-store-action="{{ route('courts.rental-items.store', $court) }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST" id="rental-method">
                    <input type="hidden" name="_rental_action" id="rental-action" value="{{ old('_rental_action') }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rental-modal-title">Agregar artículo en alquiler</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        @include('partials.errors', ['bag' => 'rental'])
                        <div class="form-group">
                            <label for="rental_name">Nombre *</label>
                            <input id="rental_name" name="name" maxlength="100" class="form-control" value="{{ $oldRental ? old('name') : '' }}" placeholder="Ej: Pelota de vóley, Raqueta de pádel" required>
                        </div>
                        <div class="form-group">
                            <label for="rental_sport">Deporte *</label>
                            <select id="rental_sport" name="sport_id" class="form-control" required>
                                @foreach ($sports as $sport)
                                    <option value="{{ $sport->id }}" @selected($oldRental && (string) old('sport_id') === (string) $sport->id)>{{ $sport->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Solo se ofrece en las reservas de este deporte.</small>
                        </div>
                        <div class="form-row">
                            <div class="col-6 form-group">
                                <label for="rental_price">Precio (Bs) *</label>
                                <input id="rental_price" name="price" type="number" step="0.01" min="0" class="form-control" value="{{ $oldRental ? old('price') : '' }}" required>
                            </div>
                            <div class="col-6 form-group">
                                <label for="rental_price_type">Se cobra *</label>
                                <select id="rental_price_type" name="price_type" class="form-control" required>
                                    @foreach (\App\Models\RentalItem::PRICE_TYPES as $value => $label)
                                        <option value="{{ $value }}" @selected($oldRental && old('price_type') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="col-6 form-group">
                                <label for="rental_stock">Cantidad disponible</label>
                                <input id="rental_stock" name="stock" type="number" min="1" max="999" class="form-control" value="{{ $oldRental ? old('stock') : '' }}" placeholder="Sin límite">
                            </div>
                            <div class="col-6 form-group d-flex align-items-end">
                                <div class="custom-control custom-switch mb-2">
                                    <input type="checkbox" class="custom-control-input" id="rental_active" name="is_active" value="1" @checked(! $oldRental || old('is_active'))>
                                    <label class="custom-control-label" for="rental_active">Disponible</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label for="rental_description">Descripción</label>
                            <input id="rental_description" name="description" maxlength="500" class="form-control" value="{{ $oldRental ? old('description') : '' }}" placeholder="Ej: Mikasa oficial, talla 5">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-info"><i class="fas fa-save"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endcanany

    @canany(['event_spaces.store', 'event_spaces.update'])
        @php($oldSpace = $errors->space->any())
        <div class="modal fade" id="space-modal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form method="POST" class="modal-content" id="space-form" enctype="multipart/form-data" data-store-action="{{ route('courts.event-spaces.store', $court) }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST" id="space-method">
                    <input type="hidden" name="_space_action" id="space-action" value="{{ old('_space_action') }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="space-modal-title">Agregar espacio para eventos</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        @include('partials.errors', ['bag' => 'space'])
                        <div class="form-row">
                            <div class="col-md-7 form-group">
                                <label for="space_name">Nombre *</label>
                                <input id="space_name" name="name" maxlength="120" class="form-control" value="{{ $oldSpace ? old('name') : '' }}" placeholder="Ej: Parrillero La Brasa" required>
                            </div>
                            <div class="col-md-5 form-group">
                                <label for="space_type">Tipo *</label>
                                <select id="space_type" name="type" class="form-control" required>
                                    @foreach (\App\Enums\EventSpaceType::cases() as $type)
                                        <option value="{{ $type->value }}" @selected($oldSpace && old('type') === $type->value)>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="col-md-4 form-group">
                                <label for="space_price">Precio por hora (Bs) *</label>
                                <input id="space_price" name="price_per_hour" type="number" step="0.01" min="0" class="form-control" value="{{ $oldSpace ? old('price_per_hour') : '' }}" required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="space_capacity">Máximo de personas *</label>
                                <input id="space_capacity" name="capacity" type="number" min="1" max="2000" class="form-control" value="{{ $oldSpace ? old('capacity') : '' }}" required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="space_min_hours">Mínimo de horas *</label>
                                <input id="space_min_hours" name="min_hours" type="number" min="1" max="12" class="form-control" value="{{ $oldSpace ? old('min_hours', 1) : 1 }}" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="col-md-4 form-group">
                                <label for="space_opening">Abre</label>
                                <input id="space_opening" name="opening_time" type="time" step="3600" class="form-control" value="{{ $oldSpace ? old('opening_time') : '' }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="space_closing">Cierra</label>
                                <input id="space_closing" name="closing_time" type="time" step="3600" class="form-control" value="{{ $oldSpace ? old('closing_time') : '' }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="d-block">&nbsp;</label>
                                <small class="text-muted">Vacío = mismo horario del centro ({{ substr($court->opening_time, 0, 5) }}–{{ substr($court->closing_time, 0, 5) }}).</small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="space_description">Descripción</label>
                            <textarea id="space_description" name="description" rows="2" maxlength="2000" class="form-control" placeholder="Qué lo hace especial: vista, ambiente, para qué tipo de evento es ideal...">{{ $oldSpace ? old('description') : '' }}</textarea>
                        </div>
                        <label>Incluye</label>
                        @php($oldAmenities = $oldSpace ? old('amenities', []) : [])
                        <div class="row mb-3">
                            @forelse ($spaceAmenities as $amenity)
                                <div class="col-6 col-md-4">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input space-amenity" id="space_amenity_{{ $amenity->key }}" name="amenities[]" value="{{ $amenity->key }}" @checked(in_array($amenity->key, $oldAmenities, true))>
                                        <label class="custom-control-label font-weight-normal" for="space_amenity_{{ $amenity->key }}">@if ($amenity->icon)<i class="{{ $amenity->icon }} text-muted mr-1"></i>@endif{{ $amenity->name }}</label>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 small text-muted">No hay servicios registrados.</div>
                            @endforelse
                        </div>
                        <div class="form-group">
                            <label for="space_rules">Normas del espacio</label>
                            <textarea id="space_rules" name="rules" rows="2" maxlength="2000" class="form-control" placeholder="Ej: música hasta las 23:00, traer carbón, entregar limpio...">{{ $oldSpace ? old('rules') : '' }}</textarea>
                            <small class="text-muted">El cliente las ve antes de reservar.</small>
                        </div>
                        <div class="form-row align-items-center">
                            <div class="col-md-8 form-group">
                                <label for="space_photo">Foto</label>
                                <div id="space-current-photo" class="mb-2 d-none">
                                    <img src="" alt="Foto actual" class="rounded mr-2" style="width: 96px; height: 64px; object-fit: cover;">
                                    <div class="custom-control custom-checkbox d-inline-block">
                                        <input type="checkbox" class="custom-control-input" id="space_remove_photo" name="remove_photo" value="1">
                                        <label class="custom-control-label font-weight-normal" for="space_remove_photo">Quitar foto</label>
                                    </div>
                                </div>
                                <input id="space_photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="form-control-file">
                                <small class="text-muted">JPG, PNG o WEBP de hasta 5 MB. Sin foto, la app usa la del centro.</small>
                            </div>
                            <div class="col-md-4 form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="space_active" name="is_active" value="1" @checked(! $oldSpace || old('is_active'))>
                                    <label class="custom-control-label" for="space_active">Disponible para reservas</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endcanany

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
                            @forelse ($fieldFeatures as $feature)
                                <div class="col-6">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input field-feature" id="field_feature_{{ $feature->key }}" name="features[]" value="{{ $feature->key }}" @checked(in_array($feature->key, $oldFieldFeatures, true))>
                                        <label class="custom-control-label font-weight-normal" for="field_feature_{{ $feature->key }}">@if ($feature->icon)<i class="{{ $feature->icon }} text-muted mr-1"></i>@endif{{ $feature->name }}</label>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 small text-muted">No hay características registradas.</div>
                            @endforelse
                        </div>
                        <div class="row" id="field-surcharges">
                            <div class="col-12 form-group" data-surcharge-for="air_conditioning">
                                <label for="field_ac_price"><i class="fas fa-snowflake text-muted mr-1"></i>Aire acondicionado: extra por hora (Bs)</label>
                                <input id="field_ac_price" name="air_conditioning_price" type="number" step="0.01" min="0" class="form-control" value="{{ $errors->field->any() ? old('air_conditioning_price') : '' }}" placeholder="Vacío = incluido sin costo">
                                <small class="text-muted">Si tiene precio, el cliente elige al reservar si lo quiere y se suma a su reserva.</small>
                            </div>
                            <div class="col-7 form-group" data-surcharge-for="lighting">
                                <label for="field_lighting_price"><i class="fas fa-lightbulb text-muted mr-1"></i>Luz: extra por hora (Bs)</label>
                                <input id="field_lighting_price" name="lighting_price" type="number" step="0.01" min="0" class="form-control" value="{{ $errors->field->any() ? old('lighting_price') : '' }}" placeholder="Vacío = sin costo">
                            </div>
                            <div class="col-5 form-group" data-surcharge-for="lighting">
                                <label for="field_lighting_from">Desde las</label>
                                <input id="field_lighting_from" name="lighting_from" type="time" step="1800" class="form-control" value="{{ $errors->field->any() ? old('lighting_from') : '' }}">
                            </div>
                            <div class="col-12 mb-3 mt-n2" data-surcharge-for="lighting">
                                <small class="text-muted">Se suma automáticamente a cada hora reservada desde esa hora (de noche).</small>
                            </div>
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
                    $('#field_ac_price').val(field.air_conditioning_price || '');
                    $('#field_lighting_price').val(field.lighting_price || '');
                    $('#field_lighting_from').val(field.lighting_from || '18:00');
                    $('.field-sport').each(function () {
                        this.checked = field.sports.map(Number).includes(Number(this.value));
                    });
                }
                toggleSurcharges();
                $modal.modal('show');
            }

            // Price inputs of an option only show while the option is checked.
            function toggleSurcharges() {
                $('[data-surcharge-for]').each(function () {
                    const checked = $('#field_feature_' + $(this).data('surcharge-for')).is(':checked');
                    $(this).toggleClass('d-none', !checked);
                });
            }

            $('.field-feature').on('change', toggleSurcharges);

            $('[data-add-field]').on('click', function () {
                $form.find('.alert').remove();
                openModal({ name: '', price_per_hour: '', dimensions: '', description: '', features: [], air_conditioning_price: '', lighting_price: '', lighting_from: '18:00', sports: [] });
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
                toggleSurcharges();
                $modal.modal('show');
            @endif
        })();

        (function () {
            const $modal = $('#rental-modal');
            const $form = $('#rental-form');

            function openModal(item) {
                const isEdit = Boolean(item.id);
                const action = isEdit ? item.action : $form.data('store-action');
                $form.attr('action', action);
                $('#rental-action').val(isEdit ? action : '');
                $('#rental-method').val(isEdit ? 'PUT' : 'POST');
                $('#rental-modal-title').text(isEdit ? 'Editar ' + item.name : 'Agregar artículo en alquiler');
                $('#rental_name').val(item.name);
                if (item.sport_id) $('#rental_sport').val(String(item.sport_id));
                $('#rental_price').val(item.price);
                $('#rental_price_type').val(item.price_type || 'per_hour');
                $('#rental_stock').val(item.stock || '');
                $('#rental_description').val(item.description || '');
                $('#rental_active').prop('checked', item.is_active !== false);
                $modal.modal('show');
            }

            $('[data-add-rental]').on('click', function () {
                $form.find('.alert').remove();
                openModal({ name: '', price: '', price_type: 'per_hour', is_active: true });
            });

            $('[data-edit-rental]').on('click', function () {
                $form.find('.alert').remove();
                openModal($(this).data('edit-rental'));
            });

            @if ($errors->rental->any())
                const previousAction = $('#rental-action').val();
                $form.attr('action', previousAction || $form.data('store-action'));
                $('#rental-method').val(previousAction ? 'PUT' : 'POST');
                $modal.modal('show');
            @endif
        })();

        (function () {
            const $modal = $('#space-modal');
            const $form = $('#space-form');

            function openModal(space) {
                const isEdit = Boolean(space.id);
                const action = isEdit ? space.action : $form.data('store-action');
                $form.attr('action', action);
                $('#space-action').val(isEdit ? action : '');
                $('#space-method').val(isEdit ? 'PUT' : 'POST');
                $('#space-modal-title').text(isEdit ? 'Editar ' + space.name : 'Agregar espacio para eventos');
                $('#space_name').val(space.name);
                $('#space_type').val(space.type || $('#space_type option:first').val());
                $('#space_price').val(space.price_per_hour);
                $('#space_capacity').val(space.capacity);
                $('#space_min_hours').val(space.min_hours || 1);
                $('#space_opening').val(space.opening_time || '');
                $('#space_closing').val(space.closing_time || '');
                $('#space_description').val(space.description || '');
                $('#space_rules').val(space.rules || '');
                $('#space_active').prop('checked', space.is_active !== false);
                $('#space_photo').val('');
                $('#space_remove_photo').prop('checked', false);
                $('#space-current-photo').toggleClass('d-none', !space.photo_url)
                    .find('img').attr('src', space.photo_url || '');
                $('.space-amenity').each(function () {
                    this.checked = (space.amenities || []).includes(this.value);
                });
                $modal.modal('show');
            }

            $('[data-add-space]').on('click', function () {
                $form.find('.alert').remove();
                openModal({ name: '', price_per_hour: '', capacity: '', min_hours: 1, amenities: [], is_active: true });
            });

            $('[data-edit-space]').on('click', function () {
                $form.find('.alert').remove();
                openModal($(this).data('edit-space'));
            });

            @if ($errors->space->any())
                // Reopen the modal with the rejected input.
                const previousAction = $('#space-action').val();
                $form.attr('action', previousAction || $form.data('store-action'));
                $('#space-method').val(previousAction ? 'PUT' : 'POST');
                $modal.modal('show');
            @endif
        })();
    </script>
@endpush
