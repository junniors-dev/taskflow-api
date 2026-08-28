<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJsonBodyIsValid
{
    /**
     * Rechaza los cuerpos que dicen ser JSON pero no lo son.
     *
     * Sin esto, Laravel trata un JSON mal formado como un cuerpo vacio y la
     * peticion sigue su curso hasta la validacion, que responde "el campo
     * name es obligatorio". El cliente se pasa la tarde buscando por que no
     * llega un campo que si mando, cuando el problema real era una llave sin
     * cerrar. Un 400 explicito le ahorra esa sesion.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $body = $request->getContent();

        if ($body !== '' && $request->isJson() && ! json_validate($body)) {
            return response()->json([
                'message' => 'The request body is not valid JSON.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return $next($request);
    }
}
