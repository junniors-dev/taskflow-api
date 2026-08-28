<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Crea el usuario y emite su primer token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        return $this->tokenResponse($user, Response::HTTP_CREATED);
    }

    /**
     * Emite un token nuevo para un usuario existente.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            // Un unico mensaje para "no existe" y "contrasena incorrecta":
            // distinguirlos convierte el login en un verificador de que
            // direcciones estan registradas.
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return $this->tokenResponse($user, Response::HTTP_OK);
    }

    /**
     * Revoca unicamente el token con el que se hizo la peticion.
     */
    public function logout(Request $request): Response
    {
        // Cerrar sesion en el movil no debe desconectar la del navegador.
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Devuelve el usuario autenticado.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    private function tokenResponse(User $user, int $status): JsonResponse
    {
        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $user->createToken('api')->plainTextToken,
                'token_type' => 'Bearer',
            ],
        ], $status);
    }
}
