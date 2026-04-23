<?php

use Platform\Dev\Http\Controllers\AgentController;
use Platform\Dev\Http\Controllers\ErrorIngestController;

Route::post('/dev/errors/ingest/{token}', [ErrorIngestController::class, 'ingest'])
    ->name('dev.api.errors.ingest');

// Agent API endpoints (token-authenticated via Bearer header)
Route::prefix('dev/agent')->middleware('auth:api')->group(function () {
    Route::post('/packages/{slug}/next-issue', [AgentController::class, 'nextIssue'])
        ->name('dev.api.agent.next-issue');
    Route::post('/issues/{id}/complete', [AgentController::class, 'complete'])
        ->name('dev.api.agent.complete');
    Route::post('/issues/{id}/fail', [AgentController::class, 'fail'])
        ->name('dev.api.agent.fail');
    Route::post('/issues/{id}/unlock', [AgentController::class, 'unlock'])
        ->name('dev.api.agent.unlock');
});
