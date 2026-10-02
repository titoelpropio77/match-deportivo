<div class="banner-thumb" style="background-color: {{ $banner->background_color }};@if ($banner->imageUrl()) background-image: url('{{ $banner->imageUrl() }}');@endif">
    <span>{{ \Illuminate\Support\Str::limit($banner->title, 28) }}</span>
</div>
