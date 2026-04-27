<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TruvService
{
    public function createUser(User $user): array
    {
        [$firstName, $lastName] = $this->splitName($user->name ?? '');

        return $this->post('/users', [
            'external_user_id' => (string) $user->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $user->email,
        ]);
    }

    public function createBridgeToken(string $externalUserId): array
    {
        return $this->post('/bridge_tokens', [
            'external_user_id' => $externalUserId,
            'product_type' => 'income',
            'env' => config('truv.env'),
        ]);
    }

    public function exchangePublicToken(string $publicToken): array
    {
        return $this->post('/public_tokens/exchange', [
            'public_token' => $publicToken,
        ]);
    }

    public function getIncomeReport(string $linkId): array
    {
        return $this->get("/links/{$linkId}/income");
    }

    public function getEmploymentReport(string $linkId): array
    {
        return $this->get("/links/{$linkId}/employment");
    }

    public function refreshLink(string $linkId): array
    {
        return $this->post("/links/{$linkId}/refresh", []);
    }

    public function deleteLink(string $linkId): array
    {
        return $this->delete("/links/{$linkId}");
    }

    private function post(string $uri, array $payload): array
    {
        return $this->request()->post($uri, $payload)->throw()->json() ?? [];
    }

    private function get(string $uri): array
    {
        return $this->request()->get($uri)->throw()->json() ?? [];
    }

    private function delete(string $uri): array
    {
        return $this->request()->delete($uri)->throw()->json() ?? [];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('truv.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'x-client-id' => (string) config('truv.client_id'),
                'x-access-secret' => (string) config('truv.access_secret'),
            ])
            ->timeout((int) config('truv.timeout', 15))
            ->retry((int) config('truv.retry_times', 2), (int) config('truv.retry_sleep_ms', 200), function (\Throwable $exception): bool {
                return $exception instanceof RequestException;
            });
    }

    private function splitName(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return ['Driver', ''];
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $firstName = Arr::first($parts) ?? 'Driver';

        if (count($parts) <= 1) {
            return [$firstName, ''];
        }

        $lastName = implode(' ', array_slice($parts, 1));

        return [$firstName, $lastName];
    }
}
