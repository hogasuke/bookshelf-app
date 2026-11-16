<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Web版の書籍登録と同じルール。登録者は Sanctum トークンの持ち主で決まるため、
     * user_id は送らせない（送られたら 422。他人になりすました登録を防ぐ。シート12 E18）。
     */
    public function rules(): array
    {
        return [
            'user_id' => ['prohibited'],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['nullable', 'digits:13', Rule::unique('books', 'isbn')->ignore($this->route('book'))],
            'published_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genre_ids' => ['required', 'array', 'min:1'],
            'genre_ids.*' => ['integer', 'exists:genres,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.prohibited' => '登録者IDは指定できません。登録者はAPIトークンの持ち主になります。',
            'title.required' => 'タイトルを入力してください。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者を入力してください。',
            'author.max' => '著者は255文字以内で入力してください。',
            'isbn.digits' => 'ISBNは13桁の数字で入力してください。',
            'isbn.unique' => 'このISBNの書籍は既に登録されています。',
            'published_date.date' => '出版日は正しい日付で入力してください。',
            'image_url.url' => '画像URLはURLの形式で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',
            'genre_ids.required' => 'ジャンルを1つ以上指定してください。',
            'genre_ids.array' => 'ジャンルは配列で指定してください。',
            'genre_ids.min' => 'ジャンルを1つ以上指定してください。',
            'genre_ids.*.integer' => 'ジャンルIDは整数で指定してください。',
            'genre_ids.*.exists' => '指定されたジャンルが存在しません。',
        ];
    }
}
