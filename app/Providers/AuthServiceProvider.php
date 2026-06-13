<?php

namespace App\Providers;

use App\Models\Student;
use App\Models\User;
use App\Policies\StudentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::policy(Student::class, StudentPolicy::class);

        Gate::before(function (User $user, string $ability): ?bool {
            return $user->isSuperAdmin() ? true : null;
        });
    }
}
