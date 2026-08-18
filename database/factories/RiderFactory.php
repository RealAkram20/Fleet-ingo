<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rider>
 */
class RiderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'phone' => '077'.$this->faker->numerify('#######'),
            'license_number' => strtoupper($this->faker->bothify('??######')),
            'license_expiry' => $this->faker->dateTimeBetween('+2 months', '+4 years'),
            'is_active' => true,
        ];
    }

    public function licenceExpired(): static
    {
        return $this->state(fn () => [
            'license_expiry' => now()->subDays(fake()->numberBetween(1, 400)),
        ]);
    }

    public function licenceExpiringSoon(): static
    {
        return $this->state(fn () => [
            'license_expiry' => now()->addDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function licenceUnknown(): static
    {
        return $this->state(fn () => ['license_expiry' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
