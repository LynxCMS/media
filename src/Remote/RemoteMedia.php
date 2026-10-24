<?php

declare(strict_types=1);

namespace LynxCms\Media\Remote;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Support\Value;
use LynxCms\Media\Downloads\RemoteFile;
use LynxCms\Media\MediaPaths;

/**
 * Media files a developer's machine lacks, downloaded from production on demand (ADR-0082): a
 * content package exported without files leaves values naming production's files, and
 * lynx-media.remote.url says where production serves its media folder. Never in production, only
 * over https, only for stored names ("42/photo.jpg"), and through RemoteFile's download limits.
 * Public files only: private ones come with the content package.
 *
 * @internal
 */
final readonly class RemoteMedia
{
    /** A stored file: a page folder and a plain name that doesn't start with a dot. */
    private const string STORED_NAME = '/^\d+\/(?!\.)[A-Za-z0-9._-]+$/';

    private const int DEFAULT_MAX_KB = 51200;

    public function __construct(private MediaPaths $paths, private RemoteFile $remote) {}

    /** Whether files are downloaded from production here: a remote is set, over https, and this is not production. */
    public function isEnabled(): bool
    {
        return str_starts_with($this->base(), 'https://') && ! app()->isProduction();
    }

    /** Whether the file is on the public disk now, downloading it from production when it isn't. */
    public function ensure(string $file): bool
    {
        $disk = $this->paths->disk(false);

        if ($disk->exists($this->paths->path($file))) {
            return true;
        }

        if (! $this->isEnabled() || preg_match(self::STORED_NAME, $file) !== 1) {
            return false;
        }

        try {
            [$contents] = $this->remote->download($this->base().'/'.$file, Value::int(config('lynx-media.remote.max_kb'), self::DEFAULT_MAX_KB) * 1024);
        } catch (ValidationException $exception) {
            Log::info("Lynx could not download media file [{$file}] from production: ".Value::string(collect($exception->errors())->flatten()->first()));

            return false;
        }

        return $disk->put($this->paths->path($file), $contents);
    }

    private function base(): string
    {
        return rtrim(Value::string(config('lynx-media.remote.url')), '/');
    }
}
