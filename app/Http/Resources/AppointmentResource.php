<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public static $wrap = null;
    public function toArray(Request $request): array
    {
        return [
            '_id'         => $this->id,
            'senderId'    => $this->senderId,
            'receiverId'  => $this->receiverId,
            'startTime'   => $this->startTime?->toISOString(),
            'endTime'     => $this->endTime?->toISOString(),
            'description' => $this->description,
            'status'      => $this->status,
            'createdAt'   => $this->created_at?->toISOString(),
        ];
    }
}
