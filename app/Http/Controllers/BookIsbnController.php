<?php

namespace App\Http\Controllers;

use App\Services\GoogleBooksClient;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class BookIsbnController extends Controller
{
    /**
     * GET /books/isbn/{isbn}：登録・編集フォームの自動入力用に、書籍情報を JSON で返す。
     * 失敗時は {"error": "..."} を 422（形式違い）・404（該当なし）・502（API失敗）で返す。
     *
     * @param  string  $isbn  13桁のISBN
     * @param  GoogleBooksClient  $client  Google Books API のクライアント
     * @return JsonResponse JSONレスポンス
     */
    public function __invoke(string $isbn, GoogleBooksClient $client): JsonResponse
    {
        if (! preg_match('/\A\d{13}\z/', $isbn)) {
            return response()->json(['error' => 'ISBNは13桁の数字で入力してください。'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $book = $client->findByIsbn($isbn);
        } catch (RuntimeException $e) {
            report($e);

            return response()->json(['error' => '書籍情報を取得できませんでした。時間をおいて再度お試しください。'], Response::HTTP_BAD_GATEWAY);
        }

        if ($book === null) {
            return response()->json(['error' => '該当する書籍が見つかりませんでした。'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($book + ['isbn' => $isbn]);
    }
}
