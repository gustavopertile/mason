<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeEntryUpdateDeleteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{company: Company, employee: Employee, project: Project, task: Task, entry: TimeEntry}
     */
    private function scenario(): array
    {
        $company  = Company::factory()->create();
        $employee = Employee::factory()->create();
        $project  = Project::factory()->for($company)->create();
        $task     = Task::factory()->for($company)->create();

        $company->employees()->attach($employee);
        $employee->projects()->attach($project);

        $entry = TimeEntry::create([
            'company_id'  => $company->id,
            'employee_id' => $employee->id,
            'project_id'  => $project->id,
            'task_id'     => $task->id,
            'date'        => '2026-02-10',
            'hours'       => 4,
        ]);

        return compact('company', 'employee', 'project', 'task', 'entry');
    }

    public function test_updates_an_entry(): void
    {
        ['entry' => $entry] = $this->scenario();

        $payload = [
            'company_id'  => $entry->company_id,
            'employee_id' => $entry->employee_id,
            'project_id'  => $entry->project_id,
            'task_id'     => $entry->task_id,
            'date'        => '2026-02-10',
            'hours'       => 6.5,
        ];

        $this->putJson("/api/time-entries/{$entry->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.hours', 6.5);

        $this->assertSame('6.50', (string) $entry->fresh()->hours);
    }

    public function test_update_does_not_conflict_with_itself(): void
    {
        // Same entry, same employee+date — should NOT trigger the
        // "different project on same date" rule against itself.
        ['entry' => $entry] = $this->scenario();

        $this->putJson("/api/time-entries/{$entry->id}", [
            'company_id'  => $entry->company_id,
            'employee_id' => $entry->employee_id,
            'project_id'  => $entry->project_id,
            'task_id'     => $entry->task_id,
            'date'        => $entry->date->format('Y-m-d'),
            'hours'       => 8,
        ])->assertOk();
    }

    public function test_update_rejects_when_changing_to_a_date_that_now_conflicts(): void
    {
        // Existing entry on project A on 2026-02-10. Then we add another
        // entry on a different project on 2026-02-11. If we try to move
        // the second entry back to 2026-02-10 (where project A already
        // owns the day), it should be rejected.
        ['company' => $company, 'employee' => $employee, 'task' => $task] = $scenario = $this->scenario();
        $second = TimeEntry::create([
            'company_id'  => $company->id,
            'employee_id' => $employee->id,
            'project_id'  => Project::factory()->for($company)->create()->id,
            'task_id'     => $task->id,
            'date'        => '2026-02-11',
            'hours'       => 3,
        ]);
        $employee->projects()->attach($second->project_id);

        $this->putJson("/api/time-entries/{$second->id}", [
            'company_id'  => $company->id,
            'employee_id' => $employee->id,
            'project_id'  => $second->project_id,
            'task_id'     => $task->id,
            'date'        => '2026-02-10', // collides with the original entry
            'hours'       => 3,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['project_id']);
    }

    public function test_deletes_an_entry(): void
    {
        ['entry' => $entry] = $this->scenario();

        $this->deleteJson("/api/time-entries/{$entry->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('time_entries', ['id' => $entry->id]);
    }
}
