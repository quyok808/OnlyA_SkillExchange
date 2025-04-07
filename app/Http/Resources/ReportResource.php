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
            'status' => $this->status,           // Trạng thái report
            'reportedBy' => $this->reportedBy, // ID người BỊ báo cáo
            'userId' => $this->userId,         // ID người báo cáo (người tạo)
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}