<?php

namespace App\Providers;

use App\Models\SchoolClass;
use App\Models\User;
use App\Policies\SchoolClassPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Gate::policy(SchoolClass::class, SchoolClassPolicy::class);

        Gate::before(function (User $user, string $ability): ?bool {
            return $user->isAdmin() ? true : null;
        });
    }
}
