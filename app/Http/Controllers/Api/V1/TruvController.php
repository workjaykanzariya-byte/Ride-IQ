<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TruvAccountResource;
use App\Models\TruvAccount;
use App\Services\TruvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class TruvController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TruvService $truvService)
    {
    }

    public function exchangeToken(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'public_token' => ['required', 'string'],
            ]);

            $response = $this->truvService->exchangeToken($validated['public_token']);
            $accessToken = (string) data_get($response, 'access_token', '');

            if ($accessToken === '') {
                throw new RuntimeException('Invalid Truv token exchange response.');
            }

            $request->user()->update([
                'truv_access_token' => encrypt($accessToken),
            ]);

            return $this->success('Truv access token stored successfully.');
        } catch (ValidationException $exception) {
            return $this->error('Validation failed.', $exception->errors(), 422);
        } catch (RuntimeException $exception) {
            Log::warning('Truv exchange token failed', [
                'user_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Unable to exchange Truv token.', [
                'detail' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Unexpected Truv exchange token failure', [
                'user_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Failed to exchange Truv token.', null, 500);
        }
    }

    public function fetchAccounts(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            if (empty($user->truv_access_token)) {
                return $this->error('Missing Truv access token. Exchange token first.', null, 422);
            }

            $profile = $this->truvService->getProfile(decrypt($user->truv_access_token));
            $accounts = data_get($profile, 'accounts', []);

            if (! is_array($accounts) || $accounts === []) {
                return $this->success('No Truv accounts found.', [
                    'truv_accounts' => [],
                ]);
            }

            foreach ($accounts as $account) {
                $truvAccountId = (string) data_get($account, 'id', '');

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

            return $this->success('Truv accounts synced successfully.', [
                'truv_accounts' => TruvAccountResource::collection($user->truvAccounts()->latest()->get()),
            ]);
        } catch (RuntimeException $exception) {
            Log::warning('Truv profile fetch failed', [
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
