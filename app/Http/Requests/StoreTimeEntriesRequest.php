<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

class StoreTimeEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'entries'               => ['required', 'array', 'min:1'],
            'entries.*.company_id'  => ['required', 'integer', 'exists:companies,id'],
            'entries.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'entries.*.project_id'  => ['required', 'integer', 'exists:projects,id'],
            'entries.*.task_id'     => ['required', 'integer', 'exists:tasks,id'],
            'entries.*.date'        => ['required', 'date_format:Y-m-d'],
            'entries.*.hours'       => ['required', 'integer', 'min:1', 'max:24'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'entries.*.company_id'  => 'company',
            'entries.*.employee_id' => 'employee',
            'entries.*.project_id'  => 'project',
            'entries.*.task_id'     => 'task',
            'entries.*.date'        => 'date',
            'entries.*.hours'       => 'hours',
        ];
    }

    /**
     * Custom messages. Anything not listed here falls back to Laravel's
     * defaults, which read fine because `attributes()` swaps the raw
     * `entries.0.company_id` paths for friendly labels like "company".
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entries.required'           => 'Add at least one row before submitting.',
            'entries.min'                => 'Add at least one row before submitting.',
            'entries.*.company_id.required'  => 'Company is required.',
            'entries.*.employee_id.required' => 'Employee is required.',
            'entries.*.project_id.required'  => 'Project is required.',
            'entries.*.task_id.required'     => 'Task is required.',
            'entries.*.date.required'        => 'Date is required.',
            'entries.*.date.date_format'     => 'Use a valid date (YYYY-MM-DD).',
            'entries.*.hours.required'       => 'Hours is required.',
            'entries.*.hours.integer'        => 'Hours must be a whole number.',
            'entries.*.hours.min'            => 'Hours must be at least 1.',
            'entries.*.hours.max'            => "Hours can't exceed 24 in a single entry.",
            'entries.*.company_id.exists'    => 'Pick a valid company.',
            'entries.*.employee_id.exists'   => 'Pick a valid employee.',
            'entries.*.project_id.exists'    => 'Pick a valid project.',
            'entries.*.task_id.exists'       => 'Pick a valid task.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            // Skip cross-row checks if base rules already failed — referenced
            // ids might not exist, which would distort the messages.
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            /** @var array<int, array<string, mixed>> $entries */
            $entries = $this->input('entries', []);
            if ($entries === []) {
                return;
            }

            $companies = Company::query()
                ->whereIn('id', collect($entries)->pluck('company_id')->unique())
                ->with(['employees:id'])
                ->get()
                ->keyBy('id');

            $projects = Project::query()
                ->whereIn('id', collect($entries)->pluck('project_id')->unique())
                ->with(['employees:id'])
                ->get()
                ->keyBy('id');

            $tasks = Task::query()
                ->whereIn('id', collect($entries)->pluck('task_id')->unique())
                ->get(['id', 'company_id'])
                ->keyBy('id');

            $this->validateRelationships($v, $entries, $companies, $projects, $tasks);
            $this->validateOneProjectPerEmployeePerDate($v, $entries);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  Collection<int, Company>  $companies
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, Task>  $tasks
     */
    private function validateRelationships(
        Validator $v,
        array $entries,
        Collection $companies,
        Collection $projects,
        Collection $tasks,
    ): void {
        foreach ($entries as $i => $entry) {
            $company    = $companies->get((int) ($entry['company_id'] ?? 0));
            $project    = $projects->get((int) ($entry['project_id'] ?? 0));
            $task       = $tasks->get((int) ($entry['task_id'] ?? 0));
            $employeeId = (int) ($entry['employee_id'] ?? 0);

            if ($company && ! $company->employees->contains('id', $employeeId)) {
                $v->errors()->add(
                    "entries.$i.employee_id",
                    'The selected employee does not belong to the selected company.',
                );
            }

            if ($company && $project && (int) $project->company_id !== (int) $company->id) {
                $v->errors()->add(
                    "entries.$i.project_id",
                    'The selected project does not belong to the selected company.',
                );
            }

            if ($company && $task && (int) $task->company_id !== (int) $company->id) {
                $v->errors()->add(
                    "entries.$i.task_id",
                    'The selected task does not belong to the selected company.',
                );
            }

            if ($project && ! $project->employees->contains('id', $employeeId)) {
                $v->errors()->add(
                    "entries.$i.project_id",
                    'The selected employee is not assigned to the selected project.',
                );
            }
        }
    }

    /**
     * Enforce: an employee cannot have time on more than one project for a
     * given date — across the submitted batch and across existing rows.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function validateOneProjectPerEmployeePerDate(Validator $v, array $entries): void
    {
        // Within-batch conflicts.
        $batchByKey = [];
        foreach ($entries as $i => $entry) {
            $key = $entry['employee_id'] . '|' . $entry['date'];
            $projectId = (int) $entry['project_id'];

            if (isset($batchByKey[$key]) && $batchByKey[$key] !== $projectId) {
                $v->errors()->add(
                    "entries.$i.project_id",
                    'An employee can only work on one project per date.',
                );
                continue;
            }

            $batchByKey[$key] = $projectId;
        }

        // Against existing DB rows — bulk-loaded by employee + date range,
        // then grouped in PHP. Storing the date column as a datetime makes
        // `whereIn('date', [...])` unreliable across DB drivers, so we filter
        // post-load using the formatted date.
        $pairs = collect($entries)
            ->map(fn (array $e): array => [
                'employee_id' => (int) $e['employee_id'],
                'date' => $e['date'],
            ])
            ->unique(fn (array $p): string => $p['employee_id'] . '|' . $p['date']);

        $employeeIds = $pairs->pluck('employee_id')->unique();
        $dates = $pairs->pluck('date')->unique();

        $existing = TimeEntry::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$dates->min(), $dates->max() . ' 23:59:59'])
            ->get(['employee_id', 'date', 'project_id'])
            ->filter(fn (TimeEntry $e): bool => $dates->contains($e->date->format('Y-m-d')))
            ->groupBy(fn (TimeEntry $e): string => $e->employee_id . '|' . $e->date->format('Y-m-d'));

        foreach ($entries as $i => $entry) {
            $key = $entry['employee_id'] . '|' . $entry['date'];
            $existingProjectIds = ($existing[$key] ?? collect())->pluck('project_id')->unique();

            if ($existingProjectIds->isNotEmpty()
                && ! $existingProjectIds->contains((int) $entry['project_id'])
            ) {
                $v->errors()->add(
                    "entries.$i.project_id",
                    'This employee already has time on a different project for this date.',
                );
            }
        }
    }
}
