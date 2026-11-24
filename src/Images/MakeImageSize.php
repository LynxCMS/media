<?php

declare(strict_types=1);

namespace LynxCms\Media\Images;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use LynxCms\Media\Values\Pageimage;
use Throwable;

/**
 * Makes one image size queued by SizeQueue (lynx-media.queue_sizes), from the original, under the
 * name that says how it is made (ADR-0046). Unique per name, so a size printed by many visitors is
 * made once. A size that can't be made is logged and not retried: its page keeps the stand-in.
 *
 * @internal
 */
final class MakeImageSize implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $original  the original image's stored values
     * @param  array{width: mixed, height: mixed, crop: mixed, quality: mixed, format: mixed}|array{}  $spec
     */
    public function __construct(public string $key, public array $original, public string $file, public array $spec) {}

    public function uniqueId(): string
    {
        return $this->file;
    }

    public function handle(Variations $variations, SizeQueue $queue): void
    {
        $image = new Pageimage($this->key, $this->original);

        try {
            $variations->write($image, $this->file, $this->spec + ['width' => 0, 'height' => 0, 'crop' => false, 'quality' => null, 'format' => null]);
            $queue->forget($this->file, $image->isPrivate());
        } catch (Throwable $exception) {
            $page = strtok($this->file, '/');
            Log::warning("Lynx could not make the image size [{$this->file}] of page [{$page}]: {$exception->getMessage()}", [
                'file' => $this->file,
                'page' => $page,
            ]);
        }
    }
}
