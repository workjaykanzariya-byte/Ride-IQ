<?php

namespace App\Services;

use Kreait\Firebase\Auth;
use Kreait\Firebase\Auth\Token\VerifiedIdToken;
use Kreait\Firebase\Factory;
use RuntimeException;

class FirebaseService
{
    private Auth $auth;

    public function __construct()
    {
        $credentials = config('services.firebase.credentials');

        if (! is_string($credentials) || $credentials === '') {
            throw new RuntimeException('Firebase credentials path is not configured.');
        }

        $credentialsPath = storage_path($credentials);

        if (! is_file($credentialsPath)) {
            throw new RuntimeException('Firebase credentials file not found at: '.$credentialsPath);
        }

        $this->auth = (new Factory())
            ->withServiceAccount($credentialsPath)
            ->createAuth();
    }

    public function verifyIdToken(string $idToken): VerifiedIdToken
    {
        return $this->auth->verifyIdToken($idToken, true);
    }

    public function extractUid(VerifiedIdToken $verifiedIdToken): string
    {
        $uid = $verifiedIdToken->claims()->get('sub');

        if (! is_string($uid) || $uid === '') {
            throw new RuntimeException('Unable to extract Firebase UID from token claims.');
        }

        return $uid;
    }
}
