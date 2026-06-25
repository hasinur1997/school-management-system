<?php

namespace App\Jobs;

use App\Mail\CredentialsMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails freshly generated login credentials to a user. Generic by design: it
 * takes any user, a transient plaintext password (never stored), and a role
 * label, so students/parents can reuse it later (Task 3.5).
 */
class SendCredentials implements ShouldQueue
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
        public readonly string $password,
        public readonly string $role = 'Teacher',
    ) {}

    /**
     * Execute the job: mail the credentials to the user's email.
     *
     * Users without an email address receive credentials out-of-band (SMS, out
     * of scope), so there is nothing to email and the job is a no-op for them.
     */
    public function handle(): void
    {
        if ($this->user->email === null) {
            return;
        }

        if (! Hash::check($this->password, $this->user->password)) {
            return;
        }

        Mail::to($this->user->email)->send(new CredentialsMail(
            name: $this->user->name,
            role: $this->role,
            email: $this->user->email,
            phone: $this->user->phone,
            password: $this->password,
            loginUrl: rtrim((string) config('app.url'), '/').'/login',
        ));
    }

    /**
     * Log exhausted credential delivery failures with enough context to retry
     * manually without exposing the plaintext password.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Credential email failed', [
            'user_id' => $this->user->id,
            'role' => $this->role,
            'error' => $exception->getMessage(),
        ]);
    }
}
