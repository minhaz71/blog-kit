{{-- Per-entity custom code (products, categories, posts, pages).
     CSS loads as a real cacheable file scoped to this entity. --}}
@php
    $entityType = strtolower(class_basename($model));
@endphp

@if (!empty($model->custom_css))
    <link rel="stylesheet" href="{{ route('entity.css', ['type' => $entityType, 'id' => $model->id]) }}?v={{ $model->updated_at?->timestamp }}">
@endif

@if (!empty($model->custom_css_file))
    <link rel="stylesheet" href="{{ \Illuminate\Support\Str::startsWith($model->custom_css_file, ['http://', 'https://', '/']) ? $model->custom_css_file : Storage::disk('public')->url($model->custom_css_file) }}">
@endif

@if (!empty($model->custom_html))
    <div class="custom-html-block">{!! $model->custom_html !!}</div>
@endif

@if (!empty($model->custom_js))
    <script>{!! $model->custom_js !!}</script>
@endif
