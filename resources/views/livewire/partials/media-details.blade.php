{{-- A file's details, in place of its card while it is edited: focus point, description per language, language and tags. --}}
<div class="bg-card grid gap-4 rounded-md border p-4 sm:grid-cols-2" data-media-details="{{ $item['key'] }}">
    <div class="flex items-center justify-between gap-3 sm:col-span-2">
        <span class="truncate text-sm font-medium" title="{{ basename((string) $item['file']) }}">{{ basename((string) $item['file']) }}</span>
        <x-lynx-admin::ui.button variant="outline" size="xs" type="button" wire:click="$set('editing', null)">{{ __('lynx-media::admin.close') }}</x-lynx-admin::ui.button>
    </div>
    @if ($isImages)
        <div class="grid gap-1">
            <p class="text-sm font-medium">{{ __('lynx-media::admin.focus') }}</p>
            <p class="text-muted-foreground text-xs">{{ __('lynx-media::admin.focus_help') }}</p>
            <div class="relative cursor-crosshair overflow-hidden rounded-md"
                 x-on:click="const r = $el.getBoundingClientRect(); $wire.focus({{ $index }}, (($event.clientX - r.left) / r.width) * 100, (($event.clientY - r.top) / r.height) * 100)">
                <img src="{{ app(\LynxCms\Media\MediaPaths::class)->url((string) $item['file']) }}" alt="" class="w-full">
                <span class="pointer-events-none absolute size-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-primary/70 shadow" style="left: {{ $item['focus']['x'] ?? 50 }}%; top: {{ $item['focus']['y'] ?? 50 }}%"></span>
            </div>
        </div>
    @endif
    <div class="grid content-start gap-3">
        <div class="grid gap-1">
            <span class="text-sm font-medium">{{ __('lynx-media::admin.description') }}</span>
            <x-lynx-admin::ui.tabs :value="$languages[0]['code']" class="gap-1">
                @if (count($languages) > 1)
                    <x-lynx-admin::ui.tabs-list variant="compact" aria-label="{{ __('lynx-media::admin.languages') }}">
                        @foreach ($languages as $language)
                            <x-lynx-admin::ui.tabs-trigger :value="$language['code']">{{ $language['label'] }}</x-lynx-admin::ui.tabs-trigger>
                        @endforeach
                    </x-lynx-admin::ui.tabs-list>
                @endif
                @foreach ($languages as $language)
                    <div role="tabpanel" :id="$id('blat-tabpanel', @js($language['code']))" :aria-labelledby="$id('blat-tab', @js($language['code']))" x-show="tab === '{{ $language['code'] }}'" @if (! $loop->first) x-cloak @endif>
                        <textarea rows="2" class="blat-textarea" wire:model="items.{{ $index }}.description.{{ $language['code'] }}" aria-label="{{ __('lynx-media::admin.description_language', ['language' => $language['label']]) }}"></textarea>
                    </div>
                @endforeach
            </x-lynx-admin::ui.tabs>
        </div>
        @if ($isPerLanguage)
            <label class="grid gap-1 text-sm">
                <span class="font-medium">{{ __('lynx-media::admin.language') }}</span>
                <select class="blat-select" wire:model.live="items.{{ $index }}.language">
                    <option value="">{{ __('lynx-media::admin.every_language') }}</option>
                    @foreach ($languages as $option)
                        <option value="{{ $option['code'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="grid gap-1 text-sm">
            <span class="font-medium">{{ __('lynx-media::admin.tags') }}</span>
            <input type="text" class="blat-input" wire:model="items.{{ $index }}.tags" placeholder="{{ $allowedTags !== [] ? implode(', ', $allowedTags) : __('lynx-media::admin.tags_placeholder') }}">
        </label>
    </div>
</div>
