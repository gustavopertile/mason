<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_has_many_employees_through_pivot(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create();
        $company->employees()->attach($employee);

        $this->assertCount(1, $company->refresh()->employees);
        $this->assertCount(1, $employee->refresh()->companies);
    }

    public function test_company_has_many_projects_and_tasks(): void
    {
        $company = Company::factory()
            ->has(Project::factory()->count(2))
            ->has(Task::factory()->count(3))
            ->create();

        $this->assertCount(2, $company->projects);
        $this->assertCount(3, $company->tasks);
    }

    public function test_employee_belongs_to_many_projects(): void
    {
        $employee = Employee::factory()->create();
        $project = Project::factory()->create();
        $employee->projects()->attach($project);

        $this->assertCount(1, $employee->refresh()->projects);
        $this->assertCount(1, $project->refresh()->employees);
    }

    public function test_time_entry_belongs_to_company_employee_project_task(): void
    {
        $entry = TimeEntry::factory()->create();

        $this->assertInstanceOf(Company::class, $entry->company);
        $this->assertInstanceOf(Employee::class, $entry->employee);
        $this->assertInstanceOf(Project::class, $entry->project);
        $this->assertInstanceOf(Task::class, $entry->task);
    }
}
