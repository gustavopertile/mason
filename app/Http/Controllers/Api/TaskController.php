<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

class TaskController extends Controller
{
    public function indexForCompany(Company $company): JsonResponse
    {
        return response()->json([
            'data' => $company->tasks()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
