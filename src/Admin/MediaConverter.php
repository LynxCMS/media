<?php

declare(strict_types=1);

namespace LynxCms\Media\Admin;

use LynxCms\Admin\Editor\FormConverter;
use LynxCms\Admin\Editor\PreparesDisplay;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * Media field values in the editor (ADR-0046): one form item per file, its description per
 * language and its tags as text; back to items for pages.save.
 *
 * @internal
 */
final class MediaConverter implements FormConverter, PreparesDisplay
{
    public function toForm(mixed $value, ?Field $field = null): mixed
    {
        $codes = Value::strings(lynx()->languages->all()->pluck('code')->all());
        $items = [];

        foreach (Value::list($value) as $item) {
            $item = Value::array($item);
            $values = Value::map($item['values'] ?? []);
            $description = Value::map($values['description'] ?? []);
            $items[] = [
                'key' => Value::string($item['key'] ?? ''),
                'enabled' => (bool) ($item['enabled'] ?? true),
                'file' => Value::string($values['file'] ?? ''),
                'width' => Value::int($values['width'] ?? 0),
                'height' => Value::int($values['height'] ?? 0),
                'bytes' => Value::int($values['bytes'] ?? 0),
                'mime' => Value::string($values['mime'] ?? ''),
                'focus' => Value::map($values['focus'] ?? ['x' => 50, 'y' => 50, 'zoom' => 0]),
                'tags' => implode(', ', Value::strings($values['tags'] ?? [])),
                'description' => array_map(fn (string $code): string => Value::string($description[$code] ?? ''), array_combine($codes, $codes)),
            ];
        }

        return $items;
    }

    public function fromForm(mixed $value, ?Field $field = null): mixed
    {
        $items = [];

        foreach (Value::list($value) as $item) {
            $item = Value::array($item);
            $values = array_filter([
                'file' => Value::string($item['file'] ?? ''),
                'width' => Value::int($item['width'] ?? 0),
                'height' => Value::int($item['height'] ?? 0),
                'bytes' => Value::int($item['bytes'] ?? 0),
                'mime' => Value::string($item['mime'] ?? ''),
                'focus' => Value::map($item['focus'] ?? []),
                'tags' => array_values(array_filter(array_map(trim(...), explode(',', Value::string($item['tags'] ?? ''))))),
                'description' => array_filter(Value::map($item['description'] ?? []), fn (mixed $text): bool => trim(Value::string($text)) !== ''),
            ], fn (mixed $v): bool => ! in_array($v, [[], '', 0], true));
            $items[] = ['key' => Value::string($item['key'] ?? ''), 'enabled' => (bool) ($item['enabled'] ?? true), 'values' => $values];
        }

        return $items;
    }

    public function display(mixed $value): mixed
    {
        $count = count(Value::list($value));

        return $count === 1 ? '1 file' : "{$count} files";
    }
}
