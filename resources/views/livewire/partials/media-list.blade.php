{{-- A list of the field's files ($shown: index => form item), sortable by drag or the arrow buttons. --}}
<ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" @unless ($disabled) wire:sort="sort" @endunless>
    @foreach ($shown as $index => $item)
        @if ($editing === $item['key'])
            {{-- The details take the card's place, across the row, until closed. --}}
            <li wire:key="file-{{ $item['key'] }}" wire:sort:item="{{ $item['key'] }}" class="col-span-full">
                @include('lynx-media::livewire.partials.media-details')
            </li>
            @continue
        @endif
        @php($url = app(\LynxCms\Media\MediaPaths::class)->url((string) $item['file']))
        <li wire:key="file-{{ $item['key'] }}" wire:sort:item="{{ $item['key'] }}" @class(['bg-card overflow-hidden rounded-md border', 'opacity-50' => ! ($item['enabled'] ?? true)])>
            @if ($isImages)
                <div @class(['relative aspect-[4/3] bg-muted', 'cursor-grab active:cursor-grabbing' => ! $disabled]) @unless ($disabled) wire:sort:handle title="{{ __('lynx-media::admin.drag') }}" @endunless>
                    <img src="{{ $url }}" alt="" class="size-full object-cover" style="object-position: {{ $item['focus']['x'] ?? 50 }}% {{ $item['focus']['y'] ?? 50 }}%" loading="lazy">
                    @php($name = basename((string) $item['file']))
                    <button type="button" wire:sort:ignore data-media-zoom
                            class="bg-background/85 text-foreground hover:bg-background focus-visible:ring-ring absolute top-1.5 right-1.5 grid size-7 place-items-center rounded-md shadow-sm focus-visible:ring-2 focus-visible:outline-none"
                            x-on:click="zoom = { url: {{ Js::from($url) }}, name: {{ Js::from($name) }} }; $dispatch('open-dialog-{{ $zoomDialog }}')"
                            aria-label="{{ __('lynx-media::admin.zoom', ['file' => $name]) }}" title="{{ __('lynx-media::admin.zoom', ['file' => $name]) }}">
                        <x-lucide-zoom-in class="size-4" aria-hidden="true" />
                    </button>
                </div>
            @else
                <div @class(['flex aspect-[4/3] items-center justify-center bg-muted p-3 text-center text-sm font-medium', 'cursor-grab active:cursor-grabbing' => ! $disabled]) @unless ($disabled) wire:sort:handle title="{{ __('lynx-media::admin.drag') }}" @endunless>
                    {{ strtoupper(pathinfo((string) $item['file'], PATHINFO_EXTENSION)) }}
                </div>
            @endif
            <div class="grid gap-1 p-2 text-xs">
                <span class="truncate font-medium" title="{{ basename((string) $item['file']) }}">{{ basename((string) $item['file']) }}</span>
                <span class="text-muted-foreground">{{ $isImages ? ($item['width'] ?? 0).' × '.($item['height'] ?? 0).' · ' : '' }}{{ \LynxCms\Core\Support\LocalizedNumber::fileSize((int) ($item['bytes'] ?? 0)) }}</span>
                @unless ($disabled)
                    <span class="flex flex-wrap gap-2">
                        <button type="button" class="text-primary underline-offset-4 hover:underline" wire:click="$set('editing', {{ ($editing === $item['key']) ? 'null' : Js::from($item['key']) }})">{{ __($editing === $item['key'] ? 'lynx-media::admin.close' : 'lynx-media::admin.edit') }}</button>
                        <button type="button" class="text-primary underline-offset-4 hover:underline" wire:click="toggle({{ $index }})">{{ __(($item['enabled'] ?? true) ? 'lynx-media::admin.hide' : 'lynx-media::admin.show') }}</button>
                        <button type="button" class="text-destructive underline-offset-4 hover:underline" wire:click="remove({{ $index }})">{{ __('lynx-media::admin.remove') }}</button>
                        @if (count($shown) > 1)
                            <span class="ms-auto flex gap-1">
                                <button type="button" class="text-muted-foreground hover:text-foreground disabled:opacity-30" wire:click="move({{ $index }}, -1)" @disabled($loop->first) aria-label="{{ __('lynx-media::admin.move_earlier', ['file' => basename((string) $item['file'])]) }}"><x-lucide-arrow-left class="size-3.5" aria-hidden="true" /></button>
                                <button type="button" class="text-muted-foreground hover:text-foreground disabled:opacity-30" wire:click="move({{ $index }}, 1)" @disabled($loop->last) aria-label="{{ __('lynx-media::admin.move_later', ['file' => basename((string) $item['file'])]) }}"><x-lucide-arrow-right class="size-3.5" aria-hidden="true" /></button>
                            </span>
                        @endif
                    </span>
                @endunless
            </div>
        </li>
    @endforeach
</ul>
@if (! $disabled && count($shown) > 1)
    <p class="text-muted-foreground -mt-1 text-xs">{{ __('lynx-media::admin.drag_hint') }}</p>
@endif
