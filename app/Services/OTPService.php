<?php

namespace App\Services;

use App\Models\OtpVerification;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class OTPService
{
    public const MAX_ATTEMPTS = 5;

    public function generateOtp(int $length = 6): string
    {
        $length = max(4, min($length, 6));
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;

        return (string) random_int($min, $max);
    }

    public function storeOtp(string $mobile, string $otp): OtpVerification
    {
        OtpVerification::query()
            ->where('mobile', $mobile)
            ->whereNull('verified_at')
            ->delete();

        return OtpVerification::query()->create([
            'mobile' => $mobile,
            'otp' => $otp,
            'expires_at' => CarbonImmutable::now()->addMinutes(5),
            'attempts' => 0,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function verifyOtp(string $mobile, string $otp): OtpVerification
    {
        $verification = OtpVerification::query()
            ->where('mobile', $mobile)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $verification) {
            throw ValidationException::withMessages([
                'otp' => ['No OTP request found for this mobile number.'],
            ]);
        }

        if ($verification->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'otp' => ['Maximum OTP attempts exceeded. Please request a new OTP.'],
            ]);
        }

        if ($verification->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => ['OTP has expired. Please request a new one.'],
            ]);
        }

        if (! hash_equals($verification->otp, $otp)) {
            $verification->increment('attempts');

            throw ValidationException::withMessages([
                'otp' => ['Invalid OTP.'],
            ]);
        }

        $verification->forceFill([
            'verified_at' => CarbonImmutable::now(),
        ])->save();

        return $verification;
    }
}
