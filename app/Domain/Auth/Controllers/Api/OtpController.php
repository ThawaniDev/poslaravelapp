<?php

namespace App\Domain\Auth\Controllers\Api;

use App\Domain\Auth\Enums\OtpChannel;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Requests\SendOtpRequest;
use App\Domain\Auth\Requests\VerifyOtpRequest;
use App\Domain\Auth\Resources\UserResource;
use App\Domain\Auth\Services\AuthService;
use App\Domain\Auth\Services\OtpService;
use App\Domain\Auth\Services\TokenService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;

class OtpController extends BaseApiController
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly TokenService $tokenService,
    ) {}

    /**
     * POST /api/v2/auth/otp/send
     *
     * Send OTP to user's phone or email.
     */
    public function send(SendOtpRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->firstOrFail();

        $channel = OtpChannel::tryFrom($request->validated('channel', 'sms')) ?? OtpChannel::Sms;
        $purpose = $request->validated('purpose', 'login');

        $result = $this->otpService->sendOtp($user, $purpose, $channel);

        return $this->success($result, 'OTP sent successfully.');
    }

    /**
     * POST /api/v2/auth/otp/verify
     *
     * Verify OTP and return auth token (for OTP-based login).
     */
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $otpRecord = $this->otpService->verifyOtp(
            $request->validated('otp_id'),
            $request->validated('otp'),
        );

        $user = $otpRecord->user;

        // For login purpose, issue a token
        if ($otpRecord->purpose === 'login') {
            $user->touchLastLogin();
            $token = $this->tokenService->createToken($user, 'otp-login');

            return $this->success([
                'user' => new UserResource($user->load(['store', 'organization'])),
                'token' => $token,
                'token_type' => 'Bearer',
            ], 'OTP verified and logged in.');
        }

        // For email_verify purpose
        if ($otpRecord->purpose === 'email_verify') {
            $user->markEmailAsVerified();

            return $this->success(
                new UserResource($user),
                'Email verified successfully.',
            );
        }

        // For password_reset purpose, return a temp token for reset
        if ($otpRecord->purpose === 'password_reset') {
            $resetToken = $this->tokenService->createToken($user, 'password-reset');

            return $this->success([
                'reset_token' => $resetToken,
                'token_type' => 'Bearer',
            ], 'OTP verified. Use token to reset password.');
        }

        return $this->success(message: 'OTP verified successfully.');
    }
}
