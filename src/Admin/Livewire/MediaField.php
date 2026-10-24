<?php

declare(strict_types=1);

namespace LynxCms\Media\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Editor\LocalizedForm;
use LynxCms\Core\Support\Value;
use LynxCms\Media\Actions\StoreMedia;
use LynxCms\Media\Admin\MediaConverter;
use LynxCms\Media\Fields\ImagesType;
use LynxCms\Media\Fields\MediaFieldType;

/**
 * An images or files field in the page editor (ADR-0046): drop or pick files, which media.store
 * writes right away; sort, switch off, remove, describe per language, tag and set the focus point.
 * The page's value changes when the editor saves; files never saved are pruned later.
 *
 * @internal
 */
final class MediaField extends Component
{
    use WithFileUploads;

    /** @var list<array<string, mixed>> form items (MediaConverter) */
    #[Modelable]
    public array $items = [];

    #[Locked]
    public ?int $pageId = null;

    #[Locked]
    public string $field = '';

    #[Locked]
    public bool $disabled = false;

    /** @var list<TemporaryUploadedFile> */
    public array $uploads = [];

    /** The file whose details are open, by key. */
    public ?string $editing = null;

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    /** Livewire hook: store each upload through media.store and add it to the field. */
    public function updatedUploads(): void
    {
        abort_if($this->disabled || $this->pageId === null, 403);

        foreach ($this->uploads as $upload) {
            $contents = $upload->get();

            if ($contents === false) {
                $this->addError('uploads', $upload->getClientOriginalName().': the upload could not be read. Try again.');

                continue;
            }

            try {
                $values = lynx()->run(StoreMedia::class, [
                    'page' => $this->pageId,
                    'field' => $this->field,
                    // The contents, never a path: no caller of media.store names a file on the server.
                    'base64' => base64_encode($contents),
                    'filename' => $upload->getClientOriginalName(),
                ])->value;
                $this->items[] = Value::map(Value::list(app(MediaConverter::class)->toForm([['key' => (string) Str::ulid(), 'values' => $values]]))[0] ?? []);
            } catch (ValidationException $exception) {
                $this->addError('uploads', $upload->getClientOriginalName().': '.Value::string(collect($exception->errors())->flatten()->first()));
            }
        }

        $this->uploads = [];
    }

    public function toggle(int $index): void
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['enabled'] = ! (bool) ($this->items[$index]['enabled'] ?? true);
        }
    }

    public function remove(int $index): void
    {
        $this->items = array_values(array_filter($this->items, fn (int $i): bool => $i !== $index, ARRAY_FILTER_USE_KEY));
    }

    /** wire:sort handler: a file key dropped at a 0-based position. */
    public function sort(string $key, int|string $position): void
    {
        $moving = array_values(array_filter($this->items, fn (array $item): bool => ($item['key'] ?? null) === $key));
        $rest = array_values(array_filter($this->items, fn (array $item): bool => ($item['key'] ?? null) !== $key));
        array_splice($rest, max(0, min(count($rest), Value::int($position))), 0, $moving);
        $this->items = $rest;
    }

    /** The arrow buttons, for keyboards: moves a file one place earlier (-1) or later (1). */
    public function move(int $index, int $offset): void
    {
        $key = $this->items[$index]['key'] ?? null;
        $target = $index + ($offset < 0 ? -1 : 1);

        if (is_string($key) && $target >= 0 && $target < count($this->items)) {
            $this->sort($key, $target);
        }
    }

    /** The focus point picked on the preview, in percent. */
    public function focus(int $index, int|float $x, int|float $y): void
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['focus'] = ['x' => max(0, min(100, (int) round($x))), 'y' => max(0, min(100, (int) round($y))), 'zoom' => Value::int(Value::map($this->items[$index]['focus'] ?? [])['zoom'] ?? 0)];
        }
    }

    public function render(): View
    {
        $definition = lynx()->fields->get($this->field);
        $type = $definition->fieldType();
        $settings = $definition->config();
        $max = Value::int($settings['max_files'] ?? 0);

        return view('lynx-media::livewire.media-field', [
            'isImages' => $type instanceof ImagesType,
            'accept' => $type instanceof MediaFieldType ? implode(',', array_map(fn (string $e): string => '.'.$e, $type->extensions($settings))) : '',
            'canAdd' => ! $this->disabled && $this->pageId !== null && ($max === 0 || count($this->items) < $max),
            'multiple' => $max !== 1,
            'languages' => LocalizedForm::languages(),
            'allowedTags' => Value::strings($settings['tags'] ?? []),
            'field' => $definition,
        ]);
    }
}
