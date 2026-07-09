<?php

namespace App\Http\Controllers;

use App\Services\ParticipationAssignmentReceiptService;
use Illuminate\Http\Request;

class ParticipationAssignmentReceiptController extends Controller
{
    public function accept(string $token, Request $request, ParticipationAssignmentReceiptService $service)
    {
        $result = $service->acceptByToken($token, $request);

        return view('participation-assignment.result', compact('result'));
    }

    public function reject(string $token, ParticipationAssignmentReceiptService $service)
    {
        $result = $service->rejectByToken($token);

        return view('participation-assignment.result', compact('result'));
    }
}
