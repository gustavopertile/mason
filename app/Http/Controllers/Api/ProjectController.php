<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function indexForCompany(Company $company): JsonResponse
    {
        return response()->json([
            'data' => $company->projects()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
