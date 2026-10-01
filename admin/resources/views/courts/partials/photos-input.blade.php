{{--
    Venue gallery. New files are previewed before saving and can be added in several picks;
    existing photos (edit) are marked with remove_photos[] and deleted on save.
--}}
@php($removePhotos = array_map('intval', old('remove_photos', [])))

<label class="mt-3">Galería de fotos</label>
<div class="court-gallery" data-court-gallery>
    @foreach ($court->exists ? $court->photos : [] as $photo)
        <div class="court-gallery-item {{ in_array($photo->id, $removePhotos, true) ? 'is-removed' : '' }}">
            <img src="{{ $photo->src }}" alt="Foto de {{ $court->name }}" loading="lazy">
            <input type="checkbox" name="remove_photos[]" value="{{ $photo->id }}" class="d-none" @checked(in_array($photo->id, $removePhotos, true))>
            <button type="button" class="btn btn-xs btn-danger court-gallery-remove" data-toggle-remove title="Quitar / restaurar"><i class="fas fa-trash"></i></button>
            <span class="court-gallery-badge badge badge-danger">Se eliminará</span>
        </div>
    @endforeach
    <label class="court-gallery-add" for="photos_input" title="Agregar fotos">
        <i class="fas fa-camera fa-2x"></i>
        <span>Agregar fotos</span>
    </label>
</div>
<input type="file" id="photos_input" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="d-none">
<small class="form-text text-muted">
    JPG, PNG o WEBP, hasta 5 MB cada una y 10 por centro deportivo. Se muestran en la app en este orden.
</small>
@error('photos')<div class="text-danger small">{{ $message }}</div>@enderror
@if ($errors->has('photos.*'))
    <div class="text-danger small">{{ $errors->first('photos.*') }} Vuelve a seleccionar las fotos.</div>
@endif

@once
    @push('styles')
        <style>
            .court-gallery { display: flex; flex-wrap: wrap; gap: .75rem; }
            .court-gallery-item, .court-gallery-add {
                position: relative; width: 140px; height: 105px; border-radius: .35rem; overflow: hidden; margin: 0;
            }
            .court-gallery-item img { width: 100%; height: 100%; object-fit: cover; }
            .court-gallery-remove { position: absolute; top: .3rem; right: .3rem; }
            .court-gallery-badge { position: absolute; left: .3rem; bottom: .3rem; display: none; }
            .court-gallery-item.is-removed img { opacity: .35; filter: grayscale(1); }
            .court-gallery-item.is-removed .court-gallery-badge { display: inline-block; }
            .court-gallery-item.is-new { outline: 2px solid #28a745; }
            .court-gallery-add {
                display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .25rem;
                border: 2px dashed #adb5bd; color: #6c757d; cursor: pointer; font-weight: 400;
            }
            .court-gallery-add:hover { border-color: #007bff; color: #007bff; }
        </style>
    @endpush

    @push('scripts')
        <script>
            (function () {
                const gallery = document.querySelector('[data-court-gallery]');
                const input = document.getElementById('photos_input');
                if (!gallery || !input) return;

                const addTile = gallery.querySelector('.court-gallery-add');
                // Accumulates every pick so the user can add photos in several steps and drop some before saving.
                let picked = new DataTransfer();

                function syncInput() {
                    input.files = picked.files;
                }

                function renderNew() {
                    gallery.querySelectorAll('.court-gallery-item.is-new').forEach(function (el) { el.remove(); });
                    Array.from(picked.files).forEach(function (file, index) {
                        const item = document.createElement('div');
                        item.className = 'court-gallery-item is-new';
                        item.title = file.name;

                        const img = document.createElement('img');
                        img.src = URL.createObjectURL(file);
                        img.onload = function () { URL.revokeObjectURL(img.src); };

                        const remove = document.createElement('button');
                        remove.type = 'button';
                        remove.className = 'btn btn-xs btn-danger court-gallery-remove';
                        remove.title = 'Quitar';
                        remove.innerHTML = '<i class="fas fa-times"></i>';
                        remove.addEventListener('click', function () {
                            const next = new DataTransfer();
                            Array.from(picked.files).forEach(function (f, i) { if (i !== index) next.items.add(f); });
                            picked = next;
                            syncInput();
                            renderNew();
                        });

                        item.append(img, remove);
                        gallery.insertBefore(item, addTile);
                    });
                }

                input.addEventListener('change', function () {
                    Array.from(input.files).forEach(function (file) {
                        if (file.type.startsWith('image/')) picked.items.add(file);
                    });
                    syncInput();
                    renderNew();
                });

                gallery.addEventListener('click', function (event) {
                    const toggle = event.target.closest('[data-toggle-remove]');
                    if (!toggle) return;
                    const item = toggle.closest('.court-gallery-item');
                    const checkbox = item.querySelector('input[name="remove_photos[]"]');
                    checkbox.checked = !checkbox.checked;
                    item.classList.toggle('is-removed', checkbox.checked);
                });
            })();
        </script>
    @endpush
@endonce
