<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bike>
 */
class BikeFactory extends Factory
{
    /** The delivery bikes actually in service on this kind of fleet. */
    protected array $models = [
        'TVS HLX 150',
        'TVS HLX 125',
        'Honda CG 125',
        'Bajaj Boxer 100',
        'Bajaj CT 100',
        'Yamaha Crux 110',
        'Haojue HJ 125',
    ];

    public function definition(): array
    {
        return [
            'reg' => strtoupper($this->faker->unique()->bothify('???')).' '.$this->faker->unique()->numerify('####'),
            'model' => $this->faker->randomElement($this->models),
            'rider_id' => null,
            'service_interval_km' => $this->faker->randomElement([2500, 3000, 3000, 4000]),
            'service_interval_months' => 6,
            'is_active' => true,
        ];
    }

    /** No time-based interval, so service status is driven by distance alone. */
    public function distanceOnly(): static
    {
        return $this->state(fn () => ['service_interval_months' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
