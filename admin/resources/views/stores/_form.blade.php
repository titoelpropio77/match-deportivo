@include('partials.errors')
@csrf
@php($selectedCategories = array_map('intval', old('categories', $store->exists ? $store->categories->modelKeys() : [])))
<div class="row">
    <div class="col-md-6 form-group">
        <label for="court_id">Centro deportivo *</label>
        @if ($store->exists)
            <input class="form-control" value="{{ $store->court->name }}" disabled>
        @else
            <select id="court_id" name="court_id" class="form-control @error('court_id') is-invalid @enderror" required>
                <option value="">— Selecciona —</option>
                @foreach ($courts as $court)
                    <option value="{{ $court->id }}" @selected((string) old('court_id', $store->court_id) === (string) $court->id)>{{ $court->name }}</option>
                @endforeach
            </select>
            <small class="text-muted">Un centro puede tener varias tiendas.</small>
        @endif
    </div>
    <div class="col-md-6 form-group">
        <label for="name">Nombre de la tienda *</label>
        <input id="name" name="name" maxlength="120" required class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $store->name) }}" placeholder="Ej: Wally Shop">
    </div>
    <div class="col-md-12 form-group">
        <label>Categorías de productos que vende *</label>
        <div class="d-flex flex-wrap @error('categories') is-invalid @enderror">
            @foreach ($categories as $category)
                <div class="custom-control custom-checkbox mr-4 mb-2">
                    <input type="checkbox" class="custom-control-input" id="category-{{ $category->id }}" name="categories[]" value="{{ $category->id }}" @checked(in_array($category->id, $selectedCategories, true))>
                    <label class="custom-control-label font-weight-normal" for="category-{{ $category->id }}">
                        @if ($category->icon)<i class="{{ $category->icon }} text-muted mr-1"></i>@endif{{ $category->name }}
                    </label>
                </div>
            @endforeach
        </div>
        <small class="text-muted">En la app los clientes encuentran la tienda por estas categorías. Los productos se cargan en una de ellas.</small>
    </div>
    <div class="col-md-8 form-group">
        <label for="description">Descripción</label>
        <textarea id="description" name="description" rows="3" maxlength="2000" class="form-control @error('description') is-invalid @enderror" placeholder="Qué vende, cómo se retiran los pedidos...">{{ old('description', $store->description) }}</textarea>
    </div>
    <div class="col-md-4 form-group">
        <label for="phone">Teléfono / WhatsApp</label>
        <input id="phone" name="phone" maxlength="30" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $store->phone) }}" placeholder="70000000">
        <div class="custom-control custom-switch mt-4">
            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $store->is_active))>
            <label class="custom-control-label" for="is_active">Tienda activa (visible en la app)</label>
        </div>
    </div>
    <div class="col-md-6 form-group">
        <label for="cover">Foto de portada</label>
        @if ($store->cover_path)
            <div class="mb-2">
                <img src="{{ $store->coverUrl() }}" alt="Portada" class="rounded" style="max-height: 120px;">
                <div class="custom-control custom-checkbox mt-1">
                    <input type="checkbox" class="custom-control-input" id="remove_cover" name="remove_cover" value="1">
                    <label class="custom-control-label" for="remove_cover">Quitar portada</label>
                </div>
            </div>
        @endif
        <input type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp" class="form-control-file @error('cover') is-invalid @enderror">
        <small class="text-muted">JPG, PNG o WEBP de hasta 5 MB. Se muestra en la tarjeta de la tienda en la app.</small>
    </div>
</div>
