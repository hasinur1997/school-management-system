<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Support\Payments\GatewaySession;
use RuntimeException;

/**
 * In-memory payment gateway for tests: no network. Returns a canned gateway URL
 * (or throws on demand to exercise the 502 path) and treats every transaction as
 * valid unless configured otherwise.
 */
class FakeGateway implements PaymentGateway
{
    private string $gatewayUrl = 'https://sandbox.sslcommerz.com/checkout/fake-session';

    private bool $shouldFail = false;

    private bool $valid = true;

    /**
     * Make the next createSession call throw, simulating an unreachable gateway.
     */
    public function failNext(): self
    {
        $this->shouldFail = true;

        return $this;
    }

    /**
     * Override the gateway URL returned by createSession.
     */
    public function returning(string $gatewayUrl): self
    {
        $this->gatewayUrl = $gatewayUrl;

        return $this;
    }

    /**
     * Set the result validate() reports.
     */
    public function validates(bool $valid): self
    {
        $this->valid = $valid;

        return $this;
    }

    public function createSession(Payment $payment): GatewaySession
    {
        if ($this->shouldFail) {
            throw new RuntimeException('Fake gateway failure.');
        }

        return new GatewaySession($this->gatewayUrl);
    }

    public function validate(string $tranId, array $payload): bool
    {
        return $this->valid;
    }
}
