<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Truv\BridgeTokenRequest;
use App\Http\Requests\Truv\CreateUserRequest;
use App\Http\Requests\Truv\ExchangePublicTokenRequest;
use App\Http\Requests\Truv\RefreshLinkRequest;
use App\Models\DriverEarning;
use App\Models\LinkedAccount;
use App\Services\TruvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TruvController extends Controller
{
    public function __construct(private readonly TruvService $truvService)
    {
    }

    public function createUser(CreateUserRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            $linkedAccount = LinkedAccount::query()
                ->firstWhere([
                    'user_id' => $user->id,
                    'provider' => LinkedAccount::PROVIDER_TRUV,
                ]);

            if ($linkedAccount?->external_user_id) {
                return $this->success('Truv user is ready.', [
                    'external_user_id' => $linkedAccount->external_user_id,
                ]);
            }

            $response = $this->truvService->createUser($user);
            $externalUserId = (string) ($response['external_user_id'] ?? $user->id);

            $linkedAccount = LinkedAccount::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'provider' => LinkedAccount::PROVIDER_TRUV,
                ],
                [
                    'external_user_id' => $externalUserId,
                    'status' => LinkedAccount::STATUS_PENDING,
                    'is_connected' => false,
                ]
            );

            return $this->success('Truv user created successfully.', [
                'external_user_id' => $linkedAccount->external_user_id,
            ]);
        } catch (Throwable $exception) {
            Log::error('Unable to create Truv user', [
                'user_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Unable to create Truv user at this time.');
        }
    }

    public function bridgeToken(BridgeTokenRequest $request): JsonResponse
    {
        try {
            $linkedAccount = $this->getUserTruvAccount($request->user()->id);

            if (! $linkedAccount || ! $linkedAccount->external_user_id) {
                return $this->error('Create Truv user first.', 404);
            }

            $response = $this->truvService->createBridgeToken($linkedAccount->external_user_id);
            $bridgeToken = (string) ($response['bridge_token'] ?? $response['token'] ?? '');

            if ($bridgeToken === '') {
                return $this->error('Bridge token is not available.', 422);
            }

            return $this->success('Bridge token generated.', [
                'bridge_token' => $bridgeToken,
            ]);
        } catch (Throwable $exception) {
            Log::error('Unable to create Truv bridge token', [
                'user_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Unable to generate bridge token.');
        }
    }

    public function exchangeToken(ExchangePublicTokenRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $linkedAccount = $this->getUserTruvAccount($user->id);

            if (! $linkedAccount) {
                return $this->error('Create Truv user first.', 404);
            }

            $response = $this->truvService->exchangePublicToken($request->string('public_token')->value());
            $linkId = (string) ($response['link_id'] ?? '');
            $accessToken = (string) ($response['access_token'] ?? '');

            if ($linkId === '' || $accessToken === '') {
                return $this->error('Invalid token exchange response from Truv.', 422);
            }

            $linkedAccount->update([
                'link_id' => $linkId,
                'access_token' => $accessToken,
                'status' => LinkedAccount::STATUS_CONNECTED,
                'is_connected' => true,
                'last_synced_at' => now(),
            ]);

            return $this->success('Public token exchanged successfully.', [
                'is_connected' => true,
                'status' => $linkedAccount->status,
                'link_id' => $linkedAccount->link_id,
                'last_synced_at' => $linkedAccount->last_synced_at,
            ]);
        } catch (Throwable $exception) {
            Log::error('Unable to exchange Truv public token', [
                'user_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Unable to exchange public token.');
        }
    }

    public function profile(Request $request): JsonResponse
    {
        $linkedAccount = $this->getUserTruvAccount($request->user()->id);

        $summary = DriverEarning::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', LinkedAccount::PROVIDER_TRUV)
            ->selectRaw('SUM(gross_income) as gross_income, SUM(net_income) as net_income, COUNT(*) as statements')
            ->first();

        return $this->success('Truv profile fetched.', [
            'is_connected' => (bool) ($linkedAccount?->is_connected),
            'provider' => $linkedAccount?->provider ?? LinkedAccount::PROVIDER_TRUV,
            'status' => $linkedAccount?->status ?? LinkedAccount::STATUS_PENDING,
            'last_synced_at' => $linkedAccount?->last_synced_at,
            'linked_at' => $linkedAccount?->created_at,
            'earnings_summary' => [
                'gross_income' => (float) ($summary?->gross_income ?? 0),
                'net_income' => (float) ($summary?->net_income ?? 0),
                'statements' => (int) ($summary?->statements ?? 0),
            ],
        ]);
    }

    public function income(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $linkedAccount = $this->getUserTruvAccount($user->id);

            if (! $linkedAccount?->link_id) {
                return $this->error('Truv account is not connected.', 422);
            }

            $incomeReport = $this->truvService->getIncomeReport($linkedAccount->link_id);
            $employmentReport = $this->truvService->getEmploymentReport($linkedAccount->link_id);

            $incomeData = $incomeReport['income'] ?? $incomeReport['data'] ?? [];
            $statementDate = Carbon::parse($incomeData['statement_date'] ?? now())->toDateString();
            $grossIncome = (float) ($incomeData['gross_income'] ?? $incomeData['gross'] ?? 0);
            $netIncome = (float) ($incomeData['net_income'] ?? $incomeData['net'] ?? 0);
            $currency = (string) ($incomeData['currency'] ?? 'USD');
            $payFrequency = (string) ($incomeData['pay_frequency'] ?? 'unknown');

            DB::transaction(function () use ($user, $statementDate, $grossIncome, $netIncome, $currency, $payFrequency, $incomeReport): void {
                DriverEarning::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'statement_date' => $statementDate,
                        'provider' => LinkedAccount::PROVIDER_TRUV,
                    ],
                    [
                        'date' => $statementDate,
                        'gross_income' => $grossIncome,
                        'net_income' => $netIncome,
                        'total_earnings' => $netIncome > 0 ? $netIncome : $grossIncome,
                        'pay_frequency' => $payFrequency,
                        'currency' => $currency,
                        'raw_json' => $incomeReport,
                    ]
                );
            });

            $linkedAccount->update([
                'last_synced_at' => now(),
            ]);

            return $this->success('Income synced successfully.', [
                'income' => [
                    'gross_income' => $grossIncome,
                    'net_income' => $netIncome,
                    'currency' => $currency,
                    'pay_frequency' => $payFrequency,
                    'statement_date' => $statementDate,
                ],
                'employment' => $employmentReport,
            ]);
        } catch (Throwable $exception) {
            Log::error('Unable to sync Truv income', [
                'user_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Unable to sync income right now.');
        }
    }

    public function refresh(RefreshLinkRequest $request): JsonResponse
    {
        try {
            $linkedAccount = $this->getUserTruvAccount($request->user()->id);

            if (! $linkedAccount?->link_id) {
                return $this->error('Truv account is not connected.', 422);
            }

            $response = $this->truvService->refreshLink($linkedAccount->link_id);

            $linkedAccount->update([
                'last_synced_at' => now(),
            ]);

            return $this->success('Truv refresh requested successfully.', [
                'last_synced_at' => $linkedAccount->last_synced_at,
                'refresh_response' => $response,
            ]);
        } catch (Throwable $exception) {
            Log::error('Unable to refresh Truv account', [
                'user_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Unable to refresh Truv account.');
        }
    }

    public function disconnect(Request $request): JsonResponse
    {
        try {
            $linkedAccount = $this->getUserTruvAccount($request->user()->id);

            if (! $linkedAccount) {
                return $this->error('Truv account not found.', 404);
            }

            if ($linkedAccount->link_id) {
                $this->truvService->deleteLink($linkedAccount->link_id);
            }

            $linkedAccount->update([
                'access_token' => null,
                'link_id' => null,
                'status' => LinkedAccount::STATUS_DISCONNECTED,
                'is_connected' => false,
            ]);

            return $this->success('Truv account disconnected.', []);
        } catch (Throwable $exception) {
            Log::error('Unable to disconnect Truv account', [
                'user_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Unable to disconnect Truv account.');
        }
    }

    private function getUserTruvAccount(int $userId): ?LinkedAccount
    {
        return LinkedAccount::query()->firstWhere([
            'user_id' => $userId,
            'provider' => LinkedAccount::PROVIDER_TRUV,
        ]);
    }

    private function success(string $message, array $data = [], int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    private function error(string $message, int $statusCode = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $statusCode);
    }
}
