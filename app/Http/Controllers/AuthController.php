<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $user = User::create($request->safe()->only(['name', 'email', 'password']));

            return (new UserResource($user))->additional([
                'token' => $user->createToken('api')->plainTextToken,
                'token_type' => 'Bearer',
            ])->response()->setStatusCode(201);
        });
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => 'Credenciais invalidas.'], 401);
        }

        return (new UserResource($user))->additional([
            'token' => $user->createToken('api')->plainTextToken,
            'token_type' => 'Bearer',
        ])->response();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sessao encerrada.']);
    }
}
