<?php

namespace App\Providers;

use App\Contracts\IdentityProvider;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Policies\DocumentPolicy;
use App\Policies\DocumentTypePolicy;
use App\Policies\PermissionPolicy;
use App\Policies\QuotationPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Services\Identity\JbluSsoIdentityProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(IdentityProvider::class, JbluSsoIdentityProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('sso-login', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('sso-callback', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('office-preview', fn (Request $request): Limit => Limit::perMinute(30)->by((string) ($request->user()?->getKey() ?? $request->ip())));
        RateLimiter::for('office-mutation', fn (Request $request): Limit => Limit::perMinute(30)->by((string) ($request->user()?->getKey() ?? $request->ip())));

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(DocumentType::class, DocumentTypePolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Quotation::class, QuotationPolicy::class);

        foreach (array_merge(...array_values(config('authorization.permissions'))) as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermissionTo($permission));
        }
    }
}
