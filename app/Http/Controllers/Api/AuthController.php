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
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    /**
     * Crea el usuario y emite su primer token.
     */
    #[OA\Post(
        path: '/auth/register',
        operationId: 'auth.register',
        summary: 'Registra un usuario y devuelve su primer token',
        description: 'Limitado a 5 peticiones por minuto por combinacion de email e IP.',
        tags: ['Autenticacion'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Usuario Demo'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'demo@taskflow.test'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'contrasena-demo'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'contrasena-demo'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Usuario creado', content: new OA\JsonContent(ref: '#/components/schemas/TokenResponse')),
            new OA\Response(response: 422, description: 'Datos invalidos o email ya registrado', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Demasiados intentos', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        return $this->tokenResponse($user, Response::HTTP_CREATED);
    }

    /**
     * Emite un token nuevo para un usuario existente.
     */
    #[OA\Post(
        path: '/auth/login',
        operationId: 'auth.login',
        summary: 'Emite un token para un usuario existente',
        description: <<<'TXT'
        Devuelve el mismo 422 tanto si el email no existe como si la contrasena
        es incorrecta. Distinguirlos convertiria este endpoint en un
        verificador de que direcciones estan registradas.

        Limitado a 5 intentos por minuto por combinacion de email e IP.
        TXT,
        tags: ['Autenticacion'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'demo@taskflow.test'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'contrasena-demo'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Token emitido', content: new OA\JsonContent(ref: '#/components/schemas/TokenResponse')),
            new OA\Response(response: 422, description: 'Credenciales incorrectas', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Demasiados intentos', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
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
    #[OA\Post(
        path: '/auth/logout',
        operationId: 'auth.logout',
        summary: 'Revoca el token usado en la peticion',
        description: 'Solo ese token. Cerrar sesion en el movil no desconecta la del navegador.',
        security: [['bearerAuth' => []]],
        tags: ['Autenticacion'],
        responses: [
            new OA\Response(response: 204, description: 'Token revocado'),
            new OA\Response(response: 401, description: 'Sin token o token invalido', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function logout(Request $request): Response
    {
        // Cerrar sesion en el movil no debe desconectar la del navegador.
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Devuelve el usuario autenticado.
     */
    #[OA\Get(
        path: '/auth/me',
        operationId: 'auth.me',
        summary: 'Devuelve el usuario autenticado',
        security: [['bearerAuth' => []]],
        tags: ['Autenticacion'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Usuario actual',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Sin token o token invalido', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
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
