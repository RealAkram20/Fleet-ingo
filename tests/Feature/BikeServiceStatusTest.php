<?php

namespace Tests\Feature;

use App\Models\Bike;
use App\Models\Reading;
use App\Models\ServiceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BikeServiceStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Every assertion below is date-sensitive, so pin "now".
        Carbon::setTestNow('2026-08-18 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Builds a bike whose last service and current odometer are exactly where
     * the test wants them, so the maths under test is the only variable.
     */
    private function bike(
        int $servicedAtKm,
        int $currentKm,
        ?string $servicedOn = '2026-06-18',
        int $intervalKm = 3000,
        ?int $intervalMonths = 6,
    ): Bike {
        $bike = Bike::factory()->create([
            'service_interval_km' => $intervalKm,
            'service_interval_months' => $intervalMonths,
        ]);

        if ($servicedOn !== null) {
            ServiceRecord::factory()->for($bike)->create([
                'serviced_on' => $servicedOn,
                'mileage' => $servicedAtKm,
            ]);
        }

        Reading::factory()->for($bike)->create([
            'recorded_on' => '2026-08-18',
            'mileage' => $currentKm,
        ]);

        return $bike->fresh();
    }

    public function test_current_mileage_is_the_highest_reading_not_the_latest_row(): void
    {
        $bike = Bike::factory()->create();

        // Inserted out of order on purpose — the newest row is not the newest date.
        Reading::factory()->for($bike)->create(['recorded_on' => '2026-08-10', 'mileage' => 12_000]);
        Reading::factory()->for($bike)->create(['recorded_on' => '2026-08-03', 'mileage' => 11_400]);

        $this->assertSame(12_000, $bike->fresh()->currentMileage());
    }

    public function test_a_bike_with_no_readings_reports_zero_rather_than_failing(): void
    {
        $bike = Bike::factory()->create();

        $this->assertSame(0, $bike->currentMileage());
        $this->assertSame(0, $bike->lastServiceMileage());
        $this->assertNull($bike->lastServicedOn());
    }

    public function test_km_until_service_counts_down_from_the_last_service(): void
    {
        $bike = $this->bike(servicedAtKm: 9_000, currentKm: 10_500);

        // 3000 interval, 1500 covered since the service
        $this->assertSame(1_500, $bike->kmUntilService());
        $this->assertSame(12_000, $bike->nextServiceAtKm());
    }

    public function test_status_is_ok_when_well_inside_the_interval(): void
    {
        $status = $this->bike(servicedAtKm: 9_000, currentKm: 10_500)->serviceStatus();

        $this->assertSame('ok', $status['level']);
        $this->assertSame('OK', $status['label']);
    }

    public function test_status_is_due_soon_at_exactly_the_threshold(): void
    {
        // 300 km remaining is the boundary, and it must count as Due Soon.
        $status = $this->bike(servicedAtKm: 9_000, currentKm: 11_700)->serviceStatus();

        $this->assertSame('warn', $status['level']);
        $this->assertSame('Due Soon', $status['label']);
        $this->assertSame(300, $status['km_remaining']);
    }

    public function test_status_is_still_ok_one_km_before_the_threshold(): void
    {
        $status = $this->bike(servicedAtKm: 9_000, currentKm: 11_699)->serviceStatus();

        $this->assertSame('ok', $status['level']);
        $this->assertSame(301, $status['km_remaining']);
    }

    public function test_status_is_overdue_the_moment_the_interval_is_reached(): void
    {
        // Exactly at the interval is overdue, not due soon.
        $status = $this->bike(servicedAtKm: 9_000, currentKm: 12_000)->serviceStatus();

        $this->assertSame('bad', $status['level']);
        $this->assertSame('Overdue', $status['label']);
        $this->assertSame(0, $status['km_remaining']);
    }

    public function test_overdue_reports_how_far_past_the_interval_it_is(): void
    {
        $status = $this->bike(servicedAtKm: 9_000, currentKm: 12_450)->serviceStatus();

        $this->assertSame('bad', $status['level']);
        $this->assertSame(-450, $status['km_remaining']);
    }

    public function test_a_bike_parked_for_months_is_overdue_on_time_alone(): void
    {
        // Barely moved — distance says OK — but the 6-month interval lapsed.
        // This is the case the original app could not see at all.
        $bike = $this->bike(
            servicedAtKm: 9_000,
            currentKm: 9_200,
            servicedOn: '2026-01-10',
        );

        $status = $bike->serviceStatus();

        $this->assertSame('bad', $status['level']);
        $this->assertSame('time', $status['driver']);
        $this->assertSame(2_800, $status['km_remaining']);
    }

    public function test_time_based_service_warns_inside_thirty_days(): void
    {
        // Serviced 2026-03-01 + 6 months = 2026-09-01, which is 14 days out.
        $bike = $this->bike(
            servicedAtKm: 9_000,
            currentKm: 9_200,
            servicedOn: '2026-03-01',
        );

        $status = $bike->serviceStatus();

        $this->assertSame('warn', $status['level']);
        $this->assertSame('time', $status['driver']);
        $this->assertSame(14, $status['days_remaining']);
    }

    public function test_the_worse_of_distance_and_time_wins(): void
    {
        // Distance is overdue while time is merely due soon — distance must win.
        $bike = $this->bike(
            servicedAtKm: 9_000,
            currentKm: 13_000,
            servicedOn: '2026-03-01',
        );

        $status = $bike->serviceStatus();

        $this->assertSame('bad', $status['level']);
        $this->assertSame('distance', $status['driver']);
    }

    public function test_a_bike_without_a_months_interval_ignores_elapsed_time(): void
    {
        $bike = $this->bike(
            servicedAtKm: 9_000,
            currentKm: 9_200,
            servicedOn: '2024-01-01',
            intervalMonths: null,
        );

        $status = $bike->serviceStatus();

        $this->assertSame('ok', $status['level']);
        $this->assertNull($status['days_remaining']);
    }

    public function test_a_never_serviced_bike_measures_from_zero(): void
    {
        $bike = $this->bike(
            servicedAtKm: 0,
            currentKm: 3_400,
            servicedOn: null,
        );

        $status = $bike->serviceStatus();

        // 3400 km covered against a 3000 km interval, and no service date to
        // measure time from, so distance alone drives it.
        $this->assertSame('bad', $status['level']);
        $this->assertSame('distance', $status['driver']);
        $this->assertNull($status['days_remaining']);
    }

    public function test_with_fleet_stats_matches_the_per_bike_lookups_in_one_query(): void
    {
        $this->bike(servicedAtKm: 9_000, currentKm: 11_800);
        $this->bike(servicedAtKm: 4_000, currentKm: 4_100);

        \DB::enableQueryLog();
        $bikes = Bike::withFleetStats()->get();
        $queries = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $this->assertCount(2, $bikes);
        $this->assertSame(1, $queries, 'The dashboard aggregate should cost exactly one query.');

        $withStats = $bikes->firstWhere('current_mileage', 11_800);
        $this->assertSame(9_000, $withStats->lastServiceMileage());
        $this->assertSame('warn', $withStats->serviceStatus()['level']);
    }
}
