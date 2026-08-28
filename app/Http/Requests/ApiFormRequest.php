<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de todas las peticiones de la API.
 */
abstract class ApiFormRequest extends FormRequest
{
    /**
     * El usuario autenticado, garantizado no nulo.
     *
     * Estas rutas pasan por auth:sanctum, asi que en la practica user() nunca
     * devuelve null. Pero "en la practica" no es una garantia: el dia que
     * alguien quite el middleware de una ruta por error, sin esto el codigo
     * reventaria con un "call to a member function on null" y el cliente
     * recibiria un 500 opaco. Asi recibe el 401 que corresponde.
     *
     * @throws AuthenticationException
     */
    public function authenticatedUser(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
