<?php

declare(strict_types=1);

namespace LynxCms\Media\Fields;

use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Support\Value;

/**
 * Checks of one media item's details while a value is normalized (MediaFieldType): its tags, its
 * descriptions, its language, and how many files one language reads. Each adds its errors under
 * the key it is given.
 *
 * @internal
 */
final class MediaItemChecks
{
    /**
     * The most files one language reads: all of them, or with a file per language, the largest
     * group of one language's files, or of the files for every language.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $settings
     */
    public static function mostFilesInOneLanguage(array $items, array $settings): int
    {
        if (! MediaFieldType::isPerLanguage($settings)) {
            return count($items);
        }

        $groups = array_count_values(array_map(fn (array $item): string => Value::string(Value::map($item['values'] ?? [])['language'] ?? ''), $items));

        return $groups === [] ? 0 : max($groups);
    }

    /**
     * A file's language: a code the site has, or null for every language.
     *
     * @param  array<string, string>  $errors
     */
    public static function language(mixed $language, string $key, array &$errors): ?string
    {
        $code = trim(Value::string($language));

        if ($code === '') {
            return null;
        }

        if (lynx()->languages->find($code) === null) {
            $errors[$key] = Value::string(__('lynx-media::validation.language', ['language' => $code]));
        }

        return $code;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, string>  $errors
     * @return list<string>
     */
    public static function tags(mixed $tags, array $settings, string $key, array &$errors): array
    {
        $list = is_string($tags) ? explode(',', $tags) : Value::list($tags);
        $clean = array_values(array_unique(array_filter(array_map(fn (mixed $tag): string => trim(Value::string($tag)), $list))));
        $allowed = Value::strings($settings['tags'] ?? []);

        foreach ($allowed === [] ? [] : array_diff($clean, $allowed) as $tag) {
            $errors[$key] = Value::string(__('lynx-media::validation.tag', ['tag' => $tag, 'tags' => implode(', ', $allowed)]));
        }

        return $clean;
    }

    /**
     * Text, or language code => text; plain text counts as the default language.
     *
     * @param  array<string, string>  $errors
     * @return array<string, string>
     */
    public static function descriptions(mixed $description, string $key, array &$errors): array
    {
        $languages = app(LanguageRegistry::class);
        $map = is_array($description) ? $description : (Value::string($description) === '' ? [] : [(string) $languages->default()->code => $description]);
        $clean = [];

        foreach ($map as $code => $text) {
            if ($languages->find((string) $code) === null) {
                $errors["{$key}.{$code}"] = Value::string(__('lynx::validation.language.missing', ['language' => $code]));
            } elseif (trim(Value::string($text)) !== '') {
                $clean[(string) $code] = mb_substr(trim(Value::string($text)), 0, 1000);
            }
        }

        return $clean;
    }
}
