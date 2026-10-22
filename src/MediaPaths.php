<?php

declare(strict_types=1);

namespace LynxCms\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use LynxCms\Core\Support\Value;

/**
 * Where media files live (ADR-0046): {root}/{owner page id}/{file} on the configured disk. Stored
 * values hold "{owner page id}/{file}", so a draft's copy of a value points at the same file.
 *
 * @api
 */
final class MediaPaths
{
    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function diskName(): string
    {
        return Value::string(config('lynx-media.disk'), 'public');
    }

    /** The disk path of a stored value such as "42/photo.jpg". */
    public function path(string $file): string
    {
        return $this->root().'/'.ltrim($file, '/');
    }

    public function folder(int $owner): string
    {
        return $this->root().'/'.$owner;
    }

    public function url(string $file): string
    {
        return $this->disk()->url($this->path($file));
    }

    public function root(): string
    {
        return trim(Value::string(config('lynx-media.root'), 'lynx'), '/');
    }
}
