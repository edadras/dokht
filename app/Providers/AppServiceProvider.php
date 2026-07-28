<?php

namespace App\Providers;

use App\Support\WorkshopContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WorkshopContext::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(false);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // رمز ساده اما امن: هشت نویسه کافی است تا کاربر عادی اذیت نشود
        Password::defaults(fn () => Password::min(8));

        Gate::define('administer-workshop', fn ($user) => $user->canAdministerWorkshop());
        Gate::define('administer-system', fn ($user) => (bool) $user->is_admin);
        Gate::define('design', fn ($user) => $user->canDesign());
        Gate::define('manage', fn ($user) => $user->canManage());
    }
}
