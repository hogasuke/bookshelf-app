<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 読書計画の作成・編集（シート8 D19）。
 * 作成：書籍と期日。同じ書籍に読了していない計画が既にあれば重ねない。
 * 編集：期日だけ（書籍は変えない）。期日は作成・編集とも今日以降。
 */
class ReadingPlanRequest extends FormRequest
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
        $rules = [
            'target_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];

        if ($this->isMethod('post')) {
            $rules['book_id'] = [
                'required',
                'integer',
                Rule::exists('books', 'id'),
                Rule::unique('reading_plans', 'book_id')
                    ->where('user_id', $this->user()->id)
                    ->where(fn ($query) => $query->where('status', '!=', ReadingPlanStatus::Completed->value)),
            ];
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
            'book_id.required' => '書籍を選択してください。',
            'book_id.integer' => '選択した書籍が見つかりません。',
            'book_id.exists' => '選択した書籍が見つかりません。',
            'book_id.unique' => 'この書籍には読了していない読書計画が既にあります。',
            'target_date.required' => '期日を入力してください。',
            'target_date.date_format' => '期日は日付の形（例：2026-12-01）で入力してください。',
            'target_date.after_or_equal' => '期日は今日以降の日付を入力してください。',
        ];
    }
}
