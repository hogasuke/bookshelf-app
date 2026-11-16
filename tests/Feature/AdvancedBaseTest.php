<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 応用への移行（#11）：ISBN・出版日の任意化と、応用版ヘッダーの表示。
 */
class AdvancedBaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
    }

    public function test_book_can_be_registered_without_isbn_and_published_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/books', [
            'title' => 'ISBNの無い本', 'author' => '著者', 'isbn' => '', 'published_date' => '',
            'genres' => [Genre::factory()->create()->id],
        ])->assertSessionHasNoErrors();

        $book = Book::firstWhere('title', 'ISBNの無い本');
        $this->assertNull($book->isbn);
        $this->assertNull($book->published_date);
        $this->get('/books/'.$book->id)->assertOk()->assertSee('ISBN:</strong> 未登録', false)
            ->assertSee('出版日:</strong> 未登録', false);
        $this->get('/books/'.$book->id.'/edit')->assertOk();
    }

    public function test_two_books_without_isbn_do_not_conflict_but_duplicates_still_do(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $input = ['title' => '本', 'author' => '著者', 'genres' => [$genre->id]];

        $this->actingAs($user)->post('/books', $input)->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/books', $input)->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/books', $input + ['isbn' => '9784000000001'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/books', $input + ['isbn' => '9784000000001'])
            ->assertSessionHasErrors(['isbn' => 'このISBNの書籍は既に登録されています。']);
        $this->assertSame(3, Book::count());
    }

    public function test_published_date_is_shown_as_a_date_on_screens_and_api(): void
    {
        $book = Book::factory()->create(['published_date' => '2012-06-23']);

        $this->get('/books/'.$book->id)->assertOk()->assertSee('2012-06-23');
        $this->actingAs($book->user)->get('/books/'.$book->id.'/edit')->assertOk()->assertSee('value="2012-06-23"', false);
        $this->getJson('/api/v1/books/'.$book->id)->assertOk()->assertJsonPath('data.published_date', '2012-06-23');
    }

    public function test_api_accepts_a_book_without_isbn_and_published_date(): void
    {
        $this->postJson('/api/v1/books', [
            'user_id' => User::factory()->create()->id, 'title' => 'APIの本', 'author' => '著者',
            'genre_ids' => [Genre::factory()->create()->id],
        ])->assertCreated()->assertJsonPath('data.isbn', null)->assertJsonPath('data.published_date', null);
    }

    public function test_header_shows_advanced_links_and_unread_notification_count(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/books')->assertOk()
            ->assertSee('マイレポート')->assertSee('読書計画')->assertSee(url('/notifications'), false);

        DB::table('notifications')->insert([
            'id' => (string) str()->uuid(), 'type' => 'App\\Notifications\\Example',
            'notifiable_type' => User::class, 'notifiable_id' => $user->id,
            'data' => '{}', 'read_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($user->fresh())->get('/books')->assertOk()
            ->assertSee('bg-red-600 rounded-full">1</span>', false);
    }

    public function test_guest_header_does_not_query_notifications(): void
    {
        $this->get('/books')->assertOk()->assertSee('ログイン')->assertDontSee(url('/notifications'), false);
    }
}
