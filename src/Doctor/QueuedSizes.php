<?php

declare(strict_types=1);

namespace LynxCms\Media\Doctor;

use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Core\Support\Value;
use LynxCms\Media\Images\SizeQueue;

/**
 * Image sizes in a queue (lynx-media.queue_sizes) need a queue a worker reads. On the sync
 * connection each size's job runs inside the page's render, which is what the setting avoids.
 *
 * @internal
 */
final readonly class QueuedSizes implements Check
{
    public function key(): string
    {
        return 'media-queued-sizes';
    }

    public function label(): string
    {
        return 'Image sizes are made by a queue worker';
    }

    public function run(): array
    {
        if (! SizeQueue::isOn()) {
            return [];
        }

        $connection = Value::string(config('queue.default'));

        if (Value::string(config("queue.connections.{$connection}.driver"), $connection) !== 'sync') {
            return [];
        }

        return [new Finding(
            Severity::Warning,
            "LYNX_MEDIA_QUEUE_SIZES is on, but the queue connection [{$connection}] is sync: image sizes are still made during the page's render.",
            'Set QUEUE_CONNECTION to a queue a worker reads (database, redis) and run php artisan queue:work, or turn LYNX_MEDIA_QUEUE_SIZES off.',
        )];
    }
}
