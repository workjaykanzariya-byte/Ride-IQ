<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TruvService
{
    public function createUser(object $user): array
    {
        return $this->post('/users/', [
            'external_user_id' => 'rydeiq-user-'.$user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
        ]);
    }

    public function createBridgeToken(
        string $truvUserId,
        int $authUserId,
        ?string $companyMappingId = null,
        ?string $providerId = null
    ): array
    {
        $payload = [
            'product_type' => 'income',
            'tracking_info' => 'driver-'.$authUserId,
        ];

        if ($companyMappingId) {
            $payload['company_mapping_id'] = $companyMappingId;
        }

        if ($providerId) {
            $payload['provider_id'] = $providerId;
        }

        return $this->post("/users/{$truvUserId}/tokens/", $payload);
    }

    public function exchangePublicToken(string $publicToken): array
    {
        return $this->post('/link-access-tokens/', [
            'public_token' => $publicToken,
        ]);
    }

    public function getIncomeReport(string $linkId): array
    {
        return $this->get("/links/{$linkId}/income/report");
    }

    public function searchCompanies(string $companyName): array
    {
        return $this->get('/companies/search?query='.urlencode($companyName));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function post(string $endpoint, array $payload): array
    {
        return $this->request('post', $endpoint, $payload);
    }

    protected function get(string $endpoint): array
    {
        return $this->request('get', $endpoint);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function request(string $method, string $endpoint, array $payload = []): array
    {
        $response = Http::baseUrl((string) config('services.truv.base_url'))
            ->acceptJson()
            ->withHeaders([
                'X-Access-Client-ID' => (string) config('services.truv.client_id'),
                'X-Access-Secret' => (string) config('services.truv.access_secret'),
                'Content-Type' => 'application/json',
            ])
            ->send(strtoupper($method), $endpoint, [
                'json' => $payload,
            ]);

        return $this->handleResponse($response);
    }

    protected function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $message = $response->json('message')
            ?? $response->json('detail')
            ?? 'Truv request failed';

        throw new RuntimeException($message, $response->status());
    }
}
