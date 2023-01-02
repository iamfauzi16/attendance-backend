<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\UserResource;
use Symfony\Component\HttpFoundation\Response;


class AuthController extends Controller
{

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            
        ]);

        $data['password'] = Hash::make($request->password);
        $data['is_admin'] = $request->is_admin ?? false;

        $user = User::create($data);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $accessToken = Auth::user()->createToken('web-token')->plainTextToken;

        return response()->json([
            'message' => 'success',
            'data' => $user,
            'meta' => [
                'token' => $accessToken
            ]
        ], Response::HTTP_CREATED);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'message' => ['The provided credentials are incorrect.'],
            ]);
        }

        $accessToken = $user->createToken('web-token')->plainTextToken;

        return (new UserResource($user))->additional(compact('accessToken'));
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->where('id', $request->user()->currentAccessToken()->id)->delete();
        return response()->json([
            'message' => 'log out success'
        ], Response::HTTP_OK);
    }
}