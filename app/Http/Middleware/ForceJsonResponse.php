<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Fuerza la cabecera Accept en todo el grupo "api".
     *
     * Sin esto, un cliente que olvide enviar "Accept: application/json"
     * recibe HTML en los errores: el formato de la respuesta pasaria a
     * depender del cliente en vez de ser parte del contrato de la API.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
