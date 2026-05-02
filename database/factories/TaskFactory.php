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
        return [
            'company_id' => Company::factory(),
            'name' => fake()->randomElement([
                'Development', 'Design', 'QA', 'Cleanup', 'Meeting',
                'Code Review', 'Planning', 'Documentation',
            ]),
        ];
    }
}
