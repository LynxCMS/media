<?php

declare(strict_types=1);

namespace LynxCms\Media\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Support\Value;
use LynxCms\Media\MediaPaths;
use LynxCms\Media\Remote\FetchRemoteMedia;
use LynxCms\Media\Remote\MediaReferences;
use LynxCms\Media\Remote\RemoteMedia;
use Override;

/**
 * Queues a download from production for every media file the pages name that this machine lacks
 * (ADR-0082), so a queue worker fills the disk while you work. What isn't fetched yet still
 * downloads when something asks for it.
 */
final class PullMediaCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:media:pull {--page= : Only this page and the pages below it, by path or id}';

    #[Override]
    protected $description = 'Download media files from production that this machine lacks, in queued jobs';

    public function handle(RemoteMedia $remote, MediaPaths $paths, MediaReferences $references): int
    {
        if (! $remote->isEnabled()) {
            $this->components->error('Set LYNX_MEDIA_REMOTE to the https address of production\'s media folder, outside production.');

            return self::FAILURE;
        }

        $given = Value::string($this->option('page'));
        $page = $given === '' ? null : lynx()->pages->get(ctype_digit($given) ? (int) $given : $given);

        if ($given !== '' && $page === null) {
            $this->components->error("There is no page [{$given}].");

            return self::FAILURE;
        }

        $disk = $paths->disk(false);
        $missing = array_values(array_filter($references->files($page?->id), fn (string $file): bool => ! $disk->exists($paths->path($file))));

        foreach ($missing as $file) {
            FetchRemoteMedia::dispatch($file);
        }

        $this->components->info('Queued '.count($missing).' file'.(count($missing) === 1 ? '' : 's').' to download. Run a queue worker to fetch them.');

        return self::SUCCESS;
    }
}
