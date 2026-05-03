<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    /**
     * Projects within `$company` that `$employee` is assigned to. Used by the
     * cascading dropdowns: once an employee is chosen, narrow the project
     * list to ones they're actually on.
     */
    public function indexForEmployee(Company $company, Employee $employee): JsonResponse
    {
        if (! $company->employees()->where('employees.id', $employee->id)->exists()) {
            throw new NotFoundHttpException();
        }

        return response()->json([
            'data' => $employee->projects()
                ->where('projects.company_id', $company->id)
                ->orderBy('projects.name')
                ->get(['projects.id', 'projects.name'])
                ->makeHidden('pivot')
                ->values(),
        ]);
    }
}
