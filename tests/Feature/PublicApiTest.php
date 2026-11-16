<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
    }

    private function input(array $overrides = []): array
    {
        return array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'API の本', 'author' => 'API 著者', 'isbn' => '9784000000009',
            'published_date' => '2021-05-01', 'genre_ids' => [Genre::factory()->create()->id],
        ], $overrides);
    }

    public function test_index_returns_genres_average_and_review_count_with_pagination(): void
    {
        $book = Book::factory()->create(['title' => '探す本']);
        $book->genres()->attach($genre = Genre::factory()->create());
        Review::factory()->create(['book_id' => $book->id, 'rating' => 5]);
        Review::factory()->create(['book_id' => $book->id, 'rating' => 4]);
        Book::factory()->count(2)->create();

        $this->getJson('/api/v1/books?per_page=2')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3)->assertJsonPath('meta.per_page', 2);

        $this->getJson('/api/v1/books?keyword=探す&genre_id='.$genre->id)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '探す本')
            ->assertJsonPath('data.0.genres.0.name', $genre->name)
            ->assertJsonPath('data.0.average_rating', 4.5)
            ->assertJsonPath('data.0.reviews_count', 2);
    }

    public function test_index_validates_search_parameters_in_japanese(): void
    {
        $this->getJson('/api/v1/books?per_page=0&genre_id=999')->assertStatus(422)
            ->assertJsonPath('errors.per_page.0', '1ページあたりの件数は1〜100の整数で指定してください。')
            ->assertJsonPath('errors.genre_id.0', '指定されたジャンルが存在しません。');
    }

    public function test_show_includes_reviews_and_missing_id_returns_404_json(): void
    {
        $review = Review::factory()->create(['comment' => '読んだ']);

        $this->getJson('/api/v1/books/'.$review->book_id)->assertOk()
            ->assertJsonPath('data.reviews.0.user_name', $review->user->name)
            ->assertJsonPath('data.reviews.0.comment', '読んだ');

        $this->get('/api/v1/books/9999')->assertNotFound()
            ->assertExactJson(['message' => '指定されたリソースが見つかりません。']);
    }

    public function test_store_returns_201_and_validation_errors_return_422_in_japanese(): void
    {
        $input = $this->input();

        $this->postJson('/api/v1/books', $input)->assertCreated()
            ->assertJsonPath('data.title', 'API の本')->assertJsonPath('data.user_id', $input['user_id']);
        $this->assertDatabaseHas('books', ['isbn' => '9784000000009', 'user_id' => $input['user_id']]);

        $this->post('/api/v1/books', ['user_id' => 9999, 'isbn' => '123'])->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', '指定された登録者が存在しません。')
            ->assertJsonPath('errors.title.0', 'タイトルを入力してください。')
            ->assertJsonPath('errors.isbn.0', 'ISBNは13桁の数字で入力してください。');
    }

    public function test_update_keeps_own_isbn_and_missing_id_returns_404(): void
    {
        $book = Book::factory()->create(['isbn' => '9784000000010']);

        $this->putJson('/api/v1/books/'.$book->id, $this->input(['title' => '改題', 'isbn' => '9784000000010']))
            ->assertOk()->assertJsonPath('data.title', '改題');

        $this->putJson('/api/v1/books/9999', $this->input(['isbn' => '9784000000011']))->assertNotFound();
    }

    public function test_destroy_removes_related_rows_and_missing_id_returns_404(): void
    {
        $review = Review::factory()->create();
        $book = $review->book;
        $book->genres()->attach(Genre::factory()->create());
        $book->favoriteByUsers()->attach(User::factory()->create());

        $this->deleteJson('/api/v1/books/'.$book->id)->assertNoContent();
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('favorites', 0);
        $this->assertDatabaseCount('book_genre', 0);

        $this->deleteJson('/api/v1/books/9999')->assertNotFound();
    }
}
