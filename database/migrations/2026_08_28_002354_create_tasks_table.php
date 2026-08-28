<?php

use App\Enums\TaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // foreignUuid, no foreignId: la clave del proyecto es un UUID.
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();

            // nullOnDelete y no cascade: borrar a un usuario no debe destruir
            // el trabajo que tenia asignado, solo dejarlo sin asignar.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title', 160);
            $table->text('description')->nullable();

            // varchar y no ENUM de MySQL: ver App\Enums\TaskStatus.
            $table->string('status', 20)->default(TaskStatus::Pending->value);

            // date y no datetime: una fecha limite no tiene hora, y guardarla
            // con hora la expone a que una conversion de huso la corra un dia.
            $table->date('due_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Un indice por cada filtro del listado.
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
