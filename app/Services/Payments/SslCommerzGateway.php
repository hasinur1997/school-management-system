<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Support\Payments\GatewaySession;
use App\Support\SettingsRepository;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SSLCommerz implementation of the payment gateway. Credentials (store id /
 * password / sandbox flag) come from the SettingsRepository (config-backed until
 * the settings table lands in 14.1). Real sandbox traffic is verified manually
 * (out of scope for 10.4) — automated tests run against the FakeGateway.
 */
class SslCommerzGateway implements PaymentGateway
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * Open a hosted checkout session and return its gateway URL. Throws when the
     * gateway is unreachable or refuses the session, which the caller maps to a
     * 502 and marks the payment failed.
     */
    public function createSession(Payment $payment): GatewaySession
    {
        $response = Http::asForm()
            ->post($this->endpoint('gwprocess/v4/api.php'), [
                'store_id' => $this->settings->sslcommerzStoreId(),
                'store_passwd' => $this->settings->sslcommerzStorePassword(),
                'total_amount' => $payment->amount,
                'currency' => 'BDT',
                'tran_id' => $payment->transaction_id,
                'success_url' => url('/api/v1/payments/sslcommerz/success'),
                'fail_url' => url('/api/v1/payments/sslcommerz/fail'),
                'cancel_url' => url('/api/v1/payments/sslcommerz/cancel'),
                'ipn_url' => url('/api/v1/payments/sslcommerz/ipn'),
                'cus_name' => 'Fee Payment',
                'product_name' => 'School Fee',
                'product_category' => 'Fee',
                'product_profile' => 'non-physical-goods',
            ]);

        $body = $response->json();

        if ($response->failed() || ($body['status'] ?? null) !== 'SUCCESS' || empty($body['GatewayPageURL'])) {
            throw new RuntimeException('SSLCommerz session creation failed.');
        }

        return new GatewaySession($body['GatewayPageURL']);
    }

    /**
     * Verify a callback transaction against the SSLCommerz validation API.
     * Consumed by the IPN handler (10.5).
     */
    public function validate(string $tranId, array $payload): bool
    {
        $response = Http::get($this->endpoint('validator/api/validationserverAPI.php'), [
            'val_id' => $payload['val_id'] ?? null,
            'store_id' => $this->settings->sslcommerzStoreId(),
            'store_passwd' => $this->settings->sslcommerzStorePassword(),
            'format' => 'json',
        ]);

        if ($response->failed()) {
            return false;
        }

        $body = $response->json();

        return in_array($body['status'] ?? null, ['VALID', 'VALIDATED'], true)
            && ($body['tran_id'] ?? null) === $tranId;
    }

    /**
     * Build the base API URL for the sandbox or live host.
     */
    private function endpoint(string $path): string
    {
        $host = $this->settings->sslcommerzSandbox()
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';

        return "{$host}/{$path}";
    }
}
