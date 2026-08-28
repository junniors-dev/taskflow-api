<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class IndexProjectRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        // El listado se construye desde $user->projects(): no hay nada que
        // autorizar porque no hay forma de pedir lo ajeno.
        return true;
    }

    /**
     * Los parametros de consulta se validan igual que un cuerpo de peticion.
     * Un ?status=terminada mal escrito debe devolver 422, no una lista vacia
     * que el cliente interpretaria como "no hay resultados".
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:120'],

            // La lista blanca no es cosmetica: es lo que hace seguro pasar
            // el valor a orderBy(). Los bindings de SQL protegen valores, no
            // identificadores como el nombre de una columna.
            'sort' => ['sometimes', Rule::in(['name', '-name', 'created_at', '-created_at'])],

            // Sin el tope, un ?per_page=100000 convierte un listado paginado
            // en un volcado completo de la tabla.
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
