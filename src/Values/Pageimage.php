<?php

declare(strict_types=1);

namespace LynxCms\Media\Values;

use LynxCms\Core\Support\Value;
use LynxCms\Media\Images\Variations;
use Override;

/**
 * An image of a page (ADR-0046). Sizes are made on first use and kept next to the original:
 * size(800, 600) crops around the focus point, resize(400) keeps the aspect ratio, webp() converts.
 * Chained calls combine: size(300, 300)->webp() is one WebP file made from the original.
 *
 * @api
 */
final class Pageimage extends Pagefile
{
    public function width(): int
    {
        return Value::int($this->values['width'] ?? 0);
    }

    public function height(): int
    {
        return Value::int($this->values['height'] ?? 0);
    }

    /**
     * @return array{x: int, y: int, zoom: int} percentages
     */
    public function focus(): array
    {
        $focus = Value::map($this->values['focus'] ?? []);

        return ['x' => Value::int($focus['x'] ?? 50, 50), 'y' => Value::int($focus['y'] ?? 50, 50), 'zoom' => Value::int($focus['zoom'] ?? 0)];
    }

    /** Exactly $width × $height, cropped around the focus point. */
    public function size(int $width, int $height, ?int $quality = null): self
    {
        return $this->variation(['width' => $width, 'height' => $height, 'crop' => true], $quality);
    }

    /** Scaled to $width (and/or $height), keeping the aspect ratio. Never larger than the original. */
    public function resize(?int $width = null, ?int $height = null, ?int $quality = null): self
    {
        return $this->variation(['width' => $width ?? 0, 'height' => $height ?? 0, 'crop' => false], $quality);
    }

    public function webp(?int $quality = null): self
    {
        return $this->variation(['format' => 'webp'], $quality);
    }

    /** The uploaded image this one was made from; itself when it is the original. */
    public function original(): self
    {
        $source = $this->values['source'] ?? null;

        return is_array($source) ? new self($this->key, Value::map($source)) : $this;
    }

    /** The stored name; a variation's file is made here, on first use. */
    #[Override]
    public function file(): string
    {
        $file = parent::file();

        if (isset($this->values['variation'])) {
            app(Variations::class)->write($this->original(), $file, $this->spec());
        }

        return $file;
    }

    /** A named size from lynx-media.presets. */
    public function preset(string $name): self
    {
        [$width, $height] = Value::list(config("lynx-media.presets.{$name}", [0, 0])) + [0, 0];

        return Value::int($width) > 0 && Value::int($height) > 0 ? $this->size(Value::int($width), Value::int($height)) : $this->resize(Value::int($width) ?: null, Value::int($height) ?: null);
    }

    /**
     * A srcset attribute value with one resized copy per width.
     *
     * @param  list<int>  $widths
     */
    public function srcset(array $widths): string
    {
        $entries = [];

        foreach ($widths as $width) {
            $copy = $this->resize($width);
            $entries[] = $copy->url().' '.$copy->width().'w';
        }

        return implode(', ', array_unique($entries));
    }

    /**
     * A variation made from the original with this one's settings and the changes, so chained
     * calls such as size(300, 300)->webp() give one file, not a copy of a copy (ADR-0046).
     *
     * @param  array<string, mixed>  $changes
     */
    private function variation(array $changes, ?int $quality): self
    {
        $spec = $changes + ($quality === null ? [] : ['quality' => $quality]) + $this->spec();

        return app(Variations::class)->make($this->original(), Value::int($spec['width']), Value::int($spec['height']), (bool) $spec['crop'], Value::nullableInt($spec['quality']), Value::nullableString($spec['format']));
    }

    /** @return array{width: mixed, height: mixed, crop: mixed, quality: mixed, format: mixed} */
    private function spec(): array
    {
        return Value::map($this->values['variation'] ?? []) + ['width' => 0, 'height' => 0, 'crop' => false, 'quality' => null, 'format' => null];
    }

    /**
     * Another file of this image (a variation) with the same description, tags and focus.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return new self($this->key, $changes + $this->values);
    }
}
