<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Auth;
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

        $normalizedPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $credentials);
        $credentialsPath = $this->isAbsolutePath($normalizedPath)
            ? $normalizedPath
            : base_path(ltrim($normalizedPath, DIRECTORY_SEPARATOR));

        if (! is_file($credentialsPath)) {
            throw new RuntimeException('Firebase credentials file not found at: '.$credentialsPath);
        }

        if ((bool) config('services.firebase.otp_debug')) {
            Log::debug('Firebase credentials resolved.', [
                'project_id' => config('services.firebase.project_id'),
                'credentials_path' => $credentialsPath,
            ]);
        }

        $this->auth = (new Factory())
            ->withServiceAccount($credentialsPath)
            ->createAuth();
    }

    /**
     * @return mixed
     */
    public function verifyIdToken(string $idToken)
    {
        return $this->auth->verifyIdToken($idToken, true);
    }

    /**
     * @return array{firebase_uid: string, phone_number: ?string}
     */
    public function parseToken($verifiedIdToken): array
    {
        if (! is_object($verifiedIdToken) || ! method_exists($verifiedIdToken, 'claims')) {
            throw new RuntimeException('Unable to parse Firebase token claims.');
        }

        $claims = $verifiedIdToken->claims();

        if (! is_object($claims) || ! method_exists($claims, 'get')) {
            throw new RuntimeException('Unable to read Firebase token claims.');
        }

        $uid = $claims->get('sub');

        if (! is_string($uid) || $uid === '') {
            throw new RuntimeException('Unable to extract Firebase UID from token claims.');
        }

        $phone = $claims->get('phone_number');

        return [
            'firebase_uid' => $uid,
            'phone_number' => is_string($phone) && $phone !== '' ? $phone : null,
        ];
    }

    public function extractUid($verifiedIdToken): string
    {
        return $this->parseToken($verifiedIdToken)['firebase_uid'];
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }
}
