<?php

use Illuminate\Support\Facades\Schedule;

// Los tokens caducados siguen ocupando fila en la tabla hasta que alguien
// los borra. Sanctum trae el comando; hay que programarlo.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
