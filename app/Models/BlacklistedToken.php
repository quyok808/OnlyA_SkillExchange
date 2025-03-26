<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BlacklistedToken extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'token',
    ];

    protected $keyType = 'string';

    public $incrementing = false;
}
