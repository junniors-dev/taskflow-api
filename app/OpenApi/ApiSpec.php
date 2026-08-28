<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Bloque raiz del documento OpenAPI.
 *
 * Esta clase no se instancia nunca: existe solo para colgar de ella los
 * atributos globales (informacion, servidor, esquema de seguridad, etiquetas
 * y esquemas compartidos). Mantenerlos aqui evita ensuciar un controlador
 * real con metadatos que no le pertenecen.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'TaskFlow API',
    description: <<<'TXT'
    API REST de gestion de proyectos y tareas. Backend puro: sin vistas y sin
    sesiones, pensada para que la consuma cualquier cliente.

    **Autenticacion.** Registrate o inicia sesion para recibir un token, y
    mandalo en cada peticion como `Authorization: Bearer <token>`. Los tokens
    caducan a los siete dias.

    **Aislamiento.** Cada usuario solo ve y edita sus propios proyectos. Pedir
    un recurso de otra persona devuelve 403; las tareas heredan el permiso del
    proyecto al que pertenecen.

    **Errores.** Toda respuesta de error es JSON, tambien si olvidas la
    cabecera `Accept`. Un cuerpo que dice ser JSON y no lo es devuelve 400 con
    un mensaje explicito, en vez de tratarse como un cuerpo vacio y acabar en
    un confuso "campo obligatorio".

    **Datos de prueba.** Tras `php artisan migrate:fresh --seed` existe el
    usuario `demo@taskflow.test` con la contrasena `contrasena-demo`, y un
    cuarto proyecto que pertenece a otra persona para poder comprobar el 403.
    TXT,
)]
#[OA\Server(url: 'http://localhost:8000/api/v1', description: 'Entorno local')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    description: 'Token devuelto por /auth/register o /auth/login.',
)]
#[OA\Tag(name: 'Autenticacion', description: 'Registro, sesion y usuario actual.')]
#[OA\Tag(name: 'Proyectos', description: 'CRUD de proyectos del usuario autenticado.')]
#[OA\Tag(name: 'Tareas', description: 'Tareas dentro de un proyecto.')]
#[OA\Tag(name: 'Servicio', description: 'Comprobacion de estado.')]

// --------------------------------------------------------------------
// Esquemas compartidos
// --------------------------------------------------------------------

#[OA\Schema(
    schema: 'Error',
    title: 'Error',
    description: 'Cuerpo de todo error salvo los de validacion.',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ValidationError',
    title: 'Error de validacion',
    description: 'Unico cuerpo de error con estructura anidada.',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The title field is required.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string')),
            example: ['title' => ['The title field is required.']],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 3),
        new OA\Property(property: 'per_page', type: 'integer', example: 15),
        new OA\Property(property: 'to', type: 'integer', nullable: true, example: 15),
        new OA\Property(property: 'total', type: 'integer', example: 42),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PaginationLinks',
    properties: [
        new OA\Property(property: 'first', type: 'string', nullable: true),
        new OA\Property(property: 'last', type: 'string', nullable: true),
        new OA\Property(property: 'prev', type: 'string', nullable: true),
        new OA\Property(property: 'next', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'TokenResponse',
    properties: [
        new OA\Property(
            property: 'data',
            properties: [
                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                new OA\Property(property: 'token', type: 'string', example: '3|kR7pQ2mZ...'),
                new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
            ],
            type: 'object',
        ),
    ],
    type: 'object',
)]

// --------------------------------------------------------------------
// Comprobacion de estado (la ruta es una closure, no tiene controlador)
// --------------------------------------------------------------------

#[OA\Get(
    path: '/health',
    operationId: 'health',
    summary: 'Comprueba que la aplicacion y la base de datos responden',
    tags: ['Servicio'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Todo en pie',
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'string', example: 'ok'),
                new OA\Property(property: 'database', type: 'string', example: 'up'),
                new OA\Property(property: 'time', type: 'string', format: 'date-time'),
            ]),
        ),
        new OA\Response(response: 503, description: 'La base de datos no responde'),
    ],
)]
final class ApiSpec {}
