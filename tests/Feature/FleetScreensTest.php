<?php

namespace Tests\Feature;

use App\Models\Bike;
use App\Models\Reading;
use App\Models\Rider;
use App\Models\ServiceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FleetScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-18 09:00:00');

        // These cover the screens themselves, so the actor needs full access.
        // Who may do what is covered separately in RolesTest.
        $this->user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function bikeWith(int $servicedAtKm, int $currentKm, string $reg = 'AEJ 1234'): Bike
    {
        $bike = Bike::factory()->create(['reg' => $reg, 'service_interval_km' => 3000, 'service_interval_months' => null]);
        ServiceRecord::factory()->for($bike)->create(['serviced_on' => '2026-06-18', 'mileage' => $servicedAtKm]);
        Reading::factory()->for($bike)->create(['recorded_on' => '2026-08-17', 'mileage' => $currentKm]);

        return $bike->fresh();
    }

    // ---------------------------------------------------------------- access

    public function test_every_fleet_screen_is_closed_to_guests(): void
    {
        foreach (['/dashboard', '/readings', '/riders', '/bikes'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_the_root_url_goes_to_the_dashboard(): void
    {
        $this->actingAs($this->user)->get('/')->assertRedirect('/dashboard');
    }

    // ------------------------------------------------------------- dashboard

    public function test_the_dashboard_shows_each_bike_and_its_status(): void
    {
        $this->bikeWith(servicedAtKm: 9_000, currentKm: 12_400, reg: 'AEJ 1234'); // overdue
        $this->bikeWith(servicedAtKm: 4_000, currentKm: 4_500, reg: 'ACY 8890');  // ok

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('AEJ 1234')
            ->assertSee('ACY 8890')
            ->assertSee('Overdue')
            ->assertSee('Bikes in fleet');
    }

    public function test_the_dashboard_can_be_filtered_to_the_bikes_needing_attention(): void
    {
        $this->bikeWith(servicedAtKm: 9_000, currentKm: 12_400, reg: 'AEJ 1234'); // overdue
        $this->bikeWith(servicedAtKm: 4_000, currentKm: 4_500, reg: 'ACY 8890');  // ok

        $this->actingAs($this->user)
            ->get('/dashboard?status=bad')
            ->assertOk()
            ->assertSee('AEJ 1234')
            ->assertDontSee('ACY 8890');
    }

    public function test_the_summary_counts_describe_the_whole_fleet_even_when_filtered(): void
    {
        $this->bikeWith(servicedAtKm: 9_000, currentKm: 12_400, reg: 'AEJ 1234');
        $this->bikeWith(servicedAtKm: 4_000, currentKm: 4_500, reg: 'ACY 8890');

        $response = $this->actingAs($this->user)->get('/dashboard?status=bad');

        $this->assertSame(2, $response->viewData('summary')['total']);
        $this->assertCount(1, $response->viewData('bikes'));
    }

    // -------------------------------------------------------------- readings

    public function test_the_reading_screen_keeps_the_chosen_bike(): void
    {
        $this->bikeWith(9_000, 11_000, 'AEJ 1234');
        $target = $this->bikeWith(4_000, 4_500, 'ZZZ 9999');

        $response = $this->actingAs($this->user)->get('/readings?bike='.$target->id);

        $response->assertOk();
        $this->assertTrue($response->viewData('selected')->is($target));
    }

    public function test_a_reading_can_be_logged_and_updates_the_mileage(): void
    {
        $bike = $this->bikeWith(9_000, 11_000);

        $this->actingAs($this->user)
            ->post('/readings', [
                'bike_id' => $bike->id,
                'recorded_on' => '2026-08-18',
                'mileage' => 11_450,
            ])
            ->assertRedirect(route('readings.index', ['bike' => $bike->id]))
            ->assertSessionHas('status');

        $this->assertSame(11_450, $bike->fresh()->currentMileage());
    }

    public function test_a_reading_below_the_previous_one_is_rejected(): void
    {
        $bike = $this->bikeWith(9_000, 11_000);

        $this->actingAs($this->user)
            ->post('/readings', [
                'bike_id' => $bike->id,
                'recorded_on' => '2026-08-18',
                'mileage' => 10_500,
            ])
            ->assertSessionHasErrors('mileage');

        $this->assertSame(11_000, $bike->fresh()->currentMileage());
    }

    public function test_a_future_dated_reading_is_rejected(): void
    {
        $bike = $this->bikeWith(9_000, 11_000);

        $this->actingAs($this->user)
            ->post('/readings', [
                'bike_id' => $bike->id,
                'recorded_on' => '2026-08-25',
                'mileage' => 11_500,
            ])
            ->assertSessionHasErrors('recorded_on');
    }

    public function test_a_second_reading_on_the_same_date_is_rejected_with_a_useful_message(): void
    {
        $bike = $this->bikeWith(9_000, 11_000);

        $this->actingAs($this->user)
            ->post('/readings', [
                'bike_id' => $bike->id,
                'recorded_on' => '2026-08-17',
                'mileage' => 11_200,
            ])
            ->assertSessionHasErrors('recorded_on');

        $this->assertSame(1, Reading::where('bike_id', $bike->id)->count());
    }

    public function test_a_back_dated_reading_is_checked_against_its_neighbours_not_the_newest(): void
    {
        $bike = $this->bikeWith(9_000, 11_000);

        // 11 200 is above the 17 Aug reading of 11 000, so back-dating it to the
        // 10th would put the odometer out of order.
        $this->actingAs($this->user)
            ->post('/readings', [
                'bike_id' => $bike->id,
                'recorded_on' => '2026-08-10',
                'mileage' => 11_200,
            ])
            ->assertSessionHasErrors('mileage');

        // Below the later reading, so it slots in cleanly.
        $this->actingAs($this->user)
            ->post('/readings', [
                'bike_id' => $bike->id,
                'recorded_on' => '2026-08-10',
                'mileage' => 10_600,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_a_mistyped_reading_can_be_corrected_from_the_screen(): void
    {
        $bike = $this->bikeWith(9_000, 11_000);
        $reading = $bike->readings()->first();

        $this->actingAs($this->user)
            ->patch('/readings/'.$reading->id, [
                'bike_id' => $bike->id,
                'recorded_on' => '2026-08-17',
                'mileage' => 10_150,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(10_150, $bike->fresh()->currentMileage());
    }

    public function test_a_reading_can_be_deleted(): void
    {
        $bike = $this->bikeWith(9_000, 11_000);
        $reading = $bike->readings()->first();

        $this->actingAs($this->user)
            ->delete('/readings/'.$reading->id)
            ->assertSessionHas('status');

        $this->assertSame(0, Reading::count());
    }

    // ---------------------------------------------------------------- riders

    public function test_a_rider_can_be_added_and_edited(): void
    {
        $this->actingAs($this->user)
            ->post('/riders', ['name' => 'Tendai Moyo', 'phone' => '0771234567', 'license_expiry' => '2027-01-01'])
            ->assertRedirect(route('riders.index'));

        $rider = Rider::firstWhere('name', 'Tendai Moyo');
        $this->assertNotNull($rider);

        $this->actingAs($this->user)
            ->patch('/riders/'.$rider->id, ['name' => 'Tendai Moyo', 'phone' => '0779999999'])
            ->assertSessionHasNoErrors();

        $this->assertSame('0779999999', $rider->fresh()->phone);
    }

    public function test_a_rider_needs_a_name(): void
    {
        $this->actingAs($this->user)
            ->post('/riders', ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_riders_can_be_searched(): void
    {
        Rider::factory()->create(['name' => 'Tendai Moyo']);
        Rider::factory()->create(['name' => 'Farai Chikwanha']);

        $this->actingAs($this->user)
            ->get('/riders?q=Tendai')
            ->assertOk()
            ->assertSee('Tendai Moyo')
            ->assertDontSee('Farai Chikwanha');
    }

    public function test_removing_a_rider_keeps_their_bike(): void
    {
        $rider = Rider::factory()->create();
        $bike = Bike::factory()->create(['rider_id' => $rider->id]);

        $this->actingAs($this->user)->delete('/riders/'.$rider->id);

        $this->assertSame(0, Rider::count());
        $this->assertNotNull($bike->fresh());
    }

    // ----------------------------------------------------------------- bikes

    public function test_a_bike_can_be_added(): void
    {
        $this->actingAs($this->user)
            ->post('/bikes', [
                'reg' => 'aej 1234',
                'model' => 'TVS HLX 150',
                'service_interval_km' => 3000,
                'service_interval_months' => 6,
            ])
            ->assertSessionHasNoErrors();

        // Registrations are normalised so "aej 1234" and "AEJ 1234" are one bike.
        $this->assertNotNull(Bike::firstWhere('reg', 'AEJ 1234'));
    }

    public function test_a_duplicate_registration_is_rejected(): void
    {
        Bike::factory()->create(['reg' => 'AEJ 1234']);

        $this->actingAs($this->user)
            ->post('/bikes', ['reg' => 'AEJ 1234', 'service_interval_km' => 3000])
            ->assertSessionHasErrors('reg');

        $this->assertSame(1, Bike::count());
    }

    public function test_a_blank_service_interval_is_rejected_rather_than_silently_becoming_zero(): void
    {
        $this->actingAs($this->user)
            ->post('/bikes', ['reg' => 'AEJ 1234', 'service_interval_km' => ''])
            ->assertSessionHasErrors('service_interval_km');

        $this->assertSame(0, Bike::count());
    }

    // -------------------------------------------------------------- services

    public function test_marking_a_bike_serviced_appends_a_record_instead_of_overwriting(): void
    {
        $bike = $this->bikeWith(9_000, 12_400);
        $this->assertSame('bad', $bike->serviceStatus()['level']);

        $this->actingAs($this->user)
            ->post('/bikes/'.$bike->id.'/service', [
                'serviced_on' => '2026-08-18',
                'mileage' => 12_400,
                'cost' => 45.50,
            ])
            ->assertSessionHasNoErrors();

        $fresh = $bike->fresh();
        $this->assertSame(2, $fresh->serviceRecords()->count(), 'The earlier service must still be on record.');
        $this->assertSame('ok', $fresh->serviceStatus()['level']);
    }

    public function test_a_service_below_the_previous_service_mileage_is_rejected(): void
    {
        $bike = $this->bikeWith(9_000, 12_400);

        $this->actingAs($this->user)
            ->post('/bikes/'.$bike->id.'/service', ['serviced_on' => '2026-08-18', 'mileage' => 8_000])
            ->assertSessionHasErrors('mileage');
    }

    // ------------------------------------------------------------------ xss

    public function test_rider_names_are_escaped_rather_than_executed(): void
    {
        $payload = '<script>alert("xss")</script>';
        Rider::factory()->create(['name' => $payload]);

        $response = $this->actingAs($this->user)->get('/riders');

        $response->assertOk();
        $response->assertDontSee($payload, escape: false);
        $response->assertSee($payload, escape: true);
    }
}
