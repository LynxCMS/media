<?php

declare(strict_types=1);

namespace LynxCms\Media\Fields;

use LynxCms\Core\Fields\Items\ItemNormalizer;
use LynxCms\Media\Values\Pagefile;

/** Files of a page: documents, downloads (ADR-0046). */
final class FilesType extends MediaFieldType
{
    public static function name(): string
    {
        return 'files';
    }

    public static function description(): string
    {
        return 'Files that belong to the page (PDFs, documents), sortable, each with a description per language and tags. Upload with media.store, then save the returned entries.';
    }

    protected function defaultExtensions(): string
    {
        return 'pdf doc docx xls xlsx ppt pptx txt csv zip';
    }

    protected function details(array $values, string $key, ItemNormalizer $normalizer): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function file(string $key, array $values): Pagefile
    {
        return new Pagefile($key, $values);
    }

    protected function fileClass(): string
    {
        return Pagefile::class;
    }
}
