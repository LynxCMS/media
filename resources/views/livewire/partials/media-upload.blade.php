{{--
    An upload zone: files chosen or dropped (issue #223) go to $model, which stores them through
    media.store. lynxUploadZone (the admin's JS) scales an images field's larger JPEG, PNG and WebP
    files down to the field's maximum first ($shrink), then uploads them; the server checks every file.
--}}
<label data-media-drop="{{ $model }}" data-upload-model="{{ $model }}" data-upload-multiple="{{ $multiple ? 'true' : 'false' }}"
       @if ($shrink !== null) data-shrink-max-width="{{ $shrink['maxWidth'] }}" data-shrink-max-height="{{ $shrink['maxHeight'] }}" data-shrink-quality="{{ $shrink['quality'] }}" @endif
       class="border-input hover:bg-muted/50 flex cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed p-6 text-center text-sm"
       x-data="lynxUploadZone"
       x-on:dragover.prevent="dragging = true"
       x-on:dragleave="if (! $el.contains($event.relatedTarget)) dragging = false"
       x-on:drop.prevent="drop($event)"
       x-bind:class="dragging && 'border-primary bg-muted/50'">
    <x-lucide-upload class="size-5 text-muted-foreground" aria-hidden="true" />
    <span><span class="text-primary font-medium">{{ __($isImages ? 'lynx-media::admin.choose_images' : 'lynx-media::admin.choose_files') }}</span> {{ __('lynx-media::admin.or_drop') }}</span>
    <span class="text-muted-foreground text-xs">{{ str_replace(',', ' ', $accept) }}</span>
    <input type="file" class="sr-only" accept="{{ $accept }}" @if ($multiple) multiple @endif x-on:change="choose($event)">
    <span x-show="busy" x-cloak class="text-muted-foreground text-xs" role="status">{{ __('lynx-media::admin.uploading') }}</span>
</label>
