<?php

use Platform\Correspondence\Livewire\Dashboard;
use Platform\Correspondence\Livewire\Inbox;
use Platform\Correspondence\Livewire\Thread\Show as ThreadShow;

Route::get('/', Dashboard::class)->name('correspondence.dashboard');
Route::get('/inbox', Inbox::class)->name('correspondence.inbox');
Route::get('/threads/{thread}', ThreadShow::class)->name('correspondence.threads.show');
