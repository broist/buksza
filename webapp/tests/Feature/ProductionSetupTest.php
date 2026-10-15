<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductionSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_production_setup_requires_the_private_installation_link(): void
    {
        $this->seed();
        $this->withoutVite();
        $this->app['env'] = 'production';
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['auth.buksza_setup_token' => 'private-installer-token']);
        $data = ['name' => 'QA', 'email' => 'qa@example.test', 'password' => 'test-password-2026', 'password_confirmation' => 'test-password-2026'];
        $this->get('/belepes')->assertInertia(fn (Assert $p) => $p->where('setup', false)->where('setupLocked', true));
        $this->post('/elso-belepes', $data)->assertForbidden();
        $this->get('/belepes?setup=wrong')->assertInertia(fn (Assert $p) => $p->where('setupLocked', true));
        $this->get('/belepes?setup=private-installer-token')->assertRedirect('/belepes');
        $this->get('/belepes')->assertInertia(fn (Assert $p) => $p->where('setup', true)->where('setupLocked', false));
        $this->post('/elso-belepes', $data)->assertRedirect('/')->assertSessionMissing('setup_authorized');
        $this->assertDatabaseCount('users', 1);
        $this->post('/elso-belepes', $data)->assertForbidden();
    }
}
