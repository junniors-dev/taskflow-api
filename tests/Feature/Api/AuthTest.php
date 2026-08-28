<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

// ---------------------------------------------------------------------
// Registro
// ---------------------------------------------------------------------

it('registra un usuario y devuelve su primer token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Junni Diaz',
        'email' => 'junni@example.com',
        'password' => 'contrasena-segura',
        'password_confirmation' => 'contrasena-segura',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'created_at'],
                'token',
                'token_type',
            ],
        ])
        ->assertJsonPath('data.user.email', 'junni@example.com')
        ->assertJsonPath('data.token_type', 'Bearer');

    $user = User::firstWhere('email', 'junni@example.com');

    expect($user)->not->toBeNull()
        ->and(Hash::check('contrasena-segura', $user->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(1);
});

it('nunca incluye la contrasena en la respuesta', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Junni Diaz',
        'email' => 'junni@example.com',
        'password' => 'contrasena-segura',
        'password_confirmation' => 'contrasena-segura',
    ])->assertJsonMissingPath('data.user.password');
});

it('rechaza un email ya registrado', function () {
    User::factory()->create(['email' => 'ocupado@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Otra Persona',
        'email' => 'ocupado@example.com',
        'password' => 'contrasena-segura',
        'password_confirmation' => 'contrasena-segura',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('exige confirmar la contrasena', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Junni Diaz',
        'email' => 'junni@example.com',
        'password' => 'contrasena-segura',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('exige una contrasena de al menos ocho caracteres', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Junni Diaz',
        'email' => 'junni@example.com',
        'password' => 'corta',
        'password_confirmation' => 'corta',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

// ---------------------------------------------------------------------
// Login
// ---------------------------------------------------------------------

it('emite un token con credenciales correctas', function () {
    $user = User::factory()->create(['password' => 'contrasena-segura']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'contrasena-segura',
    ])->assertOk()->assertJsonPath('data.user.id', $user->id);
});

it('rechaza una contrasena incorrecta', function () {
    $user = User::factory()->create(['password' => 'contrasena-segura']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'la-equivocada',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('no permite distinguir un email registrado de uno que no lo esta', function () {
    $user = User::factory()->create(['password' => 'contrasena-segura']);

    $existente = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'la-equivocada',
    ])->assertStatus(422)->json();

    $inexistente = $this->postJson('/api/v1/auth/login', [
        'email' => 'nadie@example.com',
        'password' => 'la-equivocada',
    ])->assertStatus(422)->json();

    // Si estas dos respuestas difirieran, el login serviria como verificador
    // de que direcciones estan registradas en el sistema.
    expect($inexistente)->toBe($existente);
});

// ---------------------------------------------------------------------
// Sesion
// ---------------------------------------------------------------------

it('rechaza con 401 el acceso sin token', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('devuelve el usuario autenticado', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonMissingPath('data.password');
});

it('al cerrar sesion revoca solo el token usado', function () {
    $user = User::factory()->create();
    $movil = $user->createToken('movil')->plainTextToken;
    $navegador = $user->createToken('navegador')->plainTextToken;

    $this->withToken($movil)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect(PersonalAccessToken::findToken($movil))->toBeNull()
        ->and(PersonalAccessToken::findToken($navegador))->not->toBeNull();

    // El guard cachea el usuario que resolvio en la peticion anterior. En
    // produccion cada peticion es un proceso nuevo; dentro de un test todas
    // comparten la misma instancia de la aplicacion, asi que hay que
    // limpiarlo para que la siguiente peticion vuelva a mirar el token.
    $this->app['auth']->forgetGuards();

    $this->withToken($movil)->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->withToken($navegador)->getJson('/api/v1/auth/me')->assertOk();
});

// ---------------------------------------------------------------------
// Limite de peticiones
// ---------------------------------------------------------------------

it('bloquea con 429 tras cinco intentos fallidos de login', function () {
    $credenciales = ['email' => 'alguien@example.com', 'password' => 'incorrecta'];

    foreach (range(1, 5) as $intento) {
        $this->postJson('/api/v1/auth/login', $credenciales)->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/login', $credenciales)
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});
