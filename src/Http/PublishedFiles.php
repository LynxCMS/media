<?php

declare(strict_types=1);

namespace LynxCms\Media\Http;

use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Whether a page's live values name a file, switched on: in a media field, or inside repeater and
 * matrix items that are switched on. A size made from such a file counts too. Files an editor
 * removed or hid, and files only a draft names, are not served to visitors (ServePrivateMedia).
 *
 * @internal
 */
final class PublishedFiles
{
    /** @param  string  $file  the name in the page's folder, such as "notes-abc123.pdf" or "photo-abc123.800x600.jpg" */
    public function names(Page $page, string $file): bool
    {
        // Stored names have no dot before their extension, and sizes add ".800x600" after the stem.
        $stem = strstr($file, '.', true) ?: $file;

        foreach ($page->rawValues() as $byLanguage) {
            foreach ($byLanguage as $value) {
                if ($this->holds($value, $page->id.'/'.$stem)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Whether the value, or an item in it that is switched on, names a file with the stem. */
    private function holds(mixed $value, string $stem): bool
    {
        if (! is_array($value) || ($value['enabled'] ?? true) === false) {
            return false;
        }

        $file = Value::map($value['values'] ?? [])['file'] ?? null;

        if (is_string($file) && (strstr($file, '.', true) ?: $file) === $stem) {
            return true;
        }

        return array_any($value, fn (mixed $child): bool => $this->holds($child, $stem));
    }
}
