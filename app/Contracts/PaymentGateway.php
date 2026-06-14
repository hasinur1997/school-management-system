<?php

namespace App\Contracts;

use App\Models\Payment;
use App\Support\Payments\GatewaySession;

/**
 * The payment gateway boundary. The concrete SslCommerzGateway talks to
 * SSLCommerz over HTTP; tests bind a FakeGateway so no network is touched.
 * createSession opens a hosted checkout for a pending payment; validate verifies
 * a callback's transaction against the gateway (used by the IPN handler, 10.5).
 */
interface PaymentGateway
{
    /**
     * Open a hosted checkout session for the given pending payment and return
     * the gateway URL the client is redirected to.
     *
     * @throws \Throwable when the gateway is unreachable or rejects the request
     */
    public function createSession(Payment $payment): GatewaySession;

    /**
     * Verify a callback transaction against the gateway's validation API.
     *
     * @param  array<string, mixed>  $payload  the raw callback payload
     */
    public function validate(string $tranId, array $payload): bool;
}
