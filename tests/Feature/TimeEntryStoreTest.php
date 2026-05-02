<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeEntryStoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a fully-consistent set of records (company, employee assigned to
     * the company, project on the company with the employee assigned to it,
     * task on the company) plus a valid entry payload referencing them.
     *
     * @return array{company: Company, employee: Employee, project: Project, task: Task, entry: array<string, mixed>}
     */
    private function validScenario(): array
    {
        $company  = Company::factory()->create();
        $employee = Employee::factory()->create();
        $project  = Project::factory()->for($company)->create();
        $task     = Task::factory()->for($company)->create();

        $company->employees()->attach($employee);
        $employee->projects()->attach($project);

        return [
            'company'  => $company,
            'employee' => $employee,
            'project'  => $project,
            'task'     => $task,
            'entry'    => [
                'company_id'  => $company->id,
                'employee_id' => $employee->id,
                'project_id'  => $project->id,
                'task_id'     => $task->id,
                'date'        => '2026-01-15',
                'hours'       => 4,
            ],
        ];
    }

    public function test_stores_a_valid_batch_of_time_entries(): void
    {
        $scenario = $this->validScenario();

        $this->postJson('/api/time-entries', ['entries' => [$scenario['entry']]])
            ->assertCreated()
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseCount('time_entries', 1);
    }

    public function test_rejects_entry_when_employee_does_not_belong_to_company(): void
    {
        $scenario = $this->validScenario();
        $otherCompany = Company::factory()->create();
        $entry = $scenario['entry'];
        $entry['company_id'] = $otherCompany->id;

        $this->postJson('/api/time-entries', ['entries' => [$entry]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entries.0.employee_id']);
    }

    public function test_rejects_entry_when_project_does_not_belong_to_company(): void
    {
        $scenario = $this->validScenario();
        $strayCompany = Company::factory()->create();
        $strayProject = Project::factory()->for($strayCompany)->create();
        $entry = $scenario['entry'];
        $entry['project_id'] = $strayProject->id;

        $this->postJson('/api/time-entries', ['entries' => [$entry]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entries.0.project_id']);
    }

    public function test_rejects_entry_when_task_does_not_belong_to_company(): void
    {
        $scenario = $this->validScenario();
        $strayCompany = Company::factory()->create();
        $strayTask = Task::factory()->for($strayCompany)->create();
        $entry = $scenario['entry'];
        $entry['task_id'] = $strayTask->id;

        $this->postJson('/api/time-entries', ['entries' => [$entry]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entries.0.task_id']);
    }

    public function test_rejects_entry_when_employee_is_not_assigned_to_project(): void
    {
        $scenario = $this->validScenario();
        $unassignedProject = Project::factory()->for($scenario['company'])->create();
        $entry = $scenario['entry'];
        $entry['project_id'] = $unassignedProject->id;

        $this->postJson('/api/time-entries', ['entries' => [$entry]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entries.0.project_id']);
    }

    public function test_allows_multiple_tasks_for_same_employee_date_and_project(): void
    {
        $scenario = $this->validScenario();
        $secondTask = Task::factory()->for($scenario['company'])->create();
        $second = $scenario['entry'];
        $second['task_id'] = $secondTask->id;
        $second['hours']   = 2;

        $this->postJson('/api/time-entries', ['entries' => [$scenario['entry'], $second]])
            ->assertCreated();

        $this->assertDatabaseCount('time_entries', 2);
    }

    public function test_rejects_two_entries_same_date_same_employee_different_projects(): void
    {
        $scenario = $this->validScenario();
        $otherProject = Project::factory()->for($scenario['company'])->create();
        $scenario['employee']->projects()->attach($otherProject);

        $second = $scenario['entry'];
        $second['project_id'] = $otherProject->id;

        $this->postJson('/api/time-entries', ['entries' => [$scenario['entry'], $second]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entries.1.project_id']);
    }

    public function test_rejects_new_entry_that_conflicts_with_existing_entry_on_different_project(): void
    {
        $scenario = $this->validScenario();
        TimeEntry::create($scenario['entry']);

        $otherProject = Project::factory()->for($scenario['company'])->create();
        $scenario['employee']->projects()->attach($otherProject);
        $entry = $scenario['entry'];
        $entry['project_id'] = $otherProject->id;

        $this->postJson('/api/time-entries', ['entries' => [$entry]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entries.0.project_id']);
    }

    public function test_requires_hours_to_be_positive(): void
    {
        $scenario = $this->validScenario();
        $entry = $scenario['entry'];
        $entry['hours'] = 0;

        $this->postJson('/api/time-entries', ['entries' => [$entry]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entries.0.hours']);
    }

    public function test_requires_at_least_one_entry(): void
    {
        $this->postJson('/api/time-entries', ['entries' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entries']);
    }
}
