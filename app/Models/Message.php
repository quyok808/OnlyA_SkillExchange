<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Message extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'chatRoomId',
        'senderId',
        'content',
        'file',
        'image',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    public function chatRoom()
    {
        return $this->belongsTo(ChatRoom::class, 'chatRoomId');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'senderId');
    }
}
