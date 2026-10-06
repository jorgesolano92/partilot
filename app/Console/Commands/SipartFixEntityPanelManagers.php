<?php

namespace App\Console\Commands;

use App\Models\Entity;
use App\Models\Manager;
use App\Models\User;
use App\Services\EntityPanelAccessService;
use Illuminate\Console\Command;

class SipartFixEntityPanelManagers extends Command
{
    protected $signature = 'sipart:fix-entity-panel-managers
                            {--apply : Guardar los cambios (sin esta opción solo lista)}';

    protected $description = 'Convierte en gestor normal las cuentas panel de entidad (solo consulta) que son también el gestor responsable de esa entidad';

    public function handle(EntityPanelAccessService $panelAccess): int
    {
        $apply = (bool) $this->option('apply');
        if (! $apply) {
            $this->warn('Simulación: no se guardará nada. Usa --apply para aplicar los cambios.');
        }

        $rows = [];
        $pendingAcceptance = [];

        $panelUsers = User::query()
            ->where('panel_account_type', 'entity')
            ->whereNotNull('panel_account_id')
            ->get();

        foreach ($panelUsers as $user) {
            $entity = Entity::find((int) $user->panel_account_id);
            if (! $entity || ! $panelAccess->isPrimaryManagerOf($entity, $user)) {
                continue;
            }

            $manager = Manager::query()
                ->where('entity_id', $entity->id)
                ->where('user_id', $user->id)
                ->first();
            $status = $manager?->status === null ? 'pendiente de aceptar' : (string) $manager->status;

            if ($apply) {
                $panelAccess->releasePanelAccountIfPrimaryManager($entity, $user);
            }

            if ($manager && (int) $manager->status !== Manager::STATUS_ACTIVE) {
                $pendingAcceptance[] = $entity->name.' ('.$user->email.')';
            }

            $rows[] = [$entity->id, $entity->name, $user->id, $user->email, $status];
        }

        if ($rows === []) {
            $this->info('No hay cuentas panel de entidad que sean a la vez gestor responsable.');

            return self::SUCCESS;
        }

        $this->table(['Entidad', 'Nombre', 'Usuario', 'Email', 'Estado gestor'], $rows);
        $this->info(($apply ? 'Corregidas: ' : 'Se corregirían: ').count($rows));

        if ($pendingAcceptance !== []) {
            $this->warn('Estos gestores aún no han aceptado el cargo; reenvía la invitación desde la ficha de la entidad:');
            foreach ($pendingAcceptance as $line) {
                $this->line(' - '.$line);
            }
        }

        return self::SUCCESS;
    }
}
