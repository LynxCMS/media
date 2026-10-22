<?php

declare(strict_types=1);

namespace LynxCms\Media\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use LynxCms\Media\Values\Pageimage;

/**
 * <x-lynx-image :image="$page->hero" :width="1200" :height="675" sizes="100vw" />: an img tag with
 * a cropped (both sizes) or resized size, a srcset of smaller widths, the description as alt text
 * and lazy loading (ADR-0046). Renders nothing without an image.
 *
 * @api
 */
final class ImageComponent extends Component
{
    /** Fractions of the width offered in the srcset. */
    private const array SRCSET_STEPS = [0.5, 0.75, 1];

    public function __construct(
        public ?Pageimage $image = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $alt = null,
        public ?string $sizes = null,
        public bool $lazy = true,
    ) {}

    public function render(): View
    {
        $image = $this->image;
        $shown = $image === null ? null : ($this->width !== null && $this->height !== null ? $image->size($this->width, $this->height) : $image->resize($this->width, $this->height));
        $srcset = [];

        if ($image !== null && $shown !== null && ($this->width === null || $this->height === null)) {
            $srcset = array_values(array_unique(array_map(fn (float $step): int => max(1, (int) round($shown->width() * $step)), self::SRCSET_STEPS)));
        }

        return view('lynx-media::components.image', [
            'shown' => $shown,
            'srcset' => $image !== null && $srcset !== [] ? $image->srcset($srcset) : null,
            'altText' => $this->alt ?? $image?->description() ?? '',
        ]);
    }
}
