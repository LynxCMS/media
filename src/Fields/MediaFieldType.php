<?php

declare(strict_types=1);

namespace LynxCms\Media\Fields;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\Items\HasItemProperties;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\Items\ItemNormalizer;
use LynxCms\Core\Fields\Items\ItemProperty;
use LynxCms\Core\Fields\Items\NestsInItems;
use LynxCms\Core\Fields\NeedsSavedPage;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;
use LynxCms\Media\MediaPaths;
use LynxCms\Media\Values\MediaLanguages;
use LynxCms\Media\Values\MediaList;
use LynxCms\Media\Values\Pagefile;

/**
 * Files that belong to a page, ProcessWire style (ADR-0046), on the item storage of ADR-0044: one
 * item per file, sortable and switchable, with a description per language and tags. Values hold
 * "{page id}/{file}"; the files themselves are written by media.store and removed by media.prune.
 *
 * @internal
 */
abstract class MediaFieldType extends ItemFieldType implements HasItemProperties, NeedsSavedPage, NestsInItems
{
    /**
     * What selectors may test in each file (ADR-0049): "gallery.tags=hero", "downloads.mime=application/pdf".
     *
     * @return array<string, ItemProperty>
     */
    public function itemProperties(): array
    {
        return [
            'file' => new ItemProperty(Comparison::Text),
            'mime' => new ItemProperty(Comparison::Text),
            'bytes' => new ItemProperty(Comparison::Integer),
            'tags' => new ItemProperty(Comparison::Text, isList: true),
            'language' => new ItemProperty(Comparison::Text),
            'description' => new ItemProperty(Comparison::Text, isTranslatable: true),
        ];
    }

    /** Extensions allowed when the field does not say. */
    abstract protected function defaultExtensions(): string;

    /**
     * The type's own values of one file besides file, tags and description (dimensions, focus).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    abstract protected function details(array $values, string $key, ItemNormalizer $normalizer): array;

    /**
     * The value object templates read for one file.
     *
     * @param  array<string, mixed>  $values
     */
    abstract protected function file(string $key, array $values): Pagefile;

    public function settingsSchema(JsonSchema $schema): array
    {
        return [
            'max_files' => $schema->integer()->description('Most files (0 = no limit; 1 makes templates read a single file).'),
            'extensions' => $schema->string()->description('Allowed extensions, space separated. Default "'.$this->defaultExtensions().'".'),
            'max_kb' => $schema->integer()->description('Largest upload in kilobytes. Default 10240.'),
            'tags' => $schema->array()->items($schema->string())->description('Tags editors may choose; empty allows any tag.'),
            'private' => $schema->boolean()->description('Keep the files private: stored off the public disk and served only to visitors who may open the page. Switching moves existing files.'),
            'per_language' => $schema->boolean()->description('A file per language: each file has a language (or none for every language), and templates read the current language\'s files with fallback (ADR-0150). max_files counts per language.'),
        ];
    }

    public function settingsRules(): array
    {
        return [
            'max_files' => ['sometimes', 'integer', 'min:0'],
            'extensions' => ['sometimes', 'string', 'regex:/^[a-z0-9 ]+$/', FileTypes::rule()],
            'max_kb' => ['sometimes', 'integer', 'min:1'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:64'],
            'private' => ['sometimes', 'boolean'],
            'per_language' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Whether the field's files are private (ADR-0077).
     *
     * @param  array<string, mixed>  $settings
     */
    public static function isPrivate(array $settings): bool
    {
        return (bool) ($settings['private'] ?? false);
    }

    /**
     * Whether each file has a language (ADR-0150).
     *
     * @param  array<string, mixed>  $settings
     */
    public static function isPerLanguage(array $settings): bool
    {
        return (bool) ($settings['per_language'] ?? false);
    }

    public function subfields(array $settings, ?string $type): array
    {
        return [];
    }

    /**
     * The extensions the field allows, never one of FileTypes::NEVER, even if an older setting
     * lists it.
     *
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    public function extensions(array $settings): array
    {
        return FileTypes::allowed(array_values(array_filter(explode(' ', Value::string($settings['extensions'] ?? '', $this->defaultExtensions()) ?: $this->defaultExtensions()))));
    }

    public function normalize(mixed $value, array $settings): mixed
    {
        $normalizer = app(ItemNormalizer::class);
        $items = [];
        $errors = [];

        foreach (Value::list($value) as $index => $raw) {
            $items[] = $this->item(Value::array($raw), (string) $index, $settings, $normalizer, $errors);
        }

        $max = Value::int($settings['max_files'] ?? 0);

        if ($max > 0 && MediaItemChecks::mostFilesInOneLanguage($items, $settings) > $max) {
            $errors[''] = trans_choice('lynx-media::validation.max_files', $max);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $items;
    }

    /**
     * One file row as stored, collecting its errors under its index.
     *
     * @param  array<array-key, mixed>  $raw
     * @param  array<string, mixed>  $settings
     * @param  array<string, string>  $errors
     * @return array<string, mixed>
     */
    private function item(array $raw, string $index, array $settings, ItemNormalizer $normalizer, array &$errors): array
    {
        $values = Value::map($raw['values'] ?? []);
        $file = Value::string($values['file'] ?? '');

        $facts = [];

        if (preg_match('/^\d+\/(?!\.)[A-Za-z0-9._-]+$/D', $file) !== 1) {
            $errors["{$index}.file"] = Value::string(__('lynx-media::validation.stored_name'));
        } else {
            // On a save, only this page's own files, with their real size, type and dimensions.
            $facts = app(StoredFileCheck::class)->facts($file, self::isPrivate($settings), $this instanceof ImagesType, $this->extensions($settings));
            $errors += is_string($facts) ? ["{$index}.file" => $facts] : [];
        }

        $key = Value::string($raw['key'] ?? '');

        return [
            'key' => preg_match('/^[0-9A-Z]{26}$/', $key) === 1 ? $key : (string) Str::ulid(),
            'type' => null,
            'enabled' => (bool) ($raw['enabled'] ?? true),
            'depth' => 0,
            'values' => array_filter(array_replace([
                'file' => $file,
                'bytes' => Value::int($values['bytes'] ?? 0),
                'mime' => Value::string($values['mime'] ?? ''),
                'tags' => MediaItemChecks::tags($values['tags'] ?? [], $settings, "{$index}.tags", $errors),
                'description' => MediaItemChecks::descriptions($values['description'] ?? null, "{$index}.description", $errors),
                'language' => MediaItemChecks::language($values['language'] ?? null, "{$index}.language", $errors),
            ] + $this->details($values, $index, $normalizer), is_array($facts) ? $facts : []), fn (mixed $v): bool => ! in_array($v, [[], '', null], true)),
        ];
    }

    /** Each item's stored file (ADR-0050). */
    public function files(mixed $value, array $settings): array
    {
        $files = [];

        foreach (Value::list($value) as $item) {
            $file = Value::string(Value::map(Value::array($item)['values'] ?? [])['file'] ?? '');

            if ($file !== '') {
                $files[] = $file;
            }
        }

        return $files;
    }

    public function mapFiles(mixed $value, Closure $map, array $settings): mixed
    {
        // Content packages store each file through $map without naming the field: say which disk.
        return app(MediaPaths::class)->writing(self::isPrivate($settings), fn (): array => array_map(function (mixed $item) use ($map): mixed {
            $item = Value::array($item);
            $values = Value::map($item['values'] ?? []);

            if (is_string($values['file'] ?? null) && $values['file'] !== '') {
                $values['file'] = $map($values['file']);
            }

            return ['values' => $values] + $item;
        }, Value::list($value)));
    }

    public function present(mixed $value, Field $field): mixed
    {
        $files = [];

        foreach (Value::list($value) as $item) {
            $item = Value::array($item);

            if ((bool) ($item['enabled'] ?? true)) {
                $files[] = $this->file(Value::string($item['key'] ?? ''), ['private' => self::isPrivate($field->config())] + Value::map($item['values'] ?? []));
            }
        }

        // A file per language: the current language's files, with fallback (ADR-0150).
        if (self::isPerLanguage($field->config())) {
            $files = MediaLanguages::forLanguage($files, null, $field->fallbackMode() !== 'none');
        }

        return Value::int($field->config()['max_files'] ?? 0) === 1 ? ($files[0] ?? null) : new MediaList($files);
    }

    public function pageIds(mixed $value, array $settings): array
    {
        return [];
    }

    public function mapPageIds(mixed $value, Closure $map, array $settings): mixed
    {
        return $value;
    }

    public function whereReferences(Builder $query, int $pageId, array $settings): void
    {
        $query->whereRaw('1 = 0');
    }

    protected function isTranslatedKey(string $name): bool
    {
        return $name === 'description';
    }

    protected function itemType(mixed $type, int $index, array $settings, ItemNormalizer $normalizer): ?string
    {
        return null;
    }

    /** One file (or null) when max_files is 1, else a MediaList, as present() returns. */
    public function phpType(Field $field): string
    {
        return Value::int($field->config()['max_files'] ?? 0) === 1 ? '\\'.$this->fileClass().'|null' : '\\'.MediaList::class;
    }

    /** @return class-string<Pagefile> */
    abstract protected function fileClass(): string;
}
