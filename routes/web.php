<?php

use Fateh\Phonsee\Http\Controllers\FatehController;
use Illuminate\Support\Facades\Route;

    Route::post('/backup', [FatehController::class, 'createBackup'])->name('google-drive-backup');

    Route::get('/redirect', [FatehController::class, 'redirect'])->name('google-drive-redirect');

