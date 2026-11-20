<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenreRequest extends FormRequest
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
     * ジャンル登録・編集で共通のルール。編集時は一意性チェックから自身を除外する。
     *
     * @return array<string, mixed> 項目ごとのバリデーションルール
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('genres', 'name')->ignore($this->route('genre'))],
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
            'name.required' => 'ジャンル名を入力してください。',
            'name.max' => 'ジャンル名は255文字以内で入力してください。',
            'name.unique' => 'このジャンル名は既に登録されています。',
        ];
    }
}
