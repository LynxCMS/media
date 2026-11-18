<?php

declare(strict_types=1);

namespace LynxCms\Media\Http;

use Illuminate\Http\Request;
use LynxCms\Media\MediaPaths;
use Symfony\Component\HttpFoundation\Response;

/**
 * A page's file through a temporary link (Pagefile::temporaryUrl()). The route's signature is the
 * grant, so the page's own access rules are not asked: a gated download hands a link to a visitor
 * who may not open the page's files otherwise. The file always downloads, and is never kept by caches.
 *
 * @internal
 */
final readonly class ServeSignedMedia
{
    public function __construct(private MediaPaths $paths) {}

    public function __invoke(Request $request, int $page, string $file): Response
    {
        $disk = $this->paths->disk($request->boolean('private'));
        $path = $this->paths->path("{$page}/{$file}");

        abort_unless($disk->exists($path), 404);

        return $disk->download($path, $file, [
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);
    }
}
