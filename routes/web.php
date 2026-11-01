<?php

use Illuminate\Support\Facades\Route;

/* There is no public site: the root opens the app, and its login for guests. */
Route::redirect('/', '/inicio')->name('home');

require __DIR__.'/studio.php';
require __DIR__.'/settings.php';
