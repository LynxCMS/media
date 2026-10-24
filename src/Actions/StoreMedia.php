<?php

declare(strict_types=1);

namespace LynxCms\Media\Actions;

use finfo;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Media\Downloads\RemoteFile;
use LynxCms\Media\Fields\ImagesType;
use LynxCms\Media\Fields\MediaFieldType;
use LynxCms\Media\MediaPaths;
use Throwable;

/**
 * Stores a file for a page's media field and returns the entry to save in it (ADR-0046): from a
 * URL (public servers only, see RemoteFile) or base64, which is how the admin sends uploads.
 * Checks the field's extensions, size and dimensions, scales images down to the field's maximum,
 * and writes {root}/{page id}/{name}. The page's value does not change until pages.save adds the
 * entry; files never saved are removed by media.prune.
 */
final class StoreMedia extends Action
{
    private const int DEFAULT_MAX_KB = 10240;

    private const int DEFAULT_MAX_SIDE = 2560;

    /** Largest image decoded, in pixels: about 8000 × 6000. */
    private const int MAX_PIXELS = 50_000_000;

    public function __construct(private readonly MediaPaths $paths, private readonly RemoteFile $remote) {}

    public static function name(): string
    {
        return 'media.store';
    }

    public static function description(): string
    {
        return 'Store an image or file for a page\'s media field, from a URL or base64, and get the entry to add to the field\'s value with pages.save.';
    }

    public static function permission(): string
    {
        return 'pages.edit';
    }

    public static function rules(): array
    {
        return [
            'page' => ['required'],
            'field' => ['required', 'string'],
            'url' => ['sometimes', 'nullable', 'url'],
            'base64' => ['sometimes', 'nullable', 'string'],
            'filename' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('Id, default-language path or name of the page.')->required(),
            'field' => $schema->string()->description('Name of an images or files field of the page\'s template.')->required(),
            'url' => $schema->string()->description('Download the file from this URL, on a public web server (http or https).'),
            'base64' => $schema->string()->description('Or the file\'s content, base64 encoded.'),
            'filename' => $schema->string()->description('Name to store it under, e.g. "poster.jpg". Defaults to the URL\'s name.'),
        ];
    }

    /**
     * @return array<string, mixed> the entry's values: file, bytes, mime (and width, height for images)
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $page = lynx()->pages->get(Value::string($input->get('page'))) ?? throw ValidationException::withMessages(['page' => 'The page does not exist.']);
        $context->authorize('pages.edit', $page);
        $field = $this->mediaField($page, (string) $input->string('field'));
        $type = $field->fieldType();
        assert($type instanceof MediaFieldType);
        $settings = $field->config();
        $maxBytes = Value::int($settings['max_kb'] ?? self::DEFAULT_MAX_KB, self::DEFAULT_MAX_KB) * 1024;
        [$contents, $name] = $this->contents($input, $maxBytes);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (! in_array($extension, $type->extensions($settings), true)) {
            throw ValidationException::withMessages(['filename' => "Files of type [{$extension}] are not allowed here. Allowed: ".implode(', ', $type->extensions($settings)).'.']);
        }

        if (strlen($contents) > $maxBytes) {
            throw ValidationException::withMessages(['file' => 'The file is larger than this field allows.']);
        }

        $entry = $type instanceof ImagesType ? $this->image($contents, $settings) : ['contents' => $contents, 'mime' => (string) new finfo(FILEINFO_MIME_TYPE)->buffer($contents)];
        $file = $page->id.'/'.Str::slug(pathinfo($name, PATHINFO_FILENAME)).'-'.Str::lower(Str::random(6)).'.'.($extension === 'jpeg' ? 'jpg' : $extension);
        $values = ['file' => $file, 'bytes' => strlen(Value::string($entry['contents'])), 'mime' => $entry['mime']] + array_diff_key($entry, ['contents' => true, 'mime' => true]);

        if (! $context->dryRun) {
            $path = $this->paths->path($file);
            $disk = $this->paths->disk(MediaFieldType::isPrivate($settings));
            $disk->put($path, Value::string($entry['contents']));
            DB::afterRollBack(fn () => $disk->delete($path));
        }

        $context->changes->created('file', $file, "Store {$file} for {$field->name} on page {$page->name()}", $values);

        return $values;
    }

    /** A media field of the page's template, or a media subfield of one of its repeaters or matrices. */
    private function mediaField(Page $page, string $name): Field
    {
        $field = $page->templateField($name)?->field;

        if ($field instanceof Field && $field->fieldType() instanceof MediaFieldType) {
            return $field;
        }

        foreach (lynx()->templates->fields($page->template()->name) as $templateField) {
            $type = $templateField->field->fieldType();

            foreach ($type instanceof ItemFieldType ? $type->allSubfields($templateField->field->config()) : [] as $subfield) {
                if ($subfield->name() === $name && $subfield->type() instanceof MediaFieldType) {
                    return $subfield->field;
                }
            }
        }

        throw ValidationException::withMessages(['field' => "Template [{$page->template()->name}] has no images or files field [{$name}]."]);
    }

    /**
     * @return array{0: string, 1: string} contents and file name
     */
    private function contents(Input $input, int $maxBytes): array
    {
        $name = Value::string($input->get('filename'));

        if ($input->string('url') !== null) {
            [$contents, $urlName] = $this->remote->download($input->string('url'), $maxBytes);

            return [$contents, $name !== '' ? $name : $urlName];
        }

        $decoded = base64_decode(Value::string($input->get('base64')), true);

        if ($decoded === false || $decoded === '' || $name === '') {
            throw ValidationException::withMessages(['base64' => 'Give a url, or base64 content with a filename.']);
        }

        return [$decoded, $name];
    }

    /**
     * Checks an image's size and scales it down to the field's maximum.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function image(string $contents, array $settings): array
    {
        // A small file can declare a huge canvas; refuse it from its header, before decoding it.
        // Data that is no image makes getimagesizefromstring warn; the decoder below reports it.
        $declared = @getimagesizefromstring($contents);

        if ($declared !== false && $declared[0] * $declared[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['file' => 'The image is too large to process ('.$declared[0].' × '.$declared[1].').']);
        }

        try {
            $image = Image::fromBytes($contents)->orient();
            [$width, $height] = [$image->width(), $image->height()];
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'This is not an image Lynx can read.']);
        }

        if ($width < Value::int($settings['min_width'] ?? 0) || $height < Value::int($settings['min_height'] ?? 0)) {
            throw ValidationException::withMessages(['file' => 'The image is smaller than this field allows ('.Value::int($settings['min_width'] ?? 0).' × '.Value::int($settings['min_height'] ?? 0).').']);
        }

        $maxWidth = Value::int($settings['max_width'] ?? self::DEFAULT_MAX_SIDE, self::DEFAULT_MAX_SIDE);
        $maxHeight = Value::int($settings['max_height'] ?? self::DEFAULT_MAX_SIDE, self::DEFAULT_MAX_SIDE);

        if ($width > $maxWidth || $height > $maxHeight) {
            $scale = min($maxWidth / $width, $maxHeight / $height);
            [$width, $height] = [(int) round($width * $scale), (int) round($height * $scale)];
            $image = Image::fromBytes($contents)->orient()->resize(max(1, $width), max(1, $height));
            $contents = $image->toBytes();
        }

        return ['contents' => $contents, 'mime' => $image->mimeType(), 'width' => $width, 'height' => $height, 'focus' => ['x' => 50, 'y' => 50, 'zoom' => 0]];
    }
}
