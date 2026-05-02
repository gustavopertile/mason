<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTimeEntriesRequest;
use App\Http\Requests\UpdateTimeEntryRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TimeEntryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        $entries = TimeEntry::query()
            ->with([
                'company:id,name',
                'employee:id,name',
                'project:id,name',
                'task:id,name',
            ])
            ->when($request->filled('company_id'), function ($q) use ($request) {
                $q->where('company_id', $request->integer('company_id'));
            })
            ->when($request->filled('from'), function ($q) use ($request) {
                $q->whereDate('date', '>=', $request->input('from'));
            })
            ->when($request->filled('to'), function ($q) use ($request) {
                $q->whereDate('date', '<=', $request->input('to'));
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->input('search') . '%';
                $q->where(function ($q) use ($term) {
                    $q->whereHas('company',  fn ($qq) => $qq->where('name', 'like', $term))
                      ->orWhereHas('employee', fn ($qq) => $qq->where('name', 'like', $term))
                      ->orWhereHas('project',  fn ($qq) => $qq->where('name', 'like', $term))
                      ->orWhereHas('task',     fn ($qq) => $qq->where('name', 'like', $term));
                });
            })
            ->latest('date')
            ->latest('id')
            ->paginate($perPage);

        return TimeEntryResource::collection($entries);
    }

    public function store(StoreTimeEntriesRequest $request): JsonResponse
    {
        $created = DB::transaction(function () use ($request): EloquentCollection {
            $collection = new EloquentCollection();
            foreach ($request->validated('entries') as $row) {
                $collection->push(TimeEntry::create($row));
            }
            return $collection;
        });

        $created->load([
            'company:id,name',
            'employee:id,name',
            'project:id,name',
            'task:id,name',
        ]);

        return TimeEntryResource::collection($created)
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTimeEntryRequest $request, TimeEntry $timeEntry): TimeEntryResource
    {
        $timeEntry->update($request->validated());
        $timeEntry->load([
            'company:id,name',
            'employee:id,name',
            'project:id,name',
            'task:id,name',
        ]);

        return new TimeEntryResource($timeEntry);
    }

    public function destroy(TimeEntry $timeEntry): Response
    {
        $timeEntry->delete();

        return response()->noContent();
    }
}
