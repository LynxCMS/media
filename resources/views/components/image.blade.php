@if ($shown)
<img src="{{ $shown->url() }}" width="{{ $shown->width() }}" height="{{ $shown->height() }}" alt="{{ $altText }}"
    @if ($srcset) srcset="{{ $srcset }}" @endif
    @if ($sizes) sizes="{{ $sizes }}" @endif
    @if ($lazy) loading="lazy" decoding="async" @endif
    {{ $attributes }}>
@endif
