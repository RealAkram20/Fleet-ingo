<?php

namespace Tests\Feature;

use App\Models\Bike;
use App\Models\Reading;
use App\Models\Rider;
use App\Models\ServiceRecord;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SettingsTest extends TestCase
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

    // -------------------------------------------------------------- access

    public function test_only_admins_reach_settings(): void
    {
        $this->get('/settings')->assertRedirect('/login');
        $this->actingAs($this->clerk())->get('/settings')->assertForbidden();
        $this->actingAs($this->admin())->get('/settings')->assertOk();
    }

    public function test_a_clerk_cannot_change_settings(): void
    {
        $this->actingAs($this->clerk())
            ->patch('/settings/branding', ['app_name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame('InGo Fleet Log', Settings::get('app_name'));
    }

    // ------------------------------------------------------------ defaults

    public function test_settings_fall_back_to_config_when_nothing_is_saved(): void
    {
        $this->assertSame(0, Setting::count());
        $this->assertSame('InGo Fleet Log', Settings::get('app_name'));
        $this->assertSame(300, Settings::int('due_soon_km'));
        $this->assertSame('Africa/Harare', Settings::get('timezone'));
    }

    // ------------------------------------------------------------ branding

    public function test_the_app_name_can_be_changed_and_shows_in_the_interface(): void
    {
        $this->actingAs($this->admin())
            ->patch('/settings/branding', ['app_name' => 'Harare Couriers', 'tagline' => 'Two wheels, all day'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Harare Couriers', Settings::get('app_name'));

        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Two wheels, all day');
    }

    public function test_a_logo_upload_is_stored_and_used(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch('/settings/branding', [
            'app_name' => 'InGo Fleet Log',
            'logo' => UploadedFile::fake()->image('new-logo.png', 600, 200),
        ])->assertSessionHasNoErrors();

        $path = Settings::get('logo_path');

        $this->assertStringStartsWith('branding/logo-', $path);
        $this->assertFileExists(public_path($path));

        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee($path, escape: false);

        @unlink(public_path($path));
    }

    public function test_a_non_image_is_rejected_as_a_logo(): void
    {
        $this->actingAs($this->admin())->patch('/settings/branding', [
            'app_name' => 'InGo Fleet Log',
            'logo' => UploadedFile::fake()->create('payload.php', 20, 'application/x-php'),
        ])->assertSessionHasErrors('logo');
    }

    // --------------------------------------------------------------- fleet

    public function test_the_due_soon_threshold_actually_changes_bike_status(): void
    {
        Carbon::setTestNow('2026-08-18 09:00:00');

        $bike = Bike::factory()->create(['service_interval_km' => 3000, 'service_interval_months' => null]);
        ServiceRecord::factory()->for($bike)->create(['serviced_on' => '2026-06-18', 'mileage' => 9_000]);
        Reading::factory()->for($bike)->create(['recorded_on' => '2026-08-17', 'mileage' => 11_500]);

        // 500 km remaining: fine under the default 300 km threshold.
        $this->assertSame('ok', $bike->fresh()->serviceStatus()['level']);

        $this->actingAs($this->admin())->patch('/settings/fleet', [
            'default_service_interval_km' => 3000,
            'default_service_interval_months' => 6,
            'due_soon_km' => 800,
            'due_soon_days' => 30,
            'licence_warn_days' => 30,
            'timezone' => 'Africa/Harare',
        ])->assertSessionHasNoErrors();

        $this->assertSame(800, Settings::int('due_soon_km'));
        $this->assertSame('warn', $bike->fresh()->serviceStatus()['level']);

        Carbon::setTestNow();
    }

    public function test_the_licence_warning_window_is_configurable(): void
    {
        Carbon::setTestNow('2026-08-18 09:00:00');

        $rider = Rider::factory()->create(['license_expiry' => '2026-10-01']); // 44 days out

        $this->assertSame('ok', $rider->licenseStatus()['level']);

        $this->actingAs($this->admin())->patch('/settings/fleet', [
            'default_service_interval_km' => 3000,
            'default_service_interval_months' => 6,
            'due_soon_km' => 300,
            'due_soon_days' => 30,
            'licence_warn_days' => 60,
            'timezone' => 'Africa/Harare',
        ])->assertSessionHasNoErrors();

        $this->assertSame('warn', $rider->fresh()->licenseStatus()['level']);

        Carbon::setTestNow();
    }

    public function test_an_invalid_timezone_is_rejected(): void
    {
        $this->actingAs($this->admin())->patch('/settings/fleet', [
            'default_service_interval_km' => 3000,
            'due_soon_km' => 300,
            'due_soon_days' => 30,
            'licence_warn_days' => 30,
            'timezone' => 'Mars/Olympus_Mons',
        ])->assertSessionHasErrors('timezone');
    }

    // ---------------------------------------------------------------- mail

    public function test_smtp_settings_are_saved_and_the_password_is_encrypted_at_rest(): void
    {
        $this->actingAs($this->admin())->patch('/settings/mail', [
            'mail_host' => 'smtp.example.com',
            'mail_port' => 587,
            'mail_username' => 'fleet@example.com',
            'mail_password' => 'super-secret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'fleet@example.com',
            'mail_from_name' => 'InGo Fleet',
        ])->assertSessionHasNoErrors();

        $this->assertSame('super-secret', Settings::get('mail_password'));

        $raw = Setting::find('mail_password');
        $this->assertTrue($raw->is_encrypted);
        $this->assertNotSame('super-secret', $raw->value, 'The SMTP password must not be readable in the database.');
        $this->assertStringNotContainsString('super-secret', $raw->value);
    }

    public function test_a_blank_password_field_keeps_the_stored_smtp_password(): void
    {
        $admin = $this->admin();
        $base = [
            'mail_host' => 'smtp.example.com', 'mail_port' => 587,
            'mail_username' => 'fleet@example.com', 'mail_encryption' => 'tls',
            'mail_from_address' => 'fleet@example.com', 'mail_from_name' => 'InGo Fleet',
        ];

        $this->actingAs($admin)->patch('/settings/mail', $base + ['mail_password' => 'super-secret']);
        $this->actingAs($admin)->patch('/settings/mail', $base + ['mail_password' => '']);

        $this->assertSame('super-secret', Settings::get('mail_password'));
    }

    public function test_a_port_is_required_once_a_host_is_given(): void
    {
        $this->actingAs($this->admin())->patch('/settings/mail', [
            'mail_host' => 'smtp.example.com',
            'mail_port' => '',
            'mail_from_address' => 'fleet@example.com',
            'mail_from_name' => 'InGo Fleet',
        ])->assertSessionHasErrors('mail_port');
    }

    public function test_a_test_message_can_be_sent(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post('/settings/mail/test', ['test_email' => 'boss@ingo.local'])
            ->assertSessionHas('status');

        Mail::assertSentCount(1);
    }

    public function test_the_test_address_must_be_an_email(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/mail/test', ['test_email' => 'not-an-address'])
            ->assertSessionHasErrors('test_email');
    }
}
