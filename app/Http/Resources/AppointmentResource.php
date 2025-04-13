<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public static $wrap = null;
    public function toArray(Request $request): array
    {
        // Truy cập thuộc tính camelCase của model ($this->resource)
        return [
            '_id'         => $this->id,           // Lấy id (UUID/int)
            'senderId'    => $this->senderId,   // Truy cập camelCase
            'receiverId'  => $this->receiverId,  // Truy cập camelCase
            'startTime'   => $this->startTime?->toISOString(), // Truy cập camelCase
            'endTime'     => $this->endTime?->toISOString(),   // Truy cập camelCase
            'description' => $this->description,
            'status'      => $this->status,
            'createdAt'   => $this->created_at?->toISOString(), // created_at là snake_case         
        ];
    }
}
