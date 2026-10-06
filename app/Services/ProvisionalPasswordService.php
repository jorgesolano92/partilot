<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class ProvisionalPasswordService
{
    public function generate(): string
    {
        return Str::password(12, letters: true, numbers: true, symbols: false);
    }

    public function assignToUser(User $user, ?string $plainPassword = null): string
    {
        $plain = $plainPassword ?: $this->generate();

        $user->password = $plain;
        $user->must_change_password = true;
        $user->save();

        return $plain;
    }

    public function markChanged(User $user): void
    {
        $user->update(['must_change_password' => false]);
    }
}
