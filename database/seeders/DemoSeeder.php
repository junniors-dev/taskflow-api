<?php

namespace Database\Seeders;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de demostracion reproducibles.
 *
 * No usa valores aleatorios a proposito: los ejemplos del README y de la
 * coleccion de Postman apuntan a estos mismos nombres, y una demo que cambia
 * en cada ejecucion no sirve para documentar nada.
 *
 * Crea dos usuarios porque el aislamiento por dueno es la parte interesante
 * de esta API: con uno solo no habria forma de comprobar el 403 desde
 * Postman.
 */
class DemoSeeder extends Seeder
{
    public const DEMO_EMAIL = 'demo@taskflow.test';

    public const OTRO_EMAIL = 'otra@taskflow.test';

    public const PASSWORD = 'contrasena-demo';

    public function run(): void
    {
        $demo = User::factory()->create([
            'name' => 'Usuario Demo',
            'email' => self::DEMO_EMAIL,
            'password' => self::PASSWORD,
        ]);

        $otra = User::factory()->create([
            'name' => 'Otra Persona',
            'email' => self::OTRO_EMAIL,
            'password' => self::PASSWORD,
        ]);

        $sitio = Project::factory()->for($demo, 'owner')->create([
            'name' => 'Rediseno del sitio',
            'description' => 'Landing nueva y sistema de diseno para el lanzamiento.',
        ]);

        $app = Project::factory()->for($demo, 'owner')->create([
            'name' => 'App movil',
            'description' => 'Primera version para Android, consumiendo esta misma API.',
        ]);

        Project::factory()->for($demo, 'owner')->create([
            'name' => 'Mudanza de oficina',
            'description' => 'Proyecto sin tareas todavia, para ver un listado vacio.',
        ]);

        // Proyecto de otra persona: pedirlo con el token del usuario demo
        // devuelve 403. Es la demostracion mas util de toda la coleccion.
        Project::factory()->for($otra, 'owner')->create([
            'name' => 'Proyecto ajeno',
            'description' => 'No deberia ser visible con el token del usuario demo.',
        ]);

        $this->tareasDelSitio($sitio, $demo, $otra);
        $this->tareasDeLaApp($app, $demo);

        $this->command?->newLine();
        $this->command?->info('Datos de demostracion listos.');
        $this->command?->line('  Usuario: '.self::DEMO_EMAIL.'  /  '.self::PASSWORD);
        $this->command?->line('  Otro:    '.self::OTRO_EMAIL.'  /  '.self::PASSWORD);
    }

    private function tareasDelSitio(Project $project, User $demo, User $otra): void
    {
        // Vencimientos repartidos entre pasado y futuro para que los filtros
        // por fecha devuelvan algo distinto segun el rango que se pida.
        $this->crear($project, [
            ['Definir la paleta de color', TaskStatus::Completed, '-21 days', $demo],
            ['Wireframes de la portada', TaskStatus::Completed, '-14 days', $demo],
            ['Disenar el logo', TaskStatus::InProgress, '+5 days', $otra],
            ['Escribir los textos de la landing', TaskStatus::InProgress, '-2 days', $demo],
            ['Maquetar la seccion de precios', TaskStatus::Pending, '+12 days', null],
            ['Revision de accesibilidad', TaskStatus::Pending, null, null],
        ]);
    }

    private function tareasDeLaApp(Project $project, User $demo): void
    {
        $this->crear($project, [
            ['Elegir la libreria de red', TaskStatus::Completed, '-9 days', $demo],
            ['Pantalla de inicio de sesion', TaskStatus::InProgress, '+3 days', $demo],
            ['Listado de proyectos', TaskStatus::Pending, '+18 days', $demo],
            ['Modo sin conexion', TaskStatus::Pending, null, null],
        ]);
    }

    /**
     * @param  list<array{0: string, 1: TaskStatus, 2: string|null, 3: User|null}>  $tareas
     */
    private function crear(Project $project, array $tareas): void
    {
        foreach ($tareas as [$titulo, $estado, $vencimiento, $asignado]) {
            Task::factory()->for($project)->create([
                'title' => $titulo,
                'description' => null,
                'status' => $estado,
                'due_date' => $vencimiento ? now()->modify($vencimiento)->toDateString() : null,
                'assigned_to' => $asignado?->id,
            ]);
        }
    }
}
