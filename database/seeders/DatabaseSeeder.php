<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
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
            Task::factory()->count(5)->for($company)->create();

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
    }
}
