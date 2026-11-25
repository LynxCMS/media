<?php

declare(strict_types=1);

namespace LynxCms\Media\Fields;

use finfo;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Pages\Saving\LockedFields;
use LynxCms\Core\Pages\Saving\SavingPage;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Support\Value;
use LynxCms\Media\MediaPaths;

/**
 * What a media value may name when a page is saved: a file in the page's own folder (or the
 * folder of the page a draft belongs to), stored on the field's disk, with an extension the field
 * allows, and for an images field a picture. Its size, type and dimensions are read from the file,
 * so a value can't claim others. Superusers and the site's code are trusted with any value, as an
 * import without files keeps the names the source site gave them; outside a save (SavingPage has
 * no page) nothing is checked.
 *
 * @internal
 */
final class StoredFileCheck
{
    /** Bytes read to find an image's type and size; enough for the header of every format allowed. */
    private const int HEADER_BYTES = 262144;

    /** @var array<string, array<string, mixed>|string> disk and file => facts, or the error */
    private array $known = [];

    public function __construct(
        private readonly MediaPaths $paths,
        private readonly SavingPage $saving,
        private readonly LockedFields $code,
        private readonly Authorizer $authorizer,
        private readonly ActionRunner $runner,
    ) {}

    /**
     * The facts read from the file (bytes, mime, and for images width and height), or the error.
     * Empty when no page is being saved.
     *
     * @param  list<string>  $extensions
     * @return array<string, mixed>|string
     */
    public function facts(string $file, bool $private, bool $isImage, array $extensions): array|string
    {
        $folders = $this->saving->folders();

        if ($folders === null || $this->code->mayWrite() || $this->authorizer->isSuperuser($this->runner->actor())) {
            return [];
        }

        if (! in_array((int) strstr($file, '/', true), $folders, true)) {
            return Value::string(__('lynx-media::validation.other_page'));
        }

        if (! in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $extensions, true)) {
            return Value::string(__('lynx-media::validation.stored_type'));
        }

        return $this->known[($private ? 'private:' : 'public:').$file] ??= $this->read($file, $private, $isImage);
    }

    /** @return array<string, mixed>|string */
    private function read(string $file, bool $private, bool $isImage): array|string
    {
        $disk = $this->paths->disk($private);
        $path = $this->paths->path($file);

        if (! $disk->exists($path)) {
            return Value::string(__('lynx-media::validation.missing_file'));
        }

        $stream = $disk->readStream($path);
        $header = is_resource($stream) ? (string) stream_get_contents($stream, self::HEADER_BYTES) : '';

        if (is_resource($stream)) {
            fclose($stream);
        }

        $facts = ['bytes' => $disk->size($path), 'mime' => (string) new finfo(FILEINFO_MIME_TYPE)->buffer($header)];

        if (! $isImage) {
            return $facts;
        }

        $size = @getimagesizefromstring($header) ?: @getimagesizefromstring((string) $disk->get($path));

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            return Value::string(__('lynx-media::validation.not_image'));
        }

        return ['bytes' => $facts['bytes'], 'mime' => $size['mime'], 'width' => $size[0], 'height' => $size[1]];
    }
}
