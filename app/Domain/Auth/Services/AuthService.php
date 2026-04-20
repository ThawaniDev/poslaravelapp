<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\DTOs\LoginDTO;
use App\Domain\Auth\DTOs\RegisterUserDTO;
use App\Domain\Auth\Enums\UserRole;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly TokenService $tokenService,
    ) {}

    /**
     * Register a new owner with organization and store.
     *
     * @return array{user: User, token: string, store: Store}
     */
    public function register(RegisterUserDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {
            // 1. Create organization
            $orgName = $dto->organizationName ?? $dto->name . "'s Business";
            $organization = Organization::create([
                'name' => $orgName,
                'name_ar' => $dto->organizationNameAr ?? $orgName,
                'slug' => Str::slug($orgName) . '-' . Str::random(6),
                'country' => $dto->country,
                'business_type' => $dto->businessType,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'is_active' => true,
            ]);

            // 2. Create main store/branch
            $storeName = $dto->storeName ?? $orgName;
            $store = Store::create([
                'organization_id' => $organization->id,
                'name' => $storeName,
                'name_ar' => $dto->storeNameAr ?? $storeName,
                'slug' => Str::slug($storeName) . '-' . Str::random(6),
                'timezone' => $dto->country === 'SA' ? 'Asia/Riyadh' : 'Asia/Muscat',
                'currency' => $dto->currency,
                'locale' => $dto->locale,
                'business_type' => $dto->businessType,
                'is_active' => true,
                'is_main_branch' => true,
            ]);

            // 3. Create owner user
            $user = User::create([
                'organization_id' => $organization->id,
                'store_id' => $store->id,
                'name' => $dto->name,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'password_hash' => Hash::make($dto->password),
                'role' => UserRole::Owner,
                'locale' => $dto->locale,
                'is_active' => true,
                'last_login_at' => now(),
            ]);

            // 4. Create token
            $token = $this->tokenService->createToken($user, 'registration');

            return [
                'user' => $user->load(['store', 'organization']),
                'token' => $token,
                'store' => $store,
            ];
        });
    }

    /**
     * Login with email + password.
     *
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function login(LoginDTO $dto): array
    {
        $user = User::where('email', $dto->email)->first();

        if (! $user || ! Hash::check($dto->password, $user->password_hash)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => [__('Your account has been deactivated. Contact support.')],
            ]);
        }

        // Revoke old tokens for this device or all if no device info
        if ($dto->deviceId) {
            $user->tokens()->where('name', 'device:' . $dto->deviceId)->delete();
        }

        $user->touchLastLogin();

        $tokenName = $dto->deviceId
            ? 'device:' . $dto->deviceId
            : 'api:' . Str::random(8);

        $token = $this->tokenService->createToken($user, $tokenName);

        return [
            'user' => $user->load(['store', 'organization']),
            'token' => $token,
        ];
    }

    /**
     * Login with PIN (quick switch — for users already authenticated on device).
     *
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function loginByPin(string $storeId, string $pin): array
    {
        // PIN brute-force protection: max 5 attempts per store per 15 minutes
        $cacheKey = "pin_attempts:{$storeId}";
        $attempts = (int) Cache::get($cacheKey, 0);

        if ($attempts >= 5) {
            throw ValidationException::withMessages([
                'pin' => [__('auth.pin_locked')],
            ]);
        }

        // Find user by PIN within the store
        $users = User::where('store_id', $storeId)
            ->where('is_active', true)
            ->get();

        $matchedUser = null;
        foreach ($users as $user) {
            if ($user->pin_hash && Hash::check($pin, $user->pin_hash)) {
                $matchedUser = $user;
                break;
            }
        }

        if (! $matchedUser) {
            Cache::put($cacheKey, $attempts + 1, now()->addMinutes(15));
            throw ValidationException::withMessages([
                'pin' => [__('auth.invalid_pin')],
            ]);
        }

        // Reset attempts on success
        Cache::forget($cacheKey);

        $matchedUser->touchLastLogin();
        $token = $this->tokenService->createToken($matchedUser, 'pin-login');

        return [
            'user' => $matchedUser->load(['store', 'organization']),
            'token' => $token,
        ];
    }

    /**
     * Logout — revoke the current token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * Logout from all devices.
     */
    public function logoutAll(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Update user profile.
     */
    public function updateProfile(User $user, array $data): User
    {
        $allowedFields = ['name', 'phone', 'locale'];

        $updateData = array_intersect_key($data, array_flip($allowedFields));

        if (! empty($data['password'])) {
            $updateData['password_hash'] = Hash::make($data['password']);
        }

        if (! empty($data['pin'])) {
            $updateData['pin_hash'] = Hash::make($data['pin']);
        }

        $user->update($updateData);

        return $user->fresh(['store', 'organization']);
    }

    /**
     * Change password with old password verification.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password_hash)) {
            throw ValidationException::withMessages([
                'current_password' => [__('The current password is incorrect.')],
            ]);
        }

        $user->update([
            'password_hash' => Hash::make($newPassword),
        ]);

        // Revoke all other tokens
        $currentTokenId = $user->currentAccessToken()?->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();
    }

    /**
     * Set or update PIN.
     */
    public function setPin(User $user, string $pin, ?string $currentPassword = null): void
    {
        // Require password verification for setting PIN
        if ($currentPassword && ! Hash::check($currentPassword, $user->password_hash)) {
            throw ValidationException::withMessages([
                'current_password' => [__('The current password is incorrect.')],
            ]);
        }

        $user->update([
            'pin_hash' => Hash::make($pin),
        ]);
    }
}
