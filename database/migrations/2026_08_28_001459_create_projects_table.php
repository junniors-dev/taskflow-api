<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            // UUID como clave publica: con enteros correlativos, /projects/1
            // invita a recorrer /projects/2 y ademas revela cuantos proyectos
            // existen en total.
            $table->uuid('id')->primary();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name', 120);
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Compuesto, no solo sobre user_id: todo listado filtra por dueno
            // y por "no borrado" a la vez, asi que un indice de una sola
            // columna dejaria a MariaDB descartando filas una por una.
            $table->index(['user_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
