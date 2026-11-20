<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use App\Services\GoogleBooksClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 書籍一覧の検索・ジャンル絞り込み・並び替え・ページ送り（シート7 D16〜D19）と、
 * ISBN検索（シート7 D30・Google Books）。外部通信はすべて Http::fake で止める。
 */
class BookSearchIsbnTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
        Http::preventStrayRequests();
    }

    private function book(string $title, string $author = '誰か', ?string $createdAt = null): Book
    {
        $book = Book::factory()->create(['title' => $title, 'author' => $author]);
        if ($createdAt) {
            $book->forceFill(['created_at' => $createdAt])->save();
        }

        return $book;
    }

    /** @return list<string> 一覧に出た書籍タイトルを、出た順で返す */
    private function titles(string $query): array
    {
        return $this->get('/books'.$query)->assertOk()->viewData('books')->pluck('title')->all();
    }

    public function test_keyword_matches_title_or_author_partially(): void
    {
        $this->book('Laravel入門', '山田');
        $this->book('PHP実践', '佐藤ララ');
        $this->book('料理の本', '鈴木');

        $titles = $this->titles('?keyword=ララ');
        $this->assertSame(['PHP実践'], $titles);
        $this->assertEqualsCanonicalizing(['Laravel入門'], $this->titles('?keyword=ravel'));
        $this->assertCount(3, $this->titles('?keyword='));
    }

    public function test_genre_filter_shows_only_books_in_the_genre(): void
    {
        $novel = Genre::factory()->create(['name' => '小説']);
        $tech = Genre::factory()->create(['name' => '技術']);
        $this->book('小説A')->genres()->attach($novel);
        $this->book('技術A')->genres()->attach($tech);
        $both = $this->book('両方');
        $both->genres()->attach([$novel->id, $tech->id]);

        $this->assertEqualsCanonicalizing(['小説A', '両方'], $this->titles('?genre='.$novel->id));
        $this->get('/books')->assertSee('<option value="'.$novel->id.'"', false);
    }

    public function test_keyword_and_genre_combine(): void
    {
        $novel = Genre::factory()->create();
        $this->book('猫の小説')->genres()->attach($novel);
        $this->book('猫の図鑑');

        $this->assertSame(['猫の小説'], $this->titles('?keyword=猫&genre='.$novel->id));
    }

    public function test_sort_orders(): void
    {
        $this->book('B本', '誰か', '2026-01-02 00:00:00');
        $this->book('A本', '誰か', '2026-01-03 00:00:00');
        $this->book('C本', '誰か', '2026-01-01 00:00:00');

        $this->assertSame(['A本', 'B本', 'C本'], $this->titles(''));
        $this->assertSame(['A本', 'B本', 'C本'], $this->titles('?sort=latest'));
        $this->assertSame(['A本', 'B本', 'C本'], $this->titles('?sort=newest'));
        $this->assertSame(['C本', 'B本', 'A本'], $this->titles('?sort=oldest'));
        $this->assertSame(['A本', 'B本', 'C本'], $this->titles('?sort=title'));
        $this->assertSame(['A本', 'B本', 'C本'], $this->titles('?sort=unknown'));
    }

    public function test_rating_sort_puts_books_without_reviews_last(): void
    {
        $none = $this->book('レビューなし', '誰か', '2026-01-09 00:00:00');
        $low = $this->book('低評価');
        $high = $this->book('高評価');
        Review::factory()->create(['book_id' => $low->id, 'rating' => 2]);
        Review::factory()->create(['book_id' => $high->id, 'rating' => 5]);
        Review::factory()->create(['book_id' => $high->id, 'rating' => 4]);

        $this->assertSame(['高評価', '低評価', 'レビューなし'], $this->titles('?sort=rating'));
        $this->assertNotNull($none);
    }

    public function test_pagination_links_keep_search_conditions(): void
    {
        $genre = Genre::factory()->create();
        foreach (range(1, 12) as $i) {
            $this->book(sprintf('猫%02d', $i))->genres()->attach($genre);
        }
        $this->book('犬');

        $response = $this->get('/books?keyword=猫&genre='.$genre->id.'&sort=title');
        $response->assertOk();
        $paginator = $response->viewData('books');
        $this->assertSame(12, $paginator->total());
        $next = $paginator->nextPageUrl();
        foreach (['keyword=', 'genre='.$genre->id, 'sort=title', 'page=2'] as $part) {
            $this->assertStringContainsString($part, urldecode($next));
        }

        $this->assertSame(['猫11', '猫12'], $this->titles('?keyword=猫&genre='.$genre->id.'&sort=title&page=2'));
    }

    public function test_array_query_values_do_not_break_the_list(): void
    {
        $this->book('本');

        $this->get('/books?keyword[]=x&genre[]=1&sort[]=title')->assertOk();
    }

    private function fakeGoogle(array $body, int $status = 200): void
    {
        Http::fake([GoogleBooksClient::ENDPOINT.'*' => Http::response($body, $status)]);
    }

    public function test_isbn_lookup_returns_form_values_from_google_books(): void
    {
        $this->fakeGoogle(['totalItems' => 1, 'items' => [['volumeInfo' => [
            'title' => 'リーダブルコード',
            'authors' => ['Dustin Boswell', 'Trevor Foucher'],
            'publishedDate' => '2012-06-23',
            'description' => '読みやすいコードの本',
            'imageLinks' => ['thumbnail' => 'http://books.google.com/x.jpg'],
        ]]]]);

        $this->actingAs(User::factory()->create())->getJson('/books/isbn/9784873115658')
            ->assertOk()->assertExactJson([
                'title' => 'リーダブルコード',
                'author' => 'Dustin Boswell, Trevor Foucher',
                'published_date' => '2012-06-23',
                'description' => '読みやすいコードの本',
                'image_url' => 'https://books.google.com/x.jpg',
                'isbn' => '9784873115658',
            ]);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), GoogleBooksClient::ENDPOINT)
            && $request['q'] === 'isbn:9784873115658');
    }

    public function test_isbn_lookup_sends_api_key_only_when_configured(): void
    {
        config(['services.google_books.key' => 'test-key']);
        $this->fakeGoogle(['items' => [['volumeInfo' => ['title' => 'T']]]]);

        $this->actingAs(User::factory()->create())->getJson('/books/isbn/9784873115658')->assertOk()
            ->assertJson(['title' => 'T', 'author' => '', 'image_url' => null]);
        Http::assertSent(fn (Request $request) => $request['key'] === 'test-key');
    }

    public function test_isbn_lookup_returns_404_when_not_found(): void
    {
        $this->fakeGoogle(['totalItems' => 0]);

        $this->actingAs(User::factory()->create())->getJson('/books/isbn/9780000000000')
            ->assertNotFound()->assertJson(['error' => '該当する書籍が見つかりませんでした。']);
    }

    public function test_isbn_lookup_returns_502_when_api_fails(): void
    {
        $this->fakeGoogle(['error' => ['message' => 'quota']], 429);

        $this->actingAs(User::factory()->create())->getJson('/books/isbn/9784873115658')
            ->assertStatus(502)->assertJsonStructure(['error']);
    }

    public function test_isbn_lookup_rejects_non_13_digit_without_calling_api(): void
    {
        $user = User::factory()->create();
        foreach (['123', '97848731156581', '978487311565X'] as $isbn) {
            $this->actingAs($user)->getJson('/books/isbn/'.$isbn)->assertStatus(422)->assertJsonStructure(['error']);
        }
        Http::assertNothingSent();
    }

    public function test_isbn_lookup_requires_login(): void
    {
        $this->get('/books/isbn/9784873115658')->assertRedirect('/login');
        Http::assertNothingSent();
    }
}
