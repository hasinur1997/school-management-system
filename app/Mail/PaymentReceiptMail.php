<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirms a settled online (SSLCommerz) payment to the payer: the receipt
 * number, amount and the invoice it cleared. Sent from the queued
 * SendPaymentReceipt job after the IPN settles the payment.
 */
class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $receiptNo,
        public readonly string $amount,
        public readonly string $invoiceNo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment received — '.$this->receiptNo,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.payment-receipt',
        );
    }
}
