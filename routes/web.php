<?php

use Fateh\Phonsee\Http\Controllers\FatehController;
use Illuminate\Support\Facades\Route;

Route::get('/fatehdatabase', [FatehController::class, 'backupDatabase']);
