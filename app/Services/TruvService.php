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
        Log::info('Truv Config Debug', [
            'base_url' => config('truv.base_url'),
            'client_id_present' => ! empty(config('truv.client_id')),
            'secret_present' => ! empty(config('truv.access_secret')),
        ]);

        $url = $this->buildUrl($uri);

        $response = match ($method) {
            'post' => $this->client()->post($url, $payload),
            'delete' => $this->client()->delete($url),
            default => $this->client()->get($url),
        };

        $this->logIfFailed($response, $method, $url, $payload);

        return $response->throw()->json() ?? [];
    }

    private function client(): PendingRequest
    {
        return Http::timeout(30)
            ->retry(2, 500)
            ->withHeaders([
                'X-Access-Client-ID' => config('truv.client_id'),
                'X-Access-Secret' => config('truv.access_secret'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logIfFailed(Response $response, string $method, string $url, array $payload = []): void
    {
        if (! $response->failed()) {
            return;
        }

        Log::error('Truv API request failed', [
            'method' => strtoupper($method),
            'url' => $url,
            'status_code' => $response->status(),
            'response_body' => $response->body(),
            'payload' => $payload,
        ]);
    }

    private function buildUrl(string $uri): string
    {
        return rtrim((string) config('truv.base_url'), '/').'/'.ltrim($uri, '/');
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
