<?php

declare(strict_types=1);

namespace LynxCms\Media\Admin;

use LynxCms\Core\Models\Field;
use LynxCms\Core\Pages\Page;
use LynxCms\Media\Values\MediaList;
use LynxCms\Media\Values\Pagefile;

/**
 * A page's files and images, by name and URL, for "Select file" in the rich text link dialog
 * (LinkFiles). Only the page's own media fields; files inside repeater or matrix items are left out.
 *
 * @internal
 */
final class PageMediaFiles
{
    private const array TYPES = ['images', 'files'];

    /**
     * @return list<array{name: string, url: string}>
     */
    public function __invoke(Page $page): array
    {
        $files = [];

        foreach ($page->template()->fields as $field) {
            if ($field instanceof Field && in_array((string) $field->type, self::TYPES, true)) {
                array_push($files, ...$this->rows($page->get((string) $field->name)));
            }
        }

        return $files;
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    private function rows(mixed $value): array
    {
        $files = match (true) {
            $value instanceof MediaList => $value->all(),
            $value instanceof Pagefile => [$value],
            default => [],
        };

        return array_map(fn (Pagefile $file): array => ['name' => $file->name(), 'url' => $this->onSite($file->url())], $files);
    }

    /** A URL on this site as a path, so links keep working when the site moves to another domain. */
    private function onSite(string $url): string
    {
        $site = rtrim(url('/'), '/');

        return str_starts_with($url, $site.'/') ? substr($url, strlen($site)) : $url;
    }
}
