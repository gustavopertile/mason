<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
}
