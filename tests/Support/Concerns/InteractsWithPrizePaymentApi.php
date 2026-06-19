<?php

namespace Tests\Support\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;

trait InteractsWithPrizePaymentApi
{
    protected function apiTokenFor(User $user, int $days = 30): string
    {
        return Crypt::encrypt([
            'user_id' => $user->id,
            'exp' => now()->addDays($days)->timestamp,
        ]);
    }

    protected function apiHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiTokenFor($user),
            'Accept' => 'application/json',
        ];
    }

    protected function jsonApi(User $user, string $method, string $uri, array $data = []): TestResponse
    {
        return $this->json($method, $uri, $data, $this->apiHeaders($user));
    }

    protected function databaseAvailable(): bool
    {
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
