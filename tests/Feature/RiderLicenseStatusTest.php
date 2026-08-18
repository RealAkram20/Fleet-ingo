<?php

namespace Tests\Feature;

use App\Models\Rider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RiderLicenseStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-18 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function riderExpiring(?string $date): Rider
    {
        return Rider::factory()->create(['license_expiry' => $date]);
    }

    public function test_a_licence_well_in_the_future_is_valid(): void
    {
        $status = $this->riderExpiring('2027-05-01')->licenseStatus();

        $this->assertSame('ok', $status['level']);
        $this->assertSame('Valid', $status['label']);
    }

    public function test_a_licence_inside_thirty_days_warns_and_names_the_count(): void
    {
        $status = $this->riderExpiring('2026-09-01')->licenseStatus();

        $this->assertSame('warn', $status['level']);
        $this->assertSame('Expires in 14d', $status['label']);
        $this->assertSame(14, $status['days']);
    }

    public function test_thirty_days_out_is_the_last_day_that_warns(): void
    {
        $status = $this->riderExpiring('2026-09-17')->licenseStatus();

        $this->assertSame('warn', $status['level']);
        $this->assertSame(30, $status['days']);
    }

    public function test_thirty_one_days_out_is_still_valid(): void
    {
        $status = $this->riderExpiring('2026-09-18')->licenseStatus();

        $this->assertSame('ok', $status['level']);
        $this->assertSame(31, $status['days']);
    }

    public function test_expiring_today_warns_rather_than_reporting_expired(): void
    {
        $status = $this->riderExpiring('2026-08-18')->licenseStatus();

        $this->assertSame('warn', $status['level']);
        $this->assertSame(0, $status['days']);
    }

    public function test_yesterday_is_expired(): void
    {
        $status = $this->riderExpiring('2026-08-17')->licenseStatus();

        $this->assertSame('bad', $status['level']);
        $this->assertSame('Expired', $status['label']);
    }

    public function test_a_missing_expiry_date_is_flagged_not_ignored(): void
    {
        $status = $this->riderExpiring(null)->licenseStatus();

        $this->assertSame('warn', $status['level']);
        $this->assertSame('No date on file', $status['label']);
        $this->assertNull($status['days']);
    }
}
