<?php

declare(strict_types=1);

namespace LynxCms\Media\Images;

/**
 * The crop box that keeps an image's focus point in view (ADR-0046): the largest box with the
 * target's aspect ratio, shrunk by the zoom (0 to 100 % takes up to half off), centred on the focus
 * and moved back inside the image.
 *
 * @internal
 */
final class FocusCrop
{
    private const float MAX_ZOOM_SHRINK = 0.5;

    /**
     * @return array{0: int, 1: int, 2: int, 3: int} width, height, x, y
     */
    public static function rect(int $width, int $height, int $targetWidth, int $targetHeight, int $focusX, int $focusY, int $zoom = 0): array
    {
        $ratio = $targetWidth / max(1, $targetHeight);
        [$cropWidth, $cropHeight] = $width / max(1, $height) > $ratio
            ? [$height * $ratio, (float) $height]
            : [(float) $width, $width / $ratio];
        $shrink = 1 - self::MAX_ZOOM_SHRINK * max(0, min(100, $zoom)) / 100;
        $cropWidth = max(1, (int) round($cropWidth * $shrink));
        $cropHeight = max(1, (int) round($cropHeight * $shrink));
        $x = (int) round($focusX / 100 * $width - $cropWidth / 2);
        $y = (int) round($focusY / 100 * $height - $cropHeight / 2);

        return [$cropWidth, $cropHeight, max(0, min($width - $cropWidth, $x)), max(0, min($height - $cropHeight, $y))];
    }
}
