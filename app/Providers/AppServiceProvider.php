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
use Illuminate\Support\Facades\Gate;
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
