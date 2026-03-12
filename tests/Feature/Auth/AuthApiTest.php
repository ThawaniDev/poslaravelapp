<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    // ─── Registration ────────────────────────────────────────────────

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v2/auth/register', [
            'name' => 'Test Owner',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+96891234567',
            'organization_name' => 'Test Org',
            'store_name' => 'Test Store',
            'country' => 'OM',
            'currency' => 'OMR',
            'business_type' => 'retail',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'role', 'store', 'organization'],
                    'token',
                    'token_type',
                ],
            ])
            ->assertJsonPath('data.user.role', 'owner')
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $this->assertDatabaseHas('organizations', ['email' => 'test@example.com']);
        $this->assertDatabaseHas('stores', ['currency' => 'OMR']);
    }

    public function test_register_requires_valid_data(): void
    {
        $response = $this->postJson('/api/v2/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $this->createUserWithOrgAndStore(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v2/auth/register', [
            'name' => 'Another User',
            'email' => 'taken@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ─── Login ───────────────────────────────────────────────────────

    public function test_user_can_login_with_email_password(): void
    {
        $user = $this->createUserWithOrgAndStore([
            'email' => 'login@example.com',
            'password_hash' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/v2/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                    'token_type',
                ],
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->createUserWithOrgAndStore([
            'email' => 'login@example.com',
            'password_hash' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v2/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $this->createUserWithOrgAndStore([
            'email' => 'inactive@example.com',
            'password_hash' => Hash::make('Password123!'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v2/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422);
    }

    // ─── PIN Login ───────────────────────────────────────────────────

    public function test_user_can_login_with_pin(): void
    {
        $user = $this->createUserWithOrgAndStore([
            'pin_hash' => Hash::make('1234'),
        ]);

        $response = $this->postJson('/api/v2/auth/login/pin', [
            'store_id' => $user->store_id,
            'pin' => '1234',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['user', 'token', 'token_type'],
            ]);
    }

    public function test_pin_login_fails_with_wrong_pin(): void
    {
        $user = $this->createUserWithOrgAndStore([
            'pin_hash' => Hash::make('1234'),
        ]);

        $response = $this->postJson('/api/v2/auth/login/pin', [
            'store_id' => $user->store_id,
            'pin' => '9999',
        ]);

        $response->assertStatus(422);
    }

    // ─── Profile ─────────────────────────────────────────────────────

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = $this->createUserWithOrgAndStore();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v2/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/v2/auth/me');

        $response->assertStatus(401);
    }

    public function test_user_can_update_profile(): void
    {
        $user = $this->createUserWithOrgAndStore();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v2/auth/profile', [
                'name' => 'Updated Name',
                'locale' => 'en',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.locale', 'en');
    }

    // ─── Password Change ─────────────────────────────────────────────

    public function test_user_can_change_password(): void
    {
        $user = $this->createUserWithOrgAndStore([
            'password_hash' => Hash::make('OldPassword123!'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v2/auth/password', [
                'current_password' => 'OldPassword123!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ]);

        $response->assertOk();

        // Verify new password works
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword456!', $user->password_hash));
    }

    public function test_change_password_fails_with_wrong_current(): void
    {
        $user = $this->createUserWithOrgAndStore([
            'password_hash' => Hash::make('OldPassword123!'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v2/auth/password', [
                'current_password' => 'WrongPassword!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ]);

        $response->assertStatus(422);
    }

    // ─── PIN ─────────────────────────────────────────────────────────

    public function test_user_can_set_pin(): void
    {
        $user = $this->createUserWithOrgAndStore([
            'password_hash' => Hash::make('Password123!'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v2/auth/pin', [
                'pin' => '5678',
                'pin_confirmation' => '5678',
                'current_password' => 'Password123!',
            ]);

        $response->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('5678', $user->pin_hash));
    }

    // ─── Logout ──────────────────────────────────────────────────────

    public function test_user_can_logout(): void
    {
        $user = $this->createUserWithOrgAndStore();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v2/auth/logout');

        $response->assertOk();

        // Token should be revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_user_can_logout_all_devices(): void
    {
        $user = $this->createUserWithOrgAndStore();
        $user->createToken('device-1');
        $user->createToken('device-2');
        $token = $user->createToken('device-3')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v2/auth/logout-all');

        $response->assertOk();

        $this->assertEquals(0, $user->tokens()->count());
    }

    // ─── Token Refresh ───────────────────────────────────────────────

    public function test_user_can_refresh_token(): void
    {
        $user = $this->createUserWithOrgAndStore();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v2/auth/refresh');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['token', 'token_type'],
            ]);

        // Old token should be revoked, new token issued
        $this->assertEquals(1, $user->tokens()->count());
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function createUserWithOrgAndStore(array $overrides = []): User
    {
        $org = Organization::create([
            'name' => 'Test Org',
            'name_ar' => 'منظمة تجريبية',
            'slug' => 'test-org-' . Str::random(6),
            'country' => 'OM',
            'is_active' => true,
        ]);

        $store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Test Store',
            'name_ar' => 'متجر تجريبي',
            'slug' => 'test-store-' . Str::random(6),
            'currency' => 'OMR',
            'locale' => 'ar',
            'timezone' => 'Asia/Muscat',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        return User::create(array_merge([
            'organization_id' => $org->id,
            'store_id' => $store->id,
            'name' => 'Test User',
            'email' => 'test-' . Str::random(6) . '@example.com',
            'password_hash' => Hash::make('password'),
            'role' => 'owner',
            'locale' => 'ar',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));
    }
}
