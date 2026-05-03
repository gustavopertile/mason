# Time Entry Interface (Laravel + Vue)

A small Laravel + Vue 3 (Composition API) application for entering and viewing employee time entries — built as an engineering test exercise.

## Requirements

- PHP 8.2+ (tested on 8.4)
- Composer 2.x
- Node.js 20+ (tested on 23)
- npm 10+
- SQLite (bundled with PHP — no DB server needed)

## Setup

```bash
git clone https://github.com/gustavopertile/mason.git
cd mason
composer setup
```

`composer setup` runs `composer install`, copies `.env`, creates the SQLite file, generates the app key, runs migrations + seeders, and installs/builds the frontend.

## Run

```bash
composer dev
```

Runs Laravel's bundled dev orchestrator (server + queue listener + log tail + Vite) on a single command. Open http://127.0.0.1:8000.

If you'd rather run pieces separately:

```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev
```

## Tests

```bash
php artisan test
```

30 feature/unit tests cover the data model, lookup endpoints, every business-rule path, and the edit/delete/filter flows.

## AI conversation log

As required by the challenge, the full AI conversation history is included in the repo under [`ai-sessions/`](ai-sessions/) — split into `first_session.json` through `fourth_session.json`.

---

## Spec coverage

Every requirement in the exercise brief, mapped to where it lives in the codebase.

### Tech stack

| Requirement | Status | Where |
|---|---|---|
| Laravel | ✅ | Laravel 11, app/, routes/, etc. |
| Vue with the Composition API | ✅ | every `.vue` file uses `<script setup>` |
| No authentication required | ✅ | open routes under `/api` |
| Standard Laravel setup commands | ✅ | `composer setup` + `composer dev` |

### Database structure

| Required table | Status | Migration |
|---|---|---|
| `companies` | ✅ | `2026_05_02_163128_create_companies_table.php` |
| `employees` | ✅ | `2026_05_02_163129_create_employees_table.php` |
| `projects` | ✅ | `2026_05_02_163129_create_projects_table.php` |
| `tasks` | ✅ | `2026_05_02_163129_create_tasks_table.php` |
| `time_entries` | ✅ | `2026_05_02_163133_create_time_entries_table.php` |
| `company_employee` (pivot) | ✅ | `2026_05_02_163131_create_company_employee_table.php` |
| `employee_project` (pivot) | ✅ | `2026_05_02_163132_create_employee_project_table.php` |

### Relationships

| Required relationship | Status | Where |
|---|---|---|
| A company has many employees | ✅ | `Company::employees()` (BelongsToMany via `company_employee`) |
| An employee can belong to multiple companies | ✅ | `Employee::companies()` (BelongsToMany) |
| A company has its own set of projects | ✅ | `Company::projects()` (HasMany), `projects.company_id` |
| A company has its own set of tasks | ✅ | `Company::tasks()` (HasMany), `tasks.company_id` |
| Tasks are company-specific, not project-specific | ✅ | `tasks` has only `company_id` — no `project_id` column |
| Employees are assigned to one or more projects | ✅ | `Employee::projects()` (BelongsToMany via `employee_project`) |
| A time entry belongs to a company, employee, project, task, and date | ✅ | `TimeEntry` model + 4 FKs and a `date` column |

### Required artifacts

| Required | Status | Where |
|---|---|---|
| Migrations | ✅ | `database/migrations/` |
| Models | ✅ | `app/Models/` |
| Relationships | ✅ | declared on each model |
| Seeders | ✅ | `database/seeders/DatabaseSeeder.php` (3 companies, 8 employees, projects, tasks, ~2 weeks of entries) |
| API endpoints | ✅ | see "API surface" below |
| Frontend components | ✅ | `resources/js/components/` |

### Business rules (server-enforced)

`StoreTimeEntriesRequest` and `UpdateTimeEntryRequest` enforce all five rules — both within the submitted batch and against existing rows:

1. The employee must belong to the selected company.
2. The project must belong to the selected company.
3. The task must belong to the selected company.
4. The employee must be assigned to the selected project.
5. **An employee cannot have time on more than one project for a given date.** They *can* split that day across multiple tasks within the same project.

Validation errors come back keyed per row and field (`entries.0.project_id`, `entries.2.hours`, …) so the frontend can highlight the exact offending cell.

### Interface

| Requirement | Status | Where |
|---|---|---|
| Two tabs: New Entries / History | ✅ | `Tabs.vue`, `App.vue` |
| Top-level dropdown: specific company or "All" | ✅ | inside `SummaryCard.vue`, bound via `provide`/`inject` |
| Default value is "All" | ✅ | `App.vue` — `selectedCompanyId = ref(null)` |
| Selected value impacts both tabs | ✅ | New Entries pre-fills the company on empty rows; History filters and the SummaryCard re-scopes |

### New Entries tab

| Requirement | Status |
|---|---|
| Table where each row is a new time entry | ✅ |
| Field order: Company, Date, Employee, Project, Task, Hours | ✅ (`EntryRow.vue` cell order matches) |
| User can add more rows before submit | ✅ (`Add row` button + ⌘B) |
| Submit posts entries to the API | ✅ (`POST /api/time-entries`, batched) |
| Employee list depends on selected company | ✅ (`/api/companies/{id}/employees`) |
| Project list depends on selected company | ✅ (`/api/companies/{id}/projects`) |
| Task list depends on selected company | ✅ (`/api/companies/{id}/tasks`) |
| Cannot submit invalid combinations | ✅ (frontend disables, backend validates) |

The employee/project pickers also narrow each other reciprocally: picking an employee filters the project list to ones they're on, and vice versa, via `/api/companies/{id}/employees/{id}/projects` and `/api/companies/{id}/projects/{id}/employees`.

### History tab

| Requirement | Status |
|---|---|
| Read-only table of all submitted entries | ✅ |
| Includes Company, Date, Employee, Project, Task, Hours | ✅ |

### API conventions

REST: resource controllers, plural URIs, proper HTTP verbs, `JsonResource` for output, Form Requests for input. See "API surface" below.

### UX / keyboard

| Requirement | Status |
|---|---|
| Clean, usable, polished design | ✅ |
| Tab key navigates fields in spec order | ✅ (DOM order = Company → Date → Employee → Project → Task → Hours) |
| Efficient multi-row entry | ✅ (Add/Duplicate/Remove buttons + ⌘B / ⌘D / ⌘↵) |

### Performance considerations

- Lookup endpoints (`employees`, `projects`, `tasks`) are **scoped per company** so payloads stay small even with many companies.
- The frontend caches lookup responses in-memory for the lifetime of the page (`composables/useApi.js`). Concurrent rows opening the same company share a single in-flight request via a **promise cache**, so cascading dropdowns never duplicate requests.
- The cache is invalidated for `/time-entries` whenever a row is created, updated, or deleted.
- `time_entries` carries indexes on `(employee_id, date)` for the business-rule lookup and `(company_id, date)` for history filtering.
- The list endpoint **eager-loads** all four belongs-to relations to avoid N+1.
- The History endpoint is **paginated** server-side (`per_page` configurable, default 20, capped at 100).
- For very large datasets, dropdowns would need to switch to typeahead/search rather than pre-loading the full list. Out of scope here.

### Submission requirements

| Required | Status | Where |
|---|---|---|
| Laravel backend code | ✅ | `app/`, `routes/`, `database/` |
| Vue frontend (Composition API) | ✅ | `resources/js/` |
| Migrations | ✅ | `database/migrations/` |
| Seeders | ✅ | `database/seeders/` |
| Models and relationships | ✅ | `app/Models/` |
| API endpoints | ✅ | `routes/api.php` |
| README explaining how to run | ✅ | this file |
| JSON export of the AI conversation | ✅ | `ai-sessions/` (4 session files) |

---

## Bonuses

| Bonus | Status | Where |
|---|---|---|
| Edit existing entries | ✅ | `EditEntryModal.vue`, `PUT /api/time-entries/{id}`, `UpdateTimeEntryRequest`. Same validation as create. Delete is also wired up (`DELETE /api/time-entries/{id}`). |
| Faster data entry | ✅ | Per-row "Duplicate" button; `⌘D` / `Ctrl+D` duplicates the last row; `⌘B` / `Ctrl+B` adds a new row; submitted-row state is preserved on validation errors. |
| Better validation UX | ✅ | The backend returns `entries.{i}.{field}` errors; the frontend groups them by row and renders red borders + a one-line message under the offending field. A flash banner above the table summarises the failure. |
| Summary totals | ✅ | `SummaryCard.vue` shows hours-this-month, total hours, employees, projects, tasks — all re-scoped when the company filter changes. Powered by `GET /api/summary?company_id=`. |
| History improvements | ✅ | Debounced text search across company/employee/project/task; from/to date filter; company filter (via the top-level selector); server-side pagination with a windowed page picker. |
| Keyboard shortcuts | ✅ | `⌘D` duplicate last row, `⌘B` new row, `⌘↵` submit. Native Tab navigation in spec order. |
| **Super Bonus: AI-assisted natural-language entry** | ❌ | Intentionally not implemented. The rest of the app is stable groundwork for it. |

---

## API surface

All endpoints live under `/api`.

| Method | Path | Description |
|---|---|---|
| GET | `/api/companies` | All companies. |
| GET | `/api/companies/{company}/employees` | Employees that belong to a company. |
| GET | `/api/companies/{company}/projects` | Projects owned by a company. |
| GET | `/api/companies/{company}/tasks` | Tasks defined by a company. |
| GET | `/api/companies/{company}/projects/{project}/employees` | Employees on the project who are also on the company (narrows the employee picker once a project is selected). |
| GET | `/api/companies/{company}/employees/{employee}/projects` | Projects in the company that the employee is on (narrows the project picker once an employee is selected). |
| GET | `/api/summary?company_id=<id>` | Aggregates for the SummaryCard. `company_id` optional. |
| GET | `/api/time-entries` | List entries. Query params: `company_id`, `from`, `to`, `search`, `page`, `per_page`. Paginated. |
| POST | `/api/time-entries` | Batched create. Body: `{ "entries": [ { company_id, employee_id, project_id, task_id, date, hours }, ... ] }`. Returns `201` with the created entries, or `422` with `entries.{i}.{field}` keyed errors. |
| PUT | `/api/time-entries/{time_entry}` | Update a single entry. Same validation as create. |
| DELETE | `/api/time-entries/{time_entry}` | Delete a single entry. Returns `204`. |

---

## What's intentionally not implemented

- **AI-assisted natural-language entry** (Super Bonus). The rest of the app is stable groundwork for it.
- **Authentication** — out of scope per spec.

## Project layout (relevant pieces)

```
app/
  Http/
    Controllers/Api/        # CompanyController, EmployeeController, ProjectController,
                            # TaskController, SummaryController, TimeEntryController
    Requests/               # StoreTimeEntriesRequest, UpdateTimeEntryRequest
    Resources/              # TimeEntryResource
  Models/                   # Company, Employee, Project, Task, TimeEntry
database/
  migrations/               # 7 migrations (companies, employees, projects, tasks,
                            #   company_employee, employee_project, time_entries)
  factories/                # one per model
  seeders/DatabaseSeeder.php
resources/
  js/
    App.vue
    app.js
    components/             # SummaryCard, Tabs, NewEntriesTab, EntryRow,
                            #   HistoryTab, EditEntryModal, CompanyFilter
    composables/            # useApi (axios + promise cache),
                            #   useCompanyData, useTimeEntries, useSummary
  views/app.blade.php
routes/
  api.php
  web.php
tests/
  Feature/                  # CompanyScopedListsTest, TimeEntryApiTest,
                            #   TimeEntryStoreTest, TimeEntryUpdateDeleteTest,
                            #   TimeEntryFiltersTest
  Unit/RelationshipsTest.php
docs/PLAN.md                # original implementation plan
ai-sessions/                # AI conversation export (4 session files)
```
