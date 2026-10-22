<?php

declare(strict_types=1);

namespace LynxCms\Media\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Support\Value;
use LynxCms\Media\Actions\PruneMedia;
use Override;

/** Deletes media files nothing uses (media.prune); the scheduler runs it daily. */
final class PruneMediaCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:media:prune {--older-than= : Hours; newer files stay} {--dry-run : Count without deleting}';

    #[Override]
    protected $description = 'Delete media files that no page, draft or revision uses';

    public function handle(): int
    {
        $input = $this->option('older-than') === null ? [] : ['older_than_hours' => (int) Value::string($this->option('older-than'))];
        $result = lynx()->run(PruneMedia::class, $input, dryRun: (bool) $this->option('dry-run'));
        $count = count($result->changes->all());

        $this->components->info(($this->option('dry-run') ? 'Would delete' : 'Deleted')." {$count} unused file(s).");

        return self::SUCCESS;
    }
}
