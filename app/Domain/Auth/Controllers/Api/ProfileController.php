<?php

namespace App\Domain\Auth\Controllers\Api;

use App\Domain\Auth\Requests\ChangePasswordRequest;
use App\Domain\Auth\Requests\SetPinRequest;
use App\Domain\Auth\Requests\UpdateProfileRequest;
use App\Domain\Auth\Resources\UserResource;
use App\Domain\Auth\Services\AuthService;
use App\Domain\Auth\Services\TokenService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends BaseApiController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly TokenService $tokenService,
    ) {}

    /**
     * GET /api/v2/auth/me
     *
     * Get current authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['store', 'organization']);

        return $this->success(new UserResource($user));
    }

    /**
     * PUT /api/v2/auth/profile
     *
     * Update profile fields.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile(
            $request->user(),
            $request->validated(),
        );

        return $this->success(
            new UserResource($user),
            'Profile updated successfully.',
        );
    }

    /**
     * PUT /api/v2/auth/password
     *
     * Change password (requires current password).
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
        );

        return $this->success(message: 'Password changed successfully.');
    }

    /**
     * PUT /api/v2/auth/pin
     *
     * Set or update PIN (requires current password).
     */
    public function setPin(SetPinRequest $request): JsonResponse
    {
        $this->authService->setPin(
            $request->user(),
            $request->validated('pin'),
            $request->validated('current_password'),
        );

        return $this->success(message: 'PIN set successfully.');
    }

    /**
     * POST /api/v2/auth/refresh
     *
     * Refresh the current token.
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $token = $this->tokenService->refreshToken($request->user());

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Token refreshed successfully.');
    }
}
