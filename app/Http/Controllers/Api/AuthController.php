<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Kreait\Firebase\Exception\Auth\ExpiredIdToken;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(private readonly FirebaseService $firebaseService)
    {
    }

    /**
     * POST /api/firebase-login
     */
    public function firebaseLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
        ]);

        try {
            $verifiedToken = $this->firebaseService->verifyIdToken($validated['id_token']);
            $firebaseUid = $this->firebaseService->extractUid($verifiedToken);
        } catch (ExpiredIdToken) {
            return response()->json([
                'status' => false,
                'message' => 'Token expired',
            ], 401);
        } catch (FailedToVerifyToken|RuntimeException) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid token',
            ], 401);
        }

        try {
            $user = DB::transaction(function () use ($firebaseUid, $validated): User {
                $existingByUid = User::query()->where('firebase_uid', $firebaseUid)->lockForUpdate()->first();

                if ($existingByUid) {
                    if ($existingByUid->phone !== $validated['phone']) {
                        throw ValidationException::withMessages([
                            'phone' => ['Phone number does not match verified Firebase account.'],
                        ]);
                    }

                    return $existingByUid;
                }

                $existingByPhone = User::query()->where('phone', $validated['phone'])->lockForUpdate()->first();

                if ($existingByPhone && $existingByPhone->firebase_uid !== $firebaseUid) {
                    throw ValidationException::withMessages([
                        'phone' => ['Phone number already linked to another account.'],
                    ]);
                }

                return User::query()->create([
                    'phone' => $validated['phone'],
                    'firebase_uid' => $firebaseUid,
                ]);
            }, 3);
        } catch (QueryException) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to process login at this time',
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $user->createToken('firebase-login-token')->plainTextToken,
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
            ],
        ]);
    }
}
