<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ReviewRequest extends FormRequest
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
     * レビュー投稿・編集で共通のルール。投稿時だけ、同じ書籍への2件目を止める（reviews の user_id・book_id は一意）。
     *
     * @return array<string, mixed> 項目ごとのバリデーションルール
     */
    public function rules(): array
    {
        $rules = [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];

        $book = $this->route('book');
        if ($book !== null) {
            $rules['rating'][] = function (string $attribute, mixed $value, Closure $fail) use ($book): void {
                if ($this->user()->reviews()->whereBelongsTo($book)->exists()) {
                    $fail('この書籍には既にレビューを投稿しています。');
                }
            };
        }

        return $rules;
    }

    /**
     * バリデーションエラーの日本語メッセージ。
     *
     * @return array<string, string> ルールごとのエラーメッセージ
     */
    public function messages(): array
    {
        return [
            'rating.required' => '評価を選択してください。',
            'rating.integer' => '評価は1〜5の中から選択してください。',
            'rating.between' => '評価は1〜5の中から選択してください。',
            'comment.max' => 'コメントは1000文字以内で入力してください。',
        ];
    }
}
