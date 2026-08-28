<?php

/**
 * Pruebas de arquitectura: invariantes del proyecto que ninguna prueba
 * funcional detectaria, porque no fallan al ejecutar sino al crecer.
 */

// Detecta eval(), md5/sha1 para contrasenas, generadores debiles de aleatorios
// y companyia.
arch()->preset()->security();

arch('no quedan restos de depuracion')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'ray'])
    ->not->toBeUsed();

arch('los controladores se llaman Controller y parten de la base')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller')
    ->toExtend('App\Http\Controllers\Controller')
    ->ignoring('App\Http\Controllers\Controller');

arch('toda peticion parte de la base comun de la API')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request')
    ->toExtend('App\Http\Requests\ApiFormRequest')
    ->ignoring('App\Http\Requests\ApiFormRequest');

arch('las respuestas pasan siempre por un API Resource')
    ->expect('App\Http\Resources')
    ->toHaveSuffix('Resource')
    ->toExtend('Illuminate\Http\Resources\Json\JsonResource');

arch('las policies se llaman Policy')
    ->expect('App\Policies')
    ->toHaveSuffix('Policy');

arch('los enums son enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('los modelos no conocen la capa HTTP')
    ->expect('App\Models')
    ->not->toUse('App\Http');

arch('las policies no conocen la capa HTTP')
    ->expect('App\Policies')
    ->not->toUse('App\Http');
