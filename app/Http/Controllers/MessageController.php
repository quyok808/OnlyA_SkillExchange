<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Http;

class MessageController extends Controller
{
    public function sendMessage(Request $request)
    {
        try {
            $request->validate([
                'chatRoomId' => 'required|exists:chat_rooms,id',
                'content' => 'required_without_all:file,image|string|nullable',
                'file' => 'required_without_all:content,image|file|nullable',
                'image' => 'required_without_all:content,file|image|nullable',
            ]);

            $user = JWTAuth::user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }

            $chatRoom = ChatRoom::findOrFail($request->chatRoomId);
            if (!$chatRoom->participants()->where('userId', $user->id)->exists()) {
                return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền gửi tin nhắn'], 403);
            }

            $filePath = null;
            $fileName = null;
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $fileName = $file->getClientOriginalName(); // Lấy tên file gốc
                $filePath = $file->storeAs('chat_files', $fileName, 'public'); // Lưu với tên gốc
                if (!$filePath) {
                    Log::error('Failed to store file', ['file' => $fileName]);
                    return response()->json(['status' => 'error', 'message' => 'Lỗi lưu tệp'], 500);
                }
            }

            $imagePath = null;
            $imageName = null;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = $image->getClientOriginalName(); // Lấy tên ảnh gốc
                $imagePath = $image->storeAs('chat_images', $imageName, 'public'); // Lưu với tên gốc
                if (!$imagePath) {
                    Log::error('Failed to store image', ['image' => $imageName]);
                    return response()->json(['status' => 'error', 'message' => 'Lỗi lưu hình ảnh'], 500);
                }
            }

            $message = Message::create([
                'chatRoomId' => $request->chatRoomId,
                'senderId' => $user->id,
                'content' => $request->content,
                'file' => $filePath,
                'image' => $imagePath,
            ]);

            $baseUrl = config('app.url', 'http://localhost:5008');
            $messageData = [
                'id' => $message->id,
                'username' => $user->name,
                'content' => $request->content,
                'timestamp' => $message->created_at->toISOString(),
                'chatRoomId' => $request->chatRoomId,
                'senderId' => $user->id,
                'file' => $filePath ? [
                    'url' => "{$baseUrl}/storage/{$filePath}",
                    'name' => $fileName, // Trả về tên file gốc
                ] : null,
                'image' => $imagePath ? [
                    'url' => "{$baseUrl}/storage/{$imagePath}",
                    'name' => $imageName, // Trả về tên ảnh gốc
                ] : null,
            ];

            Log::info('Message data sent to Socket.IO', $messageData);

            $response = Http::withoutVerifying()->post('https://192.168.1.8:5009/broadcast', $messageData);
            if ($response->failed()) {
                Log::error('Failed to broadcast message', ['response' => $response->body()]);
                return response()->json(['status' => 'error', 'message' => 'Lỗi gửi tin nhắn qua Socket.IO'], 500);
            }

            return response()->json(['status' => 'success', 'data' => $messageData], 200);
        } catch (\Exception $e) {
            Log::error('Error sending message: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống'], 500);
        }
    }
    public function getMessages(Request $request, $chatRoomId)
    {
        try {
            $user = JWTAuth::user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }

            $chatRoom = ChatRoom::findOrFail($chatRoomId);
            if (!$chatRoom->participants()->where('userId', $user->id)->exists()) {
                return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền xem tin nhắn'], 403);
            }

            $limit = $request->query('limit', 50);
            $messages = Message::where('chatRoomId', $chatRoomId)
                ->with('sender:id,name')
                ->orderBy('created_at', 'desc')
                ->paginate($limit);

            $baseUrl = config('app.url', 'http://localhost:5008');
            $messages->getCollection()->transform(function ($message) use ($baseUrl) {
                return [
                    'id' => $message->id,
                    'username' => $message->sender->name,
                    'content' => $message->content,
                    'timestamp' => $message->created_at->toISOString(),
                    'chatRoomId' => $message->chatRoomId,
                    'senderId' => $message->senderId,
                    'file' => $message->file ? [
                        'url' => "{$baseUrl}/storage/{$message->file}",
                        'name' => pathinfo($message->file, PATHINFO_BASENAME), // Tạm dùng tên từ đường dẫn
                    ] : null,
                    'image' => $message->image ? [
                        'url' => "{$baseUrl}/storage/{$message->image}",
                        'name' => pathinfo($message->image, PATHINFO_BASENAME), // Tạm dùng tên từ đường dẫn
                    ] : null,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $messages,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching messages: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống'], 500);
        }
    }
}
