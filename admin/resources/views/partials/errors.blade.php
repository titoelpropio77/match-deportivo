@if ($errors->{$bag ?? 'default'}->any())
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle mr-1"></i> Revisa los campos marcados:
        <ul class="mb-0 mt-1">
            @foreach ($errors->{$bag ?? 'default'}->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
