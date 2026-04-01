<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Auth\Token\VerifiedIdToken;

class FirebaseAuthService
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function verifyIdToken(string $idToken): VerifiedIdToken
    {
        return $this->auth->verifyIdToken($idToken, true);
    }

    /**
     * @throws ValidationException
     */
    public function findOrCreateUser(string $phone, string $firebaseUid): User
    {
        $userByFirebaseUid = User::query()->where('firebase_uid', $firebaseUid)->first();

        if ($userByFirebaseUid) {
            if ($userByFirebaseUid->phone !== $phone) {
                throw ValidationException::withMessages([
                    'phone' => ['Phone number does not match the verified Firebase account.'],
                ]);
            }

            return $userByFirebaseUid;
        }

        $userByPhone = User::query()->where('phone', $phone)->first();

        if ($userByPhone && $userByPhone->firebase_uid !== $firebaseUid) {
            throw ValidationException::withMessages([
                'phone' => ['This phone number is already linked to another account.'],
            ]);
        }

        return User::query()->firstOrCreate(
            ['firebase_uid' => $firebaseUid],
            ['phone' => $phone],
        );
    }
}
