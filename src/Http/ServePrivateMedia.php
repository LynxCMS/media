<?php

declare(strict_types=1);

namespace LynxCms\Media\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGates;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\Publication;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Support\Value;
use LynxCms\Media\Fields\FileTypes;
use LynxCms\Media\MediaPaths;
use Symfony\Component\HttpFoundation\Response;

/**
 * A private field's file (ADR-0077), for whoever may open its page: staff who may view the page
 * in the admin, or a visitor when the page is published, no page gate answers instead (a password
 * form, a sign-in redirect), the visibility rules allow it and the live page shows the file
 * (PublishedFiles). A gate's answer is the file's answer.
 *
 * @internal
 */
final readonly class ServePrivateMedia
{
    public function __construct(
        private MediaPaths $paths,
        private PageLoader $pages,
        private Publication $publication,
        private PageGates $gates,
        private PageVisibility $visibility,
        private PublishedFiles $files,
    ) {}

    public function __invoke(Request $request, int $page, string $file): Response
    {
        $owner = $this->pages->find($page);
        $path = $this->paths->path("{$page}/{$file}");
        $disk = $this->paths->disk(true);

        abort_unless($owner instanceof Page, 404);

        // The page's answer first, so a visitor facing its password form can't tell which names exist.
        $refusal = $this->refusal($owner, $request, $file);

        if ($refusal !== null) {
            return $refusal;
        }

        abort_unless($disk->exists($path), 404);

        // Only images, video, audio, PDFs and plain text open in the browser, never sniffed as
        // another type, and sandboxed so that even those cannot run script on the site.
        return $disk->response($path, $file, [
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Robots-Tag' => 'noindex',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ], FileTypes::opensInBrowser(Value::string($disk->mimeType($path))) ? 'inline' : 'attachment');
    }

    /** Null when the request may have the page's files, else what to answer instead. */
    private function refusal(Page $page, Request $request, string $file): ?Response
    {
        if (Gate::allows('lynx.pages.view', $page)) {
            return null;
        }

        abort_unless($this->publication->isPublished($page), 404);

        $gate = $this->gates->respond($page, $request);

        if ($gate !== null) {
            return $gate;
        }

        abort_unless($this->visibility->allows($page), 404);

        // A visitor gets only the files the live page shows: not ones an editor removed or hid, nor a draft's.
        abort_unless($this->files->names($page, $file), 404);

        return null;
    }
}
