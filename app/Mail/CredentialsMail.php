<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers freshly generated login credentials to a new user. The plaintext
 * password is carried transiently and never persisted — it lives only on this
 * mailable for the duration of the queued send.
 */
class CredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $role,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly string $password,
        public readonly string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.config('app.name').' login credentials',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.credentials',
        );
    }
}
