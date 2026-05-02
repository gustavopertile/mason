<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmployeeController extends Controller
{
    public function indexForCompany(Company $company): JsonResponse
    {
        return response()->json([
            'data' => $company->employees()
                ->orderBy('name')
                ->get(['employees.id', 'employees.name'])
                ->makeHidden('pivot')
                ->values(),
        ]);
    }

    public function indexForProject(Company $company, Project $project): JsonResponse
    {
        if ($project->company_id !== $company->id) {
            throw new NotFoundHttpException();
        }

        $companyEmployeeIds = $company->employees()->pluck('employees.id');

        return response()->json([
            'data' => $project->employees()
                ->whereIn('employees.id', $companyEmployeeIds)
                ->orderBy('name')
                ->get(['employees.id', 'employees.name'])
                ->makeHidden('pivot')
                ->values(),
        ]);
    }
}
