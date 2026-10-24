<?php

declare(strict_types=1);

namespace LynxCms\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Actions\Fields\UpdateField;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\HookRegistry;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Media\Fields\MediaFieldType;

/**
 * Moves a media field's files to the other disk when its "private" setting changes (ADR-0077):
 * every file its values name, in the field itself, inside repeater and matrix items, and in
 * revisions. Files are copied within fields.update, so a rollback leaves the old disk as it was,
 * and the originals and their image sizes are deleted only once the change commits. Sizes are
 * made again on the new disk when next asked for.
 *
 * @internal
 */
final class MovePrivateMedia
{
    /** @var array<string, bool> field name => private before the update */
    private array $wasPrivate = [];

    public function __construct(private readonly MediaPaths $paths, private readonly HookRegistry $hooks) {}

    public function listen(): void
    {
        $this->hooks->before(UpdateField::class, function (Input $input): void {
            $field = lynx()->fields->find((string) $input->string('field'));

            if ($field?->fieldType() instanceof MediaFieldType) {
                $this->wasPrivate[$field->name] = MediaFieldType::isPrivate($field->config());
            }
        });

        $this->hooks->after(UpdateField::class, function (mixed $field, Input $input, ActionContext $context): void {
            if ($field instanceof Field && array_key_exists($field->name, $this->wasPrivate)) {
                $wasPrivate = $this->wasPrivate[$field->name];
                unset($this->wasPrivate[$field->name]);

                if ($wasPrivate !== MediaFieldType::isPrivate($field->config())) {
                    $this->move($field, $wasPrivate, $context);
                }
            }
        });
    }

    private function move(Field $field, bool $wasPrivate, ActionContext $context): void
    {
        $files = $this->files($field);
        $to = $wasPrivate ? 'the public disk' : 'the private disk';
        $context->changes->schema('field', "Move the {$this->count($files)} of field {$field->name} to {$to}", ['field' => $field->name, 'files' => $files]);

        if ($context->dryRun) {
            return;
        }

        $source = $this->paths->disk($wasPrivate);
        $target = $this->paths->disk(! $wasPrivate);

        foreach ($files as $file) {
            $path = $this->paths->path($file);

            if ($source->exists($path)) {
                $target->put($path, (string) $source->get($path));
                DB::afterRollBack(fn () => $target->delete($path));
                DB::afterCommit(fn () => $source->delete([$path, ...$this->sizes($source, $file)]));
            }
        }
    }

    /**
     * Every stored file the field's values name, wherever they are kept.
     *
     * @return list<string>
     */
    private function files(Field $field): array
    {
        $files = [];

        foreach (DB::table($field->table())->where('language_id', 0)->pluck('data') as $data) {
            $this->collect([$field->name => json_decode(Value::string($data), true)], $field->name, $files);
        }

        foreach (lynx()->fields->all() as $other) {
            if ($other->fieldType() instanceof ItemFieldType && $other->name !== $field->name) {
                foreach (DB::table($other->table())->pluck('data') as $data) {
                    $this->collect(json_decode(Value::string($data), true), $field->name, $files);
                }
            }
        }

        foreach (DB::table(Tables::REVISIONS)->pluck('values') as $values) {
            $this->collect(json_decode(Value::string($values), true), $field->name, $files);
        }

        return array_values(array_unique($files));
    }

    /**
     * Files of the media items under the key $name, anywhere in $data.
     *
     * @param  list<string>  $files
     */
    private function collect(mixed $data, string $name, array &$files): void
    {
        if (! is_array($data)) {
            return;
        }

        foreach ($data as $key => $value) {
            if ($key === $name && is_array($value)) {
                foreach ($value as $item) {
                    $file = is_array($item) ? Value::string(Value::map($item['values'] ?? [])['file'] ?? '') : '';

                    if ($file !== '') {
                        $files[] = $file;
                    }
                }
            } else {
                $this->collect($value, $name, $files);
            }
        }
    }

    /**
     * The image sizes made from a file ("42/photo.800x600.jpg" for "42/photo.jpg").
     *
     * @return list<string>
     */
    private function sizes(Filesystem $disk, string $file): array
    {
        $folder = $this->paths->path(dirname($file));
        $stem = pathinfo($file, PATHINFO_FILENAME).'.';

        return array_values(array_filter($disk->files($folder), fn (string $path): bool => str_starts_with(basename($path), $stem)));
    }

    /** @param  list<string>  $files */
    private function count(array $files): string
    {
        return count($files) === 1 ? '1 file' : count($files).' files';
    }
}
