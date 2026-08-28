<?php

namespace App\Enums;

/**
 * Fuente unica de verdad del estado de una tarea.
 *
 * Alimenta el cast del modelo, la validacion via Rule::enum() y el esquema
 * OpenAPI. La columna en la base es un varchar y no un ENUM de MySQL: anadir
 * un cuarto estado a un ENUM es un ALTER TABLE que reconstruye la tabla;
 * aqui es una linea y un despliegue.
 */
enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
