<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Report extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'status',
        'reason',
        'reportedBy',
        'userId',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reportedBy');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
