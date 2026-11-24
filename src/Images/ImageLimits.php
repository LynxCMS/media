<?php

declare(strict_types=1);

namespace LynxCms\Media\Images;

use LynxCms\Core\Support\Value;

/**
 * The largest an uploaded image is kept, from an images field's max_width and max_height: larger
 * uploads are scaled down to fit, in the browser before uploading (the admin's upload zone) and
 * again by media.store, which every upload passes.
 *
 * @internal
 */
final class ImageLimits
{
    public const int DEFAULT_MAX_SIDE = 2560;

    /**
     * @param  array<string, mixed>  $settings  the field's settings
     * @return array{0: int, 1: int} width and height
     */
    public static function maxSides(array $settings): array
    {
        return [
            Value::int($settings['max_width'] ?? self::DEFAULT_MAX_SIDE, self::DEFAULT_MAX_SIDE),
            Value::int($settings['max_height'] ?? self::DEFAULT_MAX_SIDE, self::DEFAULT_MAX_SIDE),
        ];
    }

    /** JPEG and WebP quality, 1 to 100, from lynx-media.quality. */
    public static function quality(): int
    {
        return max(1, min(100, Value::int(config('lynx-media.quality'), 82)));
    }
}
