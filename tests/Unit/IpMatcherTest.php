<?php

namespace Tests\Unit;

use App\Support\IpMatcher;
use PHPUnit\Framework\TestCase;

class IpMatcherTest extends TestCase
{
    public function test_exact_ipv4_match(): void
    {
        $this->assertTrue(IpMatcher::matches('192.168.1.10', '192.168.1.10'));
        $this->assertFalse(IpMatcher::matches('192.168.1.11', '192.168.1.10'));
    }

    public function test_exact_ipv6_match_normalizes_notation(): void
    {
        $this->assertTrue(IpMatcher::matches('::1', '0:0:0:0:0:0:0:1'));
        $this->assertTrue(IpMatcher::matches('2001:db8::1', '2001:0db8:0000:0000:0000:0000:0000:0001'));
        $this->assertFalse(IpMatcher::matches('2001:db8::2', '2001:db8::1'));
    }

    public function test_inside_cidr_match(): void
    {
        $this->assertTrue(IpMatcher::matches('103.4.5.17', '103.4.5.0/24'));
        $this->assertTrue(IpMatcher::matches('10.0.0.200', '10.0.0.0/16'));
    }

    public function test_outside_cidr_does_not_match(): void
    {
        $this->assertFalse(IpMatcher::matches('103.4.6.1', '103.4.5.0/24'));
        $this->assertFalse(IpMatcher::matches('11.0.0.1', '10.0.0.0/16'));
    }

    public function test_cidr_boundary_addresses(): void
    {
        // Network and broadcast addresses are inside the range.
        $this->assertTrue(IpMatcher::matches('103.4.5.0', '103.4.5.0/24'));
        $this->assertTrue(IpMatcher::matches('103.4.5.255', '103.4.5.0/24'));

        // One below the network and one above the broadcast are outside.
        $this->assertFalse(IpMatcher::matches('103.4.4.255', '103.4.5.0/24'));
        $this->assertFalse(IpMatcher::matches('103.4.6.0', '103.4.5.0/24'));
    }

    public function test_slash_32_matches_only_the_exact_host(): void
    {
        $this->assertTrue(IpMatcher::matches('192.168.1.10', '192.168.1.10/32'));
        $this->assertFalse(IpMatcher::matches('192.168.1.11', '192.168.1.10/32'));
    }

    public function test_slash_zero_matches_every_ipv4(): void
    {
        $this->assertTrue(IpMatcher::matches('8.8.8.8', '0.0.0.0/0'));
    }

    public function test_ipv6_cidr_never_matches(): void
    {
        $this->assertFalse(IpMatcher::matches('2001:db8::1', '2001:db8::/32'));
    }

    public function test_matches_any_across_a_list(): void
    {
        $patterns = ['10.0.0.1', '103.4.5.0/24'];

        $this->assertTrue(IpMatcher::matchesAny('103.4.5.9', $patterns));
        $this->assertTrue(IpMatcher::matchesAny('10.0.0.1', $patterns));
        $this->assertFalse(IpMatcher::matchesAny('172.16.0.1', $patterns));
        $this->assertFalse(IpMatcher::matchesAny('10.0.0.1', []));
    }
}
