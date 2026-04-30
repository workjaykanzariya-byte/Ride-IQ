<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TruvAccountResource;
use App\Models\DriverTruvAccount;
use App\Models\TruvAccount;
use App\Services\TruvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TruvController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TruvService $truvService)
    {
    }

    public function fetchAndStoreAccounts(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $driverTruvAccount = DriverTruvAccount::query()
                ->where('user_id', $user->id)
                ->first();

            if (! $driverTruvAccount || empty($driverTruvAccount->access_token)) {
                return $this->error('Truv access token is missing. Connect your Truv account first.', null, 422);
            }

            $accessToken = Crypt::decryptString($driverTruvAccount->access_token);
            $profile = $this->truvService->getProfile($accessToken);
            $accounts = data_get($profile, 'accounts', []);

            if (! is_array($accounts) || $accounts === []) {
                return $this->success('No Truv accounts found for this user.', [
                    'truv_accounts' => [],
                ]);
            }

            foreach ($accounts as $account) {
                if (! is_array($account)) {
                    continue;
                }

                $truvAccountId = (string) (data_get($account, 'id') ?? data_get($account, 'account_id') ?? '');

                if ($truvAccountId === '') {
                    continue;
                }

                TruvAccount::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'truv_account_id' => $truvAccountId,
                    ],
                    [
                        'platform' => data_get($account, 'platform'),
                        'type' => data_get($account, 'type'),
                        'status' => data_get($account, 'status'),
                    ]
                );
            }

            $storedAccounts = $user->truvAccounts()->latest()->get();

            return $this->success('Truv accounts fetched successfully.', [
                'truv_accounts' => TruvAccountResource::collection($storedAccounts),
            ]);
        } catch (RuntimeException $exception) {
            Log::error('Truv profile API request failed', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Unable to fetch Truv profile.', [
                'detail' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Unexpected Truv account sync failure', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Failed to sync Truv accounts.', null, 500);
        }
    }
}
