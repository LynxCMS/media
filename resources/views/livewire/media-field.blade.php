<div class="grid gap-3">
    @if ($items !== [])
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" @unless ($disabled) wire:sort="sort" @endunless>
            @foreach ($items as $index => $item)
                @php($url = app(\LynxCms\Media\MediaPaths::class)->url((string) $item['file']))
                <li wire:key="file-{{ $item['key'] }}" wire:sort:item="{{ $item['key'] }}" @class(['bg-card overflow-hidden rounded-md border', 'opacity-50' => ! ($item['enabled'] ?? true)])>
                    @if ($isImages)
                        <div @class(['relative aspect-[4/3] bg-muted', 'cursor-grab active:cursor-grabbing' => ! $disabled]) @unless ($disabled) wire:sort:handle title="Drag to reorder" @endunless>
                            <img src="{{ $url }}" alt="" class="size-full object-cover" style="object-position: {{ $item['focus']['x'] ?? 50 }}% {{ $item['focus']['y'] ?? 50 }}%" loading="lazy">
                        </div>
                    @else
                        <div @class(['flex aspect-[4/3] items-center justify-center bg-muted p-3 text-center text-sm font-medium', 'cursor-grab active:cursor-grabbing' => ! $disabled]) @unless ($disabled) wire:sort:handle title="Drag to reorder" @endunless>
                            {{ strtoupper(pathinfo((string) $item['file'], PATHINFO_EXTENSION)) }}
                        </div>
                    @endif
                    <div class="grid gap-1 p-2 text-xs">
                        <span class="truncate font-medium" title="{{ basename((string) $item['file']) }}">{{ basename((string) $item['file']) }}</span>
                        <span class="text-muted-foreground">{{ $isImages ? ($item['width'] ?? 0).' × '.($item['height'] ?? 0).' · ' : '' }}{{ \Illuminate\Support\Number::fileSize((int) ($item['bytes'] ?? 0)) }}</span>
                        @unless ($disabled)
                            <span class="flex flex-wrap gap-2">
                                <button type="button" class="text-primary underline-offset-4 hover:underline" wire:click="$set('editing', {{ ($editing === $item['key']) ? 'null' : Js::from($item['key']) }})">{{ $editing === $item['key'] ? 'Close' : 'Edit' }}</button>
                                <button type="button" class="text-primary underline-offset-4 hover:underline" wire:click="toggle({{ $index }})">{{ ($item['enabled'] ?? true) ? 'Hide' : 'Show' }}</button>
                                <button type="button" class="text-destructive underline-offset-4 hover:underline" wire:click="remove({{ $index }})">Remove</button>
                                @if (count($items) > 1)
                                    <span class="ms-auto flex gap-1">
                                        <button type="button" class="text-muted-foreground hover:text-foreground disabled:opacity-30" wire:click="move({{ $index }}, -1)" @disabled($loop->first) aria-label="Move {{ basename((string) $item['file']) }} earlier"><x-lucide-arrow-left class="size-3.5" aria-hidden="true" /></button>
                                        <button type="button" class="text-muted-foreground hover:text-foreground disabled:opacity-30" wire:click="move({{ $index }}, 1)" @disabled($loop->last) aria-label="Move {{ basename((string) $item['file']) }} later"><x-lucide-arrow-right class="size-3.5" aria-hidden="true" /></button>
                                    </span>
                                @endif
                            </span>
                        @endunless
                    </div>
                </li>
            @endforeach
        </ul>
        @if (! $disabled && count($items) > 1)
            <p class="text-muted-foreground -mt-1 text-xs">Drag to reorder, or use the arrows.</p>
        @endif

        @foreach ($items as $index => $item)
            @if ($editing === $item['key'])
                <div class="bg-card grid gap-4 rounded-md border p-4 sm:grid-cols-2" wire:key="details-{{ $item['key'] }}">
                    @if ($isImages)
                        <div class="grid gap-1">
                            <p class="text-sm font-medium">Focus point</p>
                            <p class="text-muted-foreground text-xs">Click what crops must keep in view.</p>
                            <div class="relative cursor-crosshair overflow-hidden rounded-md"
                                 x-on:click="const r = $el.getBoundingClientRect(); $wire.focus({{ $index }}, (($event.clientX - r.left) / r.width) * 100, (($event.clientY - r.top) / r.height) * 100)">
                                <img src="{{ app(\LynxCms\Media\MediaPaths::class)->url((string) $item['file']) }}" alt="" class="w-full">
                                <span class="pointer-events-none absolute size-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-primary/70 shadow" style="left: {{ $item['focus']['x'] ?? 50 }}%; top: {{ $item['focus']['y'] ?? 50 }}%"></span>
                            </div>
                        </div>
                    @endif
                    <div class="grid content-start gap-3">
                        <div class="grid gap-1">
                            <span class="text-sm font-medium">Description</span>
                            <x-lynx-admin::ui.tabs :value="$languages[0]['code']" class="gap-1">
                                @if (count($languages) > 1)
                                    <x-lynx-admin::ui.tabs-list variant="compact" aria-label="Languages">
                                        @foreach ($languages as $language)
                                            <x-lynx-admin::ui.tabs-trigger :value="$language['code']">{{ $language['label'] }}</x-lynx-admin::ui.tabs-trigger>
                                        @endforeach
                                    </x-lynx-admin::ui.tabs-list>
                                @endif
                                @foreach ($languages as $language)
                                    <div x-show="tab === '{{ $language['code'] }}'" @if (! $loop->first) x-cloak @endif>
                                        <textarea rows="2" class="blat-textarea" wire:model="items.{{ $index }}.description.{{ $language['code'] }}" aria-label="Description ({{ $language['label'] }})"></textarea>
                                    </div>
                                @endforeach
                            </x-lynx-admin::ui.tabs>
                        </div>
                        <label class="grid gap-1 text-sm">
                            <span class="font-medium">Tags</span>
                            <input type="text" class="blat-input" wire:model="items.{{ $index }}.tags" placeholder="{{ $allowedTags !== [] ? implode(', ', $allowedTags) : 'hero, gallery' }}">
                        </label>
                    </div>
                </div>
            @endif
        @endforeach
    @endif

    @if ($canAdd)
        <label class="border-input hover:bg-muted/50 flex cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed p-6 text-center text-sm">
            <x-lucide-upload class="size-5 text-muted-foreground" aria-hidden="true" />
            <span><span class="text-primary font-medium">Choose {{ $isImages ? 'images' : 'files' }}</span> or drop them here</span>
            <span class="text-muted-foreground text-xs">{{ str_replace(',', ' ', $accept) }}</span>
            <input type="file" class="sr-only" wire:model="uploads" accept="{{ $accept }}" @if ($multiple) multiple @endif>
            <span wire:loading wire:target="uploads" class="text-muted-foreground text-xs">Uploading…</span>
        </label>
    @elseif ($pageId === null && ! $disabled)
        <p class="text-muted-foreground text-sm">Save the page first, then add {{ $isImages ? 'images' : 'files' }}.</p>
    @endif
    @error('uploads') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
</div>
