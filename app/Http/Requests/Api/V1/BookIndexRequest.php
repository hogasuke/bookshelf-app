<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BookIndexRequest extends FormRequest
{
    /**
     * このリクエスト自体では利用者を絞らない（ログインの要否はルート、本人かどうかはPolicyで判定する）。
     *
     * @return bool 常に true
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 項目ごとのバリデーションルール。
     *
     * @return array<string, mixed> 項目ごとのバリデーションルール
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'genre_id' => ['nullable', 'integer', 'exists:genres,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * バリデーションエラーの日本語メッセージ。
     *
     * @return array<string, string> ルールごとのエラーメッセージ
     */
    public function messages(): array
    {
        return [
            'keyword.max' => 'キーワードは255文字以内で指定してください。',
            'genre_id.integer' => 'ジャンルIDは整数で指定してください。',
            'genre_id.exists' => '指定されたジャンルが存在しません。',
            'page.integer' => 'ページ番号は1以上の整数で指定してください。',
            'page.min' => 'ページ番号は1以上の整数で指定してください。',
            'per_page.integer' => '1ページあたりの件数は1〜100の整数で指定してください。',
            'per_page.min' => '1ページあたりの件数は1〜100の整数で指定してください。',
            'per_page.max' => '1ページあたりの件数は1〜100の整数で指定してください。',
        ];
    }
}
