<?php

namespace App\Http\Controllers;

use App\Models\ParticipationAssignmentProposal;
use App\Services\ParticipationAssignmentReceiptService;
use Illuminate\Http\Request;

class ParticipationAssignmentReceiptController extends Controller
{
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
