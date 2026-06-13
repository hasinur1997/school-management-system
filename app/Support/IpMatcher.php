<?php

namespace App\Support;

/**
 * Matches a request IP against whitelist patterns. A pattern is either an exact
 * address (IPv4 or IPv6, compared on its normalized binary form) or an IPv4
 * CIDR range such as 103.4.5.0/24 — the IP is in range when its network bits
 * equal the subnet's. IPv6 is supported for exact matches only; an IPv6 CIDR
 * never matches.
 */
class IpMatcher
{
    /**
     * Whether the IP matches any of the given patterns.
     *
     * @param  iterable<int, string>  $patterns
     */
    public static function matchesAny(string $ip, iterable $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (self::matches($ip, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the IP matches a single exact-address or IPv4-CIDR pattern.
     */
    public static function matches(string $ip, string $pattern): bool
    {
        if (! str_contains($pattern, '/')) {
            return self::exactMatch($ip, $pattern);
        }

        return self::cidrMatch($ip, $pattern);
    }

    /**
     * Exact match on the normalized binary form, so equivalent IPv6 notations
     * (e.g. ::1 and 0:0:0:0:0:0:0:1) compare equal.
     */
    private static function exactMatch(string $ip, string $pattern): bool
    {
        $a = @inet_pton($ip);
        $b = @inet_pton($pattern);

        return $a !== false && $a === $b;
    }

    /**
     * IPv4 CIDR containment via bitmask on the 32-bit integer forms. Returns
     * false for any IPv6 operand (IPv6 CIDR is unsupported).
     */
    private static function cidrMatch(string $ip, string $pattern): bool
    {
        [$subnet, $prefix] = explode('/', $pattern, 2);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $bits = (int) $prefix;

        if ($bits <= 0) {
            return true;
        }

        $mask = (-1 << (32 - $bits)) & 0xFFFFFFFF;

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
