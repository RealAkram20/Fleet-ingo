<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * There is no public landing page — the root URL is a doorway to the
     * dashboard, which sends a signed-out visitor to the login screen.
     */
    public function test_the_root_url_sends_a_signed_out_visitor_to_login(): void
    {
        $this->get('/')
            ->assertRedirect('/dashboard');

        $this->followingRedirects()
            ->get('/')
            ->assertOk()
            ->assertSee('Sign in', escape: false);
    }
}
