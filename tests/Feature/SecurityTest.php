<?php

namespace Tests\Feature;

use App\Http\Middleware\Honeypot;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Limiters are keyed by IP, and every test here shares one.
        RateLimiter::clear('login');
    }

    private function user(string $role = User::ROLE_ADMIN): User
    {
        return User::factory()->create(['role' => $role, 'password' => Hash::make('correct-horse-battery')]);
    }

    /** A submission that looks like it came from a person. */
    private function humanLogin(array $overrides = []): array
    {
        return array_merge([
            'email' => 'someone@ingo.local',
            'password' => 'correct-horse-battery',
            Honeypot::FIELD => '',
            Honeypot::TIMER => time() - 30,
        ], $overrides);
    }

    // ---------------------------------------------------- response headers

    public function test_every_response_carries_the_hardening_headers(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'same-origin');
        $response->assertHeader('Permissions-Policy');
    }

    public function test_the_content_security_policy_blocks_inline_and_foreign_script(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    // ------------------------------------------------------------- bots

    public function test_the_login_form_carries_the_bot_trap(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(Honeypot::FIELD, escape: false)
            ->assertSee(Honeypot::TIMER, escape: false);
    }

    public function test_a_bot_that_fills_the_hidden_field_is_turned_away(): void
    {
        $user = $this->user();

        $this->post('/login', $this->humanLogin([
            'email' => $user->email,
            Honeypot::FIELD => 'http://spam.example.com',
        ]))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_form_submitted_faster_than_a_person_could_type_is_turned_away(): void
    {
        $user = $this->user();

        $this->post('/login', $this->humanLogin([
            'email' => $user->email,
            Honeypot::TIMER => time(), // posted the instant the page rendered
        ]))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_rejection_does_not_tell_a_bot_why_it_failed(): void
    {
        $user = $this->user();

        $trapped = $this->post('/login', $this->humanLogin([
            'email' => $user->email,
            Honeypot::FIELD => 'anything',
        ]))->assertSessionHasErrors('email');

        $wrongPassword = $this->post('/login', $this->humanLogin([
            'email' => $user->email,
            'password' => 'not-the-password',
        ]))->assertSessionHasErrors('email');

        $this->assertSame(
            session('errors')->get('email'),
            $wrongPassword->getSession()->get('errors')->get('email'),
            'A trapped bot and a wrong password must produce the same message.',
        );
    }

    public function test_a_person_still_gets_through(): void
    {
        $user = $this->user();

        $this->post('/login', $this->humanLogin(['email' => $user->email]))
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    // ----------------------------------------------------- rate limiting

    public function test_password_spraying_across_many_accounts_is_throttled(): void
    {
        /*
         * Breeze limits attempts per email+IP, so an attacker trying one common
         * password against many different addresses never trips it — each guess
         * uses a fresh key. The per-IP limiter is what catches this.
         */
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', $this->humanLogin([
                'email' => "victim{$i}@ingo.local",
                'password' => 'Password1',
            ]));
        }

        $this->post('/login', $this->humanLogin([
            'email' => 'victim99@ingo.local',
            'password' => 'Password1',
        ]))->assertStatus(429);
    }

    public function test_the_test_email_control_cannot_be_used_as_a_mail_relay(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $admin = $this->user();

        $this->actingAs($admin)->post('/settings/mail/test', ['test_email' => 'a@example.com'])->assertRedirect();
        $this->actingAs($admin)->post('/settings/mail/test', ['test_email' => 'b@example.com'])->assertRedirect();

        // Two a minute is the budget; the third is refused.
        $this->actingAs($admin)
            ->post('/settings/mail/test', ['test_email' => 'c@example.com'])
            ->assertStatus(429);

        \Illuminate\Support\Facades\Mail::assertSentCount(2);
    }

    public function test_writes_are_capped_per_user(): void
    {
        $admin = $this->user();

        // 60 a minute is the budget for anything that changes data.
        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($admin)->post('/riders', ['name' => "Rider {$i}"]);
        }

        $this->actingAs($admin)
            ->post('/riders', ['name' => 'One Too Many'])
            ->assertStatus(429);

        $this->assertSame(60, Rider::count());
    }

    // -------------------------------------------------- session hijacking

    public function test_a_session_replayed_from_another_browser_is_destroyed(): void
    {
        $user = $this->user();

        // Sign in and use the app from one browser.
        $this->actingAs($user)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (the real clerk)'])
            ->get('/dashboard')
            ->assertOk();

        // The same session cookie, replayed from somewhere else.
        $this->withHeaders(['User-Agent' => 'curl/8.4.0 (an attacker)'])
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_the_same_browser_keeps_its_session(): void
    {
        $user = $this->user();
        $agent = 'Mozilla/5.0 (the real clerk)';

        $this->actingAs($user)->withHeaders(['User-Agent' => $agent])->get('/dashboard')->assertOk();
        $this->withHeaders(['User-Agent' => $agent])->get('/riders')->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_signing_in_issues_a_fresh_session_id(): void
    {
        $user = $this->user();

        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', $this->humanLogin(['email' => $user->email]));

        $this->assertNotSame($before, session()->getId(), 'The session id must change on sign-in, or it can be fixated.');
    }

    // -------------------------------------------------------- injection

    public function test_a_wildcard_search_does_not_return_the_whole_table(): void
    {
        Rider::factory()->create(['name' => 'Tendai Moyo']);
        Rider::factory()->create(['name' => 'Farai Chikwanha']);

        $response = $this->actingAs($this->user())->get('/riders?q=%25');

        $response->assertOk()
            ->assertDontSee('Tendai Moyo')
            ->assertDontSee('Farai Chikwanha');
    }

    public function test_a_quote_in_a_search_term_is_harmless(): void
    {
        Rider::factory()->create(['name' => 'Tendai Moyo']);

        $this->actingAs($this->user())
            ->get('/riders?'.http_build_query(['q' => "' OR 1=1 --"]))
            ->assertOk()
            ->assertDontSee('Tendai Moyo');

        $this->assertSame(1, Rider::count());
    }

    public function test_a_clerk_cannot_promote_themselves_through_the_profile_form(): void
    {
        $clerk = $this->user(User::ROLE_CLERK);

        $this->actingAs($clerk)->patch('/profile', [
            'name' => $clerk->name,
            'email' => $clerk->email,
            'role' => User::ROLE_ADMIN,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($clerk->fresh()->isAdmin(), 'Role must not be mass-assignable from the profile form.');
    }
}
