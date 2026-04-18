<?php

use Platform\Dev\Http\Controllers\ErrorIngestController;

Route::post('/dev/errors/ingest/{token}', [ErrorIngestController::class, 'ingest'])
    ->name('dev.api.errors.ingest');
