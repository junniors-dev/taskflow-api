<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Carbon\CarbonInterface;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A diferencia de Project::$user_id, aqui "assigned_to" si es rellenable:
 * asignar una tarea es una accion legitima del usuario, no una suplantacion.
 * El proyecto al que pertenece, en cambio, lo fija la ruta anidada.
 *
 * Los @property de abajo no son decoracion: sin ellos, el analisis estatico
 * ve $task->status como un string y no detecta que ->value es valido, ni que
 * $task->due_date es un Carbon y no una cadena.
 *
 * @property string $id
 * @property string $project_id
 * @property int|null $assigned_to
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property CarbonInterface|null $due_date
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property-read Project|null $project
 * @property-read User|null $assignee
 */
#[Fillable(['title', 'description', 'status', 'due_date', 'assigned_to'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Toda tarea nace pendiente.
     *
     * El valor esta tambien como DEFAULT en la columna, pero eso solo lo
     * aplica la base al insertar: el modelo recien creado se quedaria con
     * status a null en memoria hasta releer la fila. Declararlo aqui evita
     * ese viaje de ida y vuelta.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => TaskStatus::Pending->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'due_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
