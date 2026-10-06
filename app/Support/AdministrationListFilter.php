<?php

namespace App\Support;

use App\Models\Administration;
use App\Models\User;
use Illuminate\Http\Request;

class AdministrationListFilter
{
    public static function resolve(Request $request, ?User $user): ?Administration
    {
        if (! $user || ! $request->filled('administration_id')) {
            return null;
        }

        return Administration::forUser($user)->findOrFail((int) $request->administration_id);
    }
}
