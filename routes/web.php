<?php

use App\Http\Controllers\StubPaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Stub payment routes (development only)
if (config('services.raiaccept.mode') === 'stub') {
    Route::prefix('stub/payment')->group(function (): void {
        Route::get('{order}', [StubPaymentController::class, 'form'])->name('stub.payment.form');
        Route::post('{order}/complete', [StubPaymentController::class, 'complete'])->name('stub.payment.complete');
        Route::post('{order}/fail', [StubPaymentController::class, 'fail'])->name('stub.payment.fail');
    });
}
