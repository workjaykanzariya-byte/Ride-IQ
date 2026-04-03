<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Auth\ExpiredIdToken;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use RuntimeException;
use Throwable;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly FirebaseService $firebaseService)
    {
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firebase_token' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $parsedToken = $this->firebaseService->parseToken(
                $this->firebaseService->verifyIdToken($validated['firebase_token'])
            );
        } catch (ExpiredIdToken|FailedToVerifyToken|RuntimeException $exception) {
            Log::warning('Firebase token verification failed', [
                'error' => $exception->getMessage(),
                'ip' => $request->ip(),
            ]);

            return $this->error('Invalid Firebase token', null, 401);
        } catch (Throwable $exception) {
            Log::error('Unexpected Firebase auth error', [
                'exception' => $exception,
                'ip' => $request->ip(),
            ]);

            return $this->error('Invalid Firebase token', null, 401);
        }

        try {
            $user = DB::transaction(function () use ($parsedToken, $validated): User {
                $user = User::query()
                    ->where('firebase_uid', $parsedToken['firebase_uid'])
                    ->lockForUpdate()
                    ->first();

                if (! $user) {
                    $user = User::query()->create([
                        'name' => $validated['name'] ?? null,
                        'phone' => $parsedToken['phone_number'],
                        'firebase_uid' => $parsedToken['firebase_uid'],
                        'role' => User::ROLE_RIDER,
                        'is_verified' => true,
                    ]);

                    $user->userSetting()->create([]);
                }

                return $user;
            }, 3);
        } catch (Throwable $exception) {
            Log::error('Auth verify database flow failed', [
                'exception' => $exception,
                'firebase_uid' => $parsedToken['firebase_uid'] ?? null,
            ]);

            return $this->error('Unable to complete authentication', null, 500);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return $this->success('Authentication successful', [
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function updateRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:rider,driver'],
        ]);

        $user = $request->user();
        $user->update(['role' => $validated['role']]);

        return $this->success('Role updated', [
            'user' => $user->fresh(),
        ]);
    }
}
