<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeEntryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_time_entries_with_related_names(): void
    {
        TimeEntry::factory()->count(3)->create();

        $resp = $this->getJson('/api/time-entries')->assertOk();

        $resp->assertJsonCount(3, 'data');
        $first = $resp->json('data.0');

        foreach (['id', 'date', 'hours', 'company', 'employee', 'project', 'task'] as $key) {
            $this->assertArrayHasKey($key, $first);
        }
        $this->assertArrayHasKey('id', $first['company']);
        $this->assertArrayHasKey('name', $first['company']);
    }

    public function test_filters_time_entries_by_company_id(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        TimeEntry::factory()->count(2)->create(['company_id' => $companyA->id]);
        TimeEntry::factory()->count(3)->create(['company_id' => $companyB->id]);

        $this->getJson("/api/time-entries?company_id={$companyA->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
