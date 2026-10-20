<?php

declare(strict_types=1);

use FiscalConsole\FiscalConsoleSnapshot;
use Illuminate\Support\Facades\Route;

require_once resource_path('monitoring/FiscalConsoleSnapshot.php');

// Monitoring is independent of fiscal commands, database connections and knowledge publication.
Route::get('/console-fiscal', function () {
    return response()->view('fiscal-console', ['snapshot' => (new FiscalConsoleSnapshot(base_path()))->collect()])
        ->header('Cache-Control', 'no-store');
})->name('fiscal-console');

Route::get('/console-fiscal/status', function () {
    return response()->json((new FiscalConsoleSnapshot(base_path()))->collect())
        ->header('Cache-Control', 'no-store');
})->name('fiscal-console.status');

Route::get('/console-fiscal/documento/{name}', function (string $name) {
    abort_unless(preg_match('/^[a-z0-9][a-z0-9.-]*\.md$/D', $name) === 1, 404);
    $path = base_path('docs/fiscal/'.$name);
    abort_unless(is_file($path), 404);

    return response((string) file_get_contents($path))
        ->header('Content-Type', 'text/plain; charset=UTF-8')
        ->header('X-Content-Type-Options', 'nosniff');
})->name('fiscal-console.document');
