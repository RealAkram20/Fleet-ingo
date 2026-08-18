<?php

namespace Database\Factories;

use App\Models\Bike;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reading>
 */
class ReadingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bike_id' => Bike::factory(),
            'recorded_on' => now()->toDateString(),
            'mileage' => $this->faker->numberBetween(5_000, 60_000),
            'note' => null,
            'recorded_by' => null,
        ];
    }

    public function on(string $date): static
    {
        return $this->state(fn () => ['recorded_on' => $date]);
    }

    public function mileage(int $km): static
    {
        return $this->state(fn () => ['mileage' => $km]);
    }
}
