<?php

declare(strict_types=1);

namespace LynxCms\Media\Remote;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * The stored names ("42/photo.jpg") pages' media values name, read from media fields and from
 * repeater and matrix items: what lynx:media:pull downloads (ADR-0082). Reads only.
 *
 * @internal
 */
final readonly class MediaReferences
{
    public function __construct(private FieldRegistry $fields) {}

    /**
     * @param  int|null  $pageId  only this page and the pages below it
     * @return list<string>
     */
    public function files(?int $pageId = null): array
    {
        $branch = $pageId === null ? null : DB::table(Tables::PAGE_CLOSURE)->where('ancestor_id', $pageId)->pluck('descendant_id')->all();
        $files = [];

        foreach ($this->fields->all() as $field) {
            if (! $field->fieldType() instanceof ItemFieldType) {
                continue;
            }

            $rows = DB::table($field->table())->where('language_id', 0)->when($branch !== null, fn ($query) => $query->whereIn('page_id', $branch));

            foreach ($rows->pluck('data') as $data) {
                preg_match_all('/"file":"(\d+\\\\?\/[A-Za-z0-9._-]+)"/', Value::string($data), $matches);
                array_push($files, ...array_map(stripslashes(...), $matches[1]));
            }
        }

        return array_values(array_unique($files));
    }
}
