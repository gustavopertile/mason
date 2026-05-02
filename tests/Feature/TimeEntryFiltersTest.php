<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeEntryFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginates_results(): void
    {
        TimeEntry::factory()->count(25)->create();

        $resp = $this->getJson('/api/time-entries?per_page=10')->assertOk();

        $resp->assertJsonCount(10, 'data');
        $resp->assertJsonPath('meta.total', 25);
        $resp->assertJsonPath('meta.per_page', 10);
        $resp->assertJsonPath('meta.last_page', 3);
    }

    public function test_filters_by_date_range(): void
    {
        $entries = TimeEntry::factory()->count(5)->create();
        $entries[0]->update(['date' => '2026-01-01']);
        $entries[1]->update(['date' => '2026-01-15']);
        $entries[2]->update(['date' => '2026-02-01']);
        $entries[3]->update(['date' => '2026-02-15']);
        $entries[4]->update(['date' => '2026-03-01']);

        $this->getJson('/api/time-entries?from=2026-02-01&to=2026-02-28')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_searches_across_related_names(): void
    {
        TimeEntry::factory()->count(3)->create();

        $company = Company::factory()->create(['name' => 'Findable Co']);
        $employee = Employee::factory()->create(['name' => 'Other Person']);
        $project = Project::factory()->for($company)->create(['name' => 'Other Project']);
        $task = Task::factory()->for($company)->create(['name' => 'Other Task']);
        $company->employees()->attach($employee);
        $employee->projects()->attach($project);
        TimeEntry::create([
            'company_id'  => $company->id,
            'employee_id' => $employee->id,
            'project_id'  => $project->id,
            'task_id'     => $task->id,
            'date'        => '2026-01-01',
            'hours'       => 2,
        ]);

        $resp = $this->getJson('/api/time-entries?search=Findable')->assertOk();
        $this->assertSame(1, $resp->json('meta.total'));
        $this->assertSame('Findable Co', $resp->json('data.0.company.name'));
    }
}
