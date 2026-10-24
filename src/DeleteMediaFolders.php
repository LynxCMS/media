<?php

declare(strict_types=1);

namespace LynxCms\Media;

use LynxCms\Core\Events\PageDeleted;

/**
 * A page deleted for good takes its media folder with it (ADR-0046). Domain events come after the
 * transaction commits, so a rolled back delete keeps the files.
 *
 * @internal
 */
final readonly class DeleteMediaFolders
{
    public function __construct(private MediaPaths $paths) {}

    public function handle(PageDeleted $event): void
    {
        foreach ($event->pageIds as $pageId) {
            foreach ($this->paths->disks() as $disk) {
                $disk->deleteDirectory($this->paths->folder($pageId));
            }
        }
    }
}
