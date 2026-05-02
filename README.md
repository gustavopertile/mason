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
git clone <repo-url> mason
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

23 feature/unit tests cover the data model, lookup endpoints, and every business-rule path.

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
| GET | `/api/time-entries?company_id=<id>` | List entries; `company_id` is optional and filters the list. |
| POST | `/api/time-entries` | Batched create. Body: `{ "entries": [ { company_id, employee_id, project_id, task_id, date, hours }, ... ] }`. Returns `201` with the created entries, or `422` with `entries.{i}.{field}` keyed errors. |

## Business rules (server-enforced)

`StoreTimeEntriesRequest` enforces all five rules — both within the submitted batch and against existing rows:

1. The employee must belong to the selected company.
2. The project must belong to the selected company.
3. The task must belong to the selected company.
4. The employee must be assigned to the selected project.
5. An employee cannot have time on more than one project for a given date. They *can* split that day across multiple tasks within the same project.

Validation errors come back keyed per row and field (`entries.0.project_id`, `entries.2.hours`, …) so the frontend can highlight exactly the offending cell.

## UX choices

- The top-level company selector defaults to **All** (per spec). When set to a specific company:
  - New Entries pre-fills that company on rows whose company hasn't been chosen yet (never overwrites a manual selection).
  - History narrows to that company's entries.
- Per-row validation errors render inline with red borders and a one-line message under the field.
- Tab moves through fields in the spec order (Company → Date → Employee → Project → Task → Hours).
- `⌘D` / `Ctrl+D` duplicates the last row; `⌘Enter` / `Ctrl+Enter` submits.
- Each row has explicit "Duplicate" and "Remove" buttons for mouse users.

## Performance notes

- Lookup endpoints (`employees`, `projects`, `tasks`) are scoped per company so payloads stay small even with many companies.
- The frontend caches lookup responses in-memory for the lifetime of the page (`composables/useApi.js`). Concurrent rows opening the same company share a single in-flight request via a promise cache.
- `time_entries` carries indexes on `(employee_id, date)` for the business-rule lookup and `(company_id, date)` for history filtering.
- The list endpoint eager-loads all four belongs-to relations to avoid N+1.
- For larger datasets, the History endpoint should add pagination (`paginate(50)`) and the dropdowns should switch to typeahead/search. Stubbed for now to keep the surface small.

## What's intentionally not implemented

- AI-assisted natural-language entry (Super Bonus). Will be added in a follow-up — the rest of the app is stable groundwork for it.
- Edit / delete from history.
- Summary totals beyond row count + sum.
- Authentication (out of scope per spec).

## Project layout (relevant pieces)

```
app/
  Http/
    Controllers/Api/    # one controller per resource
    Requests/StoreTimeEntriesRequest.php
    Resources/TimeEntryResource.php
  Models/               # Company, Employee, Project, Task, TimeEntry
database/
  migrations/           # 7 migrations
  factories/            # one per model
  seeders/DatabaseSeeder.php
resources/
  js/
    App.vue
    components/         # CompanyFilter, Tabs, NewEntriesTab, EntryRow, HistoryTab
    composables/        # useApi (axios + cache), useCompanyData, useTimeEntries
  views/app.blade.php
routes/
  api.php
  web.php
tests/
  Feature/              # CompanyScopedListsTest, TimeEntryApiTest, TimeEntryStoreTest
  Unit/RelationshipsTest.php
docs/PLAN.md            # original implementation plan
conversation.json       # AI conversation export
```
