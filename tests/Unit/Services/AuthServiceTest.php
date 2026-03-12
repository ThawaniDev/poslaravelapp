<?php

namespace Tests\Unit\Services;

use App\Domain\Auth\DTOs\LoginDTO;
use App\Domain\Auth\DTOs\RegisterUserDTO;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Services\AuthService;
use App\Domain\Auth\Services\TokenService;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;
    private TokenService $tokenService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenService = app(TokenService::class);
        $this->authService = app(AuthService::class);
    }

    // ─── Registration ────────────────────────────────────────────

    public function test_register_creates_org_store_and_user(): void
    {
        $dto = new RegisterUserDTO(
            name: 'Test Owner',
            email: 'register@test.com',
            password: 'Password123!',
            country: 'OM',
            currency: 'OMR',
            businessType: 'retail',
        );

        $result = $this->authService->register($dto);

        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertNotEmpty($result['token']);
        $this->assertInstanceOf(Store::class, $result['store']);

        $this->assertEquals('owner', $result['user']->role->value);
        $this->assertTrue($result['store']->is_main_branch);
        $this->assertDatabaseHas('users', ['email' => 'register@test.com']);
        $this->assertDatabaseHas('organizations', ['email' => 'register@test.com']);
    }

    public function test_register_sets_correct_timezone_for_sa(): void
    {
        $dto = new RegisterUserDTO(
            name: 'SA Owner',
            email: 'sa@test.com',
            password: 'Password123!',
            country: 'SA',
            currency: 'SAR',
            businessType: 'retail',
        );

        $result = $this->authService->register($dto);
        $this->assertEquals('Asia/Riyadh', $result['store']->timezone);
    }

    public function test_register_sets_correct_timezone_for_om(): void
    {
        $dto = new RegisterUserDTO(
            name: 'OM Owner',
            email: 'om@test.com',
            password: 'Password123!',
            country: 'OM',
            currency: 'OMR',
            businessType: 'retail',
        );

        $result = $this->authService->register($dto);
        $this->assertEquals('Asia/Muscat', $result['store']->timezone);
    }

    public function test_register_auto_generates_org_name(): void
    {
        $dto = new RegisterUserDTO(
            name: 'Auto Gen',
            email: 'autogen@test.com',
            password: 'Password123!',
            country: 'OM',
            currency: 'OMR',
        );

        $result = $this->authService->register($dto);
        $this->assertStringContainsString("Auto Gen", $result['user']->organization->name);
    }

    // ─── Login ───────────────────────────────────────────────────

    public function test_login_with_valid_credentials(): void
    {
        $user = $this->seedUser(['password_hash' => Hash::make('ValidPass123!')]);

        $dto = new LoginDTO(
            email: $user->email,
            password: 'ValidPass123!',
        );

        $result = $this->authService->login($dto);

        $this->assertEquals($user->id, $result['user']->id);
        $this->assertNotEmpty($result['token']);
    }

    public function test_login_throws_on_wrong_password(): void
    {
        $user = $this->seedUser(['password_hash' => Hash::make('CorrectPass!')]);

        $this->expectException(ValidationException::class);

        $this->authService->login(new LoginDTO(
            email: $user->email,
            password: 'WrongPass!',
        ));
    }

    public function test_login_throws_for_inactive_user(): void
    {
        $user = $this->seedUser([
            'password_hash' => Hash::make('ValidPass123!'),
            'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);

        $this->authService->login(new LoginDTO(
            email: $user->email,
            password: 'ValidPass123!',
        ));
    }

    public function test_login_throws_for_nonexistent_email(): void
    {
        $this->expectException(ValidationException::class);

        $this->authService->login(new LoginDTO(
            email: 'nonexistent@test.com',
            password: 'anything',
        ));
    }

    public function test_login_updates_last_login_timestamp(): void
    {
        $user = $this->seedUser(['password_hash' => Hash::make('ValidPass!')]);

        $this->authService->login(new LoginDTO(
            email: $user->email,
            password: 'ValidPass!',
        ));

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
    }

    // ─── PIN Login ───────────────────────────────────────────────

    public function test_pin_login_matches_correct_user(): void
    {
        $orgStore = $this->seedOrgAndStore();

        $userA = User::create([
            'organization_id' => $orgStore['org']->id,
            'store_id' => $orgStore['store']->id,
            'name' => 'A',
            'email' => 'a@test.com',
            'password_hash' => Hash::make('p'),
            'pin_hash' => Hash::make('1111'),
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $userB = User::create([
            'organization_id' => $orgStore['org']->id,
            'store_id' => $orgStore['store']->id,
            'name' => 'B',
            'email' => 'b@test.com',
            'password_hash' => Hash::make('p'),
            'pin_hash' => Hash::make('2222'),
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $result = $this->authService->loginByPin($orgStore['store']->id, '2222');
        $this->assertEquals($userB->id, $result['user']->id);
    }

    public function test_pin_login_throws_for_no_match(): void
    {
        $orgStore = $this->seedOrgAndStore();

        $this->expectException(ValidationException::class);
        $this->authService->loginByPin($orgStore['store']->id, '9999');
    }

    // ─── Profile Update ──────────────────────────────────────────

    public function test_update_profile_only_allows_safe_fields(): void
    {
        $user = $this->seedUser(['email' => 'safe@test.com']);

        $updated = $this->authService->updateProfile($user, [
            'name' => 'New Name',
            'locale' => 'en',
            'email' => 'hacked@test.com', // should be ignored
            'role' => 'owner',            // should be ignored
        ]);

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('safe@test.com', $updated->email); // unchanged
    }

    // ─── Password Change ─────────────────────────────────────────

    public function test_change_password_verifies_current(): void
    {
        $user = $this->seedUser(['password_hash' => Hash::make('OldPass!')]);
        $user->createToken('test');

        $this->expectException(ValidationException::class);
        $this->authService->changePassword($user, 'WrongCurrent!', 'NewPass!');
    }

    public function test_change_password_hashes_new(): void
    {
        $user = $this->seedUser(['password_hash' => Hash::make('OldPass!')]);
        $user->createToken('test');

        $this->authService->changePassword($user, 'OldPass!', 'NewPass!');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPass!', $user->password_hash));
    }

    // ─── Logout ──────────────────────────────────────────────────

    public function test_logout_all_removes_all_tokens(): void
    {
        $user = $this->seedUser();
        $user->createToken('t1');
        $user->createToken('t2');
        $user->createToken('t3');

        $this->assertEquals(3, $user->tokens()->count());

        $this->authService->logoutAll($user);

        $this->assertEquals(0, $user->tokens()->count());
    }

    // ─── Token Service ───────────────────────────────────────────

    public function test_token_has_role_based_abilities(): void
    {
        $owner = $this->seedUser(['role' => 'owner']);
        $token = $this->tokenService->createToken($owner, 'test');
        $this->assertNotEmpty($token);

        // Owner gets all abilities
        $tokenModel = $owner->tokens()->first();
        $this->assertContains('*', $tokenModel->abilities);
    }

    public function test_cashier_token_has_limited_abilities(): void
    {
        $cashier = $this->seedUser(['role' => 'cashier']);
        $token = $this->tokenService->createToken($cashier, 'test');
        $this->assertNotEmpty($token);

        $tokenModel = $cashier->tokens()->first();
        $this->assertContains('pos:*', $tokenModel->abilities);
        $this->assertNotContains('*', $tokenModel->abilities);
    }

    public function test_refresh_token_revokes_old(): void
    {
        $user = $this->seedUser();
        $this->tokenService->createToken($user, 'original');
        $this->assertEquals(1, $user->tokens()->count());

        // Simulate authenticated context — set the current access token
        $tokenModel = $user->tokens()->first();
        $user->withAccessToken($tokenModel);

        $newToken = $this->tokenService->refreshToken($user);
        $this->assertNotEmpty($newToken);
        $this->assertEquals(1, $user->tokens()->count());
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function seedOrgAndStore(): array
    {
        $org = Organization::create([
            'name' => 'Test Org',
            'country' => 'OM',
            'is_active' => true,
        ]);

        $store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Test Store',
            'slug' => 'store-' . Str::random(6),
            'currency' => 'OMR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        return ['org' => $org, 'store' => $store];
    }

    private function seedUser(array $overrides = []): User
    {
        $orgStore = $this->seedOrgAndStore();

        return User::create(array_merge([
            'organization_id' => $orgStore['org']->id,
            'store_id' => $orgStore['store']->id,
            'name' => 'Test User',
            'email' => 'user-' . Str::random(6) . '@test.com',
            'password_hash' => Hash::make('password'),
            'role' => 'owner',
            'is_active' => true,
        ], $overrides));
    }
}
