<?php

namespace Tests\Feature;

use App\Models\Bike;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function clerk(): User
    {
        return User::factory()->create(['role' => User::ROLE_CLERK]);
    }

    // ------------------------------------------------------- registration is closed

    public function test_the_public_registration_routes_are_gone(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Walk In',
            'email' => 'walkin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertSame(0, User::where('email', 'walkin@example.com')->count());
    }

    public function test_the_login_screen_offers_no_way_to_sign_up(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Register', escape: false);
    }

    // -------------------------------------------------------------- new users default

    public function test_a_new_account_is_a_clerk_unless_told_otherwise(): void
    {
        $this->artisan('ingo:user', [
            'email' => 'yard@ingo.local',
            '--name' => 'Yard Hand',
            '--password' => 'correct-horse-battery',
        ])->assertSuccessful();

        $user = User::firstWhere('email', 'yard@ingo.local');

        $this->assertSame(User::ROLE_CLERK, $user->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_the_command_can_create_an_admin_and_rejects_an_unknown_role(): void
    {
        $this->artisan('ingo:user', [
            'email' => 'boss@ingo.local',
            '--name' => 'Fleet Boss',
            '--role' => 'admin',
            '--password' => 'correct-horse-battery',
        ])->assertSuccessful();

        $this->assertTrue(User::firstWhere('email', 'boss@ingo.local')->isAdmin());

        $this->artisan('ingo:user', [
            'email' => 'nope@ingo.local',
            '--name' => 'Nope',
            '--role' => 'superuser',
            '--password' => 'correct-horse-battery',
        ])->assertFailed();

        $this->assertNull(User::firstWhere('email', 'nope@ingo.local'));
    }

    public function test_the_command_will_not_create_an_account_with_a_weak_password(): void
    {
        $this->artisan('ingo:user', [
            'email' => 'weak@ingo.local',
            '--name' => 'Weak',
            '--password' => 'short',
        ])->assertFailed();

        $this->assertNull(User::firstWhere('email', 'weak@ingo.local'));
    }

    // ------------------------------------------------------------------ what a clerk can do

    public function test_a_clerk_can_reach_the_day_to_day_screens(): void
    {
        $clerk = $this->clerk();

        foreach (['/dashboard', '/readings', '/riders', '/bikes'] as $path) {
            $this->actingAs($clerk)->get($path)->assertOk();
        }
    }

    public function test_a_clerk_cannot_change_the_roster(): void
    {
        $clerk = $this->clerk();
        $rider = Rider::factory()->create(['name' => 'Existing Rider']);

        $this->actingAs($clerk)->post('/riders', ['name' => 'New Rider'])->assertForbidden();
        $this->actingAs($clerk)->patch('/riders/'.$rider->id, ['name' => 'Renamed'])->assertForbidden();
        $this->actingAs($clerk)->delete('/riders/'.$rider->id)->assertForbidden();

        $this->assertSame('Existing Rider', $rider->fresh()->name);
        $this->assertSame(1, Rider::count());
    }

    public function test_a_clerk_cannot_change_the_fleet(): void
    {
        $clerk = $this->clerk();
        $bike = Bike::factory()->create(['reg' => 'AEJ 1234']);

        $this->actingAs($clerk)
            ->post('/bikes', ['reg' => 'ZZZ 9999', 'service_interval_km' => 3000])
            ->assertForbidden();
        $this->actingAs($clerk)
            ->patch('/bikes/'.$bike->id, ['reg' => 'XXX 1111', 'service_interval_km' => 3000])
            ->assertForbidden();
        $this->actingAs($clerk)->delete('/bikes/'.$bike->id)->assertForbidden();

        $this->assertSame('AEJ 1234', $bike->fresh()->reg);
        $this->assertSame(1, Bike::count());
    }

    public function test_a_clerk_is_not_shown_controls_they_cannot_use(): void
    {
        Rider::factory()->create(['name' => 'Tendai Moyo']);

        $this->actingAs($this->clerk())
            ->get('/riders')
            ->assertOk()
            ->assertSee('Tendai Moyo')          // can read the roster
            ->assertDontSee('Add Rider')        // but sees no form
            ->assertDontSee('Remove');          // and no destructive action
    }

    public function test_a_clerk_can_still_log_a_reading_and_mark_a_bike_serviced(): void
    {
        $clerk = $this->clerk();
        $bike = Bike::factory()->create();

        $this->actingAs($clerk)
            ->post('/readings', [
                'bike_id' => $bike->id,
                'recorded_on' => now()->toDateString(),
                'mileage' => 12_000,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($clerk)
            ->post('/bikes/'.$bike->id.'/service', [
                'serviced_on' => now()->toDateString(),
                'mileage' => 12_000,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(12_000, $bike->fresh()->currentMileage());
        $this->assertSame(1, $bike->serviceRecords()->count());
    }

    // ------------------------------------------------------------------ what an admin can do

    public function test_an_admin_can_change_the_roster_and_the_fleet(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/riders', ['name' => 'New Rider'])->assertSessionHasNoErrors();
        $this->actingAs($admin)
            ->post('/bikes', ['reg' => 'ZZZ 9999', 'service_interval_km' => 3000])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Rider::count());
        $this->assertSame(1, Bike::count());
    }

    public function test_an_admin_is_shown_the_management_controls(): void
    {
        Rider::factory()->create();

        $this->actingAs($this->admin())
            ->get('/riders')
            ->assertOk()
            ->assertSee('Add Rider')
            ->assertSee('Remove');
    }
}
