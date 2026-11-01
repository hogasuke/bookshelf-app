<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
        $this->withoutVite();
    }

    public function test_missing_book_shows_japanese_404_page_with_link_to_book_list(): void
    {
        $this->get('/books/99999')->assertNotFound()
            ->assertSee('ページが見つかりません')
            ->assertSee('href="'.url('/books').'"', false);
    }

    public function test_unknown_url_shows_the_404_page_for_guests(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('ページが見つかりません');
    }

    public function test_editing_another_users_review_shows_japanese_403_page(): void
    {
        $book = Book::create(['title' => 'Book', 'author' => 'Author', 'isbn' => '9780000000001', 'published_date' => '2026-01-01']);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $review = Review::create(['user_id' => $owner->id, 'book_id' => $book->id, 'rating' => 4]);

        $this->actingAs($other)->get('/reviews/'.$review->id.'/edit')->assertForbidden()
            ->assertSee('権限がありません')
            ->assertSee('href="'.url('/books').'"', false);
    }
}
