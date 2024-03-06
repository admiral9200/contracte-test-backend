<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::controller(AuthController::class)->group(function() {
    Route::post('/auth/register', 'register');
    Route::post('/auth/login', 'login');
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::controller(ProfileController::class)->group(function() {
        Route::post('/profile-create', 'store');
        Route::get('/profile/@me', 'index');
        Route::post('/profile-update', 'update');
    });

    Route::controller(PaymentController::class)->group(function() {
        Route::get('/pricing-plans', 'getPricingPlans');
        Route::post('/user/subscribe', 'createSubscription');
        Route::post('/checkout', 'checkout');
        Route::get('/checkout/success', 'checkoutSuccess');
        Route::post("/create-payment-intent", "createPaymentIntent");
        Route::post("/setup-subscription", "setupSubscription");
        Route::post("/create-free-trials", "createFreeTrials");
        Route::get("/check-subscription-status", "checkSubscriptionStatus");
    });

    Route::controller(DocumentController::class)->group(function() {
        Route::post("/document/risk-assessment", "assessmentRisk");
        Route::post("/document/extract-data-pattern", "extractDataFromPattern");
        Route::post("/document/set-vote/", "setVote");
        Route::post("/document/get-vote/", "getVote");
        Route::post("/document/document-regenerate-text", "regenerateText");
        Route::post("/document/track-changes", "handleTrackChanges");
        Route::post("/document/get-all-risky-clauses", "getAllRiskySentences");
        Route::post("/document/copilot-track-changes", "copilotTrackChanges");
    });
});

Route::get('/test', function () {
    return "test";
})->name('test');