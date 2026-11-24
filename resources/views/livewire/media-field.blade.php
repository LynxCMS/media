{{--
    zoom: the picture a zoom button opened, shown at full size in the field's one dialog. Its id is
    lower case: HTML lowers the dialog's event listener name, and Livewire ids have capitals.
--}}
@php
    $zoomDialog = 'media-zoom-'.strtolower($this->getId());
@endphp
<div class="grid gap-3" x-data="{ zoom: { url: '', name: '' } }">
    @if ($isPerLanguage && count($languages) > 1)
        {{-- A file per language (ADR-0150): a tab per language, with its own files and those every language shares. --}}
        @php
            $own = array_filter($items, fn ($item) => ($item['language'] ?? '') === $language);
            $shared = array_filter($items, fn ($item) => ($item['language'] ?? '') === '');
            $languageLabel = collect($languages)->firstWhere('code', $language)['label'] ?? $language;
            // "PT image" on a field with one file, "PT images" on others.
            $groupTitle = trans_choice($isImages ? 'lynx-media::admin.language_images' : 'lynx-media::admin.language_files', $multiple ? 2 : 1, ['language' => $languageLabel]);
        @endphp
        <div class="flex flex-wrap items-center gap-1" role="group" aria-label="{{ __('lynx-media::admin.languages') }}" data-media-languages>
            @foreach ($languages as $tab)
                @php
                    $hasOwn = collect($items)->contains(fn ($item) => ($item['language'] ?? '') === $tab['code']);
                @endphp
                <x-lynx-admin::ui.button size="xs" type="button" :variant="$language === $tab['code'] ? 'secondary' : 'ghost'" wire:click="$set('language', {{ Js::from($tab['code']) }})"
                    aria-pressed="{{ $language === $tab['code'] ? 'true' : 'false' }}" data-media-language="{{ $tab['code'] }}">
                    {{ $tab['label'] }}
                    <span aria-hidden="true" @class(['lynx-tab-state', 'is-filled' => $hasOwn])></span>
                </x-lynx-admin::ui.button>
            @endforeach
        </div>
        <section class="grid gap-2" data-media-group="language" aria-label="{{ $groupTitle }}">
            <p class="text-sm font-medium" data-media-group-title>{{ $groupTitle }}</p>
            @if ($own !== [])
                @include('lynx-media::livewire.partials.media-list', ['shown' => $own])
            @elseif ($fallsBackTo !== null)
                <p class="text-muted-foreground text-sm" data-media-fallback>{{ match (true) { $shared !== [] => __('lynx-media::admin.uses_shared'), $fallsBackTo === '' => __('lynx-media::admin.no_language_file'), default => __('lynx-media::admin.falls_back_to', ['language' => $fallsBackTo]) } }}</p>
            @endif
            @if ($canAdd)
                @include('lynx-media::livewire.partials.media-upload', ['model' => 'uploads'])
            @endif
        </section>
        <section class="grid gap-2 border-t pt-3" data-media-group="shared" aria-label="{{ __('lynx-media::admin.shared_files') }}">
            <p class="text-sm font-medium">{{ __('lynx-media::admin.shared_files') }}</p>
            @if ($shared !== [])
                @include('lynx-media::livewire.partials.media-list', ['shown' => $shared])
            @endif
            @if ($canAddShared)
                @include('lynx-media::livewire.partials.media-upload', ['model' => 'sharedUploads'])
            @endif
        </section>
    @elseif ($items !== [])
        @include('lynx-media::livewire.partials.media-list', ['shown' => $items])
    @endif

    @if ($isImages && $items !== [])
        <x-lynx-admin::ui.dialog :id="$zoomDialog" data-media-viewer>
            <x-lynx-admin::ui.dialog-content class="w-max max-w-[calc(100vw-2rem)] gap-3 p-3 sm:max-w-[calc(100vw-4rem)]">
                <x-lynx-admin::ui.dialog-title class="truncate pe-8 text-sm" x-text="zoom.name" />
                <img :src="zoom.url" :alt="zoom.name" class="max-h-[calc(100vh-8rem)] max-w-full justify-self-center object-contain">
            </x-lynx-admin::ui.dialog-content>
        </x-lynx-admin::ui.dialog>
    @endif

    @if ($canAdd && ! ($isPerLanguage && count($languages) > 1))
        @include('lynx-media::livewire.partials.media-upload', ['model' => 'uploads'])
    @elseif ($pageId === null && ! $disabled)
        <p class="text-muted-foreground text-sm">{{ __($isImages ? 'lynx-media::admin.save_first_images' : 'lynx-media::admin.save_first_files') }}</p>
    @endif
    @error('uploads') <p data-error-for="uploads" role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
    @error('sharedUploads') <p data-error-for="sharedUploads" role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
</div>
