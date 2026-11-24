{{-- An upload zone: chosen or dropped files go to $model, which stores them through media.store. --}}
{{-- Files dropped here go into the file input, so they upload and are checked as chosen ones are (issue #223). --}}
<label data-media-drop="{{ $model }}"
       class="border-input hover:bg-muted/50 flex cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed p-6 text-center text-sm"
       x-data="{
           dragging: false,
           drop(event, multiple) {
               this.dragging = false;
               const files = [...event.dataTransfer.files].slice(0, multiple ? undefined : 1);
               if (files.length === 0) return;
               const chosen = new DataTransfer();
               files.forEach((file) => chosen.items.add(file));
               const input = this.$el.querySelector('input[type=file]');
               input.files = chosen.files;
               input.dispatchEvent(new Event('change', { bubbles: true }));
           },
       }"
       x-on:dragover.prevent="dragging = true"
       x-on:dragleave="if (! $el.contains($event.relatedTarget)) dragging = false"
       x-on:drop.prevent="drop($event, {{ $multiple ? 'true' : 'false' }})"
       x-bind:class="dragging && 'border-primary bg-muted/50'">
    <x-lucide-upload class="size-5 text-muted-foreground" aria-hidden="true" />
    <span><span class="text-primary font-medium">{{ __($isImages ? 'lynx-media::admin.choose_images' : 'lynx-media::admin.choose_files') }}</span> {{ __('lynx-media::admin.or_drop') }}</span>
    <span class="text-muted-foreground text-xs">{{ str_replace(',', ' ', $accept) }}</span>
    <input type="file" class="sr-only" wire:model="{{ $model }}" accept="{{ $accept }}" @if ($multiple) multiple @endif>
    <span wire:loading wire:target="{{ $model }}" class="text-muted-foreground text-xs">{{ __('lynx-media::admin.uploading') }}</span>
</label>
