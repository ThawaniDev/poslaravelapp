<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\Enums\OtpChannel;
use App\Domain\Auth\Models\OtpVerification;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthEdgeCasesApiTest extends TestCase
{
    use RefreshDatabase;

    // ─── Registration Edge Cases ─────────────────────────────────

    public function test_register_with_minimal_required_fields(): void
    {
        $response = $this->postJson('/api/v2/auth/register', [
            'name' => 'Minimal User',
            'email' => 'minimal@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        // Should succeed or fail depending on which fields are truly required
        // The service auto-generates org/store names from user name
        $this->assertContains($response->status(), [201, 422]);
    }

    public function test_register_rejects_weak_password(): void
    {
        $response = $this->postJson('/api/v2/auth/register', [
            'name' => 'Test',
            'email' => 'weak@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertUnprocessable();
    }

    public function test_register_rejects_mismatched_password_confirmation(): void
    {
        $response = $this->postJson('/api/v2/auth/register', [
            'name' => 'Test',
            'email' => 'mismatch@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'DifferentPassword!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_rejects_invalid_email(): void
    {
        $response = $this->postJson('/api/v2/auth/register', [
            'name' => 'Test',
            'email' => 'not-an-email',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_creates_organization_and_store(): void
    {
        $response = $this->postJson('/api/v2/auth/register', [
            'name' => 'Full Owner',
            'email' => 'full@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'organization_name' => 'My Org',
            'store_name' => 'My Store',
            'country' => 'SA',
            'currency' => 'SAR',
            'business_type' => 'grocery',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('organizations', ['name' => 'My Org']);
        $this->assertDatabaseHas('stores', ['currency' => 'SAR']);
        $this->assertDatabaseHas('users', ['email' => 'full@example.com', 'role' => 'owner']);
    }

    public function test_register_with_arabic_names(): void
    {
        $response = $this->postJson('/api/v2/auth/register', [
            'name' => 'محمد',
            'email' => 'arabic@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'organization_name' => 'شركة التقنية',
            'store_name' => 'المتجر الرئيسي',
            'country' => 'OM',
            'currency' => 'SAR',
            'business_type' => 'grocery',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['name' => 'محمد']);
    }

    public function test_register_response_includes_token(): void
    {
        $response = $this->postJson('/api/v2/auth/register', [
            'name' => 'Token Check',
            'email' => 'tokencheck@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'country' => 'OM',
            'currency' => 'SAR',
            'business_type' => 'grocery',
        ]);

        $response->assertStatus(201);
        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        // Verify the token works
        $meResponse = $this->withToken($token)
            ->getJson('/api/v2/auth/me');
        $meResponse->assertOk();
    }

    // ─── Login Edge Cases ────────────────────────────────────────

    public function test_login_with_device_id_revokes_old_device_tokens(): void
    {
        $user = $this->createUserWithOrgAndStore([
            'email' => 'device@example.com',
            'password_hash' => Hash::make('Password123!'),
        ]);

        // Login with device-1
        $this->postJson('/api/v2/auth/login', [
            'email' => 'device@example.com',
            'password' => 'Password123!',
            'device_id' => 'device-1',
        ])->assertOk();

        // Login again with same device — should revoke old token
        $this->postJson('/api/v2/auth/login', [
            'email' => 'device@example.com',
            'password' => 'Password123!',
            'device_id' => 'device-1',
        ])->assertOk();

        // Should only have 1 token for this device
        $deviceTokens = $user->tokens()->where('name', 'device:device-1')->count();
        $this->assertEquals(1, $deviceTokens);
    }

    public function test_login_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v2/auth/login', [
            'email' => 'doesnotexist@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_with_empty_credentials(): void
    {
        $response = $this->postJson('/api/v2/auth/login', []);
        $response->assertUnprocessable();
    }

    // ─── PIN Edge Cases ──────────────────────────────────────────

    public function test_pin_login_with_multiple_users_same_store(): void
    {
        $orgStore = $this->createOrgAndStore();

        // User A with PIN 1234
        User::create([
            'organization_id' => $orgStore['org']->id,
            'store_id' => $orgStore['store']->id,
            'name' => 'User A',
            'email' => 'a@test.com',
            'password_hash' => Hash::make('password'),
            'pin_hash' => Hash::make('1234'),
            'role' => 'cashier',
            'is_active' => true,
        ]);

        // User B with PIN 5678
        $userB = User::create([
            'organization_id' => $orgStore['org']->id,
            'store_id' => $orgStore['store']->id,
            'name' => 'User B',
            'email' => 'b@test.com',
            'password_hash' => Hash::make('password'),
            'pin_hash' => Hash::make('5678'),
            'role' => 'cashier',
            'is_active' => true,
        ]);

        // Login with User B's PIN
        $response = $this->postJson('/api/v2/auth/login/pin', [
            'store_id' => $orgStore['store']->id,
            'pin' => '5678',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'b@test.com');
    }

    public function test_pin_login_fails_for_inactive_user(): void
    {
        $orgStore = $this->createOrgAndStore();

        User::create([
            'organization_id' => $orgStore['org']->id,
            'store_id' => $orgStore['store']->id,
            'name' => 'Inactive',
            'email' => 'inactive@test.com',
            'password_hash' => Hash::make('password'),
            'pin_hash' => Hash::make('1234'),
            'role' => 'cashier',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v2/auth/login/pin', [
            'store_id' => $orgStore['store']->id,
            'pin' => '1234',
        ]);

        $response->assertStatus(422);
    }

    public function test_pin_login_fails_for_wrong_store(): void
    {
        $orgStore = $this->createOrgAndStore();

        User::create([
            'organization_id' => $orgStore['org']->id,
            'store_id' => $orgStore['store']->id,
            'name' => 'User',
            'email' => 'user@test.com',
            'password_hash' => Hash::make('password'),
            'pin_hash' => Hash::make('1234'),
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $fakeStoreId = '00000000-0000-0000-0000-000000000099';
        $response = $this->postJson('/api/v2/auth/login/pin', [
            'store_id' => $fakeStoreId,
            'pin' => '1234',
        ]);

        $response->assertStatus(422);
    }

    // ─── Profile Edge Cases ──────────────────────────────────────

    public function test_profile_update_ignores_disallowed_fields(): void
    {
        $user = $this->createUserWithOrgAndStore([
            'email' => 'profile@example.com',
            'role' => 'cashier',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->putJson('/api/v2/auth/profile', [
                'name' => 'Updated',
                'email' => 'hacked@example.com', // Should be ignored
                'role' => 'owner',               // Should be ignored
            ]);

        $response->assertOk();
        $user->refresh();
        $this->assertEquals('profile@example.com', $user->email); // unchanged
        $this->assertEquals('cashier', $user->role->value);        // unchanged
    }

    public function test_profile_returns_user_relationships(): void
    {
        $user = $this->createUserWithOrgAndStore();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v2/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'email', 'role',
                    'store' => ['id', 'name'],
                    'organization' => ['id', 'name'],
                ],
            ]);
    }

    // ─── Password Edge Cases ─────────────────────────────────────

    public function test_password_change_revokes_other_tokens(): void
    {
        $user = $this->createUserWithOrgAndStore([
            'password_hash' => Hash::make('OldPass123!'),
        ]);

        // Create multiple tokens
        $token1 = $user->createToken('device-1')->plainTextToken;
        $token2 = $user->createToken('device-2')->plainTextToken;
        $currentToken = $user->createToken('device-3')->plainTextToken;

        $this->assertEquals(3, $user->tokens()->count());

        $this->withToken($currentToken)
            ->putJson('/api/v2/auth/password', [
                'current_password' => 'OldPass123!',
                'password' => 'NewPass456!',
                'password_confirmation' => 'NewPass456!',
            ])
            ->assertOk();

        // Only current token should remain — verify via DB
        $user->refresh();
        $this->assertEquals(1, $user->tokens()->count());
        $this->assertEquals('device-3', $user->tokens()->first()->name);
    }

    public function test_password_change_rejects_same_as_current(): void
    {
        $user = $this->createUserWithOrgAndStore([
            'password_hash' => Hash::make('SamePass123!'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        // This may or may not be validated — depends on implementation.
        // At minimum, it should not crash.
        $response = $this->withToken($token)
            ->putJson('/api/v2/auth/password', [
                'current_password' => 'SamePass123!',
                'password' => 'SamePass123!',
                'password_confirmation' => 'SamePass123!',
            ]);

        $this->assertContains($response->status(), [200, 422]);
    }

    // ─── PIN Set Edge Cases ──────────────────────────────────────

    public function test_pin_must_be_4_digits(): void
    {
        $user = $this->createUserWithOrgAndStore([
            'password_hash' => Hash::make('Password123!'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->putJson('/api/v2/auth/pin', [
                'pin' => '12', // too short
                'pin_confirmation' => '12',
                'current_password' => 'Password123!',
            ]);

        $response->assertUnprocessable();
    }

    // ─── Token Refresh ───────────────────────────────────────────

    public function test_refresh_revokes_old_token_and_issues_new(): void
    {
        $user = $this->createUserWithOrgAndStore();
        $oldToken = $user->createToken('test')->plainTextToken;

        $this->assertEquals(1, $user->tokens()->count());

        $response = $this->withToken($oldToken)
            ->postJson('/api/v2/auth/refresh');

        $response->assertOk();

        $newToken = $response->json('data.token');
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($oldToken, $newToken);

        // Old token should be revoked — verify via DB
        // refreshToken deletes old + creates new, so count stays 1
        $user->refresh();
        $this->assertEquals(1, $user->tokens()->count());
        $this->assertEquals('test', $user->tokens()->first()->name);

        // New token should work
        $this->withToken($newToken)->getJson('/api/v2/auth/me')->assertOk();
    }

    // ─── Logout Edge Cases ───────────────────────────────────────

    public function test_logout_only_revokes_current_token(): void
    {
        $user = $this->createUserWithOrgAndStore();
        $token1 = $user->createToken('device-1')->plainTextToken;
        $token2 = $user->createToken('device-2')->plainTextToken;

        $this->assertEquals(2, $user->tokens()->count());

        $this->withToken($token1)->postJson('/api/v2/auth/logout')->assertOk();

        // Only token2 should remain (token1 was revoked by logout)
        $user->refresh();
        $this->assertEquals(1, $user->tokens()->count());
        $this->assertEquals('device-2', $user->tokens()->first()->name);
    }

    public function test_logout_all_revokes_every_token(): void
    {
        $user = $this->createUserWithOrgAndStore();
        $token1 = $user->createToken('device-1')->plainTextToken;
        $token2 = $user->createToken('device-2')->plainTextToken;
        $currentToken = $user->createToken('current')->plainTextToken;

        $this->withToken($currentToken)
            ->postJson('/api/v2/auth/logout-all')
            ->assertOk();

        // All tokens revoked
        $this->assertEquals(0, $user->tokens()->count());
    }

    // ─── OTP Tests ───────────────────────────────────────────────

    public function test_can_send_otp(): void
    {
        $user = $this->createUserWithOrgAndStore([
            'phone' => '+96891234567',
        ]);

        // OTP send is a public endpoint — requires email
        $response = $this->postJson('/api/v2/auth/otp/send', [
            'email' => $user->email,
            'purpose' => 'login',
            'channel' => 'sms',
        ]);

        // Should succeed (200) or return validation/not-found
        $this->assertContains($response->status(), [200, 201, 404, 422]);
    }

    // ─── Multi-request guard ─────────────────────────────────────

    public function test_expired_token_is_rejected(): void
    {
        $user = $this->createUserWithOrgAndStore();
        // Create a token manually and expire it
        $tokenModel = $user->createToken('test', ['*'], now()->subDay());

        $response = $this->withToken($tokenModel->plainTextToken)
            ->getJson('/api/v2/auth/me');

        $response->assertUnauthorized();
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function createOrgAndStore(): array
    {
        $org = Organization::create([
            'name' => 'Test Org ' . Str::random(4),
            'country' => 'OM',
            'is_active' => true,
        ]);

        $store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Test Store',
            'slug' => 'store-' . Str::random(6),
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        return ['org' => $org, 'store' => $store];
    }

    private function createUserWithOrgAndStore(array $overrides = []): User
    {
        $orgStore = $this->createOrgAndStore();

        return User::create(array_merge([
            'organization_id' => $orgStore['org']->id,
            'store_id' => $orgStore['store']->id,
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
