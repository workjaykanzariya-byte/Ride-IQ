<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
            // Backward compatibility for old clients that still send `name`.
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        // Normalize blank strings from clients to null so optional fields are safely ignored.
        $profilePayload = [
            'name' => $this->normalizeString($validated['name'] ?? null),
            'first_name' => $this->normalizeString($validated['first_name'] ?? null),
            'last_name' => $this->normalizeString($validated['last_name'] ?? null),
            'email' => $this->normalizeString($validated['email'] ?? null),
        ];

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
            $user = DB::transaction(function () use ($parsedToken, $profilePayload): User {
                $user = User::query()
                    ->where('firebase_uid', $parsedToken['firebase_uid'])
                    ->lockForUpdate()
                    ->first();

                if ($profilePayload['email'] !== null) {
                    $emailOwner = User::query()
                        ->where('email', $profilePayload['email'])
                        ->when($user, fn ($query) => $query->where('id', '!=', $user->id))
                        ->exists();

                    if ($emailOwner) {
                        throw ValidationException::withMessages([
                            'email' => ['The email has already been taken.'],
                        ]);
                    }
                }

                if (! $user) {
                    $composedName = $this->composeName(
                        $profilePayload['first_name'],
                        $profilePayload['last_name']
                    );

                    $user = User::query()->create([
                        'name' => $profilePayload['name'] ?? $composedName,
                        'first_name' => $profilePayload['first_name'],
                        'last_name' => $profilePayload['last_name'],
                        'email' => $profilePayload['email'],
                        'phone' => $parsedToken['phone_number'],
                        'firebase_uid' => $parsedToken['firebase_uid'],
                        'role' => User::ROLE_RIDER,
                        'is_verified' => true,
                    ]);

                    $user->userSetting()->create([]);

                    return $user;
                }

                // Auto-fill profile fields only when currently empty.
                $updates = [];

                if ($this->isNullOrEmpty($user->first_name) && $profilePayload['first_name'] !== null) {
                    $updates['first_name'] = $profilePayload['first_name'];
                }

                if ($this->isNullOrEmpty($user->last_name) && $profilePayload['last_name'] !== null) {
                    $updates['last_name'] = $profilePayload['last_name'];
                }

                if ($this->isNullOrEmpty($user->email) && $profilePayload['email'] !== null) {
                    $updates['email'] = $profilePayload['email'];
                }

                if ($this->isNullOrEmpty($user->name)) {
                    if ($profilePayload['name'] !== null) {
                        $updates['name'] = $profilePayload['name'];
                    } else {
                        $composedName = $this->composeName(
                            $updates['first_name'] ?? $user->first_name,
                            $updates['last_name'] ?? $user->last_name
                        );

                        if ($composedName !== null) {
                            $updates['name'] = $composedName;
                        }
                    }
                }

                if ($updates !== []) {
                    $user->fill($updates)->save();
                }

                return $user->fresh();
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
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

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function isNullOrEmpty(mixed $value): bool
    {
        return ! is_string($value) || trim($value) === '';
    }

    private function composeName(?string $firstName, ?string $lastName): ?string
    {
        $name = trim(($firstName ?? '').' '.($lastName ?? ''));

        return $name === '' ? null : $name;
    }
}
