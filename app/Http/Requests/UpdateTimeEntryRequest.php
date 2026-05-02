<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeEntryRequest extends FormRequest
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
            'company_id'  => ['required', 'integer', 'exists:companies,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'project_id'  => ['required', 'integer', 'exists:projects,id'],
            'task_id'     => ['required', 'integer', 'exists:tasks,id'],
            'date'        => ['required', 'date_format:Y-m-d'],
            'hours'       => ['required', 'numeric', 'min:0.01', 'max:24'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_id'  => 'company',
            'employee_id' => 'employee',
            'project_id'  => 'project',
            'task_id'     => 'task',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_id.required'   => 'Company is required.',
            'employee_id.required'  => 'Employee is required.',
            'project_id.required'   => 'Project is required.',
            'task_id.required'      => 'Task is required.',
            'date.required'         => 'Date is required.',
            'date.date_format'      => 'Use a valid date (YYYY-MM-DD).',
            'hours.required'        => 'Hours is required.',
            'hours.numeric'         => 'Hours must be a number.',
            'hours.min'             => 'Hours must be greater than 0.',
            'hours.max'             => "Hours can't exceed 24 in a single entry.",
            'company_id.exists'     => 'Pick a valid company.',
            'employee_id.exists'    => 'Pick a valid employee.',
            'project_id.exists'     => 'Pick a valid project.',
            'task_id.exists'        => 'Pick a valid task.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $companyId  = (int) $this->input('company_id');
            $employeeId = (int) $this->input('employee_id');
            $projectId  = (int) $this->input('project_id');
            $taskId     = (int) $this->input('task_id');
            $date       = (string) $this->input('date');

            $company = Company::with('employees:id')->find($companyId);
            $project = Project::with('employees:id')->find($projectId);
            $task    = Task::find($taskId);

            if ($company && ! $company->employees->contains('id', $employeeId)) {
                $v->errors()->add('employee_id', 'The selected employee does not belong to the selected company.');
            }
            if ($company && $project && (int) $project->company_id !== $company->id) {
                $v->errors()->add('project_id', 'The selected project does not belong to the selected company.');
            }
            if ($company && $task && (int) $task->company_id !== $company->id) {
                $v->errors()->add('task_id', 'The selected task does not belong to the selected company.');
            }
            if ($project && ! $project->employees->contains('id', $employeeId)) {
                $v->errors()->add('project_id', 'The selected employee is not assigned to the selected project.');
            }

            // One-project-per-(employee, date), excluding the entry being edited.
            $currentEntry = $this->route('time_entry');
            $currentId = $currentEntry instanceof TimeEntry ? $currentEntry->id : null;

            $conflict = TimeEntry::query()
                ->where('employee_id', $employeeId)
                ->whereDate('date', $date)
                ->where('project_id', '!=', $projectId)
                ->when($currentId, fn ($q, $id) => $q->where('id', '!=', $id))
                ->exists();

            if ($conflict) {
                $v->errors()->add(
                    'project_id',
                    'This employee already has time on a different project for this date.',
                );
            }
        });
    }
}
