<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\PrintOrder;
use App\Models\User;
use App\Policies\PrintOrderPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        PrintOrder::class => PrintOrderPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::define('export-design-pdf', function (User $user, \App\Models\DesignFormat $design): bool {
            return $user->canExportDesignPdf($design);
        });
    }
}
