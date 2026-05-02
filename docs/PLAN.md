# Time Entry Interface — Implementation Plan

> **Source:** Engineering Test Exercise — Laravel + Vue Time Entry Interface
> **Repo:** `/Users/gustavopertile/workplace/extras/mason`

**Goal:** Build a Laravel + Vue 3 (Composition API) app to create and view employee time entries, with cascading company-scoped dropdowns and a business rule preventing an employee from working on more than one project per date.

**Architecture:** Laravel API backend (REST) + Vue 3 SPA mounted on a single Blade page. Frontend talks to `/api/*` via axios. SQLite for zero-config local DB. Validation enforced in Form Requests on the server. Cascading dropdowns are loaded on demand and cached client-side per company.

**Tech Stack:** Laravel 11+, Vue 3 (Composition API + `<script setup>`), Vite, Tailwind CSS (Laravel default), axios, SQLite, Pest (Laravel default test runner).

**Scope decisions:**
- Implementing **all required features** + the bonuses that improve correctness/polish: row-level validation UX, duplicate-row, basic keyboard navigation/shortcuts, simple history filtering by the top company selector.
- **Skipping for now:** AI-assisted entry (Super Bonus — explicit user instruction, will be added later), edit-from-history, summary totals.
- TDD on backend (model relationships, validation rules, API behavior). Manual testing on frontend (dev server + browser).

---

## File Structure

**Backend (Laravel):**
- `database/migrations/*_create_companies_table.php`
- `database/migrations/*_create_employees_table.php`
- `database/migrations/*_create_company_employee_table.php` (pivot)
- `database/migrations/*_create_projects_table.php`
- `database/migrations/*_create_tasks_table.php`
- `database/migrations/*_create_employee_project_table.php` (pivot)
- `database/migrations/*_create_time_entries_table.php`
- `app/Models/Company.php`
- `app/Models/Employee.php`
- `app/Models/Project.php`
- `app/Models/Task.php`
- `app/Models/TimeEntry.php`
- `database/factories/*Factory.php` (one per model)
- `database/seeders/DatabaseSeeder.php`
- `app/Http/Controllers/Api/CompanyController.php`
- `app/Http/Controllers/Api/EmployeeController.php`
- `app/Http/Controllers/Api/ProjectController.php`
- `app/Http/Controllers/Api/TaskController.php`
- `app/Http/Controllers/Api/TimeEntryController.php`
- `app/Http/Requests/StoreTimeEntriesRequest.php`
- `app/Http/Resources/TimeEntryResource.php`
- `routes/api.php`
- `tests/Feature/TimeEntryApiTest.php`
- `tests/Feature/CompanyScopedListsTest.php`
- `tests/Unit/RelationshipsTest.php`

**Frontend (Vue):**
- `resources/views/app.blade.php` (single mount point)
- `resources/js/app.js`
- `resources/js/App.vue`
- `resources/js/components/CompanyFilter.vue`
- `resources/js/components/Tabs.vue`
- `resources/js/components/NewEntriesTab.vue`
- `resources/js/components/EntryRow.vue`
- `resources/js/components/HistoryTab.vue`
- `resources/js/composables/useApi.js` (axios + cache helpers)
- `resources/js/composables/useCompanyData.js` (employees/projects/tasks per company, cached)
- `resources/js/composables/useTimeEntries.js`

**Project root:**
- `README.md` — setup + run + design notes
- `conversation.json` — exported AI conversation (added at end)
- `.env.example` — SQLite config

---

## Database Schema (Reference)

```
companies          (id, name, timestamps)
employees          (id, name, timestamps)
company_employee   (company_id, employee_id) — many-to-many
projects           (id, company_id, name, timestamps)
tasks              (id, company_id, name, timestamps)
employee_project   (employee_id, project_id) — many-to-many
time_entries       (id, company_id, employee_id, project_id, task_id, date, hours, timestamps)
```

**Business rule enforcement:** validation in `StoreTimeEntriesRequest` checks that for each (employee_id, date) tuple — both within the submitted batch and against existing rows — only one `project_id` exists.

---

## Task 1: Bootstrap Laravel Project

**Files:**
- Create: entire Laravel skeleton in repo root
- Modify: `.env` (use SQLite)

- [ ] **Step 1: Scaffold Laravel into the existing (empty) repo**

The repo already has `.git` but no files. Create Laravel in a temp dir and move files in (preserving `.git`).

```bash
cd /tmp
composer create-project laravel/laravel mason-tmp
cd /Users/gustavopertile/workplace/extras/mason
# move everything except .git
shopt -s dotglob
cp -r /tmp/mason-tmp/* /tmp/mason-tmp/.[!.]* . 2>/dev/null || true
shopt -u dotglob
rm -rf /tmp/mason-tmp
```

- [ ] **Step 2: Configure SQLite**

Edit `.env`:
```
DB_CONNECTION=sqlite
# remove DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD lines
```

```bash
touch database/database.sqlite
```

Mirror the same in `.env.example` so the README "just works".

- [ ] **Step 3: Install Vue 3 + Vite plugin**

```bash
npm install
npm install vue@latest @vitejs/plugin-vue axios
```

Update `vite.config.js` to register `@vitejs/plugin-vue`:
```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
    ],
});
```

- [ ] **Step 4: Verify base app runs**

```bash
php artisan migrate
php artisan serve &
npm run dev &
```

Visit `http://127.0.0.1:8000` — default Laravel page should render. Kill both processes.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: scaffold Laravel app with Vue 3 + SQLite"
```

---

## Task 2: Migrations

**Files:**
- Create: 7 migration files in `database/migrations/`

- [ ] **Step 1: Generate migration files**

```bash
php artisan make:migration create_companies_table
php artisan make:migration create_employees_table
php artisan make:migration create_company_employee_table
php artisan make:migration create_projects_table
php artisan make:migration create_tasks_table
php artisan make:migration create_employee_project_table
php artisan make:migration create_time_entries_table
```

- [ ] **Step 2: Fill in `companies`**

```php
Schema::create('companies', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->timestamps();
});
```

- [ ] **Step 3: Fill in `employees`**

```php
Schema::create('employees', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->timestamps();
});
```

- [ ] **Step 4: Fill in `company_employee` pivot**

```php
Schema::create('company_employee', function (Blueprint $table) {
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->primary(['company_id', 'employee_id']);
});
```

- [ ] **Step 5: Fill in `projects`**

```php
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->timestamps();
    $table->index('company_id');
});
```

- [ ] **Step 6: Fill in `tasks`**

```php
Schema::create('tasks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->timestamps();
    $table->index('company_id');
});
```

- [ ] **Step 7: Fill in `employee_project` pivot**

```php
Schema::create('employee_project', function (Blueprint $table) {
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->primary(['employee_id', 'project_id']);
});
```

- [ ] **Step 8: Fill in `time_entries`**

```php
Schema::create('time_entries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('task_id')->constrained()->cascadeOnDelete();
    $table->date('date');
    $table->decimal('hours', 5, 2);
    $table->timestamps();
    // Helps the business-rule lookup: "for this employee on this date,
    // is there already an entry with a different project?"
    $table->index(['employee_id', 'date']);
    // Speeds up history filtering by company.
    $table->index(['company_id', 'date']);
});
```

- [ ] **Step 9: Run migrations**

```bash
php artisan migrate:fresh
```

Expected: all 7 tables created without errors.

- [ ] **Step 10: Commit**

```bash
git add database/migrations
git commit -m "feat: add migrations for companies, employees, projects, tasks, time entries"
```

---

## Task 3: Models + Relationships (TDD)

**Files:**
- Create: `app/Models/{Company,Employee,Project,Task,TimeEntry}.php`
- Create: `tests/Unit/RelationshipsTest.php`

- [ ] **Step 1: Generate models**

```bash
php artisan make:model Company -f
php artisan make:model Employee -f
php artisan make:model Project -f
php artisan make:model Task -f
php artisan make:model TimeEntry -f
```

- [ ] **Step 2: Write the failing relationship test**

`tests/Unit/RelationshipsTest.php`:
```php
<?php

use App\Models\{Company, Employee, Project, Task, TimeEntry};

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('company has many employees through pivot', function () {
    $company = Company::factory()->create();
    $employee = Employee::factory()->create();
    $company->employees()->attach($employee);

    expect($company->employees)->toHaveCount(1);
    expect($employee->companies)->toHaveCount(1);
});

it('company has many projects and tasks', function () {
    $company = Company::factory()
        ->has(Project::factory()->count(2))
        ->has(Task::factory()->count(3))
        ->create();

    expect($company->projects)->toHaveCount(2);
    expect($company->tasks)->toHaveCount(3);
});

it('employee belongs to many projects', function () {
    $employee = Employee::factory()->create();
    $project = Project::factory()->create();
    $employee->projects()->attach($project);

    expect($employee->projects)->toHaveCount(1);
    expect($project->employees)->toHaveCount(1);
});

it('time entry belongs to company, employee, project, task', function () {
    $entry = TimeEntry::factory()->create();

    expect($entry->company)->toBeInstanceOf(Company::class);
    expect($entry->employee)->toBeInstanceOf(Employee::class);
    expect($entry->project)->toBeInstanceOf(Project::class);
    expect($entry->task)->toBeInstanceOf(Task::class);
});
```

- [ ] **Step 3: Run — should fail**

```bash
php artisan test --filter=RelationshipsTest
```

Expected: failures for missing relationships and factories.

- [ ] **Step 4: Implement `Company`**

```php
class Company extends Model {
    use HasFactory;
    protected $fillable = ['name'];

    public function employees() { return $this->belongsToMany(Employee::class); }
    public function projects()  { return $this->hasMany(Project::class); }
    public function tasks()     { return $this->hasMany(Task::class); }
    public function timeEntries() { return $this->hasMany(TimeEntry::class); }
}
```

- [ ] **Step 5: Implement `Employee`**

```php
class Employee extends Model {
    use HasFactory;
    protected $fillable = ['name'];

    public function companies() { return $this->belongsToMany(Company::class); }
    public function projects()  { return $this->belongsToMany(Project::class); }
    public function timeEntries() { return $this->hasMany(TimeEntry::class); }
}
```

- [ ] **Step 6: Implement `Project`**

```php
class Project extends Model {
    use HasFactory;
    protected $fillable = ['name', 'company_id'];

    public function company()   { return $this->belongsTo(Company::class); }
    public function employees() { return $this->belongsToMany(Employee::class); }
}
```

- [ ] **Step 7: Implement `Task`**

```php
class Task extends Model {
    use HasFactory;
    protected $fillable = ['name', 'company_id'];

    public function company() { return $this->belongsTo(Company::class); }
}
```

- [ ] **Step 8: Implement `TimeEntry`**

```php
class TimeEntry extends Model {
    use HasFactory;
    protected $fillable = ['company_id', 'employee_id', 'project_id', 'task_id', 'date', 'hours'];
    protected $casts = ['date' => 'date', 'hours' => 'decimal:2'];

    public function company()  { return $this->belongsTo(Company::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function project()  { return $this->belongsTo(Project::class); }
    public function task()     { return $this->belongsTo(Task::class); }
}
```

- [ ] **Step 9: Implement factories**

`CompanyFactory`:
```php
public function definition(): array {
    return ['name' => fake()->company()];
}
```

`EmployeeFactory`:
```php
public function definition(): array {
    return ['name' => fake()->name()];
}
```

`ProjectFactory`:
```php
public function definition(): array {
    return [
        'name' => fake()->catchPhrase(),
        'company_id' => Company::factory(),
    ];
}
```

`TaskFactory`:
```php
public function definition(): array {
    return [
        'name' => fake()->randomElement(['Development', 'Design', 'QA', 'Cleanup', 'Meeting']),
        'company_id' => Company::factory(),
    ];
}
```

`TimeEntryFactory`:
```php
public function definition(): array {
    $company = Company::factory()->create();
    $project = Project::factory()->for($company)->create();
    $task = Task::factory()->for($company)->create();
    $employee = Employee::factory()->create();
    $company->employees()->attach($employee);
    $employee->projects()->attach($project);

    return [
        'company_id'  => $company->id,
        'employee_id' => $employee->id,
        'project_id'  => $project->id,
        'task_id'     => $task->id,
        'date'        => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
        'hours'       => fake()->randomFloat(2, 0.5, 8),
    ];
}
```

- [ ] **Step 10: Run — should pass**

```bash
php artisan test --filter=RelationshipsTest
```

Expected: all 4 tests pass.

- [ ] **Step 11: Commit**

```bash
git add app/Models database/factories tests/Unit
git commit -m "feat: add models, relationships, and factories with tests"
```

---

## Task 4: Seeder

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Write seeder**

Replace contents:
```php
<?php

namespace Database\Seeders;

use App\Models\{Company, Employee, Project, Task};
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 3 companies, each with its own projects + tasks
        $companies = Company::factory()->count(3)->create();

        // 8 shared employees, each assigned to 1-2 companies
        $employees = Employee::factory()->count(8)->create();

        foreach ($companies as $company) {
            Project::factory()->count(4)->for($company)->create();
            Task::factory()->count(5)->for($company)->create();

            // Assign 4-6 random employees to this company
            $companyEmployees = $employees->random(rand(4, 6));
            $company->employees()->attach($companyEmployees->pluck('id'));

            // Assign each company employee to 1-3 of this company's projects
            foreach ($companyEmployees as $employee) {
                $employee->projects()->syncWithoutDetaching(
                    $company->projects->random(rand(1, 3))->pluck('id')->all()
                );
            }
        }
    }
}
```

- [ ] **Step 2: Run seeder**

```bash
php artisan migrate:fresh --seed
```

Expected: completes without error. Sanity check via tinker:
```bash
php artisan tinker --execute="dump(\App\Models\Company::with('employees', 'projects', 'tasks')->get()->toArray());" | head -50
```

- [ ] **Step 3: Commit**

```bash
git add database/seeders
git commit -m "feat: seed companies, employees, projects, tasks with realistic relationships"
```

---

## Task 5: Lookup API Endpoints (TDD)

These endpoints feed the cascading dropdowns. Each company-scoped list is its own route.

**Files:**
- Create: `app/Http/Controllers/Api/{Company,Employee,Project,Task}Controller.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/CompanyScopedListsTest.php`

- [ ] **Step 1: Ensure `routes/api.php` is loaded**

Laravel 11 doesn't ship with `api.php` by default. Run:
```bash
php artisan install:api
```

This creates `routes/api.php` and registers `/api` prefix.

- [ ] **Step 2: Write failing endpoint tests**

`tests/Feature/CompanyScopedListsTest.php`:
```php
<?php

use App\Models\{Company, Employee, Project, Task};

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('lists all companies', function () {
    Company::factory()->count(3)->create();
    $this->getJson('/api/companies')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('lists employees for a company only', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $shared = Employee::factory()->create();
    $onlyA  = Employee::factory()->create();
    $onlyB  = Employee::factory()->create();

    $companyA->employees()->attach([$shared->id, $onlyA->id]);
    $companyB->employees()->attach([$shared->id, $onlyB->id]);

    $resp = $this->getJson("/api/companies/{$companyA->id}/employees")
        ->assertOk();

    $ids = collect($resp->json('data'))->pluck('id')->all();
    expect($ids)->toContain($shared->id, $onlyA->id);
    expect($ids)->not->toContain($onlyB->id);
});

it('lists projects for a company only', function () {
    $company = Company::factory()->create();
    Project::factory()->count(2)->for($company)->create();
    Project::factory()->count(3)->create(); // other companies

    $this->getJson("/api/companies/{$company->id}/projects")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lists tasks for a company only', function () {
    $company = Company::factory()->create();
    Task::factory()->count(4)->for($company)->create();
    Task::factory()->count(2)->create();

    $this->getJson("/api/companies/{$company->id}/tasks")
        ->assertOk()
        ->assertJsonCount(4, 'data');
});

it('lists project employees scoped to a company', function () {
    $company = Company::factory()->create();
    $project = Project::factory()->for($company)->create();
    $assigned = Employee::factory()->create();
    $unassigned = Employee::factory()->create();

    $company->employees()->attach([$assigned->id, $unassigned->id]);
    $assigned->projects()->attach($project);

    $resp = $this->getJson("/api/companies/{$company->id}/projects/{$project->id}/employees")
        ->assertOk();

    $ids = collect($resp->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$assigned->id]);
});
```

- [ ] **Step 3: Run — should fail (404s)**

```bash
php artisan test --filter=CompanyScopedListsTest
```

- [ ] **Step 4: Implement `CompanyController`**

```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;

class CompanyController extends Controller
{
    public function index() {
        return response()->json([
            'data' => Company::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
```

- [ ] **Step 5: Implement `EmployeeController`**

```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Project;

class EmployeeController extends Controller
{
    public function indexForCompany(Company $company) {
        return response()->json([
            'data' => $company->employees()->orderBy('name')->get(['employees.id', 'name']),
        ]);
    }

    public function indexForProject(Company $company, Project $project) {
        abort_unless($project->company_id === $company->id, 404);

        $companyEmployeeIds = $company->employees()->pluck('employees.id');

        return response()->json([
            'data' => $project->employees()
                ->whereIn('employees.id', $companyEmployeeIds)
                ->orderBy('name')
                ->get(['employees.id', 'name']),
        ]);
    }
}
```

- [ ] **Step 6: Implement `ProjectController`**

```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;

class ProjectController extends Controller
{
    public function indexForCompany(Company $company) {
        return response()->json([
            'data' => $company->projects()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
```

- [ ] **Step 7: Implement `TaskController`**

```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;

class TaskController extends Controller
{
    public function indexForCompany(Company $company) {
        return response()->json([
            'data' => $company->tasks()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
```

- [ ] **Step 8: Wire routes**

`routes/api.php`:
```php
<?php

use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TimeEntryController;
use Illuminate\Support\Facades\Route;

Route::get('/companies', [CompanyController::class, 'index']);
Route::get('/companies/{company}/employees', [EmployeeController::class, 'indexForCompany']);
Route::get('/companies/{company}/projects', [ProjectController::class, 'indexForCompany']);
Route::get('/companies/{company}/tasks', [TaskController::class, 'indexForCompany']);
Route::get('/companies/{company}/projects/{project}/employees', [EmployeeController::class, 'indexForProject']);

// Time entries — added in Task 6/7
Route::get('/time-entries', [TimeEntryController::class, 'index']);
Route::post('/time-entries', [TimeEntryController::class, 'store']);
```

- [ ] **Step 9: Run — should pass**

```bash
php artisan test --filter=CompanyScopedListsTest
```

Expected: all 5 tests pass.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Api routes/api.php tests/Feature/CompanyScopedListsTest.php
git commit -m "feat: add company-scoped lookup endpoints with tests"
```

---

## Task 6: Time Entry — List Endpoint (TDD)

**Files:**
- Create: `app/Http/Controllers/Api/TimeEntryController.php`
- Create: `app/Http/Resources/TimeEntryResource.php`
- Create/extend: `tests/Feature/TimeEntryApiTest.php`

- [ ] **Step 1: Write failing test**

`tests/Feature/TimeEntryApiTest.php`:
```php
<?php

use App\Models\{Company, Employee, Project, Task, TimeEntry};

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('lists time entries with related names', function () {
    TimeEntry::factory()->count(3)->create();

    $resp = $this->getJson('/api/time-entries')->assertOk();

    expect($resp->json('data'))->toHaveCount(3);
    $first = $resp->json('data.0');
    expect($first)->toHaveKeys(['id', 'date', 'hours', 'company', 'employee', 'project', 'task']);
    expect($first['company'])->toHaveKeys(['id', 'name']);
});

it('filters time entries by company_id', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    TimeEntry::factory()->count(2)->create(['company_id' => $companyA->id]);
    TimeEntry::factory()->count(3)->create(['company_id' => $companyB->id]);

    $this->getJson("/api/time-entries?company_id={$companyA->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});
```

Note: `TimeEntry::factory()->create(['company_id' => …])` will not auto-link the related project/task to the same company, so for filter-only tests we just need the company_id column. If a later test asserts cross-company integrity, build the entry through `TimeEntryFactory`'s default which already creates a coherent set.

- [ ] **Step 2: Run — should fail**

```bash
php artisan test --filter=TimeEntryApiTest
```

- [ ] **Step 3: Implement `TimeEntryResource`**

```bash
php artisan make:resource TimeEntryResource
```

```php
<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'date'     => $this->date->format('Y-m-d'),
            'hours'    => (float) $this->hours,
            'company'  => ['id' => $this->company->id,  'name' => $this->company->name],
            'employee' => ['id' => $this->employee->id, 'name' => $this->employee->name],
            'project'  => ['id' => $this->project->id,  'name' => $this->project->name],
            'task'     => ['id' => $this->task->id,     'name' => $this->task->name],
        ];
    }
}
```

- [ ] **Step 4: Implement `TimeEntryController@index`**

```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use Illuminate\Http\Request;

class TimeEntryController extends Controller
{
    public function index(Request $request)
    {
        $query = TimeEntry::query()
            ->with(['company:id,name', 'employee:id,name', 'project:id,name', 'task:id,name'])
            ->latest('date')
            ->latest('id');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        return TimeEntryResource::collection($query->get());
    }

    public function store() { /* implemented in Task 7 */ }
}
```

- [ ] **Step 5: Run — should pass**

```bash
php artisan test --filter=TimeEntryApiTest
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/TimeEntryController.php app/Http/Resources tests/Feature/TimeEntryApiTest.php
git commit -m "feat: list time entries with eager-loaded relations and company filter"
```

---

## Task 7: Time Entry — Store Endpoint with Business Rule (TDD)

This is the most important validation. Write the tests first; they encode the business rule precisely.

**Files:**
- Create: `app/Http/Requests/StoreTimeEntriesRequest.php`
- Modify: `app/Http/Controllers/Api/TimeEntryController.php`
- Extend: `tests/Feature/TimeEntryApiTest.php`

- [ ] **Step 1: Write failing tests**

Append to `tests/Feature/TimeEntryApiTest.php`:
```php
function makeValidEntryPayload(): array {
    $company = Company::factory()->create();
    $employee = Employee::factory()->create();
    $project = Project::factory()->for($company)->create();
    $task = Task::factory()->for($company)->create();
    $company->employees()->attach($employee);
    $employee->projects()->attach($project);

    return [
        'company' => $company,
        'employee' => $employee,
        'project' => $project,
        'task' => $task,
        'entry' => [
            'company_id'  => $company->id,
            'employee_id' => $employee->id,
            'project_id'  => $project->id,
            'task_id'     => $task->id,
            'date'        => '2026-01-15',
            'hours'       => 4,
        ],
    ];
}

it('stores a valid batch of time entries', function () {
    ['entry' => $entry] = makeValidEntryPayload();

    $this->postJson('/api/time-entries', ['entries' => [$entry]])
        ->assertCreated()
        ->assertJsonCount(1, 'data');

    $this->assertDatabaseCount('time_entries', 1);
});

it('rejects an entry whose employee does not belong to the company', function () {
    ['entry' => $entry, 'employee' => $employee] = makeValidEntryPayload();
    $otherCompany = Company::factory()->create();
    $entry['company_id'] = $otherCompany->id; // employee not attached to this one

    $this->postJson('/api/time-entries', ['entries' => [$entry]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['entries.0.employee_id']);
});

it('rejects an entry whose project does not belong to the company', function () {
    ['entry' => $entry] = makeValidEntryPayload();
    $strayCompany = Company::factory()->create();
    $strayProject = Project::factory()->for($strayCompany)->create();
    $entry['project_id'] = $strayProject->id;

    $this->postJson('/api/time-entries', ['entries' => [$entry]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['entries.0.project_id']);
});

it('rejects an entry whose task does not belong to the company', function () {
    ['entry' => $entry] = makeValidEntryPayload();
    $strayCompany = Company::factory()->create();
    $strayTask = Task::factory()->for($strayCompany)->create();
    $entry['task_id'] = $strayTask->id;

    $this->postJson('/api/time-entries', ['entries' => [$entry]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['entries.0.task_id']);
});

it('rejects an entry where the employee is not assigned to the project', function () {
    ['entry' => $entry, 'employee' => $employee, 'company' => $company] = makeValidEntryPayload();
    $unassignedProject = Project::factory()->for($company)->create();
    $entry['project_id'] = $unassignedProject->id;

    $this->postJson('/api/time-entries', ['entries' => [$entry]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['entries.0.project_id']);
});

it('allows multiple tasks for the same employee/date/project', function () {
    ['entry' => $entry, 'company' => $company] = makeValidEntryPayload();
    $secondTask = Task::factory()->for($company)->create();
    $second = $entry;
    $second['task_id'] = $secondTask->id;
    $second['hours']   = 2;

    $this->postJson('/api/time-entries', ['entries' => [$entry, $second]])
        ->assertCreated();

    $this->assertDatabaseCount('time_entries', 2);
});

it('rejects two entries on the same date for the same employee on different projects', function () {
    ['entry' => $entry, 'company' => $company, 'employee' => $employee] = makeValidEntryPayload();
    $otherProject = Project::factory()->for($company)->create();
    $employee->projects()->attach($otherProject);

    $second = $entry;
    $second['project_id'] = $otherProject->id;

    $this->postJson('/api/time-entries', ['entries' => [$entry, $second]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['entries.1.project_id']);
});

it('rejects a new entry that conflicts with an existing entry on a different project', function () {
    ['entry' => $entry, 'company' => $company, 'employee' => $employee] = makeValidEntryPayload();
    TimeEntry::create($entry);

    $otherProject = Project::factory()->for($company)->create();
    $employee->projects()->attach($otherProject);
    $entry['project_id'] = $otherProject->id;

    $this->postJson('/api/time-entries', ['entries' => [$entry]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['entries.0.project_id']);
});

it('requires hours to be positive', function () {
    ['entry' => $entry] = makeValidEntryPayload();
    $entry['hours'] = 0;

    $this->postJson('/api/time-entries', ['entries' => [$entry]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['entries.0.hours']);
});
```

- [ ] **Step 2: Run — should fail**

```bash
php artisan test --filter=TimeEntryApiTest
```

- [ ] **Step 3: Implement `StoreTimeEntriesRequest`**

```bash
php artisan make:request StoreTimeEntriesRequest
```

```php
<?php
namespace App\Http\Requests;

use App\Models\{Company, Project, Task, TimeEntry};
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreTimeEntriesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'entries'                => ['required', 'array', 'min:1'],
            'entries.*.company_id'   => ['required', 'integer', 'exists:companies,id'],
            'entries.*.employee_id'  => ['required', 'integer', 'exists:employees,id'],
            'entries.*.project_id'   => ['required', 'integer', 'exists:projects,id'],
            'entries.*.task_id'      => ['required', 'integer', 'exists:tasks,id'],
            'entries.*.date'         => ['required', 'date_format:Y-m-d'],
            'entries.*.hours'        => ['required', 'numeric', 'min:0.01', 'max:24'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $entries = $this->input('entries', []);
            if (! is_array($entries)) return;

            // Cache lookups to avoid N queries inside the loop.
            $companyIds = collect($entries)->pluck('company_id')->unique()->filter();
            $projects = Project::whereIn('id', collect($entries)->pluck('project_id')->unique()->filter())
                ->get(['id', 'company_id'])->keyBy('id');
            $tasks = Task::whereIn('id', collect($entries)->pluck('task_id')->unique()->filter())
                ->get(['id', 'company_id'])->keyBy('id');
            $companies = Company::whereIn('id', $companyIds)
                ->with(['employees:id', 'projects:id,company_id'])
                ->get()->keyBy('id');

            // Map: project_id => Set<employee_id> (for assignment check)
            $projectEmployeeIds = \App\Models\Project::whereIn('id', $projects->keys())
                ->with('employees:id')
                ->get()
                ->mapWithKeys(fn ($p) => [$p->id => $p->employees->pluck('id')->all()]);

            foreach ($entries as $i => $entry) {
                $company = $companies->get($entry['company_id'] ?? null);
                $project = $projects->get($entry['project_id'] ?? null);
                $task    = $tasks->get($entry['task_id'] ?? null);

                if (! $company) continue; // base 'exists' rule already failed

                // employee belongs to company
                if ($company && ! $company->employees->contains('id', (int) ($entry['employee_id'] ?? 0))) {
                    $v->errors()->add(
                        "entries.$i.employee_id",
                        'The selected employee does not belong to the selected company.'
                    );
                }

                // project belongs to company
                if ($project && (int) $project->company_id !== (int) $company->id) {
                    $v->errors()->add(
                        "entries.$i.project_id",
                        'The selected project does not belong to the selected company.'
                    );
                }

                // task belongs to company
                if ($task && (int) $task->company_id !== (int) $company->id) {
                    $v->errors()->add(
                        "entries.$i.task_id",
                        'The selected task does not belong to the selected company.'
                    );
                }

                // employee assigned to project
                if ($project && isset($entry['employee_id'])) {
                    $assigned = $projectEmployeeIds[$project->id] ?? [];
                    if (! in_array((int) $entry['employee_id'], $assigned, true)) {
                        $v->errors()->add(
                            "entries.$i.project_id",
                            'The selected employee is not assigned to the selected project.'
                        );
                    }
                }
            }

            // Business rule: one project per (employee, date) — across batch + DB
            $byEmployeeDate = []; // [employee_id|date] => first project_id seen
            foreach ($entries as $i => $entry) {
                $key = ($entry['employee_id'] ?? '') . '|' . ($entry['date'] ?? '');
                if (! isset($entry['employee_id'], $entry['date'], $entry['project_id'])) continue;

                if (isset($byEmployeeDate[$key]) && $byEmployeeDate[$key] !== (int) $entry['project_id']) {
                    $v->errors()->add(
                        "entries.$i.project_id",
                        'An employee can only work on one project per date.'
                    );
                    continue;
                }
                $byEmployeeDate[$key] = (int) $entry['project_id'];

                $existing = TimeEntry::where('employee_id', $entry['employee_id'])
                    ->whereDate('date', $entry['date'])
                    ->where('project_id', '!=', $entry['project_id'])
                    ->exists();

                if ($existing) {
                    $v->errors()->add(
                        "entries.$i.project_id",
                        'This employee already has time on a different project for this date.'
                    );
                }
            }
        });
    }
}
```

- [ ] **Step 4: Implement `TimeEntryController@store`**

```php
public function store(\App\Http\Requests\StoreTimeEntriesRequest $request)
{
    $created = \DB::transaction(function () use ($request) {
        return collect($request->validated('entries'))->map(
            fn (array $row) => \App\Models\TimeEntry::create($row)
        );
    });

    $created->load(['company:id,name', 'employee:id,name', 'project:id,name', 'task:id,name']);
    return \App\Http\Resources\TimeEntryResource::collection($created)
        ->response()
        ->setStatusCode(201);
}
```

- [ ] **Step 5: Run — should pass**

```bash
php artisan test --filter=TimeEntryApiTest
```

Expected: all 9 tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests app/Http/Controllers/Api/TimeEntryController.php tests/Feature/TimeEntryApiTest.php
git commit -m "feat: validate and store batched time entries with business rules"
```

---

## Task 8: Frontend Mount Point + Tab Shell

**Files:**
- Create/modify: `resources/views/app.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/js/app.js`
- Create: `resources/js/App.vue`, `resources/js/components/Tabs.vue`

- [ ] **Step 1: Single-page Blade view**

`resources/views/app.blade.php`:
```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Time Entries</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <div id="app"></div>
</body>
</html>
```

- [ ] **Step 2: Replace `routes/web.php` root route**

```php
Route::get('/', fn () => view('app'));
```

- [ ] **Step 3: Wire Vue in `resources/js/app.js`**

```js
import './bootstrap';
import { createApp } from 'vue';
import App from './App.vue';

createApp(App).mount('#app');
```

- [ ] **Step 4: Create `App.vue` with tabs and company filter slot**

```vue
<script setup>
import { ref, provide } from 'vue';
import CompanyFilter from './components/CompanyFilter.vue';
import Tabs from './components/Tabs.vue';
import NewEntriesTab from './components/NewEntriesTab.vue';
import HistoryTab from './components/HistoryTab.vue';

const selectedCompanyId = ref(null); // null = "All"
provide('selectedCompanyId', selectedCompanyId);

const tab = ref('new');
</script>

<template>
  <div class="max-w-6xl mx-auto p-6 space-y-6">
    <header class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Time Entries</h1>
      <CompanyFilter v-model="selectedCompanyId" />
    </header>

    <Tabs v-model="tab" :tabs="[{ id: 'new', label: 'New Entries' }, { id: 'history', label: 'History' }]" />

    <NewEntriesTab v-if="tab === 'new'" />
    <HistoryTab v-else />
  </div>
</template>
```

- [ ] **Step 5: Create `Tabs.vue`**

```vue
<script setup>
const props = defineProps({ tabs: Array, modelValue: String });
const emit = defineEmits(['update:modelValue']);
</script>

<template>
  <nav class="flex gap-2 border-b">
    <button
      v-for="t in tabs" :key="t.id"
      @click="emit('update:modelValue', t.id)"
      :class="[
        'px-4 py-2 text-sm font-medium -mb-px border-b-2',
        modelValue === t.id ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800'
      ]"
    >{{ t.label }}</button>
  </nav>
</template>
```

- [ ] **Step 6: Verify dev server boots**

```bash
php artisan serve &
npm run dev &
```

Visit `http://127.0.0.1:8000` — header, dropdown, and tab strip render. Stub components OK to be empty.

- [ ] **Step 7: Commit**

```bash
git add resources routes/web.php
git commit -m "feat: scaffold Vue SPA with tabs and global company filter"
```

---

## Task 9: API Composable + Caching

**Files:**
- Create: `resources/js/composables/useApi.js`
- Create: `resources/js/composables/useCompanyData.js`

- [ ] **Step 1: Create `useApi.js`**

```js
import axios from 'axios';

const client = axios.create({
  baseURL: '/api',
  headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
});

const cache = new Map(); // key -> Promise<data>

export function get(url) {
  if (cache.has(url)) return cache.get(url);
  const p = client.get(url).then(r => r.data.data ?? r.data);
  cache.set(url, p);
  // If it fails, evict so the next caller can retry.
  p.catch(() => cache.delete(url));
  return p;
}

export function invalidate(prefix) {
  for (const key of cache.keys()) {
    if (key.startsWith(prefix)) cache.delete(key);
  }
}

export const apiClient = client;
```

- [ ] **Step 2: Create `useCompanyData.js`**

```js
import { ref } from 'vue';
import { get } from './useApi';

export function useCompanies() {
  const companies = ref([]);
  const load = async () => { companies.value = await get('/companies'); };
  return { companies, load };
}

// Per-company resource fetchers (cached at the HTTP layer)
export const fetchEmployees = (companyId)            => get(`/companies/${companyId}/employees`);
export const fetchProjects  = (companyId)            => get(`/companies/${companyId}/projects`);
export const fetchTasks     = (companyId)            => get(`/companies/${companyId}/tasks`);
export const fetchProjectEmployees = (companyId, projectId) =>
  get(`/companies/${companyId}/projects/${projectId}/employees`);
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/composables
git commit -m "feat: axios wrapper with promise-cached lookups"
```

---

## Task 10: `CompanyFilter` Component

**Files:**
- Create: `resources/js/components/CompanyFilter.vue`

- [ ] **Step 1: Build the component**

```vue
<script setup>
import { onMounted } from 'vue';
import { useCompanies } from '../composables/useCompanyData';

const props = defineProps({ modelValue: { type: [Number, null], default: null } });
const emit  = defineEmits(['update:modelValue']);
const { companies, load } = useCompanies();

onMounted(load);
</script>

<template>
  <label class="text-sm text-slate-600 flex items-center gap-2">
    Company
    <select
      class="rounded border-slate-300 text-slate-900"
      :value="modelValue ?? ''"
      @change="e => emit('update:modelValue', e.target.value === '' ? null : Number(e.target.value))"
    >
      <option value="">All</option>
      <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
    </select>
  </label>
</template>
```

- [ ] **Step 2: Manual test**

Reload, dropdown should show "All" + seeded companies.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/CompanyFilter.vue
git commit -m "feat: top-level company filter dropdown (default All)"
```

---

## Task 11: `NewEntriesTab` + `EntryRow` (Cascading Dropdowns)

This is the centerpiece. The entry row has:
- Company select (defaults to top filter if specific; otherwise blank)
- Date input
- Employee select (loaded from selected company × project)
- Project select (loaded from selected company)
- Task select (loaded from selected company)
- Hours input

When the row's company changes, employees/projects/tasks are reloaded and the existing employee/project/task selections are cleared if they no longer belong.

**Files:**
- Create: `resources/js/components/NewEntriesTab.vue`
- Create: `resources/js/components/EntryRow.vue`
- Create: `resources/js/composables/useTimeEntries.js`

- [ ] **Step 1: Create `useTimeEntries.js`**

```js
import { apiClient, invalidate } from './useApi';

export async function listTimeEntries(companyId = null) {
  const params = companyId ? { company_id: companyId } : {};
  const r = await apiClient.get('/time-entries', { params });
  return r.data.data;
}

export async function createTimeEntries(entries) {
  const r = await apiClient.post('/time-entries', { entries });
  invalidate('/time-entries'); // (no GET cache currently, but keeps room for one)
  return r.data.data;
}
```

- [ ] **Step 2: Create `EntryRow.vue`**

```vue
<script setup>
import { ref, watch, computed } from 'vue';
import { fetchEmployees, fetchProjects, fetchTasks, fetchProjectEmployees } from '../composables/useCompanyData';

const props = defineProps({
  modelValue: { type: Object, required: true }, // { company_id, date, employee_id, project_id, task_id, hours }
  companies: { type: Array, required: true },
  errors: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:modelValue', 'duplicate', 'remove']);

const row = computed(() => props.modelValue);
const update = (patch) => emit('update:modelValue', { ...row.value, ...patch });

const projects  = ref([]);
const tasks     = ref([]);
const employees = ref([]); // narrowed by project once selected

async function refreshCompanyLists(companyId) {
  if (!companyId) { projects.value = []; tasks.value = []; employees.value = []; return; }
  [projects.value, tasks.value, employees.value] = await Promise.all([
    fetchProjects(companyId),
    fetchTasks(companyId),
    fetchEmployees(companyId),
  ]);
}

async function refreshProjectEmployees(companyId, projectId) {
  if (!companyId || !projectId) {
    if (companyId) employees.value = await fetchEmployees(companyId);
    return;
  }
  employees.value = await fetchProjectEmployees(companyId, projectId);
}

watch(() => row.value.company_id, async (id, prev) => {
  if (id === prev) return;
  await refreshCompanyLists(id);
  // Wipe selections that depend on company.
  update({ employee_id: null, project_id: null, task_id: null });
}, { immediate: true });

watch(() => row.value.project_id, async (pid) => {
  await refreshProjectEmployees(row.value.company_id, pid);
  // Wipe employee if no longer in the list.
  if (!employees.value.some(e => e.id === row.value.employee_id)) {
    update({ employee_id: null });
  }
});

const fieldClass = (field) => [
  'rounded border w-full',
  props.errors[field] ? 'border-red-500' : 'border-slate-300',
];
</script>

<template>
  <tr class="align-top">
    <td class="p-2">
      <select :class="fieldClass('company_id')" :value="row.company_id ?? ''"
        @change="e => update({ company_id: e.target.value ? Number(e.target.value) : null })">
        <option value="">Select…</option>
        <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <p v-if="errors.company_id" class="text-xs text-red-600 mt-1">{{ errors.company_id[0] }}</p>
    </td>
    <td class="p-2">
      <input type="date" :class="fieldClass('date')" :value="row.date"
        @input="e => update({ date: e.target.value })" />
      <p v-if="errors.date" class="text-xs text-red-600 mt-1">{{ errors.date[0] }}</p>
    </td>
    <td class="p-2">
      <select :class="fieldClass('employee_id')" :value="row.employee_id ?? ''"
        :disabled="!row.company_id"
        @change="e => update({ employee_id: e.target.value ? Number(e.target.value) : null })">
        <option value="">Select…</option>
        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
      </select>
      <p v-if="errors.employee_id" class="text-xs text-red-600 mt-1">{{ errors.employee_id[0] }}</p>
    </td>
    <td class="p-2">
      <select :class="fieldClass('project_id')" :value="row.project_id ?? ''"
        :disabled="!row.company_id"
        @change="e => update({ project_id: e.target.value ? Number(e.target.value) : null })">
        <option value="">Select…</option>
        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <p v-if="errors.project_id" class="text-xs text-red-600 mt-1">{{ errors.project_id[0] }}</p>
    </td>
    <td class="p-2">
      <select :class="fieldClass('task_id')" :value="row.task_id ?? ''"
        :disabled="!row.company_id"
        @change="e => update({ task_id: e.target.value ? Number(e.target.value) : null })">
        <option value="">Select…</option>
        <option v-for="t in tasks" :key="t.id" :value="t.id">{{ t.name }}</option>
      </select>
      <p v-if="errors.task_id" class="text-xs text-red-600 mt-1">{{ errors.task_id[0] }}</p>
    </td>
    <td class="p-2">
      <input type="number" step="0.25" min="0" :class="fieldClass('hours')" :value="row.hours"
        @input="e => update({ hours: e.target.value === '' ? null : Number(e.target.value) })" />
      <p v-if="errors.hours" class="text-xs text-red-600 mt-1">{{ errors.hours[0] }}</p>
    </td>
    <td class="p-2 whitespace-nowrap">
      <button type="button" class="text-xs text-slate-500 hover:text-slate-900" @click="$emit('duplicate')">Duplicate</button>
      <button type="button" class="text-xs text-red-500 hover:text-red-700 ml-2" @click="$emit('remove')">Remove</button>
    </td>
  </tr>
</template>
```

- [ ] **Step 3: Create `NewEntriesTab.vue`**

```vue
<script setup>
import { ref, inject, onMounted, computed, nextTick } from 'vue';
import EntryRow from './EntryRow.vue';
import { useCompanies } from '../composables/useCompanyData';
import { createTimeEntries } from '../composables/useTimeEntries';

const selectedCompanyId = inject('selectedCompanyId');
const { companies, load } = useCompanies();
onMounted(load);

const today = () => new Date().toISOString().slice(0, 10);
const blankRow = () => ({
  company_id: selectedCompanyId.value ?? null,
  date: today(),
  employee_id: null,
  project_id: null,
  task_id: null,
  hours: null,
});

const rows   = ref([blankRow()]);
const errors = ref({}); // { 0: { field: [msg] }, ... }
const submitting = ref(false);
const flash  = ref(null);

const addRow = () => { rows.value.push(blankRow()); };
const duplicate = (i) => { rows.value.splice(i + 1, 0, { ...rows.value[i] }); };
const remove    = (i) => { rows.value.splice(i, 1); if (rows.value.length === 0) addRow(); };

// Keyboard: Ctrl/Cmd+Enter submits, Ctrl/Cmd+D duplicates last row.
function onKeydown(e) {
  const mod = e.metaKey || e.ctrlKey;
  if (mod && e.key === 'Enter') { e.preventDefault(); submit(); }
  if (mod && e.key.toLowerCase() === 'd') { e.preventDefault(); duplicate(rows.value.length - 1); }
}

async function submit() {
  errors.value = {};
  submitting.value = true;
  flash.value = null;
  try {
    await createTimeEntries(rows.value);
    flash.value = { type: 'success', message: `Saved ${rows.value.length} entr${rows.value.length === 1 ? 'y' : 'ies'}.` };
    rows.value = [blankRow()];
  } catch (err) {
    if (err.response?.status === 422) {
      const flat = err.response.data.errors || {};
      const byRow = {};
      for (const key of Object.keys(flat)) {
        const m = key.match(/^entries\.(\d+)\.(.+)$/);
        if (!m) continue;
        const [, idx, field] = m;
        byRow[idx] ??= {};
        byRow[idx][field] = flat[key];
      }
      errors.value = byRow;
      flash.value = { type: 'error', message: 'Please fix the highlighted fields.' };
    } else {
      flash.value = { type: 'error', message: 'Something went wrong. Try again.' };
    }
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <section class="space-y-4" @keydown="onKeydown">
    <div v-if="flash" :class="['rounded p-3 text-sm', flash.type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
      {{ flash.message }}
    </div>

    <div class="overflow-x-auto rounded border bg-white">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100 text-left">
          <tr>
            <th class="p-2 font-medium">Company</th>
            <th class="p-2 font-medium">Date</th>
            <th class="p-2 font-medium">Employee</th>
            <th class="p-2 font-medium">Project</th>
            <th class="p-2 font-medium">Task</th>
            <th class="p-2 font-medium">Hours</th>
            <th class="p-2"></th>
          </tr>
        </thead>
        <tbody>
          <EntryRow
            v-for="(row, i) in rows"
            :key="i"
            :model-value="row"
            :companies="companies"
            :errors="errors[i] ?? {}"
            @update:model-value="rows[i] = $event"
            @duplicate="duplicate(i)"
            @remove="remove(i)"
          />
        </tbody>
      </table>
    </div>

    <div class="flex items-center justify-between">
      <button type="button" class="text-sm rounded border px-3 py-1 hover:bg-slate-50" @click="addRow">
        + Add row <span class="text-xs text-slate-400 ml-1">(or ⌘D to duplicate last)</span>
      </button>
      <button
        type="button"
        :disabled="submitting"
        class="rounded bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800 disabled:opacity-60"
        @click="submit"
      >
        {{ submitting ? 'Saving…' : 'Submit (⌘+Enter)' }}
      </button>
    </div>
  </section>
</template>
```

- [ ] **Step 4: Manual test in browser**

```bash
php artisan serve &
npm run dev &
```

Verify:
- Selecting a company loads employees/projects/tasks for that row.
- Selecting a project narrows employees to those assigned.
- Submit succeeds with valid data; row resets.
- Submit with invalid data (e.g., 0 hours) shows red border + per-field error message.
- Tab key navigates between fields top-to-bottom, left-to-right (default browser behavior).
- ⌘D duplicates the last row, ⌘Enter submits.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components resources/js/composables/useTimeEntries.js
git commit -m "feat: new entries tab with cascading dropdowns, validation UX, keyboard shortcuts"
```

---

## Task 12: `HistoryTab`

**Files:**
- Create: `resources/js/components/HistoryTab.vue`

- [ ] **Step 1: Build component**

```vue
<script setup>
import { ref, watch, inject, computed } from 'vue';
import { listTimeEntries } from '../composables/useTimeEntries';

const selectedCompanyId = inject('selectedCompanyId');
const entries = ref([]);
const loading = ref(false);

async function reload() {
  loading.value = true;
  try {
    entries.value = await listTimeEntries(selectedCompanyId.value);
  } finally {
    loading.value = false;
  }
}

watch(selectedCompanyId, reload, { immediate: true });

const totalHours = computed(() => entries.value.reduce((s, e) => s + Number(e.hours), 0));
</script>

<template>
  <section class="space-y-3">
    <div class="text-sm text-slate-600">
      <span v-if="loading">Loading…</span>
      <span v-else>{{ entries.length }} entr{{ entries.length === 1 ? 'y' : 'ies' }} · {{ totalHours.toFixed(2) }} hours total</span>
    </div>
    <div class="overflow-x-auto rounded border bg-white">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100 text-left">
          <tr>
            <th class="p-2 font-medium">Date</th>
            <th class="p-2 font-medium">Company</th>
            <th class="p-2 font-medium">Employee</th>
            <th class="p-2 font-medium">Project</th>
            <th class="p-2 font-medium">Task</th>
            <th class="p-2 font-medium text-right">Hours</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in entries" :key="e.id" class="border-t">
            <td class="p-2">{{ e.date }}</td>
            <td class="p-2">{{ e.company.name }}</td>
            <td class="p-2">{{ e.employee.name }}</td>
            <td class="p-2">{{ e.project.name }}</td>
            <td class="p-2">{{ e.task.name }}</td>
            <td class="p-2 text-right tabular-nums">{{ Number(e.hours).toFixed(2) }}</td>
          </tr>
          <tr v-if="!loading && entries.length === 0">
            <td colspan="6" class="p-6 text-center text-slate-500">No entries yet.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
```

- [ ] **Step 2: Manual test**

- Switch top company filter — table refreshes for that company.
- "All" shows everything.
- Submit a new entry from New Entries tab → switch to History → entry visible (after a brief reload triggered when re-entering the tab).

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/HistoryTab.vue
git commit -m "feat: history tab with company-scoped read-only listing and totals"
```

---

## Task 13: README + Run Script

**Files:**
- Create: `README.md`

- [ ] **Step 1: Write README**

```markdown
# Time Entry Interface (Laravel + Vue)

A small Laravel + Vue 3 (Composition API) application for entering and viewing employee time entries.

## Requirements
- PHP 8.2+
- Composer
- Node.js 20+
- SQLite (bundled with PHP — no server needed)

## Setup

```bash
git clone <repo-url> mason
cd mason
cp .env.example .env
composer install
npm install
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
```

## Run

In two terminals:
```bash
php artisan serve
npm run dev
```

Open http://127.0.0.1:8000.

## Tests

```bash
php artisan test
```

## How it's organized

- **Backend** — Laravel REST API under `/api`:
  - `GET /api/companies`
  - `GET /api/companies/{company}/employees`
  - `GET /api/companies/{company}/projects`
  - `GET /api/companies/{company}/tasks`
  - `GET /api/companies/{company}/projects/{project}/employees`
  - `GET /api/time-entries?company_id=…`
  - `POST /api/time-entries` — accepts `{ entries: [ … ] }` for batched creation
- **Frontend** — single Vue 3 SPA mounted on the root view; tabs + global company filter.

## Business rules (enforced on the server)

`StoreTimeEntriesRequest` validates each entry plus cross-entry/cross-DB rules:
1. Employee must belong to the selected company.
2. Project must belong to the selected company.
3. Task must belong to the selected company.
4. Employee must be assigned to the selected project.
5. An employee cannot have time on more than one project for a given date (across the submitted batch and existing rows). They *can* split that day across multiple tasks within the same project.

## Performance / scalability notes

- Lookup endpoints (`companies`, `employees`, `projects`, `tasks`) are scoped per-company so payloads stay small even with many companies.
- Frontend caches each lookup response in-memory for the lifetime of the page (`composables/useApi.js`), so reopening a row that uses the same company costs zero requests.
- `time_entries` has indexes on `(employee_id, date)` (for the business-rule check) and `(company_id, date)` (for the history filter).
- Eager-loading on the list endpoint avoids N+1.
- For larger datasets, the History endpoint should add pagination (`paginate(50)`) and the dropdowns should switch to typeahead/search. Stubbed for now to keep the surface small.

## UX choices

- Top-level company selector defaults to **All** (per spec). When set to a specific company, it pre-fills new rows so the user doesn't repeat themselves; History narrows to that company's entries.
- Validation errors come back with the row index and field; the row highlights and shows per-field messages.
- Keyboard: Tab navigates fields, ⌘D (Ctrl+D) duplicates the last row, ⌘Enter (Ctrl+Enter) submits.
- "Duplicate" per row for fast repetitive entry.

## What's intentionally not implemented

- AI-assisted natural-language entry (Super Bonus). Will be added in a follow-up.
- Edit existing entries / summary totals beyond a simple total in History.
- Authentication (out of scope per spec).
```

- [ ] **Step 2: Sync `.env.example`**

Edit `.env.example` to use SQLite (same edits as `.env` in Task 1).

- [ ] **Step 3: Commit**

```bash
git add README.md .env.example
git commit -m "docs: add README with setup, API, and design notes"
```

---

## Task 14: Final Verification

- [ ] **Step 1: Fresh install dry-run**

```bash
rm database/database.sqlite
touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan test
```

Expected: all tests green.

- [ ] **Step 2: Smoke test in browser**

```bash
php artisan serve &
npm run dev &
```

Walk through:
1. Default load — "All" selected, History shows all seeded entries (none yet, so empty).
2. Pick a company up top — New Entries row prefills its company.
3. Add 2 rows for the same employee, same date, **same project**, different tasks → submit → success.
4. Add a row that violates rules (different project, same employee+date) → submit → 422 with per-field message visible.
5. Switch to History — submitted rows appear; switching company filters list.
6. Tab through fields with keyboard; ⌘D duplicates a row; ⌘Enter submits.

- [ ] **Step 3: Commit any tweaks**

```bash
git add -A
git commit -m "chore: final polish from manual smoke test" || true
```

---

## Task 15: Export AI Conversation as JSON

The challenge requires submitting the AI conversation alongside the code.

- [ ] **Step 1: Locate Claude Code transcript**

Claude Code stores conversation transcripts as JSONL under `~/.claude/projects/-Users-gustavopertile-workplace-extras-mason/`. Identify the relevant session file by mtime:

```bash
ls -lt ~/.claude/projects/-Users-gustavopertile-workplace-extras-mason/*.jsonl | head -5
```

- [ ] **Step 2: Convert JSONL to a single JSON array**

```bash
python3 -c "
import json, sys, pathlib
src = pathlib.Path(sys.argv[1])
records = [json.loads(l) for l in src.read_text().splitlines() if l.strip()]
print(json.dumps(records, indent=2, ensure_ascii=False))
" ~/.claude/projects/-Users-gustavopertile-workplace-extras-mason/<session>.jsonl > conversation.json
```

(Pick the actual session filename in place of `<session>`.)

- [ ] **Step 3: Commit**

```bash
git add conversation.json
git commit -m "docs: add AI conversation export"
```

---

## Out of scope (explicit deferrals)

- **AI-assisted natural-language entry** (Super Bonus) — to be implemented after the rest is verified perfect.
- **Edit-from-history** — possible follow-up.
- **Summary totals beyond row count + sum** — possible follow-up.
- **Frontend unit tests** — the validation logic that *matters* lives on the backend and is covered by feature tests; frontend behavior is verified manually.

---

## Self-review checklist (run before declaring complete)

- [ ] Every required field appears in `New Entries` in spec order: Company, Date, Employee, Project, Task, Hours.
- [ ] Top dropdown defaults to "All" and meaningfully changes both tabs' content.
- [ ] Server rejects every invalid combination listed in "Business Rules" with a 422 + field-keyed message.
- [ ] `/api/time-entries` supports `company_id` filter.
- [ ] All 5 required tables exist; pivots `company_employee` and `employee_project` are present.
- [ ] Tab key navigates the form; ⌘Enter submits.
- [ ] README runs from a clean clone with the documented commands.
- [ ] `conversation.json` exists at the repo root.
