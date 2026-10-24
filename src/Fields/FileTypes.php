<?php

declare(strict_types=1);

namespace LynxCms\Media\Fields;

use Closure;
use LynxCms\Core\Support\Value;

/**
 * File types a media field may never allow, whatever its extensions setting says: files a web
 * server may run (php, cgi…) and files a browser runs as part of the site (html, svg, js…). Media
 * files are served from the site's own address, so either would let an editor's upload run code.
 *
 * @internal
 */
final class FileTypes
{
    public const array NEVER = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar',
        'cgi', 'pl', 'py', 'rb', 'sh', 'asp', 'aspx', 'jsp', 'htaccess', 'htpasswd',
        'html', 'htm', 'xhtml', 'shtml', 'xht', 'svg', 'svgz', 'xml', 'xsl', 'js', 'mjs',
        'swf', 'hta',
    ];

    /** MIME types a browser may show in the page instead of downloading. */
    private const array INLINE_PREFIXES = ['image/', 'video/', 'audio/'];

    private const array INLINE_TYPES = ['application/pdf', 'text/plain'];

    /**
     * @param  list<string>  $extensions
     * @return list<string> the ones that may be allowed
     */
    public static function allowed(array $extensions): array
    {
        return array_values(array_diff($extensions, self::NEVER));
    }

    /** A validation rule for the extensions setting. */
    public static function rule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $refused = array_intersect(explode(' ', Value::string($value)), self::NEVER);

            if ($refused !== []) {
                $fail('Files of type ['.implode(', ', $refused).'] can run code on your site, so a field can never allow them.');
            }
        };
    }

    /** Whether a private file of this type may open in the browser; others are downloaded. */
    public static function opensInBrowser(string $mime): bool
    {
        $mime = strtolower($mime);

        return ! str_contains($mime, 'svg')
            && (in_array($mime, self::INLINE_TYPES, true) || array_any(self::INLINE_PREFIXES, fn (string $prefix): bool => str_starts_with($mime, $prefix)));
    }
}
