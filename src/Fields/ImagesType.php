<?php

declare(strict_types=1);

namespace LynxCms\Media\Fields;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\Items\ItemNormalizer;
use LynxCms\Core\Fields\Items\ItemProperty;
use LynxCms\Core\Support\Value;
use LynxCms\Media\Values\Pageimage;

/**
 * Images of a page (ADR-0046): dimensions, a focus point that crops keep in view, a description per
 * language and tags. Templates resize them lazily: $image->size(800, 600), ->resize(400), ->webp().
 */
final class ImagesType extends MediaFieldType
{
    public static function name(): string
    {
        return 'images';
    }

    public static function description(): string
    {
        return 'Images that belong to the page, sortable, each with a focus point, a description per language and tags. Upload with media.store, then save the returned entries. Setting "max_files": 1 makes it a single image.';
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return parent::settingsSchema($schema) + [
            'min_width' => $schema->integer()->description('Smallest width accepted, in pixels.'),
            'min_height' => $schema->integer()->description('Smallest height accepted, in pixels.'),
            'max_width' => $schema->integer()->description('Wider uploads are scaled down to this width. Default 2560.'),
            'max_height' => $schema->integer()->description('Taller uploads are scaled down to this height. Default 2560.'),
        ];
    }

    public function settingsRules(): array
    {
        return parent::settingsRules() + [
            'min_width' => ['sometimes', 'integer', 'min:0'],
            'min_height' => ['sometimes', 'integer', 'min:0'],
            'max_width' => ['sometimes', 'integer', 'min:1'],
            'max_height' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    protected function defaultExtensions(): string
    {
        return 'jpg jpeg png webp gif avif';
    }

    /** Files' properties, and each image's size: "hero.width>=1200". */
    public function itemProperties(): array
    {
        return parent::itemProperties() + [
            'width' => new ItemProperty(Comparison::Integer),
            'height' => new ItemProperty(Comparison::Integer),
        ];
    }

    protected function details(array $values, string $key, ItemNormalizer $normalizer): array
    {
        $focus = Value::map($values['focus'] ?? []);

        return [
            'width' => Value::int($values['width'] ?? 0),
            'height' => Value::int($values['height'] ?? 0),
            'focus' => [
                'x' => $this->percent($focus['x'] ?? 50),
                'y' => $this->percent($focus['y'] ?? 50),
                'zoom' => $this->percent($focus['zoom'] ?? 0),
            ],
        ];
    }

    protected function file(string $key, array $values): Pageimage
    {
        return new Pageimage($key, $values);
    }

    private function percent(mixed $value): int
    {
        return max(0, min(100, (int) round(is_numeric($value) ? (float) $value : 50)));
    }

    protected function fileClass(): string
    {
        return Pageimage::class;
    }
}
