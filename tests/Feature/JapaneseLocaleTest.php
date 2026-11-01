<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JapaneseLocaleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
        $this->withoutVite();
    }

    public function test_app_locale_is_japanese(): void
    {
        $this->assertSame('ja', app()->getLocale());
    }

    public function test_register_validation_errors_are_shown_in_japanese(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->from('/register')->post('/register', [
            'name' => '',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください。',
            'email' => 'そのメールアドレスはすでに使用されています。',
            'password' => 'パスワードが確認用の入力と一致しません。',
        ]);
        $this->followingRedirects()->from('/register')->post('/register', [
            'name' => '', 'email' => 'bad', 'password' => 'x', 'password_confirmation' => 'x',
        ])->assertSee('お名前を入力してください。')
            ->assertSee('メールアドレスは有効なメールアドレス形式で入力してください。')
            ->assertDontSee('field is required');
    }

    public function test_login_validation_and_failure_errors_are_shown_in_japanese(): void
    {
        $this->from('/login')->post('/login', ['email' => '', 'password' => ''])
            ->assertSessionHasErrors([
                'email' => 'メールアドレスを入力してください。',
                'password' => 'パスワードを入力してください。',
            ]);

        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        $this->assertGuest();
    }
}
