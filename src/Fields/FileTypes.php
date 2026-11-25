<?php

declare(strict_types=1);

namespace LynxCms\Media\Fields;

use Closure;
use LynxCms\Core\Support\Value;
use Symfony\Component\Mime\MimeTypes;

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
        'swf', 'hta', 'xslt', 'xhtm', 'shtm', 'stm', 'mht', 'mhtml', 'jspx', 'asa', 'ashx', 'asmx', 'cfm',
        'rss', 'atom', 'rdf', 'gpx', 'kml', 'mml',
    ];

    /** MIME types browsers run as documents or scripts: HTML, every XML type, JavaScript. */
    private const string RUNS_IN_BROWSER = '/^(?:text\/html|text\/xml|application\/xml|[^\/]+\/[^\/]*\+xml|[^\/]+\/(?:x-)?(?:java|ecma)script)$/D';

    /** MIME types a browser may show in the page instead of downloading. */
    private const array INLINE_PREFIXES = ['image/', 'video/', 'audio/'];

    private const array INLINE_TYPES = ['application/pdf', 'text/plain'];

    /**
     * @param  list<string>  $extensions
     * @return list<string> the ones that may be allowed
     */
    public static function allowed(array $extensions): array
    {
        return array_values(array_filter($extensions, fn (string $extension): bool => ! self::isRefused($extension)));
    }

    /**
     * Never allowed: an extension on the list, one with no known type (a web server then sends
     * none, and browsers guess from the content), or one whose type a browser runs. Media files
     * are served from the site's own address.
     */
    public static function isRefused(string $extension): bool
    {
        $types = MimeTypes::getDefault()->getMimeTypes($extension);

        return in_array($extension, self::NEVER, true)
            || $types === []
            || array_any($types, fn (string $type): bool => preg_match(self::RUNS_IN_BROWSER, $type) === 1);
    }

    /** A validation rule for the extensions setting. */
    public static function rule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $refused = array_filter(array_filter(explode(' ', Value::string($value))), self::isRefused(...));

            if ($refused !== []) {
                $fail(Value::string(__('lynx-media::validation.never', ['types' => implode(', ', $refused)])));
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
