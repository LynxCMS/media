<?php

declare(strict_types=1);

namespace LynxCms\Media\Images;

use Illuminate\Support\Facades\Image;
use LynxCms\Core\Support\Value;
use LynxCms\Media\MediaPaths;
use LynxCms\Media\Remote\RemoteMedia;
use LynxCms\Media\Values\Pageimage;

/**
 * Sizes of an image, made with Laravel's Image facade on first use and kept next to the original,
 * always from the original (a chained size is one file, not a copy of a copy),
 * under a name that says how they were made, "photo.800x600-f30x40z0-q70.jpg" (ADR-0046), so a
 * later request finds them. Files are never larger than asked; resizes never upscale.
 *
 * @internal
 */
final readonly class Variations
{
    public function __construct(private MediaPaths $paths, private RemoteMedia $remote) {}

    /**
     * The variation of an original image, named and measured but not made yet: its file is
     * written by write() when a template first asks for it (Pageimage::file()).
     */
    public function make(Pageimage $image, int $width, int $height, bool $crop, ?int $quality = null, ?string $format = null): Pageimage
    {
        $crop = $crop && $width > 0 && $height > 0;
        [$targetWidth, $targetHeight] = $crop ? [$width, $height] : $this->fit($image->width(), $image->height(), $width, $height);
        $quality ??= $this->defaultQuality();
        $spec = ['width' => $width, 'height' => $height, 'crop' => $crop, 'quality' => $quality, 'format' => $format];

        return $image->with([
            'file' => $this->name($image, $targetWidth, $targetHeight, $crop, $quality, $format),
            'width' => $targetWidth,
            'height' => $targetHeight,
            'mime' => $format === 'webp' ? 'image/webp' : $image->mime(),
            'variation' => $spec,
            'source' => $image->stored(),
        ]);
    }

    /**
     * Writes a variation's file from the original, unless it exists.
     *
     * @param  array{width: mixed, height: mixed, crop: mixed, quality: mixed, format: mixed}  $spec
     */
    public function write(Pageimage $original, string $file, array $spec): void
    {
        $disk = $this->paths->disk($original->isPrivate());

        if ($disk->exists($this->paths->path($file))) {
            return;
        }

        // On a developer's machine the original may still be on production (ADR-0082).
        if (! $original->isPrivate()) {
            $this->remote->ensure($original->file());
        }

        $crop = (bool) $spec['crop'];
        [$width, $height] = $crop ? [Value::int($spec['width']), Value::int($spec['height'])] : $this->fit($original->width(), $original->height(), Value::int($spec['width']), Value::int($spec['height']));
        $disk->put($this->paths->path($file), $this->render($original, $width, $height, $crop, Value::int($spec['quality'], $this->defaultQuality()), Value::nullableString($spec['format'])));
    }

    private function render(Pageimage $image, int $width, int $height, bool $crop, int $quality, ?string $format): string
    {
        $made = Image::fromStorage($this->paths->path($image->file()), $this->paths->diskName($image->isPrivate()))->orient();

        if ($crop) {
            $focus = $image->focus();
            [$cropWidth, $cropHeight, $x, $y] = FocusCrop::rect($image->width(), $image->height(), $width, $height, $focus['x'], $focus['y'], $focus['zoom']);
            $made = $made->crop(max(1, $cropWidth), max(1, $cropHeight), $x, $y);
        }

        $made = $made->resize(max(1, $width), max(1, $height))->quality(max(1, min(100, $quality)));

        return ($format === 'webp' ? $made->toWebp() : $made)->toBytes();
    }

    /**
     * Within $width × $height (0 = any), keeping the aspect ratio, never larger than the original.
     *
     * @return array{0: int, 1: int}
     */
    private function fit(int $originalWidth, int $originalHeight, int $width, int $height): array
    {
        $scales = array_filter([
            $width > 0 ? $width / max(1, $originalWidth) : null,
            $height > 0 ? $height / max(1, $originalHeight) : null,
        ], fn (?float $scale): bool => $scale !== null);
        $scale = min(1.0, $scales === [] ? 1.0 : min($scales));

        return [max(1, (int) round($originalWidth * $scale)), max(1, (int) round($originalHeight * $scale))];
    }

    private function name(Pageimage $image, int $width, int $height, bool $crop, int $quality, ?string $format): string
    {
        $info = pathinfo($image->file());
        $focus = $image->focus();
        $suffix = "{$width}x{$height}"
            .($crop && $focus !== ['x' => 50, 'y' => 50, 'zoom' => 0] ? "-f{$focus['x']}x{$focus['y']}z{$focus['zoom']}" : '')
            .($quality !== $this->defaultQuality() ? "-q{$quality}" : '');
        $extension = $format ?? strtolower(Value::string($info['extension'] ?? 'jpg'));

        return Value::string($info['dirname'] ?? '').'/'.$info['filename'].'.'.$suffix.'.'.($extension === 'jpeg' ? 'jpg' : $extension);
    }

    private function defaultQuality(): int
    {
        return Value::int(config('lynx-media.quality'), 82);
    }
}
