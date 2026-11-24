<?php

declare(strict_types=1);

namespace LynxCms\Media\Images;

use LynxCms\Contracts\Pages\PageCache;
use LynxCms\Core\Support\Value;
use LynxCms\Media\MediaPaths;
use LynxCms\Media\Values\Pageimage;

/**
 * Image sizes made by queued jobs (lynx-media.queue_sizes, off by default): a size printed before
 * its file exists queues MakeImageSize and prints a stand-in, the smallest existing image of the
 * same original that is at least as large and keeps its aspect ratio, else the original. The page
 * is kept out of the page cache, so the cache stores the render after the jobs ran.
 * Scoped per request: each folder is listed once.
 *
 * @internal
 */
final class SizeQueue
{
    /** @var array<string, list<string>> folder => file names, listed once per request */
    private array $listed = [];

    public function __construct(private readonly MediaPaths $paths) {}

    public static function isOn(): bool
    {
        return (bool) config('lynx-media.queue_sizes', false);
    }

    public function exists(string $file, bool $isPrivate): bool
    {
        return in_array(basename($file), $this->filesIn(dirname($file), $isPrivate), true);
    }

    /**
     * Queues the size and returns the stand-in's URL.
     *
     * @param  array<string, mixed>  $values  the original's stored values, for the job
     * @param  array{width: mixed, height: mixed, crop: mixed, quality: mixed, format: mixed}  $spec
     */
    public function queue(Pageimage $original, array $values, string $file, array $spec, int $width, int $height): string
    {
        MakeImageSize::dispatch($original->key(), $values, $file, $spec);
        request()->attributes->set(PageCache::SKIP, true);

        return $this->paths->url($this->standIn($original, $width, $height), $original->isPrivate());
    }

    /** The stored name of the smallest existing size that covers $width × $height, else the original's. */
    private function standIn(Pageimage $original, int $width, int $height): string
    {
        $file = $original->file();
        $info = pathinfo($file);
        $pattern = '/^'.preg_quote(Value::string($info['filename'] ?? ''), '/').'\.(\d+)x(\d+)(?:-[^.]+)?\.\w+$/';
        $ratio = $original->width() / max(1, $original->height());
        $best = null;

        foreach ($this->filesIn(dirname($file), $original->isPrivate()) as $name) {
            if (preg_match($pattern, $name, $size) !== 1) {
                continue;
            }

            [$candidateWidth, $candidateHeight] = [(int) $size[1], (int) $size[2]];
            $keepsRatio = abs($candidateWidth / max(1, $candidateHeight) - $ratio) < 0.02;

            if ($keepsRatio && $candidateWidth >= $width && $candidateHeight >= $height && ($best === null || $candidateWidth < $best[0])) {
                $best = [$candidateWidth, $name];
            }
        }

        return $best === null ? $file : dirname($file).'/'.$best[1];
    }

    /** @return list<string> */
    private function filesIn(string $folder, bool $isPrivate): array
    {
        $key = ($isPrivate ? 'private:' : 'public:').$folder;

        return $this->listed[$key] ??= array_values(array_map(basename(...), $this->paths->disk($isPrivate)->files($this->paths->path($folder))));
    }

    /** A size made in this request (the job ran on the sync connection) is seen by the next check. */
    public function forget(string $file, bool $isPrivate): void
    {
        unset($this->listed[($isPrivate ? 'private:' : 'public:').dirname($file)]);
    }
}
