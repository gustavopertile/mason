<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyScopedListsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_all_companies(): void
    {
        Company::factory()->count(3)->create();

        $this->getJson('/api/companies')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_lists_employees_for_a_company_only(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $shared  = Employee::factory()->create();
        $onlyA   = Employee::factory()->create();
        $onlyB   = Employee::factory()->create();

        $companyA->employees()->attach([$shared->id, $onlyA->id]);
        $companyB->employees()->attach([$shared->id, $onlyB->id]);

        $resp = $this->getJson("/api/companies/{$companyA->id}/employees")->assertOk();

        $ids = collect($resp->json('data'))->pluck('id')->all();
        $this->assertContains($shared->id, $ids);
        $this->assertContains($onlyA->id, $ids);
        $this->assertNotContains($onlyB->id, $ids);
    }

    public function test_lists_projects_for_a_company_only(): void
    {
        $company = Company::factory()->create();
        Project::factory()->count(2)->for($company)->create();
        Project::factory()->count(3)->create();

        $this->getJson("/api/companies/{$company->id}/projects")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_lists_tasks_for_a_company_only(): void
    {
        $company = Company::factory()->create();
        Task::factory()->count(4)->for($company)->create();
        Task::factory()->count(2)->create();

        $this->getJson("/api/companies/{$company->id}/tasks")
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_lists_project_employees_scoped_to_a_company(): void
    {
        $company = Company::factory()->create();
        $project = Project::factory()->for($company)->create();
        $assigned = Employee::factory()->create();
        $unassigned = Employee::factory()->create();

        $company->employees()->attach([$assigned->id, $unassigned->id]);
        $assigned->projects()->attach($project);

        $resp = $this->getJson("/api/companies/{$company->id}/projects/{$project->id}/employees")->assertOk();

        $ids = collect($resp->json('data'))->pluck('id')->all();
        $this->assertSame([$assigned->id], $ids);
    }
}
