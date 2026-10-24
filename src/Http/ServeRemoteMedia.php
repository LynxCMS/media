<?php

declare(strict_types=1);

namespace LynxCms\Media\Http;

use LynxCms\Media\MediaPaths;
use LynxCms\Media\Remote\RemoteMedia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The address of a public media file the web server did not find on this machine (ADR-0082):
 * downloaded from production, stored, then sent. Registered only while remote media is on.
 *
 * @internal
 */
final readonly class ServeRemoteMedia
{
    public function __construct(private RemoteMedia $remote, private MediaPaths $paths) {}

    public function __invoke(int $page, string $file): Response
    {
        abort_unless($this->remote->ensure("{$page}/{$file}"), 404);

        return $this->paths->disk(false)->response($this->paths->path("{$page}/{$file}"), $file);
    }
}
