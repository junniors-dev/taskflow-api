<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * "user_id" no figura entre los atributos rellenables a proposito: el dueno
 * sale del token, nunca del cuerpo de la peticion. Los proyectos se crean con
 * $request->user()->projects()->create(...), que lo asigna por la relacion.
 * Si estuviera aqui, bastaria con mandar user_id en el JSON para crear
 * proyectos a nombre de otra persona.
 */
#[Fillable(['name', 'description'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * El ON DELETE CASCADE de la foranea es una restriccion de MySQL y solo
     * reacciona a un DELETE real. Un borrado logico es un UPDATE de
     * deleted_at: la base de datos no se entera. Sin esto, las tareas de un
     * proyecto borrado seguirian respondiendo 200 en /tasks/{id}, huerfanas
     * de un padre que ya no existe.
     */
    protected static function booted(): void
    {
        static::deleting(function (Project $project) {
            // En un borrado fisico no hace falta: de eso ya se encarga la
            // cascada de la base. Ademas, marcar deleted_at en filas que van
            // a desaparecer en el mismo instante seria trabajo perdido.
            if (! $project->isForceDeleting()) {
                $project->tasks()->delete();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
