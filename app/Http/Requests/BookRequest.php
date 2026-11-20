<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookRequest extends FormRequest
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
     * 書籍登録・編集で共通のルール。編集時は ISBN の一意性チェックから自身を除外する。
     *
     * @return array<string, mixed> 項目ごとのバリデーションルール
     */
    public function rules(): array
    {
        $book = $this->route('book');

        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['nullable', 'digits:13', Rule::unique('books', 'isbn')->ignore($book)],
            'published_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['integer', 'exists:genres,id'],
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
            'title.required' => 'タイトルを入力してください。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者を入力してください。',
            'author.max' => '著者は255文字以内で入力してください。',
            'isbn.digits' => 'ISBNは13桁の数字で入力してください。',
            'isbn.unique' => 'このISBNの書籍は既に登録されています。',
            'published_date.date' => '出版日は正しい日付で入力してください。',
            'image_url.url' => '画像URLはURLの形式で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',
            'genres.required' => 'ジャンルを1つ以上選択してください。',
            'genres.min' => 'ジャンルを1つ以上選択してください。',
            'genres.*.exists' => '選択されたジャンルが存在しません。',
        ];
    }
}
