<?php

declare(strict_types=1);

namespace LynxCms\Media\Downloads;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Support\Value;
use Throwable;

/**
 * Downloads a file for media.store without reaching the server's own network: http and https on
 * their default ports, hosts that resolve only to public addresses (the connection is pinned to
 * the address checked, so DNS cannot change it in between), redirects followed by hand with the
 * same checks, and the transfer stopped past the field's size limit. lynx-media.downloads.allowed_hosts
 * lifts the address and port checks for hosts the site trusts, such as its own asset server.
 *
 * @internal
 */
final readonly class RemoteFile
{
    private const int MAX_REDIRECTS = 3;

    private const int TIMEOUT_SECONDS = 20;

    private const array DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    public function __construct(private HostResolver $resolver) {}

    /**
     * @return array{0: string, 1: string} the contents and the file name the last URL gives
     *
     * @throws ValidationException when the URL is refused, the download fails or is too large
     */
    public function download(string $url, int $maxBytes): array
    {
        if (! Value::bool(config('lynx-media.downloads.enabled', true), true)) {
            throw $this->refuse('Downloading files from URLs is turned off on this site.');
        }

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $response = $this->request($url, $maxBytes);
            $location = $response->header('Location');

            if (! $response->redirect() || $location === '') {
                return [$this->body($response, $url, $maxBytes), basename((string) parse_url($url, PHP_URL_PATH))];
            }

            $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));
        }

        throw $this->refuse("The file at [{$url}] redirects too many times.");
    }

    /**
     * Curl stops the transfer once more than $maxBytes arrive, whether or not the server said how
     * many it would send.
     */
    private function request(string $url, int $maxBytes): Response
    {
        $pin = $this->pin($url);
        $isTooLarge = false;
        $options = [
            'allow_redirects' => false,
            // A true answer makes Guzzle abort the transfer.
            'progress' => function (int $total, int $received) use ($maxBytes, &$isTooLarge): bool {
                return $isTooLarge = $total > $maxBytes || $received > $maxBytes;
            },
        ] + ($pin === null ? [] : ['curl' => [CURLOPT_RESOLVE => [$pin]]]);

        try {
            return Http::timeout(self::TIMEOUT_SECONDS)->withOptions($options)->get($url);
        } catch (Throwable $exception) {
            throw $isTooLarge ? $this->tooLarge() : $this->refuse("The file at [{$url}] could not be downloaded.", $exception);
        }
    }

    /**
     * Checks the URL and returns the curl "host:port:address" entry that pins its connection, or
     * null for a trusted host.
     */
    private function pin(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower(Value::string($parts['scheme'] ?? ''));
        $host = strtolower(trim(Value::string($parts['host'] ?? ''), '[]'));

        if (! isset(self::DEFAULT_PORTS[$scheme]) || $host === '') {
            throw $this->refuse("Only http and https URLs can be downloaded, not [{$url}].");
        }

        if (in_array($host, Value::strings(config('lynx-media.downloads.allowed_hosts', [])), true)) {
            return null;
        }

        $port = Value::int($parts['port'] ?? self::DEFAULT_PORTS[$scheme]);
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolver->addresses($host);

        if ($port !== self::DEFAULT_PORTS[$scheme] || $addresses === [] || ! array_all($addresses, PublicAddress::is(...))) {
            throw $this->refuse("The file at [{$url}] is not on a public web server, so it cannot be downloaded.");
        }

        $address = $addresses[0];

        return "{$host}:{$port}:".(str_contains($address, ':') ? "[{$address}]" : $address);
    }

    private function body(Response $response, string $url, int $maxBytes): string
    {
        if (! $response->successful()) {
            throw $this->refuse("The file at [{$url}] could not be downloaded (HTTP {$response->status()}).");
        }

        $contents = $response->body();

        if (Value::int($response->header('Content-Length')) > $maxBytes || strlen($contents) > $maxBytes) {
            throw $this->tooLarge();
        }

        return $contents;
    }

    private function tooLarge(): ValidationException
    {
        return ValidationException::withMessages(['file' => 'The file is larger than this field allows.']);
    }

    private function refuse(string $message, ?Throwable $cause = null): ValidationException
    {
        if ($cause !== null) {
            Log::info("Lynx could not download a media file: {$cause->getMessage()}");
        }

        return ValidationException::withMessages(['url' => $message]);
    }
}
