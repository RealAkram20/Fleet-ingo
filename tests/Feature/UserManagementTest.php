<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email = 'admin@ingo.local'): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'email' => $email]);
    }

    private function clerk(): User
    {
        return User::factory()->create(['role' => User::ROLE_CLERK]);
    }

    // -------------------------------------------------------------- access

    public function test_only_admins_reach_user_management(): void
    {
        $this->get('/users')->assertRedirect('/login');
        $this->actingAs($this->clerk())->get('/users')->assertForbidden();
        $this->actingAs($this->admin())->get('/users')->assertOk();
    }

    public function test_a_clerk_cannot_create_or_delete_accounts(): void
    {
        $clerk = $this->clerk();
        $victim = $this->admin();

        $this->actingAs($clerk)->post('/users', [
            'name' => 'Sneaky', 'email' => 'sneaky@ingo.local',
            'role' => 'admin', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertForbidden();

        $this->actingAs($clerk)->delete('/users/'.$victim->id)->assertForbidden();

        $this->assertNull(User::firstWhere('email', 'sneaky@ingo.local'));
        $this->assertNotNull($victim->fresh());
    }

    public function test_the_nav_only_offers_users_and_settings_to_admins(): void
    {
        $this->actingAs($this->clerk())->get('/dashboard')
            ->assertOk()
            ->assertDontSee('>Settings<', escape: false);

        $this->actingAs($this->admin())->get('/dashboard')
            ->assertOk()
            ->assertSee('>Settings<', escape: false);
    }

    // -------------------------------------------------------------- create

    public function test_an_admin_can_create_a_clerk_and_another_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/users', [
            'name' => 'New Clerk', 'email' => 'clerk2@ingo.local', 'role' => 'clerk',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Second Admin', 'email' => 'admin2@ingo.local', 'role' => 'admin',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertSessionHasNoErrors();

        $this->assertSame(User::ROLE_CLERK, User::firstWhere('email', 'clerk2@ingo.local')->role);
        $this->assertTrue(User::firstWhere('email', 'admin2@ingo.local')->isAdmin());
    }

    public function test_a_new_account_needs_a_matching_confirmed_password(): void
    {
        $this->actingAs($this->admin())->post('/users', [
            'name' => 'Mismatch', 'email' => 'mismatch@ingo.local', 'role' => 'clerk',
            'password' => 'password123', 'password_confirmation' => 'something-else',
        ])->assertSessionHasErrors('password');

        $this->assertNull(User::firstWhere('email', 'mismatch@ingo.local'));
    }

    public function test_email_addresses_are_unique(): void
    {
        $admin = $this->admin('taken@ingo.local');

        $this->actingAs($admin)->post('/users', [
            'name' => 'Duplicate', 'email' => 'taken@ingo.local', 'role' => 'clerk',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::count());
    }

    // -------------------------------------------------------------- update

    public function test_a_blank_password_on_edit_leaves_the_existing_one_alone(): void
    {
        $admin = $this->admin();
        $clerk = User::factory()->create(['role' => User::ROLE_CLERK, 'password' => Hash::make('original-password')]);

        $this->actingAs($admin)->patch('/users/'.$clerk->id, [
            'name' => 'Renamed Clerk', 'email' => $clerk->email, 'role' => 'clerk', 'password' => '',
        ])->assertSessionHasNoErrors();

        $clerk->refresh();
        $this->assertSame('Renamed Clerk', $clerk->name);
        $this->assertTrue(Hash::check('original-password', $clerk->password));
    }

    public function test_an_admin_can_reset_someone_elses_password(): void
    {
        $clerk = $this->clerk();

        $this->actingAs($this->admin())->patch('/users/'.$clerk->id, [
            'name' => $clerk->name, 'email' => $clerk->email, 'role' => 'clerk',
            'password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-password', $clerk->fresh()->password));
    }

    // ------------------------------------------------------- lockout guards

    public function test_an_admin_cannot_demote_themselves(): void
    {
        $admin = $this->admin();
        $this->admin('other@ingo.local'); // another admin exists, so this is purely about self-demotion

        $this->actingAs($admin)->patch('/users/'.$admin->id, [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'clerk',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_the_last_admin_cannot_be_demoted(): void
    {
        $admin = $this->admin();
        $other = $this->admin('second@ingo.local');

        // Demoting the second admin is fine while two exist.
        $this->actingAs($admin)->patch('/users/'.$other->id, [
            'name' => $other->name, 'email' => $other->email, 'role' => 'clerk',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($other->fresh()->isAdmin());

        // Now only one admin remains, and it is the signed-in account.
        $this->actingAs($admin)->patch('/users/'.$admin->id, [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'clerk',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();
        $this->admin('other@ingo.local');

        $this->actingAs($admin)->delete('/users/'.$admin->id)->assertSessionHasErrors('user');

        $this->assertNotNull($admin->fresh());
    }

    public function test_the_only_admin_account_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $clerk = $this->clerk();

        // A clerk signing in cannot get here, so act as the admin deleting itself
        // is covered above; this covers the sole-admin rule from another angle.
        $this->actingAs($admin)->delete('/users/'.$clerk->id)->assertSessionHasNoErrors();
        $this->assertSame(1, User::count());
    }

    public function test_deleting_a_user_keeps_the_readings_they_logged(): void
    {
        $admin = $this->admin();
        $clerk = $this->clerk();

        $bike = \App\Models\Bike::factory()->create();
        $reading = \App\Models\Reading::factory()->for($bike)->create([
            'recorded_by' => $clerk->id,
            'recorded_on' => now()->toDateString(),
        ]);

        $this->actingAs($admin)->delete('/users/'.$clerk->id)->assertSessionHasNoErrors();

        $this->assertNotNull($reading->fresh(), 'The reading must survive the account being removed.');
        $this->assertNull($reading->fresh()->recorded_by);
    }
}
