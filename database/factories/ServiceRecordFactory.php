<?php

namespace Database\Factories;

use App\Models\Bike;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceRecord>
 */
class ServiceRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bike_id' => Bike::factory(),
            'serviced_on' => now()->subMonths(2)->toDateString(),
            'mileage' => $this->faker->numberBetween(4_000, 50_000),
            'cost' => $this->faker->randomFloat(2, 15, 120),
            'notes' => null,
            'recorded_by' => null,
        ];
    }

    public function on(string $date): static
    {
        return $this->state(fn () => ['serviced_on' => $date]);
    }

    public function mileage(int $km): static
    {
        return $this->state(fn () => ['mileage' => $km]);
    }
}
