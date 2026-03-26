<?php

namespace Database\Factories;

use App\Domain\Auth\Enums\UserRole;
use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Auth\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+968########'),
            'password_hash' => Hash::make('password'),
            'role' => UserRole::Cashier,
            'locale' => 'ar',
            'is_active' => true,
            'email_verified_at' => now(),
            'last_login_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => UserRole::Owner]);
    }

    public function cashier(): static
    {
        return $this->state(fn () => ['role' => UserRole::Cashier]);
    }

    public function branchManager(): static
    {
        return $this->state(fn () => ['role' => UserRole::BranchManager]);
    }

    public function withPin(string $pin = '1234'): static
    {
        return $this->state(fn () => ['pin_hash' => Hash::make($pin)]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function withOrganizationAndStore(): static
    {
        return $this->state(function () {
            $org = Organization::create([
                'name' => fake()->company(),
                'name_ar' => fake()->company(),
                'slug' => Str::slug(fake()->company()) . '-' . Str::random(4),
                'country' => 'OM',
                'is_active' => true,
            ]);

            $store = Store::create([
                'organization_id' => $org->id,
                'name' => $org->name . ' Main',
                'name_ar' => $org->name_ar,
                'slug' => Str::slug($org->name) . '-main-' . Str::random(4),
                'currency' => 'SAR',
                'locale' => 'ar',
                'timezone' => 'Asia/Muscat',
                'is_active' => true,
                'is_main_branch' => true,
            ]);

            return [
                'organization_id' => $org->id,
                'store_id' => $store->id,
            ];
        });
    }
}
