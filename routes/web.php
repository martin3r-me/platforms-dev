<?php

use Platform\Dev\Livewire\Dashboard;
use Platform\Dev\Livewire\Package\Show;
use Platform\Dev\Livewire\Package\Board;
use Platform\Dev\Livewire\Package\Issue;
use Platform\Dev\Livewire\Package\Docs;
use Platform\Dev\Livewire\Package\Doc;

Route::get('/', Dashboard::class)->name('dev.dashboard');

// Health-Index (teamweite Package-Snapshot-Aggregat-Sicht)
Route::get('/health-index', \Platform\Dev\Livewire\HealthIndex::class)
    ->name('dev.health-index');

Route::get('/packages/{package}', Show::class)->name('dev.packages.show');

// Package-Health (Snapshot-Detail-Sicht pro Package)
Route::get('/packages/{package}/health', \Platform\Dev\Livewire\Package\Health::class)
    ->name('dev.packages.health');

Route::get('/packages/{package}/boards/{board}', Board::class)->name('dev.packages.boards.show');
Route::get('/packages/{package}/issues/{issue}', Issue::class)->name('dev.packages.issues.show');
Route::get('/packages/{package}/docs', Docs::class)->name('dev.packages.docs');
Route::get('/packages/{package}/docs/{docPage}', Doc::class)->name('dev.packages.docs.show');
