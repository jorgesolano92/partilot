<?php

namespace App\Services;

use App\Mail\ParticipationAssignmentAcceptedEntityMail;
use App\Mail\ParticipationAssignmentMail;
use App\Mail\ParticipationAssignmentProposalMail;
use App\Models\EmailCommunicationLog;
use App\Models\Entity;
use App\Models\LegalAcceptance;
use App\Models\Participation;
use App\Models\ParticipationAssignmentProposal;
use App\Models\Seller;
use App\Models\Set;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ParticipationAssignmentReceiptService
{
    public const TOKEN_TTL_DAYS = 7;

    public function __construct(
        private readonly CommunicationEmailService $communicationEmailService,
        private readonly LegalAcceptanceService $legalAcceptance,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $participationPayloads
     * @return array{physical: array<int, array<string, mixed>>, digital: array<int, array<string, mixed>>}
     */
    public function splitPayloadsByReceiptRequirement(array $participationPayloads): array
    {
        $setIds = array_values(array_unique(array_filter(array_map(
            fn ($p) => (int) ($p['set_id'] ?? 0),
            $participationPayloads
        ))));

        $sets = $setIds === []
            ? collect()
            : Set::query()->whereIn('id', $setIds)->get()->keyBy('id');

        $physical = [];
        $digital = [];

        foreach ($participationPayloads as $payload) {
            $setId = (int) ($payload['set_id'] ?? 0);
            $set = $sets->get($setId);
            if ($set && $this->setRequiresReceipt($set)) {
                $physical[] = $payload;
            } else {
                $digital[] = $payload;
            }
        }

        return ['physical' => $physical, 'digital' => $digital];
    }

    /**
     * @param  array<int, array<string, mixed>>  $participationPayloads
     * @return array{
     *   proposal: ?ParticipationAssignmentProposal,
     *   proposal_count: int,
     *   assigned_count: int,
     *   assigned_participation_ids: array<int, int>
     * }
     */
    public function processAssignmentBatch(Seller $seller, array $participationPayloads, ?User $createdBy = null): array
    {
        $split = $this->splitPayloadsByReceiptRequirement($participationPayloads);

        $result = [
            'proposal' => null,
            'proposal_count' => 0,
            'assigned_count' => 0,
            'assigned_participation_ids' => [],
        ];

        if ($split['physical'] !== []) {
            $proposal = $this->createProposal($seller, $split['physical'], $createdBy);
            $result['proposal'] = $proposal;
            $result['proposal_count'] = (int) $proposal->participation_count;
        }

        if ($split['digital'] !== []) {
            $immediate = $this->assignImmediately($seller, $split['digital']);
            $result['assigned_count'] = (int) $immediate['assigned_count'];
            $result['assigned_participation_ids'] = $immediate['assigned_participation_ids'];

            if ($result['assigned_count'] > 0) {
                $this->sendAssignmentConfirmationEmail($seller, $result['assigned_participation_ids']);

                try {
                    app(AppInboxNotificationService::class)->notifyParticipationAssigned(
                        $seller,
                        $result['assigned_count']
                    );
                } catch (\Throwable $e) {
                    Log::warning('Inbox notify digital assignment: '.$e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $participationPayloads
     */
    public function requiresReceiptFlow(array $participationPayloads): bool
    {
        if ($participationPayloads === []) {
            return false;
        }

        $setIds = array_values(array_unique(array_filter(array_map(
            fn ($p) => (int) ($p['set_id'] ?? 0),
            $participationPayloads
        ))));

        if ($setIds === []) {
            return false;
        }

        $sets = Set::query()->whereIn('id', $setIds)->get(['id', 'digital_participations', 'physical_participations']);

        foreach ($sets as $set) {
            if ($this->setRequiresReceipt($set)) {
                return true;
            }
        }

        return false;
    }

    public function setRequiresReceipt(Set $set): bool
    {
        $digital = (int) ($set->digital_participations ?? 0);
        $physical = (int) ($set->physical_participations ?? 0);

        return ! ($digital > 0 && $physical === 0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $participationPayloads
     */
    public function createProposal(Seller $seller, array $participationPayloads, ?User $createdBy = null): ParticipationAssignmentProposal
    {
        $seller->loadMissing(['entities', 'user']);

        $ids = array_values(array_unique(array_filter(array_map(
            fn ($p) => (int) ($p['id'] ?? 0),
            $participationPayloads
        ))));

        if ($ids === []) {
            throw new \InvalidArgumentException('No hay participaciones válidas para proponer.');
        }

        $participations = Participation::with(['set.reserve.lottery', 'set.entity'])
            ->whereIn('id', $ids)
            ->get();

        if ($participations->isEmpty()) {
            throw new \InvalidArgumentException('No se encontraron las participaciones indicadas.');
        }

        foreach ($participations as $participation) {
            if (! $participation->set || ! $this->setRequiresReceipt($participation->set)) {
                continue;
            }

            if ($participation->status !== 'disponible' || $participation->seller_id !== null) {
                throw new \InvalidArgumentException(
                    'Una o más participaciones ya no están disponibles para asignar.'
                );
            }
        }

        $physicalIds = $participations
            ->filter(fn (Participation $p) => $p->set && $this->setRequiresReceipt($p->set))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($physicalIds === []) {
            throw new \InvalidArgumentException('No hay participaciones físicas en la propuesta.');
        }

        $entity = $seller->entities->first();
        $firstParticipation = $participations->first();
        $lottery = $firstParticipation?->set?->reserve?->lottery;

        $proposal = ParticipationAssignmentProposal::create([
            'seller_id' => $seller->id,
            'entity_id' => $entity?->id,
            'lottery_id' => $lottery?->id,
            'created_by' => $createdBy?->id,
            'participation_ids' => $physicalIds,
            'participation_count' => count($physicalIds),
            'token' => Str::random(64),
            'status' => ParticipationAssignmentProposal::STATUS_PENDING,
            'expires_at' => now()->addDays(self::TOKEN_TTL_DAYS),
        ]);

        $proposal->load(['seller.entities', 'entity', 'lottery']);

        $this->sendProposalEmail($proposal);

        return $proposal;
    }

    /**
     * @return array{type: string, title: string, message: string, proposal?: ParticipationAssignmentProposal}
     */
    public function acceptByToken(string $token, Request $request): array
    {
        $proposal = ParticipationAssignmentProposal::query()
            ->with(['seller.user', 'seller.entities', 'entity', 'lottery'])
            ->where('token', $token)
            ->first();

        if (! $proposal) {
            return [
                'type' => 'error',
                'title' => 'Enlace no válido',
                'message' => 'El enlace no es válido o ya ha sido utilizado.',
            ];
        }

        if ($proposal->status === ParticipationAssignmentProposal::STATUS_ACCEPTED) {
            return [
                'type' => 'success',
                'title' => 'Recibo ya aceptado',
                'message' => 'Esta propuesta de asignación ya fue aceptada anteriormente.',
                'proposal' => $proposal,
            ];
        }

        if ($proposal->status === ParticipationAssignmentProposal::STATUS_REJECTED) {
            return [
                'type' => 'error',
                'title' => 'Propuesta rechazada',
                'message' => 'Esta propuesta de asignación fue rechazada y ya no está disponible.',
            ];
        }

        if ($proposal->isExpired()) {
            if ($proposal->isPending()) {
                $proposal->update([
                    'status' => ParticipationAssignmentProposal::STATUS_EXPIRED,
                    'responded_at' => now(),
                ]);
            }

            return [
                'type' => 'error',
                'title' => 'Enlace caducado',
                'message' => 'El enlace ha caducado. Contacta con tu entidad para solicitar una nueva asignación.',
            ];
        }

        if (! $proposal->isPending()) {
            return [
                'type' => 'error',
                'title' => 'Enlace no disponible',
                'message' => 'Esta propuesta ya no está pendiente de aceptación.',
            ];
        }

        $seller = $proposal->seller;
        if (! $seller || (int) $seller->status !== Seller::STATUS_ACTIVE) {
            return [
                'type' => 'error',
                'title' => 'Vendedor no activo',
                'message' => 'No se puede completar la asignación porque el vendedor no está activo.',
            ];
        }

        $participationIds = array_map('intval', (array) $proposal->participation_ids);

        try {
            $assignedIds = DB::transaction(function () use ($proposal, $seller, $participationIds, $request) {
                $locked = Participation::query()
                    ->whereIn('id', $participationIds)
                    ->lockForUpdate()
                    ->get();

                if ($locked->count() !== count($participationIds)) {
                    throw new \RuntimeException('unavailable');
                }

                foreach ($locked as $participation) {
                    if ($participation->status !== 'disponible' || $participation->seller_id !== null) {
                        throw new \RuntimeException('unavailable');
                    }
                }

                $now = now();
                foreach ($locked as $participation) {
                    $participation->update([
                        'seller_id' => $seller->id,
                        'sale_date' => $now->toDateString(),
                        'sale_time' => $now->toTimeString(),
                        'status' => 'asignada',
                    ]);
                }

                $proposal->update([
                    'status' => ParticipationAssignmentProposal::STATUS_ACCEPTED,
                    'responded_at' => $now,
                ]);

                $user = $seller->user;
                $role = config('legal_roles.recibo_participaciones', []);
                $this->legalAcceptance->recordFromRequest(
                    action: LegalAcceptance::ACTION_ACEPTACION_RECIBO_PARTICIPACIONES,
                    request: $request,
                    user: $user,
                    version: (string) ($role['version'] ?? '1'),
                    textHash: (string) ($role['hash'] ?? 'recibo_participaciones_v1'),
                    entityId: $proposal->entity_id ? (int) $proposal->entity_id : null,
                    lotteryId: $proposal->lottery_id ? (int) $proposal->lottery_id : null,
                    administrationId: $proposal->entity?->administration_id ? (int) $proposal->entity->administration_id : null,
                    context: [
                        'seller_id' => $seller->id,
                        'proposal_id' => $proposal->id,
                        'participation_ids' => $participationIds,
                        'participation_count' => count($participationIds),
                    ],
                );

                return $locked->pluck('id')->map(fn ($id) => (int) $id)->all();
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'unavailable') {
                $proposal->delete();

                return [
                    'type' => 'error',
                    'title' => 'Participaciones no disponibles',
                    'message' => 'Una o más participaciones ya han sido asignadas a otro vendedor. Contacta con tu entidad si necesitas aclaración.',
                ];
            }

            throw $e;
        }

        $this->sendPostAcceptanceEmails($seller, $assignedIds, $proposal);

        try {
            app(AppInboxNotificationService::class)->notifyParticipationAssigned(
                $seller,
                count($assignedIds),
                $proposal->lottery?->name ? 'Sorteo: '.$proposal->lottery->name.'.' : null
            );
        } catch (\Throwable $e) {
            Log::warning('Inbox notify participation assigned after receipt: '.$e->getMessage());
        }

        return [
            'type' => 'success',
            'title' => 'Recibo aceptado',
            'message' => 'Has aceptado el recibo de '.count($assignedIds).' participación(es). Ya puedes gestionarlas desde tu panel de vendedor.',
            'proposal' => $proposal->fresh(),
        ];
    }

    /**
     * @return array{type: string, title: string, message: string}
     */
    public function rejectByToken(string $token): array
    {
        $proposal = ParticipationAssignmentProposal::query()
            ->where('token', $token)
            ->first();

        if (! $proposal) {
            return [
                'type' => 'error',
                'title' => 'Enlace no válido',
                'message' => 'El enlace no es válido o ya ha sido utilizado.',
            ];
        }

        if ($proposal->isExpired() && $proposal->isPending()) {
            $proposal->delete();

            return [
                'type' => 'error',
                'title' => 'Enlace caducado',
                'message' => 'El enlace ha caducado.',
            ];
        }

        if ($proposal->status === ParticipationAssignmentProposal::STATUS_ACCEPTED) {
            return [
                'type' => 'error',
                'title' => 'No se puede rechazar',
                'message' => 'Esta asignación ya fue aceptada y no puede rechazarse.',
            ];
        }

        if ($proposal->status === ParticipationAssignmentProposal::STATUS_REJECTED) {
            return [
                'type' => 'success',
                'title' => 'Ya rechazada',
                'message' => 'Esta propuesta de asignación ya había sido rechazada.',
            ];
        }

        $proposal->delete();

        return [
            'type' => 'success',
            'title' => 'Asignación rechazada',
            'message' => 'Has rechazado la propuesta de asignación. Las participaciones siguen disponibles para la entidad.',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $participationPayloads
     * @return array{assigned_count: int, assigned_participation_ids: array<int, int>}
     */
    public function assignImmediately(Seller $seller, array $participationPayloads): array
    {
        $ids = array_column($participationPayloads, 'id');
        $setIds = array_unique(array_column($participationPayloads, 'set_id'));

        $participationsToUpdate = Participation::with(['set.reserve.lottery'])
            ->whereIn('id', $ids)
            ->whereIn('set_id', $setIds)
            ->where(function ($query) use ($seller) {
                $query->where(function ($q) {
                    $q->where('status', 'disponible')->whereNull('seller_id');
                })->orWhere(function ($q) use ($seller) {
                    $q->where('status', 'asignada')->where('seller_id', $seller->id);
                });
            })
            ->get()
            ->keyBy('id');

        $assignedCount = 0;
        $assignedParticipationIds = [];

        foreach ($participationPayloads as $participationData) {
            $participation = $participationsToUpdate->get($participationData['id'] ?? null);
            if (! $participation || (int) $participation->set_id !== (int) ($participationData['set_id'] ?? 0)) {
                continue;
            }

            $participation->update([
                'seller_id' => $seller->id,
                'sale_date' => now()->toDateString(),
                'sale_time' => now()->toTimeString(),
                'status' => 'asignada',
            ]);
            $assignedCount++;
            $assignedParticipationIds[] = (int) $participation->id;
        }

        return [
            'assigned_count' => $assignedCount,
            'assigned_participation_ids' => $assignedParticipationIds,
        ];
    }

    /**
     * @param  array<int, int>  $assignedParticipationIds
     */
    public function sendAssignmentConfirmationEmail(Seller $seller, array $assignedParticipationIds): void
    {
        if ($assignedParticipationIds === [] || empty($seller->email)) {
            return;
        }

        $assignedParticipations = Participation::with(['set.reserve.lottery'])
            ->whereIn('id', $assignedParticipationIds)
            ->get();

        $assignmentsBySet = [];
        foreach ($assignedParticipations as $participation) {
            $setId = (int) $participation->set_id;
            if (! isset($assignmentsBySet[$setId])) {
                $set = $participation->set;
                $assignmentsBySet[$setId] = [
                    'set' => $set,
                    'lottery' => $set->reserve->lottery ?? null,
                    'count' => 0,
                ];
            }
            $assignmentsBySet[$setId]['count']++;
        }

        $assignmentsList = [];
        foreach ($assignmentsBySet as $setId => $data) {
            $assignmentsList[] = [
                'set_id' => (int) $setId,
                'count' => (int) ($data['count'] ?? 0),
            ];
        }

        $log = $this->communicationEmailService->sendAndLog(
            recipientEmail: (string) $seller->email,
            recipientRole: 'vendedor',
            recipientUser: $seller->user,
            messageType: 'participation_assignment',
            templateKey: null,
            mailClass: ParticipationAssignmentMail::class,
            mailPayload: [
                'seller_id' => $seller->id,
                'assignments' => $assignmentsList,
            ],
            context: [
                'seller_id' => $seller->id,
                'assigned_count' => count($assignedParticipationIds),
            ],
        );

        if ($log->status === EmailCommunicationLog::STATUS_CANCELLED) {
            Log::error('Error enviando email de asignación de participaciones: '.($log->error_message ?? 'unknown'));
        }
    }

    private function sendProposalEmail(ParticipationAssignmentProposal $proposal): void
    {
        $seller = $proposal->seller;
        if (! $seller || empty($seller->email)) {
            throw new \RuntimeException('El vendedor no tiene email para enviar la propuesta de asignación.');
        }

        $log = $this->communicationEmailService->sendAndLog(
            recipientEmail: (string) $seller->email,
            recipientRole: 'vendedor',
            recipientUser: $seller->user,
            messageType: 'participation_assignment_proposal',
            templateKey: null,
            mailClass: ParticipationAssignmentProposalMail::class,
            mailPayload: ['proposal_id' => $proposal->id],
            context: [
                'seller_id' => $seller->id,
                'proposal_id' => $proposal->id,
                'participation_count' => $proposal->participation_count,
            ],
        );

        if ($log->status === EmailCommunicationLog::STATUS_CANCELLED) {
            throw new \RuntimeException('No se pudo enviar el email de aceptación del recibo: '.($log->error_message ?? 'unknown'));
        }
    }

    /**
     * @param  array<int, int>  $assignedParticipationIds
     */
    private function sendPostAcceptanceEmails(Seller $seller, array $assignedParticipationIds, ParticipationAssignmentProposal $proposal): void
    {
        $this->sendAssignmentConfirmationEmail($seller, $assignedParticipationIds);

        $entity = $proposal->entity ?? $seller->entities->first();
        if (! $entity instanceof Entity) {
            return;
        }

        $entity->loadMissing('manager.user');
        $managerUser = $entity->manager?->user;
        if (! $managerUser || empty($managerUser->email)) {
            return;
        }

        try {
            $this->communicationEmailService->sendAndLog(
                recipientEmail: (string) $managerUser->email,
                recipientRole: 'gestor_entidad',
                recipientUser: $managerUser,
                messageType: 'participation_assignment_accepted_entity',
                templateKey: null,
                mailClass: ParticipationAssignmentAcceptedEntityMail::class,
                mailPayload: [
                    'proposal_id' => $proposal->id,
                    'seller_id' => $seller->id,
                    'assigned_count' => count($assignedParticipationIds),
                ],
                context: [
                    'entity_id' => $entity->id,
                    'seller_id' => $seller->id,
                    'proposal_id' => $proposal->id,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('Fallo enviando aviso a entidad por recibo aceptado: '.$e->getMessage());
        }
    }
}
