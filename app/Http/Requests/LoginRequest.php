<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        // Aqui no se valida el formato de la contrasena: las reglas de
        // fortaleza son cosa del registro. Exigirlas al entrar solo le diria
        // a un atacante que su intento no llego siquiera a compararse.
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
