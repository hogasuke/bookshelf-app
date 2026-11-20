<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Books API から ISBN で書籍情報を引く。
 */
class GoogleBooksClient
{
    public const ENDPOINT = 'https://www.googleapis.com/books/v1/volumes';

    /**
     * ISBN-13 で1冊引き、登録フォームの項目名に揃えて返す。見つからなければ null。
     *
     * @return array{title: string, author: string, published_date: ?string, description: ?string, image_url: ?string}|null
     *
     * @throws RuntimeException 通信に失敗した、または API がエラーを返した時
     */
    public function findByIsbn(string $isbn): ?array
    {
        $query = ['q' => 'isbn:'.$isbn, 'maxResults' => 1];
        if (filled($key = config('services.google_books.key'))) {
            $query['key'] = $key;
        }

        try {
            $response = Http::timeout(5)->acceptJson()->get(self::ENDPOINT, $query)->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new RuntimeException('Google Books API への問い合わせに失敗しました。', 0, $e);
        }

        $info = $response->json('items.0.volumeInfo');
        if (! is_array($info) || blank($info['title'] ?? null)) {
            return null;
        }

        $image = $info['imageLinks']['thumbnail'] ?? $info['imageLinks']['smallThumbnail'] ?? null;

        return [
            'title' => $info['title'],
            'author' => implode(', ', $info['authors'] ?? []),
            'published_date' => $info['publishedDate'] ?? null,
            'description' => $info['description'] ?? null,
            // 画面は https で開くので、http の画像URLは https に揃える
            'image_url' => $image ? preg_replace('/^http:/', 'https:', $image) : null,
        ];
    }
}
