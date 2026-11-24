<?php

declare(strict_types=1);

namespace LynxCms\Media\Values;

use LynxCms\Core\Models\Language;

/**
 * Picks a language's files. A file's language is its "language" value (ADR-0150), or for older
 * files a tag with the language's code (ADR-0046).
 *
 * @internal
 */
final class MediaLanguages
{
    /**
     * A field with a file per language (ADR-0150): the language's own files and the files for every
     * language, the language's first. With neither, the next language's along the fallback chain
     * (none when the field doesn't fall back).
     *
     * @template T of Pagefile
     *
     * @param  list<T>  $files
     * @return list<T>
     */
    public static function forLanguage(array $files, ?string $language = null, bool $fallsBack = true): array
    {
        $chain = lynx()->languages->chain($language);
        $forEvery = self::forEveryLanguage($files);
        $own = self::of($files, (string) ($chain[0]->code ?? ''));

        if ($own !== [] || $forEvery !== []) {
            return [...$own, ...$forEvery];
        }

        foreach ($fallsBack ? array_slice($chain, 1) : [] as $step) {
            $fallback = self::of($files, (string) $step->code);

            if ($fallback !== []) {
                return $fallback;
            }
        }

        return [];
    }

    /**
     * MediaList::inLanguage() (ADR-0046): the first language along the chain that has files, else
     * the files for every language.
     *
     * @template T of Pagefile
     *
     * @param  list<T>  $files
     * @return list<T>
     */
    public static function firstAlongChain(array $files, ?string $language = null): array
    {
        foreach (lynx()->languages->chain($language) as $step) {
            $own = self::of($files, (string) $step->code);

            if ($own !== []) {
                return $own;
            }
        }

        return self::forEveryLanguage($files);
    }

    /**
     * @template T of Pagefile
     *
     * @param  list<T>  $files
     * @return list<T>
     */
    private static function of(array $files, string $code): array
    {
        return array_values(array_filter($files, fn (Pagefile $file): bool => self::belongsTo($file, $code)));
    }

    /**
     * Files with no language and no language tag.
     *
     * @template T of Pagefile
     *
     * @param  list<T>  $files
     * @return list<T>
     */
    private static function forEveryLanguage(array $files): array
    {
        $codes = lynx()->languages->all()->map(fn (Language $l): string => (string) $l->code)->all();

        return array_values(array_filter($files, fn (Pagefile $file): bool => $file->language() === null && array_intersect($codes, $file->tags()) === []));
    }

    private static function belongsTo(Pagefile $file, string $code): bool
    {
        return $file->language() === $code || ($file->language() === null && $file->hasTag($code));
    }
}
