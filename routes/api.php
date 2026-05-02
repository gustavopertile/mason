<?php

use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TimeEntryController;
use Illuminate\Support\Facades\Route;

Route::get('companies', [CompanyController::class, 'index']);
Route::get('companies/{company}/employees', [EmployeeController::class, 'indexForCompany']);
Route::get('companies/{company}/projects', [ProjectController::class, 'indexForCompany']);
Route::get('companies/{company}/tasks', [TaskController::class, 'indexForCompany']);
Route::get('companies/{company}/projects/{project}/employees', [EmployeeController::class, 'indexForProject']);

Route::get('time-entries', [TimeEntryController::class, 'index']);
Route::post('time-entries', [TimeEntryController::class, 'store']);
