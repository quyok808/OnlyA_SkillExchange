<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'senderId',
        'receiverId',
        'startTime',
        'endTime',
        'description',
        'status',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    
    protected $casts = [
        'startTime' => 'datetime', 
        'endTime' => 'datetime',   
    ];
   

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'senderId');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiverId');
    }
}