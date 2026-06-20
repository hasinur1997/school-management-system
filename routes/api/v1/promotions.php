<?php

use App\Http\Controllers\Api\V1\PromotionController;
use Illuminate\Support\Facades\Route;

// Promotion preview (9.1): who moves up for a (session, class) and the
// resolved next class. Branch isolation comes through the class (validated
// branch-scoped) and the enrollment → student chain.
Route::middleware(['auth:sanctum', 'permission:promotion.execute'])->group(function () {
    Route::get('promotions/preview', [PromotionController::class, 'preview'])
        ->name('promotions.preview');

    // Bulk promote (9.2): close the class's old enrollments and open the new
    // session's in one transaction. Passed → next class, failed → same class.
    Route::post('promotions/bulk', [PromotionController::class, 'bulk'])
        ->name('promotions.bulk');

    // Individual promote (9.3): move one student; failed/result-less needs
    // promotion.override (checked in the service).
    Route::post('promotions/individual', [PromotionController::class, 'individual'])
        ->name('promotions.individual');
});

// Promotion history (9.3): paginated, filterable log under promotion.view.
Route::middleware(['auth:sanctum', 'permission:promotion.view'])->group(function () {
    Route::get('promotions', [PromotionController::class, 'index'])
        ->name('promotions.index');
});
