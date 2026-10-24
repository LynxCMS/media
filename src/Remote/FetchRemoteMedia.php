<?php

declare(strict_types=1);

namespace LynxCms\Media\Remote;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Downloads one media file from production in the background, for lynx:media:pull (ADR-0082).
 *
 * @internal
 */
final class FetchRemoteMedia implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public readonly string $file) {}

    public function handle(RemoteMedia $remote): void
    {
        $remote->ensure($this->file);
    }
}
