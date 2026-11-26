<?php

declare(strict_types=1);

namespace LynxCms\Media\Fields;

use finfo;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Pages\Saving\LockedFields;
use LynxCms\Core\Pages\Saving\SavingPage;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Support\Value;
use LynxCms\Media\MediaPaths;

/**
 * What a media value may name when a page is saved: a file in the page's own folder (or the
 * folder of the page a draft belongs to), stored on the field's disk, with an extension the field
 * allows, and for an images field a picture. Its size, type and dimensions come from what the
 * page stores for it, or for a file added in this save from the file itself, so a value can't claim
 * others and a save reads the disk only for files it adds. Superusers and the site's code are trusted with any value, as an
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

    /** @var array<string, array<string, array<string, mixed>>> page ids => file => stored entry */
    private array $storedByPages = [];

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

        // A file the page already holds was checked when it was added: keep what is stored for it,
        // without reading the disk, which may be a network away (S3).
        $stored = $this->stored($folders)[$file] ?? null;

        // Only an images field's entry, which has dimensions, vouches that the file is a picture.
        if ($stored !== null && (! $isImage || isset($stored['width'], $stored['height']))) {
            return array_intersect_key($stored, array_flip(['bytes', 'mime', 'width', 'height']));
        }

        return $this->known[($private ? 'private:' : 'public:').$file] ??= $this->read($file, $private, $isImage);
    }

    /**
     * Every file entry the pages store now, by file name, read once per save: rows of media, repeater
     * and matrix fields only.
     *
     * @param  list<int>  $pageIds
     * @return array<string, array<string, mixed>>
     */
    private function stored(array $pageIds): array
    {
        $key = implode(',', $pageIds);

        if (isset($this->storedByPages[$key])) {
            return $this->storedByPages[$key];
        }

        $entries = [];

        foreach (lynx()->fields->all() as $field) {
            if ($field->fieldType() instanceof ItemFieldType) {
                foreach (DB::table($field->table())->whereIn('page_id', $pageIds)->pluck('data') as $data) {
                    $this->collect(json_decode(Value::string($data), true), $entries);
                }
            }
        }

        return $this->storedByPages[$key] = $entries;
    }

    /** @param  array<string, array<string, mixed>>  $entries */
    private function collect(mixed $data, array &$entries): void
    {
        if (! is_array($data)) {
            return;
        }

        if (is_string($data['file'] ?? null) && array_key_exists('bytes', $data)) {
            $entries[$data['file']] = Value::map($data);
        }

        foreach ($data as $child) {
            $this->collect($child, $entries);
        }
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
