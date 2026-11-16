<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 公開APIの書き込み系（POST/PUT/DELETE）の Sanctum トークン認証と BookPolicy による認可（シート13 AP06）。
 */
class ApiSanctumTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
    }

    /** @return array<string, mixed> */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'title' => 'トークンの本', 'author' => '著者', 'isbn' => '9784000000101',
            'genre_ids' => [Genre::factory()->create()->id],
        ], $overrides);
    }

    public function test_reads_stay_public_without_token(): void
    {
        $book = Book::factory()->create();

        $this->getJson('/api/v1/books')->assertOk();
        $this->getJson('/api/v1/books/'.$book->id)->assertOk();
    }

    public function test_writes_without_token_return_401_in_japanese(): void
    {
        $book = Book::factory()->create();
        $message = '認証が必要です。APIトークンを Authorization ヘッダーに付けてください。';

        $this->postJson('/api/v1/books', $this->input())->assertUnauthorized()->assertExactJson(['message' => $message]);
        $this->putJson('/api/v1/books/'.$book->id, $this->input())->assertUnauthorized()->assertExactJson(['message' => $message]);
        $this->deleteJson('/api/v1/books/'.$book->id)->assertUnauthorized()->assertExactJson(['message' => $message]);
        // Accept ヘッダー無しでもログイン画面へ飛ばさず 401 の JSON
        $this->post('/api/v1/books', $this->input())->assertUnauthorized()->assertExactJson(['message' => $message]);
        $this->withHeader('Authorization', 'Bearer invalid-token')->postJson('/api/v1/books', $this->input())->assertUnauthorized();

        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_other_users_book_cannot_be_updated_or_deleted_403(): void
    {
        $book = Book::factory()->create(['title' => '元の題']);
        Sanctum::actingAs(User::factory()->create());
        $message = 'この書籍を変更する権限がありません。';

        $this->putJson('/api/v1/books/'.$book->id, $this->input(['title' => '乗っ取り']))->assertForbidden()->assertExactJson(['message' => $message]);
        $this->deleteJson('/api/v1/books/'.$book->id)->assertForbidden()->assertExactJson(['message' => $message]);

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '元の題']);
    }

    public function test_owner_can_update_and_delete_own_book(): void
    {
        $book = Book::factory()->create();
        Sanctum::actingAs($book->user);

        $this->putJson('/api/v1/books/'.$book->id, $this->input(['title' => '自分で改題']))->assertOk()
            ->assertJsonPath('data.title', '自分で改題')->assertJsonPath('data.user_id', $book->user_id);
        $this->deleteJson('/api/v1/books/'.$book->id)->assertNoContent();
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_registrant_is_the_token_owner_and_user_id_is_rejected(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/books', $this->input(['user_id' => $other->id]))->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', '登録者IDは指定できません。登録者はAPIトークンの持ち主になります。');
        $this->assertDatabaseCount('books', 0);

        $this->postJson('/api/v1/books', $this->input())->assertCreated()->assertJsonPath('data.user_id', $owner->id);
    }

    public function test_token_issued_by_login_works_as_bearer_token(): void
    {
        $user = User::factory()->create(['email' => 'api@example.com', 'password' => bcrypt('password123')]);

        $token = $this->postJson('/api/v1/tokens', ['email' => 'api@example.com', 'password' => 'password123', 'device_name' => 'cli'])
            ->assertCreated()->assertJsonPath('token_type', 'Bearer')->json('token');
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'name' => 'cli']);

        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/books', $this->input())
            ->assertCreated()->assertJsonPath('data.user_id', $user->id);
    }

    public function test_token_request_with_wrong_password_returns_422_in_japanese(): void
    {
        User::factory()->create(['email' => 'api@example.com', 'password' => bcrypt('password123')]);

        $this->postJson('/api/v1/tokens', ['email' => 'api@example.com', 'password' => 'wrong'])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'メールアドレスまたはパスワードが正しくありません。');
        $this->postJson('/api/v1/tokens', [])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'メールアドレスを入力してください。')
            ->assertJsonPath('errors.password.0', 'パスワードを入力してください。');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
