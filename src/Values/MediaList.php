<?php

declare(strict_types=1);

namespace LynxCms\Media\Values;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use LynxCms\Core\Models\Language;
use Traversable;

/**
 * The files of a media field that are switched on, in order (ADR-0046).
 *
 * @template T of Pagefile
 *
 * @implements IteratorAggregate<int, T>
 *
 * @api
 */
final readonly class MediaList implements Countable, IteratorAggregate
{
    /**
     * @param  list<T>  $files
     */
    public function __construct(private array $files) {}

    /** @return T|null */
    public function first(): ?Pagefile
    {
        return $this->files[0] ?? null;
    }

    /** @return T|null */
    public function last(): ?Pagefile
    {
        return $this->files[count($this->files) - 1] ?? null;
    }

    /** @return list<T> */
    public function all(): array
    {
        return $this->files;
    }

    /** @return list<T> files with this tag */
    public function tagged(string $tag): array
    {
        return array_values(array_filter($this->files, fn (Pagefile $file): bool => $file->hasTag($tag)));
    }

    /**
     * Files that have every one of these tags, e.g. withTags('a4', 'color').
     *
     * @return self<T>
     */
    public function withTags(string ...$tags): self
    {
        return new self(array_values(array_filter($this->files, fn (Pagefile $file): bool => array_diff($tags, $file->tags()) === [])));
    }

    /**
     * Files tagged with a language code: the current language's (or $language's), else those of the
     * next language along its fallback chain, else the files tagged with no language at all.
     *
     * @return self<T>
     */
    public function inLanguage(?string $language = null): self
    {
        foreach (lynx()->languages->chain($language) as $step) {
            $tagged = $this->tagged((string) $step->code);

            if ($tagged !== []) {
                return new self($tagged);
            }
        }

        $codes = lynx()->languages->all()->map(fn (Language $language): string => (string) $language->code)->all();

        return new self(array_values(array_filter($this->files, fn (Pagefile $file): bool => array_intersect($codes, $file->tags()) === [])));
    }

    public function isEmpty(): bool
    {
        return $this->files === [];
    }

    public function count(): int
    {
        return count($this->files);
    }

    /** @return Traversable<int, T> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->files);
    }
}
