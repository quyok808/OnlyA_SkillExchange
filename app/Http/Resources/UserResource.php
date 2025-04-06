<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->when($this->phone, $this->phone),
            'address' => $this->when($this->address, $this->address),
            'photo' => $this->photo,
            'role' => $this->role,
            'active' => $this->active,
            'lock' => $this->lock,
            'created_at' => $this->created_at?->toIso8601String(),
            // Tránh trả về các trường nhạy cảm
        ];
    }
}