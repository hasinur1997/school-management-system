<?php

namespace App\Jobs;

use App\Contracts\SmsGateway;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Texts a one-time password-reset code to a user's phone via the SMS gateway.
 * Complements the email channel so phone-only accounts can still reset. The
 * plaintext code is passed transiently and never stored.
 */
class SendPasswordResetCodeSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The seconds to wait before retrying, escalating per attempt.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly User $user,
        public readonly string $code,
        public readonly int $ttlMinutes,
    ) {}

    /**
     * Execute the job: send the reset code over SMS. A failed gateway send
     * throws so the job retries; an exhausted job is logged in failed().
     */
    public function handle(SmsGateway $sms): void
    {
        if ($this->user->phone === null) {
            return;
        }

        $message = sprintf(
            'Your %s password reset code is %s. It expires in %d minutes.',
            config('app.name'),
            $this->code,
            $this->ttlMinutes,
        );

        if (! $sms->send($this->user->phone, $message)) {
            throw new \RuntimeException('SMS gateway refused the password reset code.');
        }
    }

    /**
     * Log exhausted delivery failures without exposing the plaintext code.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Password reset code SMS failed', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
