<?php

namespace App\Jobs;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails a one-time password-reset code to a user. The plaintext code is passed
 * transiently (never stored) so the queued send can render it. Users without an
 * email address receive the code by SMS instead (see SendPasswordResetCodeSms).
 */
class SendPasswordResetCode implements ShouldQueue
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
     * Execute the job: mail the reset code to the user's email. A no-op for
     * users without an email — they are reached over SMS instead.
     */
    public function handle(): void
    {
        if ($this->user->email === null) {
            return;
        }

        Mail::to($this->user->email)->send(new PasswordResetCodeMail(
            name: $this->user->name,
            code: $this->code,
            ttlMinutes: $this->ttlMinutes,
        ));
    }

    /**
     * Log exhausted delivery failures without exposing the plaintext code.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Password reset code email failed', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
