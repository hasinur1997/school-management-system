<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a value is either an exact IP address (IPv4/IPv6) or a CIDR
 * range such as 103.4.5.0/24 (or 2001:db8::/32). The CIDR prefix length must be
 * within the address family's bounds (0-32 for IPv4, 0-128 for IPv6).
 */
class IpOrCidr implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->isValid($value)) {
            $fail('The :attribute must be a valid IP address or CIDR range.');
        }
    }

    /**
     * Determine whether the value is a valid IP or CIDR notation.
     */
    private function isValid(string $value): bool
    {
        if (! str_contains($value, '/')) {
            return filter_var($value, FILTER_VALIDATE_IP) !== false;
        }

        [$ip, $prefix] = explode('/', $value, 2);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $max = 32;
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $max = 128;
        } else {
            return false;
        }

        if ($prefix === '' || ! ctype_digit($prefix)) {
            return false;
        }

        return (int) $prefix >= 0 && (int) $prefix <= $max;
    }
}
