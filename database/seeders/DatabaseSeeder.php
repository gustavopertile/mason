<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $companies = Company::factory()->count(3)->create();
        $employees = Employee::factory()->count(8)->create();

        $companies->each(function (Company $company) use ($employees) {
            Project::factory()->count(4)->for($company)->create();

            // Tasks must be unique per company (company_id + name), so pick
            // distinct names instead of letting the factory's randomElement
            // produce collisions.
            collect(['Development', 'Design', 'QA', 'Cleanup', 'Meeting'])
                ->each(fn (string $name) => Task::factory()->for($company)->create(['name' => $name]));

            // Assign 4–6 random employees to this company.
            $companyEmployees = $employees->random(random_int(4, 6));
            $company->employees()->syncWithoutDetaching($companyEmployees->pluck('id'));

            // Assign each company employee to 1–3 of this company's projects.
            $companyEmployees->each(function (Employee $employee) use ($company) {
                $employee->projects()->syncWithoutDetaching(
                    $company->projects->random(random_int(1, 3))->pluck('id')->all()
                );
            });
        });

        $this->seedTimeEntries(Employee::with(['projects.company', 'companies.tasks'])->get());
    }

    /**
     * Generate ~2 weeks of historical time entries while respecting the
     * "one project per (employee, date)" rule across the *entire* dataset
     * — even when an employee belongs to multiple companies. We iterate
     * per employee × date, pick a single project from any company, then
     * spread 1–2 tasks from that project's company across the day.
     *
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     */
    private function seedTimeEntries($employees): void
    {
        $start = CarbonImmutable::now()->subDays(14)->startOfDay();

        foreach ($employees as $employee) {
            if ($employee->projects->isEmpty()) {
                continue;
            }

            for ($offset = 0; $offset < 14; $offset++) {
                $date = $start->addDays($offset);

                // Skip weekends and a sprinkle of weekdays so the dataset
                // doesn't look like a wall of identical rows.
                if ($date->isWeekend() || random_int(0, 100) < 30) {
                    continue;
                }

                /** @var Project $project */
                $project = $employee->projects->random();
                $companyTasks = $employee->companies
                    ->firstWhere('id', $project->company_id)
                    ?->tasks ?? collect();

                if ($companyTasks->isEmpty()) {
                    continue;
                }

                $taskCount = min(random_int(1, 2), $companyTasks->count());
                $taskIds = $companyTasks->random($taskCount);
                $taskIds = $taskIds instanceof \Illuminate\Support\Collection
                    ? $taskIds->pluck('id')
                    : collect([$taskIds->id]);

                foreach ($taskIds as $taskId) {
                    TimeEntry::create([
                        'company_id'  => $project->company_id,
                        'employee_id' => $employee->id,
                        'project_id'  => $project->id,
                        'task_id'     => $taskId,
                        'date'        => $date->toDateString(),
                        'hours'       => $this->randomHours(),
                    ]);
                }
            }
        }
    }

    private function randomHours(): int
    {
        return random_int(1, 6);
    }
}
