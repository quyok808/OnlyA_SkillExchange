<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
// Không cần import UserResource vì không trả về dữ liệu lồng nhau

class ReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Chỉ trả về các cột trực tiếp của bảng reports.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'status' => $this->status,
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
            'userId' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'reportedBy' => [
                'id' => $this->reportedByUser->id,
                'name' => $this->reportedByUser->name,
                'email' => $this->reportedByUser->email,
            ],
        ];
    }
}
