<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Budgets\BudgetController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Shared\CategoryController;
use App\Http\Controllers\Transactions\TransactionController;
use Illuminate\Support\Facades\Route;

/**
 * Auth (public)
 */
Route::post('users/register', [AuthController::class, 'register']);
Route::post('users/login', [AuthController::class, 'login']);
Route::post('users/refresh', [AuthController::class, 'refresh']);

/**
 * Users
 */
Route::group(['prefix' => 'users', 'middleware' => 'auth:api'], function ($router) {
    $router->get('me', [AuthController::class, 'me']);
    $router->post('logout', [AuthController::class, 'logout']);
});

/**
 * Categories
 */
Route::group(['prefix' => 'categories', 'middleware' => 'auth:api'], function ($router) {
    $router->get('fetch', [CategoryController::class, 'fetch']);
    $router->post('store', [CategoryController::class, 'store']);
    $router->put('edit', [CategoryController::class, 'editRecord']);
    $router->put('destroy', [CategoryController::class, 'destroy']);
});

/**
 * Transactions
 */
Route::group(['prefix' => 'transactions', 'middleware' => 'auth:api'], function ($router) {
    $router->post('search', [TransactionController::class, 'search']);
    $router->post('get-details', [TransactionController::class, 'getRecordDetails']);
    $router->post('store', [TransactionController::class, 'store']);
    $router->put('edit', [TransactionController::class, 'editRecord']);
    $router->put('destroy', [TransactionController::class, 'destroy']);
    $router->post('import', [TransactionController::class, 'import']);
});

/**
 * Budgets
 */
Route::group(['prefix' => 'budgets', 'middleware' => 'auth:api'], function ($router) {
    $router->post('search', [BudgetController::class, 'search']);
    $router->post('store', [BudgetController::class, 'store']);
    $router->put('edit', [BudgetController::class, 'editRecord']);
    $router->put('destroy', [BudgetController::class, 'destroy']);
});

/**
 * Dashboard
 */
Route::group(['prefix' => 'dashboard', 'middleware' => 'auth:api'], function ($router) {
    $router->post('summary', [DashboardController::class, 'summary']);
});
