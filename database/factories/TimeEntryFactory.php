<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * Default state builds a fully consistent set of related records:
     * company → (project, task, employee) → time entry — so that the
     * factory always yields an entry that satisfies every business rule.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = Company::factory()->create();
        $project = Project::factory()->for($company)->create();
        $task = Task::factory()->for($company)->create();
        $employee = Employee::factory()->create();

        $company->employees()->attach($employee);
        $employee->projects()->attach($project);

        return [
            'company_id'  => $company->id,
            'employee_id' => $employee->id,
            'project_id'  => $project->id,
            'task_id'     => $task->id,
            'date'        => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'hours'       => fake()->randomFloat(2, 0.5, 8),
        ];
    }
}
