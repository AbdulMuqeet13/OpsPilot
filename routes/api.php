<?php

use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Middleware\AuthenticateWorkspaceApiKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([AuthenticateWorkspaceApiKey::class, 'throttle:workspace-api'])
    ->group(function () {
        Route::post('tickets', [TicketController::class, 'store'])->name('api.v1.tickets.store');
    });
