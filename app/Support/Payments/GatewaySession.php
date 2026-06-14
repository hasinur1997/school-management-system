<?php

namespace App\Support\Payments;

/**
 * The result of opening a checkout session with a payment gateway: the hosted
 * payment page URL the client is redirected to.
 */
final readonly class GatewaySession
{
    public function __construct(public string $gatewayUrl) {}
}
