<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

require __DIR__.'/studio.php';
require __DIR__.'/settings.php';
