<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Predoza\Laravel\Http\HandoffController;

// on "web" for the member's session; without the CSRF check, which embed.js can't pass and the handoff's own
// same-origin guard replaces
Route::post((string) config('predoza.handoff_path'), HandoffController::class)
    ->middleware('web')
    ->withoutMiddleware([ValidateCsrfToken::class, VerifyCsrfToken::class])
    ->name('predoza.handoff');
