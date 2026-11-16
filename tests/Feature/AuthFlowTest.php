<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
        $this->withoutVite();
    }

    public function test_register_and_login_pages_show_their_forms(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee('action="'.route('register').'"', false)
            ->assertSee('name="name"', false)->assertSee('name="email"', false)
            ->assertSee('name="password"', false)->assertSee('name="password_confirmation"', false);
        $this->get('/login')->assertOk()
            ->assertSee('action="'.route('login').'"', false)
            ->assertSee('name="email"', false)->assertSee('name="password"', false);
    }

    public function test_valid_registration_creates_user_and_lands_on_an_existing_page(): void
    {
        $response = $this->post('/register', [
            'name' => 'New reader',
            'email' => 'reader@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $user = User::where('email', 'reader@example.com')->sole();
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->get($response->headers->get('Location'))->assertOk();
    }

    public function test_invalid_registration_shows_errors_and_creates_no_user(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->from('/register')->post('/register', [
            'name' => '',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ])->assertRedirect('/register')->assertSessionHasErrors(['name', 'email', 'password']);

        $this->assertGuest();
        $this->assertSame(1, User::count());
    }

    public function test_valid_login_lands_on_an_existing_page(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password123']);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->get($response->headers->get('Location'))->assertOk();
    }

    public function test_failed_login_shows_an_error_and_stays_guest(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session_and_returns_to_login(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->get('/login')->assertOk();
    }

    public function test_logged_in_user_is_sent_home_from_login_and_register(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/login')->assertRedirect('/');
        $this->get('/register')->assertRedirect('/');
    }

    public function test_header_offers_logout_only_when_logged_in(): void
    {
        $logoutForm = 'action="'.route('logout').'"';

        $this->get('/')->assertOk()->assertDontSee($logoutForm, false)
            ->assertSee('href="'.route('login').'"', false)->assertSee('href="'.route('register').'"', false);
        $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertSee($logoutForm, false);
    }
}
