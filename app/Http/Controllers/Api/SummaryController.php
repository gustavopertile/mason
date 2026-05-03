<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    /**
     * Aggregates for the SummaryCard. When `company_id` is provided, every
     * counter is scoped to that company; otherwise counts span everything.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->integer('company_id') ?: null;
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();
        $monthEnd   = $now->endOfMonth();

        $entriesQuery = TimeEntry::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $thisMonthQuery = (clone $entriesQuery)
            ->whereBetween('date', [$monthStart, $monthEnd]);

        return response()->json([
            'data' => [
                'scope'             => $companyId ? 'company' : 'all',
                'company_id'        => $companyId,
                'company_name'      => $companyId ? Company::find($companyId)?->name : null,
                'hours_this_month'  => (int) (clone $thisMonthQuery)->sum('hours'),
                'entries_this_month' => (clone $thisMonthQuery)->count(),
                'total_hours'       => (int) (clone $entriesQuery)->sum('hours'),
                'total_entries'     => (clone $entriesQuery)->count(),
                'companies_count'   => $companyId ? 1 : Company::count(),
                'employees_count'   => $companyId
                    ? Company::find($companyId)?->employees()->count() ?? 0
                    : Employee::count(),
                'projects_count'    => $companyId
                    ? Project::where('company_id', $companyId)->count()
                    : Project::count(),
                'tasks_count'       => $companyId
                    ? Task::where('company_id', $companyId)->count()
                    : Task::count(),
                'month_label'       => $now->format('F Y'),
            ],
        ]);
    }
}
