<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\SendOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyOtpRequest;
use App\Services\AuthService;
use App\Services\FirebaseService;
use App\Services\OTPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly OTPService $otpService,
        private readonly FirebaseService $firebaseService,
        private readonly AuthService $authService,
    ) {
    }

    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $mobile = $request->string('mobile')->toString();
        $otp = $this->otpService->generateOtp();

        $this->otpService->storeOtp($mobile, $otp);
        $this->firebaseService->sendOtp($mobile, $otp);

        $data = [];

        if (config('app.debug')) {
            $data['otp'] = $otp;
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully.',
            'data' => $data,
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $mobile = $request->string('mobile')->toString();
        $otp = $request->string('otp')->toString();

        $this->otpService->verifyOtp($mobile, $otp);

        $user = $this->authService->findOrCreateUser($mobile);
        $token = $this->authService->createToken($user);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
            'data' => (object) [],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Authenticated user fetched successfully.',
            'data' => [
                'user' => $request->user(),
            ],
        ]);
    }
}
