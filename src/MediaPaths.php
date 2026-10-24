<?php

declare(strict_types=1);

namespace LynxCms\Media;

use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use LynxCms\Core\Support\Value;

/**
 * Where media files live (ADR-0046): {root}/{owner page id}/{file} on the configured disk, or on
 * the private disk for private fields (ADR-0077). Stored values hold "{owner page id}/{file}", so a
 * draft's copy of a value points at the same file.
 *
 * @api
 */
final class MediaPaths
{
    /** Set while a private field's files are written by code that names no field (content packages). */
    private bool $isWritingPrivate = false;

    /** The public disk, or the private one (ADR-0077). Null means the field being written now. */
    public function disk(?bool $private = null): Filesystem
    {
        return Storage::disk($this->diskName($private));
    }

    public function diskName(?bool $private = null): string
    {
        return ($private ?? $this->isWritingPrivate)
            ? Value::string(config('lynx-media.private_disk'), 'local')
            : Value::string(config('lynx-media.disk'), 'public');
    }

    /**
     * Both disks, once each: what pruning and deleting a page's folder go through.
     *
     * @return list<Filesystem>
     */
    public function disks(): array
    {
        $names = array_unique([$this->diskName(false), $this->diskName(true)]);

        return array_values(array_map(fn (string $name): Filesystem => Storage::disk($name), $names));
    }

    /**
     * Runs $callback with writes going to the private disk or not, for code that stores a field's
     * files without naming the field (content packages through PageFiles).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function writing(bool $private, Closure $callback): mixed
    {
        $previous = $this->isWritingPrivate;
        $this->isWritingPrivate = $private;

        try {
            return $callback();
        } finally {
            $this->isWritingPrivate = $previous;
        }
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

    /** A public file's address on the disk, or a private one's Lynx route. */
    public function url(string $file, bool $private = false): string
    {
        if (! $private) {
            return $this->disk(false)->url($this->path($file));
        }

        [$page, $name] = array_pad(explode('/', ltrim($file, '/'), 2), 2, '');

        return route('lynx.media', ['page' => $page, 'file' => $name]);
    }

    public function root(): string
    {
        return trim(Value::string(config('lynx-media.root'), 'lynx'), '/');
    }
}
