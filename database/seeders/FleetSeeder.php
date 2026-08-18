<?php

namespace Database\Seeders;

use App\Models\Bike;
use App\Models\Reading;
use App\Models\Rider;
use App\Models\ServiceRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A believable Harare delivery fleet, built so every status the dashboard can
 * show is actually present — otherwise the UI gets built against a fleet where
 * nothing is ever overdue.
 */
class FleetSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@ingo.local'],
            ['name' => 'Fleet Admin', 'password' => Hash::make('password'), 'role' => 'admin'],
        );

        User::updateOrCreate(
            ['email' => 'clerk@ingo.local'],
            ['name' => 'Yard Clerk', 'password' => Hash::make('password'), 'role' => 'clerk'],
        );

        // name, licence expiry (null = missing on file)
        $riderSpec = [
            ['Tendai Moyo', '+2 years'],
            ['Farai Chikwanha', '+18 months'],
            ['Blessing Ncube', '+21 days'],     // expiring soon
            ['Tapiwa Mutasa', '-40 days'],      // expired
            ['Kudzai Dube', '+3 years'],
            ['Simba Marufu', null],             // no date on file
            ['Nyasha Banda', '+8 months'],
            ['Tinashe Gwara', '+11 days'],      // expiring soon
        ];

        $riders = collect($riderSpec)->map(fn (array $spec) => Rider::updateOrCreate(
            ['name' => $spec[0]],
            [
                'phone' => '077'.random_int(1_000_000, 9_999_999),
                'license_number' => strtoupper(fake()->bothify('??######')),
                'license_expiry' => $spec[1] ? now()->modify($spec[1])->toDateString() : null,
                'is_active' => true,
            ],
        ));

        /*
         * reg, model, interval km, km/week, km since last service, months since
         * last service, extra days since last service. The last three columns are
         * what place each bike in its status bucket, so the spread is deliberate
         * rather than random. The extra-days column exists because a whole number
         * of months lands just outside the 30-day warning window.
         */
        $bikeSpec = [
            ['AEJ 1234', 'TVS HLX 150',     3000, 420,  900, 2, 0],  // ok
            ['AEK 5521', 'TVS HLX 125',     3000, 380, 2100, 3, 0],  // ok
            ['ACY 8890', 'Honda CG 125',    3000, 510, 2760, 4, 0],  // due soon (distance)
            ['ADB 3312', 'Bajaj Boxer 100', 2500, 340, 2500, 5, 0],  // overdue (distance)
            ['AEM 7745', 'Bajaj CT 100',    3000, 290, 3480, 6, 0],  // overdue (distance)
            ['ACD 1109', 'Yamaha Crux 110', 3000, 250, 1200, 6, 0],  // overdue (time)
            ['AEF 6620', 'Haojue HJ 125',   4000, 600, 1800, 1, 0],  // ok
            ['ADH 2287', 'TVS HLX 150',     3000, 445, 1450, 5, 17], // due soon (time)
            ['ACR 9034', 'Honda CG 125',    3000, 300,  600, 2, 0],  // ok
            ['AEB 4417', 'TVS HLX 125',     3000, 390, 2740, 3, 0],  // due soon (distance)
        ];

        foreach ($bikeSpec as $i => [$reg, $model, $intervalKm, $kmPerWeek, $kmSinceService, $monthsSinceService, $extraDays]) {
            $bike = Bike::updateOrCreate(
                ['reg' => $reg],
                [
                    'model' => $model,
                    'rider_id' => $riders[$i % $riders->count()]->id,
                    'service_interval_km' => $intervalKm,
                    'service_interval_months' => 6,
                    'is_active' => true,
                ],
            );

            $bike->readings()->delete();
            $bike->serviceRecords()->delete();

            $servicedOn = now()->subMonths($monthsSinceService)->subDays($extraDays)->startOfDay();
            $servicedAtKm = random_int(6_000, 40_000);

            ServiceRecord::create([
                'bike_id' => $bike->id,
                'serviced_on' => $servicedOn->toDateString(),
                'mileage' => $servicedAtKm,
                'cost' => random_int(20, 90),
                'notes' => 'Oil, filter, chain adjustment.',
                'recorded_by' => $admin->id,
            ]);

            /*
             * Twelve weeks of Monday readings ending on the most recent Monday,
             * walked backwards from today's odometer so the fleet history is
             * consistent with the status each bike is meant to be in.
             */
            $currentKm = $servicedAtKm + $kmSinceService;

            for ($week = 0; $week < 12; $week++) {
                $date = now()->startOfWeek()->subWeeks($week);

                if ($date->lt($servicedOn)) {
                    break;
                }

                Reading::create([
                    'bike_id' => $bike->id,
                    'recorded_on' => $date->toDateString(),
                    'mileage' => max($servicedAtKm, $currentKm - ($week * $kmPerWeek)),
                    'recorded_by' => $admin->id,
                ]);
            }
        }

        $this->command?->info('Seeded '.Rider::count().' riders and '.Bike::count().' bikes with '.Reading::count().' readings.');
    }
}
