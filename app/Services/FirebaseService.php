<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FirebaseService
{
    public function sendOtp(string $mobile, string $otp): void
    {
        if (! config('otp.debug')) {
            return;
        }

        Log::channel(config('logging.default'))->info('Mock OTP sent', [
            'mobile' => $mobile,
            'otp' => $otp,
            'provider' => 'firebase-mock',
        ]);
    }
}
