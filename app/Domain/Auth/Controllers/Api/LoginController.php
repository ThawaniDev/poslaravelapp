<?php

namespace App\Domain\Auth\Controllers\Api;

use App\Domain\Auth\DTOs\LoginDTO;
use App\Domain\Auth\Requests\LoginByPinRequest;
use App\Domain\Auth\Requests\LoginRequest;
use App\Domain\Auth\Resources\UserResource;
use App\Domain\Auth\Services\AuthService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginController extends BaseApiController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * POST /api/v2/auth/login
     *
     * Login with email/password.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $dto = LoginDTO::fromRequest($request->validated());
        $result = $this->authService->login($dto);

        return $this->success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ], 'Login successful.');
    }

    /**
     * POST /api/v2/auth/login/pin
     *
     * Login with PIN (quick user switch at POS terminal).
     */
    public function loginByPin(LoginByPinRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->authService->loginByPin(
            $validated['store_id'],
            $validated['pin'],
        );

        return $this->success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ], 'PIN login successful.');
    }

    /**
     * POST /api/v2/auth/logout
     *
     * Logout — revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success(message: 'Logged out successfully.');
    }

    /**
     * POST /api/v2/auth/logout-all
     *
     * Logout from all devices.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $this->authService->logoutAll($request->user());

        return $this->success(message: 'Logged out from all devices.');
    }
}
