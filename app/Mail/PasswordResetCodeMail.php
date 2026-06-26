<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers a one-time password-reset code to a user's email. The code is
 * carried transiently and never persisted in plaintext — only its hash lives
 * in the database for the duration of the reset window.
 */
class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.config('app.name').' password reset code',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.password-reset-code',
        );
    }
}
