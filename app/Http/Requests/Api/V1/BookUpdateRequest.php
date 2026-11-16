<?php

namespace App\Http\Requests\Api\V1;

/**
 * 公開API書籍登録と同じルール（ISBNの一意性チェックは自身を除外。BookStoreRequest 側で route('book') を見て除外する）。
 */
class BookUpdateRequest extends BookStoreRequest {}
