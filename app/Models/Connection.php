<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Connection extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'senderId',
        'receiverId',
        'status',
        'chatRoomId',
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

    public function chatRoom()
    {
        return $this->belongsTo(ChatRoom::class, 'chatRoomId');
    }
}
