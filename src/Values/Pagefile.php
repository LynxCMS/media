<?php

declare(strict_types=1);

namespace LynxCms\Media\Values;

use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;
use LynxCms\Media\MediaPaths;
use Stringable;

/**
 * A file of a page as templates read it (ADR-0046): its URL, name, size, description in the current
 * language (with fallback) and tags.
 *
 * @api
 */
class Pagefile implements Stringable
{
    /**
     * @param  array<string, mixed>  $values  the stored item values: file, bytes, mime, tags, description...
     */
    public function __construct(protected readonly string $key, protected readonly array $values) {}

    public function key(): string
    {
        return $this->key;
    }

    /** The stored name, "{page id}/{file}". */
    public function file(): string
    {
        return Value::string($this->values['file'] ?? '');
    }

    public function url(): string
    {
        return app(MediaPaths::class)->url($this->file(), $this->isPrivate());
    }

    /** Whether the file belongs to a private field, served through Lynx's route (ADR-0077). */
    public function isPrivate(): bool
    {
        return (bool) ($this->values['private'] ?? false);
    }

    public function name(): string
    {
        return basename($this->file());
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->file(), PATHINFO_EXTENSION));
    }

    public function bytes(): int
    {
        return Value::int($this->values['bytes'] ?? 0);
    }

    public function mime(): string
    {
        return Value::string($this->values['mime'] ?? '');
    }

    public function description(): string
    {
        return LocalizedText::from($this->values['description'] ?? [])->in();
    }

    /** @return list<string> */
    public function tags(): array
    {
        return Value::strings($this->values['tags'] ?? []);
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags(), true);
    }

    /**
     * The stored item values, as saved in the field.
     *
     * @return array<string, mixed>
     */
    public function stored(): array
    {
        return $this->values;
    }

    public function __toString(): string
    {
        return $this->url();
    }
}
