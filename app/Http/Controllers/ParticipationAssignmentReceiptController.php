<?php

namespace App\Http\Controllers;

use App\Models\ParticipationAssignmentProposal;
use App\Services\ParticipationAssignmentReceiptService;
use Illuminate\Http\Request;

class ParticipationAssignmentReceiptController extends Controller
{
    public function confirmAccept(string $token, ParticipationAssignmentReceiptService $service)
    {
        $proposal = ParticipationAssignmentProposal::query()->where('token', $token)->first();

        if (! $proposal || ! $proposal->isPending() || $proposal->isExpired()) {
            $result = $service->acceptByToken($token, request());

            return view('participation-assignment.result', compact('result'));
        }

        return view('public.confirm-accept', [
            'title' => 'Aceptar asignación de participaciones',
            'message' => '¿Confirmas que aceptas el recibo de '.((int) $proposal->participation_count).' participación(es)? Esta acción registra la entrega a tu nombre.',
            'action' => route('participation-assignment.accept.store', ['token' => $token]),
        ]);
    }

    public function accept(string $token, Request $request, ParticipationAssignmentReceiptService $service)
    {
        $result = $service->acceptByToken($token, $request);

        return view('participation-assignment.result', compact('result'));
    }

    public function confirmReject(string $token, ParticipationAssignmentReceiptService $service)
    {
        $proposal = ParticipationAssignmentProposal::query()->where('token', $token)->first();

        if (! $proposal || ! $proposal->isPending() || $proposal->isExpired()) {
            $result = $service->rejectByToken($token);

            return view('participation-assignment.result', compact('result'));
        }

        return view('public.confirm-reject', [
            'title' => 'Rechazar asignación de participaciones',
            'message' => '¿Seguro que quieres rechazar esta asignación de participaciones?',
            'action' => route('participation-assignment.reject.store', ['token' => $token]),
        ]);
    }

    public function reject(string $token, ParticipationAssignmentReceiptService $service)
    {
        $result = $service->rejectByToken($token);

        return view('participation-assignment.result', compact('result'));
    }
}
