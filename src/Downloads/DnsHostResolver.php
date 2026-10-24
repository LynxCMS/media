<?php

declare(strict_types=1);

namespace LynxCms\Media\Downloads;

use LynxCms\Core\Support\Value;

/**
 * Resolves through the system's DNS: A records and AAAA records.
 *
 * @internal
 */
final class DnsHostResolver implements HostResolver
{
    public function addresses(string $host): array
    {
        $ipv4 = gethostbynamel($host);
        // A name without AAAA records makes dns_get_record warn; that is an answer, not an error.
        $ipv6 = @dns_get_record($host, DNS_AAAA);

        return [
            ...($ipv4 === false ? [] : $ipv4),
            ...array_map(fn (array $record): string => Value::string($record['ipv6'] ?? ''), $ipv6 === false ? [] : $ipv6),
        ];
    }
}
