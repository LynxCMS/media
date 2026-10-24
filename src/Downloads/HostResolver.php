<?php

declare(strict_types=1);

namespace LynxCms\Media\Downloads;

/**
 * The IP addresses a host name points to, so a download can check them before connecting.
 *
 * @internal
 */
interface HostResolver
{
    /**
     * @return list<string> IPv4 and IPv6 addresses; empty when the name does not resolve
     */
    public function addresses(string $host): array;
}
