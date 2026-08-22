<?php

use Platform\Dev\Http\Controllers\AgentController;
use Platform\Dev\Http\Controllers\ErrorIngestController;
use Platform\Dev\Http\Controllers\IngestController;

// Generic ingest endpoint — dispatches by payload `type` (error | feature).
Route::post('/dev/ingest/{token}', [IngestController::class, 'ingest'])
    ->name('dev.api.ingest');

// Backwards-compatible alias — always handled as an error report.
Route::post('/dev/errors/ingest/{token}', [ErrorIngestController::class, 'ingest'])
    ->name('dev.api.errors.ingest');

// Agent API endpoints (token-authenticated via Bearer header)
Route::prefix('dev/agent')->middleware('auth:api')->group(function () {
    Route::get('/packages', [AgentController::class, 'packages'])
        ->name('dev.api.agent.packages');
    Route::get('/pipeline', [AgentController::class, 'pipeline'])
        ->name('dev.api.agent.pipeline');
    Route::post('/packages/{slug}/next-issue', [AgentController::class, 'nextIssue'])
        ->name('dev.api.agent.next-issue');
    Route::post('/packages/{slug}/next-untriaged', [AgentController::class, 'nextUntriaged'])
        ->name('dev.api.agent.next-untriaged');
    Route::post('/issues/{id}/triage', [AgentController::class, 'triage'])
        ->name('dev.api.agent.triage');
    Route::post('/issues/{id}/complete', [AgentController::class, 'complete'])
        ->name('dev.api.agent.complete');
    Route::post('/issues/{id}/log-time', [AgentController::class, 'logTime'])
        ->name('dev.api.agent.log-time');
    Route::post('/issues/{id}/fail', [AgentController::class, 'fail'])
        ->name('dev.api.agent.fail');
    Route::post('/issues/{id}/defer', [AgentController::class, 'defer'])
        ->name('dev.api.agent.defer');
    Route::post('/issues/{id}/ask', [AgentController::class, 'ask'])
        ->name('dev.api.agent.ask');
    Route::get('/stats', [AgentController::class, 'stats'])
        ->name('dev.api.agent.stats');
    Route::post('/issues/{id}/unlock', [AgentController::class, 'unlock'])
        ->name('dev.api.agent.unlock');
});
