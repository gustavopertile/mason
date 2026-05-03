<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Tasks are unique per (company_id, name); use Faker's `unique()` so
        // factory callers can create multiple tasks for the same company
        // without colliding. Seeders/tests can still override `name`.
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
