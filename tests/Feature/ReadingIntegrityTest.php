<?php

namespace Tests\Feature;

use App\Models\Bike;
use App\Models\Reading;
use App\Models\Rider;
use App\Models\ServiceRecord;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guarantees that make the Firestore version's data problems impossible here.
 */
class ReadingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_bike_cannot_have_two_readings_on_the_same_day(): void
    {
        $bike = Bike::factory()->create();
        Reading::factory()->for($bike)->create(['recorded_on' => '2026-08-18', 'mileage' => 12_000]);

        $this->expectException(QueryException::class);

        Reading::factory()->for($bike)->create(['recorded_on' => '2026-08-18', 'mileage' => 12_400]);
    }

    public function test_two_bikes_may_share_a_reading_date(): void
    {
        Reading::factory()->for(Bike::factory())->create(['recorded_on' => '2026-08-18']);
        Reading::factory()->for(Bike::factory())->create(['recorded_on' => '2026-08-18']);

        $this->assertSame(2, Reading::count());
    }

    public function test_registration_numbers_are_unique(): void
    {
        Bike::factory()->create(['reg' => 'AEJ 1234']);

        $this->expectException(QueryException::class);

        Bike::factory()->create(['reg' => 'AEJ 1234']);
    }

    /**
     * The fix for the original app's worst data bug: mileage lived on the bike as
     * well as in the readings, so a typo could never be undone.
     */
    public function test_correcting_a_mistyped_reading_corrects_the_current_mileage(): void
    {
        $bike = Bike::factory()->create(['service_interval_km' => 3000, 'service_interval_months' => null]);
        ServiceRecord::factory()->for($bike)->create(['serviced_on' => '2026-06-18', 'mileage' => 9_000]);

        // A transposed digit: 145200 instead of 14520.
        $reading = Reading::factory()->for($bike)->create([
            'recorded_on' => '2026-08-18',
            'mileage' => 145_200,
        ]);

        $this->assertSame(145_200, $bike->fresh()->currentMileage());
        $this->assertSame('bad', $bike->fresh()->serviceStatus()['level']);

        $reading->update(['mileage' => 11_520]);

        $this->assertSame(11_520, $bike->fresh()->currentMileage());
        $this->assertSame('ok', $bike->fresh()->serviceStatus()['level']);
    }

    public function test_deleting_a_bike_removes_its_readings(): void
    {
        $bike = Bike::factory()->create();
        Reading::factory()->for($bike)->count(3)->create(
            new \Illuminate\Database\Eloquent\Factories\Sequence(
                ['recorded_on' => '2026-08-01'],
                ['recorded_on' => '2026-08-08'],
                ['recorded_on' => '2026-08-15'],
            )
        );

        $this->assertSame(3, Reading::count());

        $bike->forceDelete();

        $this->assertSame(0, Reading::count());
    }

    public function test_deleting_a_rider_unassigns_their_bikes_rather_than_deleting_them(): void
    {
        $rider = Rider::factory()->create();
        $bike = Bike::factory()->create(['rider_id' => $rider->id]);

        $rider->forceDelete();

        $this->assertNotNull($bike->fresh(), 'The bike must survive its rider leaving.');
        $this->assertNull($bike->fresh()->rider_id);
    }

    public function test_soft_deleting_a_rider_keeps_the_assignment_intact(): void
    {
        $rider = Rider::factory()->create();
        $bike = Bike::factory()->create(['rider_id' => $rider->id]);

        $rider->delete();

        $this->assertSame($rider->id, $bike->fresh()->rider_id);
        $this->assertSame(1, Rider::withTrashed()->count());
        $this->assertSame(0, Rider::count());
    }
}
