<?php

use Platform\Dev\Livewire\Dashboard;
use Platform\Dev\Livewire\Package\Show;
use Platform\Dev\Livewire\Package\Board;
use Platform\Dev\Livewire\Package\Issue;
use Platform\Dev\Livewire\Package\Discussions;
use Platform\Dev\Livewire\Package\Doc;

Route::get('/', Dashboard::class)->name('dev.dashboard');
Route::get('/packages/{package}', Show::class)->name('dev.packages.show');
Route::get('/packages/{package}/boards/{board}', Board::class)->name('dev.packages.boards.show');
Route::get('/packages/{package}/issues/{issue}', Issue::class)->name('dev.packages.issues.show');
Route::get('/packages/{package}/discussions', Discussions::class)->name('dev.packages.discussions');
Route::get('/packages/{package}/docs/{docPage}', Doc::class)->name('dev.packages.docs.show');
