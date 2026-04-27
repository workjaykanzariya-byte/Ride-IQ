<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        return $this->send('post', $uri, $payload);
    }

    private function get(string $uri): array
    {
        return $this->send('get', $uri);
    }

    private function delete(string $uri): array
    {
        return $this->send('delete', $uri);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $method, string $uri, array $payload = []): array
    {
        $response = match ($method) {
            'post' => $this->client()->post($uri, $payload),
            'delete' => $this->client()->delete($uri),
            default => $this->client()->get($uri),
        };

        $this->logIfFailed($response, $method, $uri, $payload);

        return $response->throw()->json() ?? [];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('truv.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-Access-Client-ID' => (string) config('truv.client_id'),
                'X-Access-Secret' => (string) config('truv.access_secret'),
            ])
            ->timeout(30)
            ->retry(2, 500);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logIfFailed(Response $response, string $method, string $uri, array $payload = []): void
    {
        if (! $response->failed()) {
            return;
        }

        Log::error('Truv API request failed', [
            'method' => strtoupper($method),
            'uri' => $uri,
            'status' => $response->status(),
            'response' => $response->json() ?? $response->body(),
            'payload' => $payload,
        ]);
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
