<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookReadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
        $this->withoutVite();
    }

    public function test_guest_sees_latest_ten_books_and_genres_on_both_list_urls(): void
    {
        $genre = Genre::create(['name' => '小説']);
        for ($index = 1; $index <= 11; $index++) {
            $book = $this->createBook($index);
            $book->genres()->attach($genre);
        }

        foreach (['/', '/books'] as $url) {
            $this->get($url)->assertOk()->assertSee('小説')->assertSee('Book 11')
                ->assertDontSee('Book 01')->assertViewHas('books', function ($books) {
                    return $books->count() === 10 && $books->total() === 11
                        && $books->first()->title === 'Book 11'
                        && $books->every(fn ($book) => $book->relationLoaded('genres'));
                });
        }
        $this->get('/books?page=2')->assertOk()->assertSee('Book 01')->assertDontSee('Book 11');
    }

    public function test_guest_and_logged_in_user_can_read_details_with_review_author_and_like_count(): void
    {
        $book = $this->createBook(1);
        $genre = Genre::create(['name' => '技術']);
        $book->genres()->attach($genre);
        $author = User::factory()->create(['name' => 'Review author']);
        $review = Review::create(['user_id' => $author->id, 'book_id' => $book->id, 'rating' => 4, 'comment' => '役立つ本']);
        $review->likedByUsers()->attach($author);

        foreach ([false, true] as $loggedIn) {
            if ($loggedIn) {
                $this->actingAs($author);
            }
            $this->get('/books/'.$book->id)->assertOk()->assertSee('技術')
                ->assertSee('Review author')->assertSee('役立つ本')->assertSee('(1)')
                ->assertViewHas('book', fn ($loaded) => $loaded->relationLoaded('genres')
                    && $loaded->reviews->first()->relationLoaded('user')
                    && $loaded->reviews->first()->relationLoaded('likedByUsers'));
        }
    }

    public function test_empty_list_and_book_without_reviews_render(): void
    {
        $this->get('/books')->assertOk()->assertSee('書籍が登録されていません。');
        $book = $this->createBook(1);
        $this->get('/books/'.$book->id)->assertOk()->assertSee('まだレビューはありません。');
    }

    private function createBook(int $index): Book
    {
        $book = Book::factory()->make([
            'title' => sprintf('Book %02d', $index),
            'author' => 'Test author',
            'isbn' => sprintf('978000000%04d', $index),
            'published_date' => '2026-01-01',
        ]);
        $book->created_at = now()->subDays(20 - $index);
        $book->save();

        return $book;
    }
}
