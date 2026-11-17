<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($data) {
            $user = new User;
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->password = $data['password'];
            $user->save();

            $token = $user->createToken('api')->plainTextToken;

            return response()->json([
                'data' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ], 201);
        });
    }

    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Credenciais invalidas.'], 401);
        }

        if (! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciais invalidas.'], 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'data' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        }

        return response()->json(['message' => 'Sessao encerrada.']);
    }
}
