<?php

namespace App\Http\Resources;

use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TimeEntry
 */
class TimeEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'date'     => $this->date->format('Y-m-d'),
            'hours'    => (int) $this->hours,
            'company'  => ['id' => $this->company->id,  'name' => $this->company->name],
            'employee' => ['id' => $this->employee->id, 'name' => $this->employee->name],
            'project'  => ['id' => $this->project->id,  'name' => $this->project->name],
            'task'     => ['id' => $this->task->id,     'name' => $this->task->name],
        ];
    }
}
