<?php

declare(strict_types=1);

namespace LynxCms\Media\Doctor;

use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Core\Support\Value;
use LynxCms\Media\MediaPaths;

/**
 * The public media disk reaches visitors through a link in public/ (Laravel's filesystems.links,
 * made by storage:link). Without it every image and file of a public field is a broken link.
 * Disks no link points at (a CDN, S3) are left alone.
 *
 * @internal
 */
final readonly class StorageLink implements Check
{
    public function __construct(private MediaPaths $paths) {}

    public function key(): string
    {
        return 'media-storage-link';
    }

    public function label(): string
    {
        return 'Images and files can be reached by visitors';
    }

    public function run(): array
    {
        $disk = $this->paths->diskName(false);
        $root = Value::string(config("filesystems.disks.{$disk}.root"));

        foreach (Value::map(config('filesystems.links')) as $link => $target) {
            if ($root !== '' && rtrim(Value::string($target), '/') === rtrim($root, '/') && ! file_exists((string) $link)) {
                return [new Finding(
                    Severity::Error,
                    "The media disk [{$disk}] has no link at {$link}: visitors get broken images and file links.",
                    'Run php artisan storage:link (on every server you deploy to).',
                )];
            }
        }

        return [];
    }
}
