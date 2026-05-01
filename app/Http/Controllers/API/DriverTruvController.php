<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Api\V1\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DriverTruvAccount;
use App\Services\TruvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class DriverTruvController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TruvService $truvService)
    {
    }

    public function createToken(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'company_mapping_id' => ['nullable', 'string'],
            ]);

            $payload = ['product_type' => 'employment'];
            if (! empty($validated['company_mapping_id'])) {
                $payload['company_mapping_id'] = $validated['company_mapping_id'];
            }

            $truvConfig = $this->resolveTruvConfig();
            $response = Http::withHeaders($this->truvHeaders($truvConfig))
                ->post(rtrim($truvConfig['base_url'], '/').'/v1/bridge-tokens', $payload);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to create bridge token',
                    'data' => $response->json(),
                ], $response->status());
            }

            return response()->json([
                'success' => true,
                'message' => 'Bridge token created successfully',
                'data' => $response->json(),
            ]);
        } catch (ValidationException $exception) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'data' => $exception->errors()], 422);
        } catch (Throwable $exception) {
            Log::error('Truv create token request failed', ['user_id' => $request->user()?->id, 'error' => $exception->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Unable to create bridge token', 'data' => null], 422);
        }
    }

    public function searchCompany(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'query' => ['required', 'string'],
            ]);

            $truvConfig = $this->resolveTruvConfig();
            $headers = $this->truvHeaders($truvConfig);

            Log::info('Truv company search config', [
                'client_id' => $truvConfig['client_id'],
                'secret' => $this->maskSecret($truvConfig['secret']),
                'base_url' => $truvConfig['base_url'],
            ]);

            Log::info('Truv company search request', [
                'url' => rtrim($truvConfig['base_url'], '/').'/v1/company-mappings-search',
                'headers' => $headers,
                'query' => $validated['query'],
            ]);

            $response = Http::withHeaders($headers)->get(
                rtrim($truvConfig['base_url'], '/').'/v1/company-mappings-search',
                ['query' => $validated['query']]
            );

            Log::info('Truv company search response', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to search companies',
                    'debug' => $response->json() ?? ['raw' => $response->body()],
                ], $response->status());
            }

            $companies = collect($response->json('items', $response->json() ?? []))
                ->map(fn ($item) => [
                    'company_mapping_id' => $item['company_mapping_id'] ?? null,
                    'name' => $item['name'] ?? null,
                    'domain' => $item['domain'] ?? null,
                    'logo_url' => $item['logo_url'] ?? null,
                ])->values()->all();

            return response()->json([
                'success' => true,
                'message' => 'Companies fetched successfully',
                'data' => $companies,
            ]);
        } catch (ValidationException $exception) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'data' => $exception->errors()], 422);
        } catch (Throwable $exception) {
            Log::error('Truv company search failed', ['user_id' => $request->user()?->id, 'error' => $exception->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Unable to search companies', 'debug' => ['error' => $exception->getMessage()]], 422);
        }
    }

    private function resolveTruvConfig(): array
    {
        $clientId = (string) config('services.truv.client_id');
        $secret = (string) config('services.truv.secret');
        $baseUrl = (string) config('services.truv.base_url', 'https://sandbox.truv.com');

        if (str_starts_with($secret, 'sandbox-') && ! str_contains($baseUrl, 'sandbox.truv.com')) {
            $baseUrl = 'https://sandbox.truv.com';
        }

        return compact('clientId', 'secret', 'baseUrl');
    }

    private function truvHeaders(array $truvConfig): array
    {
        return [
            'X-Access-Client-Id' => $truvConfig['clientId'],
            'X-Access-Secret' => $truvConfig['secret'],
            'Accept' => 'application/json',
        ];
    }

    private function maskSecret(string $secret): string
    {
        if ($secret === '') {
            return '';
        }

        return substr($secret, 0, 4).str_repeat('*', max(strlen($secret) - 8, 0)).substr($secret, -4);
    }

    public function exchangeToken(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'public_token' => ['required', 'string'],
            ]);

            $exchangeResponse = $this->truvService->exchangePublicToken($validated['public_token']);
            $accessToken = (string) ($exchangeResponse['access_token'] ?? '');
            $linkId = (string) ($exchangeResponse['link_id'] ?? '');

            if ($accessToken === '' || $linkId === '') {
                throw new \RuntimeException('Invalid Truv exchange token response');
            }

            DriverTruvAccount::query()->updateOrCreate(
                ['user_id' => $request->user()->id],
                [
                    'access_token' => Crypt::encryptString($accessToken),
                    'link_id' => $linkId,
                    'verification_status' => 'connected',
                    'connected_at' => now(),
                ]
            );

            Log::info('Truv exchange token success', [
                'user_id' => $request->user()->id,
                'link_id' => $linkId,
            ]);

            return $this->success('Driver connected successfully');
        } catch (ValidationException $exception) {
            return $this->error('Validation failed', $exception->errors(), 422);
        } catch (Throwable $exception) {
            Log::error('Truv exchange token failed', [
                'user_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Unable to connect driver account', null, 422);
        }
    }

    public function report(Request $request): JsonResponse
    {
        try {
            $driverTruvAccount = DriverTruvAccount::query()
                ->where('user_id', $request->user()->id)
                ->first();

            if (! $driverTruvAccount || empty($driverTruvAccount->link_id)) {
                return $this->error('Driver is not connected with Truv yet', null, 422);
            }

            $reportResponse = $this->truvService->getIncomeReport($driverTruvAccount->link_id);

            $updateData = [
                'last_report' => $reportResponse,
            ];

            $employments = $reportResponse['employments']
                ?? data_get($reportResponse, 'report.employments')
                ?? [];

            if (is_array($employments) && count($employments) > 0) {
                $updateData['verification_status'] = 'verified';
                $updateData['verified_at'] = now();
            }

            $driverTruvAccount->update($updateData);

            Log::info('Truv report fetch success', [
                'user_id' => $request->user()->id,
                'link_id' => $driverTruvAccount->link_id,
            ]);

            return $this->success('Report fetched', $reportResponse);
        } catch (Throwable $exception) {
            Log::error('Truv report fetch failed', [
                'user_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Unable to fetch report', null, 422);
        }
    }

    public function status(Request $request): JsonResponse
    {
        try {
            $driverTruvAccount = DriverTruvAccount::query()
                ->where('user_id', $request->user()->id)
                ->first();

            return $this->success('Truv status', [
                'verification_status' => $driverTruvAccount?->verification_status,
                'truv_user_id' => $driverTruvAccount?->truv_user_id,
                'link_id' => $driverTruvAccount?->link_id,
                'connected_at' => $driverTruvAccount?->connected_at,
                'verified_at' => $driverTruvAccount?->verified_at,
            ]);
        } catch (Throwable $exception) {
            Log::error('Truv status fetch failed', [
                'user_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Unable to fetch truv status', null, 422);
        }
    }
}
