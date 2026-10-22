<?php

declare(strict_types=1);

namespace LynxCms\Media\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Media\MediaPaths;

/**
 * Deletes media files nothing refers to any more (ADR-0046): not a value of any media field (live
 * pages, drafts, trashed pages) nor of a revision. Variations of a kept file are kept. Only files
 * older than lynx-media.prune_after_hours go, so a file uploaded but not saved yet stays.
 */
final class PruneMedia extends Action
{
    public function __construct(private readonly MediaPaths $paths, private readonly FieldRegistry $fields) {}

    public static function name(): string
    {
        return 'media.prune';
    }

    public static function description(): string
    {
        return 'Delete stored media files that no page, draft or revision uses any more.';
    }

    public static function destructive(): bool
    {
        return true;
    }

    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function rules(): array
    {
        return ['older_than_hours' => ['sometimes', 'integer', 'min:0']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['older_than_hours' => $schema->integer()->description('Keep files newer than this. Default lynx-media.prune_after_hours.')];
    }

    /**
     * @return array{deleted: int}
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $hours = $input->has('older_than_hours') ? Value::int($input->get('older_than_hours')) : Value::int(config('lynx-media.prune_after_hours'), 24);
        $before = now()->subHours($hours)->getTimestamp();
        $used = $this->usedStems();
        $disk = $this->paths->disk();
        $deleted = 0;

        foreach ($disk->allFiles($this->paths->root()) as $path) {
            $file = substr($path, strlen($this->paths->root()) + 1);

            if (isset($used[$this->stem($file)]) || $disk->lastModified($path) > $before) {
                continue;
            }

            if (! $context->dryRun) {
                $disk->delete($path);
            }

            $deleted++;
            $context->changes->deleted('file', $file, "Delete unused media file {$file}");
        }

        return ['deleted' => $deleted];
    }

    /**
     * "{page id}/{name}" without extension of every file in use; variations share it
     * ("42/photo.800x600.jpg" belongs to "42/photo.jpg").
     *
     * @return array<string, true>
     */
    private function usedStems(): array
    {
        $used = [];

        // Media values sit in media fields and inside repeater and matrix items (as nested JSON).
        foreach ($this->fields->all() as $field) {
            if ($field->fieldType() instanceof ItemFieldType) {
                foreach (DB::table($field->table())->where('language_id', 0)->pluck('data') as $data) {
                    $used += $this->filesIn(Value::string($data));
                }
            }
        }

        foreach (DB::table(Tables::REVISIONS)->pluck('values') as $values) {
            $used += $this->filesIn(Value::string($values));
        }

        return $used;
    }

    /**
     * Stems of every "file": "42/name.jpg" in a JSON text.
     *
     * @return array<string, true>
     */
    private function filesIn(string $json): array
    {
        preg_match_all('/"file":"(\d+\\\\?\/[A-Za-z0-9._-]+)"/', $json, $matches);
        $stems = [];

        foreach ($matches[1] as $file) {
            $stems[$this->stem(stripslashes($file))] = true;
        }

        return $stems;
    }

    private function stem(string $file): string
    {
        $directory = dirname($file);

        return $directory.'/'.explode('.', basename($file))[0];
    }
}
