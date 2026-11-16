<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TokenStoreRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    /**
     * メールアドレスとパスワードを確かめ、書き込み系APIで使う Sanctum のトークンを発行する。
     */
    public function store(TokenStoreRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'メールアドレスまたはパスワードが正しくありません。',
            ]);
        }

        $token = $user->createToken($request->validated('device_name') ?? 'api')->plainTextToken;

        return response()->json(['token' => $token, 'token_type' => 'Bearer'], Response::HTTP_CREATED);
    }
}
