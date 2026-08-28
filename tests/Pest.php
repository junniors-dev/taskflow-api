<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Caso base
|--------------------------------------------------------------------------
| Toda prueba de Feature corre contra la base taskflow_api_test, envuelta en
| una transaccion que se revierte al terminar. Es el mismo motor que usa
| desarrollo: un test verde en SQLite probaria menos de lo que aparenta.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
