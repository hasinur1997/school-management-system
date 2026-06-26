<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use App\Support\SettingsRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BulkSMSBD (bulksmsbd.net) implementation of the SMS gateway. Credentials
 * (api key + sender id) come from the SettingsRepository, config-backed as a
 * fallback. The HTTP "one-to-one" endpoint takes api_key, senderid, number and
 * message; a JSON body with response_code 202 means the message was accepted.
 */
class BulkSmsBdGateway implements SmsGateway
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function send(string $phone, string $message): bool
    {
        $apiKey = $this->settings->smsApiKey();
        $senderId = $this->settings->smsSenderId();

        if ($apiKey === null || $senderId === null) {
            Log::warning('SMS not sent: BulkSMSBD credentials are not configured.');

            return false;
        }

        $response = Http::asForm()->post(config('services.bulksmsbd.url'), [
            'api_key' => $apiKey,
            'senderid' => $senderId,
            'number' => $this->normalize($phone),
            'message' => $message,
        ]);

        if ($response->failed() || (int) ($response->json('response_code')) !== 202) {
            Log::warning('BulkSMSBD rejected an SMS', [
                'response_code' => $response->json('response_code'),
                'error_message' => $response->json('error_message'),
            ]);

            return false;
        }

        return true;
    }

    /**
     * BulkSMSBD expects numbers in 8801XXXXXXXXX form. Strip separators and fold
     * a local "01…" or "+880…" number to the bare "880…" country form.
     */
    private function normalize(string $phone): string
    {
        $digits = preg_replace('/[\s\-()+]/', '', $phone);

        if (str_starts_with($digits, '880')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '880'.substr($digits, 1);
        }

        return $digits;
    }
}
