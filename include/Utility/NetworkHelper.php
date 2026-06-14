<?php
declare(strict_types=1);

/**
 * Network utility functions.
 */
class NetworkHelper
{
    /**
     * Normalize a netmask.
     */
    public static function normalizeNetmask(string $netmask): string
    {
        return \normalizeNetmask($netmask);
    }

    /**
     * Convert netmask to bits.
     */
    public static function netmaskToBits(string $netmask): int
    {
        return \netmaskToBits($netmask);
    }

    /**
     * Check if an IP is in a network.
     */
    public static function isIpInNet(string $ip, string $net, string $mask): bool
    {
        return \isIpInNet($ip, $net, $mask);
    }

    /**
     * Expand an IPv6 address.
     */
    public static function expandIPv6(string $ip): string
    {
        return \expandIPv6($ip);
    }
}
