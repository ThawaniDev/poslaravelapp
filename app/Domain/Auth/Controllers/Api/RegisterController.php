<?php

namespace App\Domain\Auth\Controllers\Api;

use App\Domain\Auth\DTOs\RegisterUserDTO;
use App\Domain\Auth\Requests\RegisterRequest;
use App\Domain\Auth\Resources\AuthTokenResource;
use App\Domain\Auth\Resources\UserResource;
use App\Domain\Auth\Services\AuthService;
use App\Http\Controllers\Api\BaseApiController;

class RegisterController extends BaseApiController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * POST /api/v2/auth/register
     *
     * Register a new owner account with organization and store.
     */
    public function __invoke(RegisterRequest $request)
    {
        $dto = RegisterUserDTO::fromRequest($request->validated());
        $result = $this->authService->register($dto);

        return $this->created([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ], 'Account registered successfully.');
    }
}
