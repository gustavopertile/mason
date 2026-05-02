<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Company::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
