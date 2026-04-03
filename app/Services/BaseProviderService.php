<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class BaseProviderService implements ProviderServiceInterface
{
    abstract protected function providerName(): string;

    protected function httpClient(?string $accessToken = null): PendingRequest
    {
        $baseUrl = config('services.'.$this->providerName().'.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException($this->providerName().' base_url not configured.');
        }

        $client = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->retry(3, 300, throw: false)
            ->timeout(12);

        if ($accessToken !== null && $accessToken !== '') {
            $client->withToken($accessToken);
        }

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    protected function request(string $method, string $uri, array $payload = [], ?string $accessToken = null): array
    {
        try {
            $response = $this->httpClient($accessToken)->send($method, $uri, [
                'query' => strtoupper($method) === 'GET' ? $payload : [],
                'json' => strtoupper($method) !== 'GET' ? $payload : [],
            ]);

            if ($response->failed()) {
                throw new RuntimeException($this->providerName().' API request failed: '.$response->body());
            }

            return $response->json() ?? [];
        } catch (ConnectionException|RequestException $exception) {
            throw new RuntimeException($this->providerName().' API unavailable.', previous: $exception);
        }
    }

    /**
     * @return array{access_token: string, refresh_token: ?string}
     */
    public function exchangeOAuthToken(string $code): array
    {
        $data = $this->request('POST', '/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => config('services.'.$this->providerName().'.client_id'),
            'client_secret' => config('services.'.$this->providerName().'.client_secret'),
        ]);

        return [
            'access_token' => (string) ($data['access_token'] ?? ''),
            'refresh_token' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
        ];
    }
}
