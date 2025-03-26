<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

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

    public function sender()
    {
        return $this->belongsTo(User::class, 'senderId');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiverId');
    }
}
