<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => 'API de Controle de Estoque',
    'documentation' => 'README.md',
]));
