<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\MemberController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
Route::apiResource('/members', MemberController::class);
Route::get('/map', [MapController::class, 'show']);
Route::get('/map/areas/{svgElementId}', [MapController::class, 'area']);
