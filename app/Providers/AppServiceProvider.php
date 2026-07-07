<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use App\Models\User;
use App\Services\LotteryDeadlineReminderService;
use App\Services\ManagementFeeService;
use App\Models\Participation;
use App\Observers\UserObserver;
use App\Observers\ParticipationObserver;
use App\View\Composers\FlashNotifyComposer;

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
        // Registrar Observer para vinculación automática de vendedores
        User::observe(UserObserver::class);
        
        // Registrar Observer para auditoría de participaciones
        Participation::observe(ParticipationObserver::class);

        // Modo debug de correo: forzar TODOS los envíos a un email de pruebas.
        if (config('mail.debug_mode') && filled(config('mail.debug_to'))) {
            Mail::alwaysTo(config('mail.debug_to'));
        }

        View::composer('layouts.layout', FlashNotifyComposer::class);

        View::composer('layouts.layout', function ($view) {
            $user = auth()->user();
            if (! $user) {
                $view->with('lotteryDeadlineModalAlerts', []);
                $view->with('lotteryDeadlineAdminDecisionAlerts', []);
                $view->with('entityManagementFeeModalAlert', null);

                return;
            }

            $reminderService = app(LotteryDeadlineReminderService::class);

            $view->with(
                'lotteryDeadlineModalAlerts',
                $reminderService->getModalAlertsForUser($user)
            );
            $view->with(
                'lotteryDeadlineAdminDecisionAlerts',
                $reminderService->getAdminDecisionModalsForUser($user)
            );
            $view->with(
                'entityManagementFeeModalAlert',
                $this->shouldSuppressEntityManagementFeeModal()
                    ? null
                    : app(ManagementFeeService::class)->getEntityManagementFeeModalAlert($user)
            );
        });
    }

    private function shouldSuppressEntityManagementFeeModal(): bool
    {
        return request()->routeIs([
            'design.managementFee.pay',
            'design.managementFee.paymentIntent',
            'design.managementFee.confirmStripe',
            'design.managementFee.confirmRemittance',
        ]);
    }
}
