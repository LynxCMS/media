<?php

declare(strict_types=1);

namespace LynxCms\Media;

use Illuminate\Support\Str;
use LynxCms\Core\Content\PageFiles;

/**
 * Media files for content packages (ADR-0050): read from and written to the media disk, in the
 * page's folder. A file of the same name and contents is reused, so importing twice stores once.
 *
 * @internal
 */
final readonly class MediaPageFiles implements PageFiles
{
    public function __construct(private MediaPaths $paths) {}

    public function read(string $file): ?string
    {
        $path = $this->paths->path($file);

        return $this->paths->disk()->exists($path) ? $this->paths->disk()->get($path) : null;
    }

    public function write(int $pageId, string $file, string $contents, bool $dryRun = false): string
    {
        $name = basename($file);
        $target = "{$pageId}/{$name}";
        $existing = $this->read($target);

        if ($existing === $contents) {
            return $target;
        }

        if ($existing !== null) {
            $target = "{$pageId}/".pathinfo($name, PATHINFO_FILENAME).'-'.Str::lower(Str::random(6)).'.'.pathinfo($name, PATHINFO_EXTENSION);
        }

        if (! $dryRun) {
            $this->paths->disk()->put($this->paths->path($target), $contents);
        }

        return $target;
    }
}
