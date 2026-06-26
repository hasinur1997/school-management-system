<?php

namespace App\Contracts;

interface SmsGateway
{
    /**
     * Send a text message to a Bangladeshi mobile number. Returns true when the
     * gateway accepted the message for delivery. Implementations must not throw
     * on a refused send — return false so callers can decide how to react.
     */
    public function send(string $phone, string $message): bool;
}
