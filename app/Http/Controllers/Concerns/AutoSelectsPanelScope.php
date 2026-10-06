<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Administration;
use App\Models\Entity;
use App\Support\PanelSelectionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait AutoSelectsPanelScope
{
    /**
     * Obligatorio al mostrar un paso 2+ del asistente sin pasar por store_entity
     * (p. ej. salto automático con una sola entidad en reservas/sets).
     */
    protected function putSelectedEntityInSession(Request $request, Entity $entity): void
    {
        $request->session()->put('selected_entity', $entity);
        $request->session()->put('selected_entity_id', $entity->id);
    }

    protected function putSelectedAdministrationInSession(Request $request, Administration $administration): void
    {
        $request->session()->put('selected_administration_id', (int) $administration->id);
        $request->session()->put('selected_administration', $administration);
    }

    protected function redirectIfImplicitEntity(
        Request $request,
        string $redirectRoute,
        array $routeParams = [],
        ?string $permission = null
    ): ?RedirectResponse {
        $entity = PanelSelectionResolver::resolveEntity($request->user(), $permission);
        if (! $entity) {
            return null;
        }

        $this->putSelectedEntityInSession($request, $entity);

        return redirect()->route($redirectRoute, $routeParams);
    }

    protected function redirectIfImplicitAdministration(
        Request $request,
        string $redirectRoute,
        array $routeParams = []
    ): ?RedirectResponse {
        $administration = PanelSelectionResolver::resolveAdministration($request->user());
        if (! $administration) {
            return null;
        }

        $this->putSelectedAdministrationInSession($request, $administration);

        return redirect()->route($redirectRoute, $routeParams);
    }

    protected function redirectIfImplicitEntityForDesign(Request $request): ?RedirectResponse
    {
        $entity = PanelSelectionResolver::resolveEntity($request->user(), 'design');
        if (! $entity) {
            return null;
        }

        $request->session()->put('design_entity_id', $entity->id);

        return redirect()->route('design.selectLottery', $entity->id);
    }

    protected function redirectConfigurationIfImplicitEntity(
        Request $request,
        string $section,
        int $targetStep,
        ?int $entityId,
        int $step
    ): ?RedirectResponse {
        $implicitId = PanelSelectionResolver::implicitEntityId($request->user(), 'payments');
        if (! $implicitId) {
            return null;
        }

        if ((int) $entityId === $implicitId && $step >= $targetStep) {
            return null;
        }

        return redirect()->route('configuration.index', [
            'section' => $section,
            'step' => $targetStep,
            'entity_id' => $implicitId,
        ]);
    }
}
