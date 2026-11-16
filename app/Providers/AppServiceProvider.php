<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('access-admin', fn (User $user): bool => $user->role === 'admin');
        Gate::define('book-appointments', fn (User $user): bool => $user->role === 'client');
        Gate::define('cancel-appointment', fn (User $user, Appointment $appointment): bool => $user->role === 'admin' || ($user->role === 'client' && $appointment->client_id === $user->id)
        );
        Gate::define('manage-appointment', fn (User $user, Appointment $appointment): bool => $user->role === 'photographer' && $appointment->photographer_id === $user->id
        );
    }
}
