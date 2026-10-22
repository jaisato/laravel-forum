<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ThreadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('threads', [ThreadController::class, 'index'])->name('threads.index');
Route::get('t/{thread:slug}', [ThreadController::class, 'show'])->name('threads.show');
Route::get('c/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('tags/{tag:slug}', [TagController::class, 'show'])->name('tags.show');

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');

require __DIR__.'/settings.php';
