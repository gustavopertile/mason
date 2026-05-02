<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTimeEntriesRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class TimeEntryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $entries = TimeEntry::query()
            ->with([
                'company:id,name',
                'employee:id,name',
                'project:id,name',
                'task:id,name',
            ])
            ->when($request->filled('company_id'), function ($query) use ($request) {
                $query->where('company_id', $request->integer('company_id'));
            })
            ->latest('date')
            ->latest('id')
            ->get();

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
}
