{{-- Images and files fields (ADR-0046): the upload and list component, bound to the editor's form. --}}
<livewire:lynx-media.field
    wire:model="{{ $model }}"
    :items="(array) ($value ?? [])"
    :page-id="$field->pageId"
    :field="$field->field()"
    :disabled="$disabled"
    :key="'media-'.$model"
/>
