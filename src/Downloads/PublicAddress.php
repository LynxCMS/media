<?php

declare(strict_types=1);

namespace LynxCms\Media\Downloads;

/**
 * Whether an IP address is on the public internet: not private, loopback, link-local, reserved or
 * multicast, and not an IPv6 form that carries an IPv4 address (NAT64, 6to4) which could be one.
 *
 * @internal
 */
final class PublicAddress
{
    /** IPv6 prefixes PHP's global range check lets through: multicast, NAT64 and 6to4. */
    private const array REFUSED_IPV6_PREFIXES = [['ff00::', 8], ['64:ff9b::', 96], ['64:ff9b:1::', 48], ['2002::', 16]];

    /** IPv4 multicast, 224.0.0.0/4, which PHP's global range check lets through. */
    private const string IPV4_MULTICAST = '224.0.0.0';

    public static function is(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }

        $refused = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            ? [[self::IPV4_MULTICAST, 4]]
            : self::REFUSED_IPV6_PREFIXES;

        return ! array_any($refused, fn (array $prefix): bool => self::inPrefix($ip, $prefix[0], $prefix[1]));
    }

    private static function inPrefix(string $ip, string $network, int $bits): bool
    {
        $address = (string) inet_pton($ip);
        $base = (string) inet_pton($network);
        $bytes = intdiv($bits, 8);
        $mask = $bits % 8 === 0 ? 0 : (0xFF << (8 - $bits % 8)) & 0xFF;

        return strncmp($address, $base, $bytes) === 0
            && ($mask === 0 || (ord($address[$bytes]) & $mask) === (ord($base[$bytes]) & $mask));
    }
}
