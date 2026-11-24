<?php

declare(strict_types=1);

namespace LynxCms\Media\Values;

use ArrayIterator;
use Countable;
use IteratorAggregate;
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
     * The files of the current language (or $language's): those with that language, or tagged with
     * its code, else those of the next language along its fallback chain, else the files for every
     * language (ADR-0046, ADR-0150).
     *
     * @return self<T>
     */
    public function inLanguage(?string $language = null): self
    {
        return new self(MediaLanguages::firstAlongChain($this->files, $language));
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
