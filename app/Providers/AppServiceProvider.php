<?php

namespace App\Providers;

use App\Support\PanelUrl;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use App\Models\User;
use App\Services\LotteryDeadlineReminderService;
use App\Services\ManagementFeeService;
use App\Services\PanelLegalAcceptanceService;
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
        if ($this->app->runningInConsole()) {
            URL::forceRootUrl(PanelUrl::root());
        }

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
                $view->with('panelLegalAcceptanceModal', null);

                return;
            }

            $reminderService = app(LotteryDeadlineReminderService::class);

            $panelLegalService = app(PanelLegalAcceptanceService::class);
            $panelLegalModal = $panelLegalService->userMustAcceptBeforeAccess($user)
                ? $panelLegalService->buildViewData($user)
                : null;

            $view->with(
                'entityManagementFeeModalAlert',
                $panelLegalModal || $this->shouldSuppressEntityManagementFeeModal()
                    ? null
                    : app(ManagementFeeService::class)->getEntityManagementFeeModalAlert($user)
            );
            $view->with('panelLegalAcceptanceModal', $panelLegalModal);
            $view->with(
                'lotteryDeadlineModalAlerts',
                $panelLegalModal ? [] : $reminderService->getModalAlertsForUser($user)
            );
            $view->with(
                'lotteryDeadlineAdminDecisionAlerts',
                $panelLegalModal ? [] : $reminderService->getAdminDecisionModalsForUser($user)
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
