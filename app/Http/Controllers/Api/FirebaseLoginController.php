<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FirebaseLoginRequest;
use App\Services\FirebaseAuthService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Exception\Auth\ExpiredIdToken;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class FirebaseLoginController extends Controller
{
    public function __construct(
        private readonly FirebaseAuthService $firebaseAuthService,
    ) {
    }

    public function __invoke(FirebaseLoginRequest $request): JsonResponse
    {
        try {
            $verifiedToken = $this->firebaseAuthService->verifyIdToken(
                $request->string('id_token')->toString()
            );
        } catch (ExpiredIdToken) {
            return response()->json([
                'status' => false,
                'message' => 'Firebase token has expired.',
            ], 401);
        } catch (FailedToVerifyToken) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Firebase token.',
            ], 401);
        }

        $firebaseUid = $verifiedToken->claims()->get('sub');

        if (! is_string($firebaseUid) || $firebaseUid === '') {
            return response()->json([
                'status' => false,
                'message' => 'Unable to resolve Firebase UID from token.',
            ], 422);
        }

        try {
            $user = DB::transaction(function () use ($request, $firebaseUid) {
                return $this->firebaseAuthService->findOrCreateUser(
                    $request->string('phone')->toString(),
                    $firebaseUid,
                );
            });
        } catch (QueryException) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to complete login at this time.',
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
