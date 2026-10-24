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
use LynxCms\Media\MediaPaths;
use Symfony\Component\HttpFoundation\Response;

/**
 * A private field's file (ADR-0077), for whoever may open its page: staff who may view the page
 * in the admin, or a visitor when the page is published, no page gate answers instead (a password
 * form, a sign-in redirect) and the visibility rules allow it. A gate's answer is the file's answer.
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
    ) {}

    public function __invoke(Request $request, int $page, string $file): Response
    {
        $owner = $this->pages->find($page);
        $path = $this->paths->path("{$page}/{$file}");
        $disk = $this->paths->disk(true);

        abort_unless($owner instanceof Page && $disk->exists($path), 404);

        $refusal = $this->refusal($owner, $request);

        if ($refusal !== null) {
            return $refusal;
        }

        return $disk->response($path, $file, [
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    /** Null when the request may have the page's files, else what to answer instead. */
    private function refusal(Page $page, Request $request): ?Response
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

        return null;
    }
}
