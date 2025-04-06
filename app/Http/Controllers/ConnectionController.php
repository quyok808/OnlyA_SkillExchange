<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use App\Models\ChatRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class ConnectionController extends Controller
{
    /**
     * Sent request connection
     */
    public function sendRequest(Request $request)
    {
        try {
            $request->validate([
                'receiver_id' => 'required|exists:users,id|different:id',
            ]);

            $user = JWTAuth::user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }

            // Kiểm tra nếu đã có yêu cầu kết bạn hoặc đã là bạn bè
            $existingConnection = Connection::where(function ($query) use ($user, $request) {
                $query->where('senderId', $user->id)
                      ->where('receiverId', $request->receiver_id);
            })->orWhere(function ($query) use ($user, $request) {
                $query->where('senderId', $request->receiver_id)
                      ->where('receiverId', $user->id);
            })->first();

            if ($existingConnection) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Yêu cầu kết bạn đã tồn tại hoặc đã là bạn bè'
                ], 400);
            }

            $connection = Connection::create([
                'senderId' => $user->id,
                'receiverId' => $request->receiver_id,
                'status' => 'pending'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Đã gửi yêu cầu kết bạn',
                'data' => $connection
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in sendRequest: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi gửi yêu cầu kết bạn'
            ], 500);
        }
    }


    /**
     * Accept request
     */
    public function acceptRequest(Request $request, $connection_id)
    {
        try {
            $user = JWTAuth::user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }

            $connection = Connection::findOrFail($connection_id);

            if ($connection->receiverId !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bạn không có quyền chấp nhận yêu cầu này'
                ], 403);
            }

            $chatRoom = new ChatRoom();
            $chatRoom->save();

            $connection->update([
                'status' => 'accepted',
                'chatRoomId' => $chatRoom->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Kết bạn thành công',
                'data' => $connection
            ], 200);

        } catch (\Exception $e) {
            Log::error('Lỗi trong acceptRequest: ' . $e->getMessage(), [
                'connection_id' => $connection_id,
                'user_id' => $user->id ?? 'N/A'
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi chấp nhận kết bạn: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * decline request
     */

    public function declineRequest(Request $request, $connection_id)
    {
        try {
            $user = JWTAuth::user();

            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }

            $connection = Connection::findOrFail($connection_id);

            if ($connection->receiverId !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bạn không có quyền chấp nhận yêu cầu này'
                ], 403);
            }
           
            $connection->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Đã từ chối yêu cầu kết bạn'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in declineRequest: ' . $e->getMessage(),[
                'connection_id' => $connection_id,
                'user_id' => $user->id ?? 'N/A'
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi từ chối kết bạn' . $e->getMessage()
            ], 500);
        }
    }

     /**
     * cancel request
     */
    public function cancelRequest(Request $request)
    {
        try {
            $request->validate([
                'receiver_id' => 'required|exists:users,id|different:id',
            ]);
            
            $user = JWTAuth::user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }

            $connection = Connection::where('senderId', $user->id)
                                    ->where('receiverId', $request->receiver_id)
                                    ->where('status', 'pending')
                                    ->first();

            if (!$connection) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Không tìm thấy yêu cầu kết bạn'
                ], 404);
            }

            $connection->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Đã hủy yêu cầu kết bạn'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in cancelRequest: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi hủy yêu cầu kết bạn'
            ], 500);
        }      
    }

    /**
     * unfriend
     */

     public function removeFriend(Request $request)
    {
        try {
            $request->validate([
                'friend_id' => 'required|exists:users,id',
            ]);

            $user = JWTAuth::user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }

            $connection = Connection::where(function ($query) use ($user, $request) {
                $query->where('senderId', $user->id)
                      ->where('receiverId', $request->friend_id);
            })->orWhere(function ($query) use ($user, $request) {
                $query->where('senderId', $request->friend_id)
                      ->where('receiverId', $user->id);
            })->where('status', 'accepted')->first();

            if (!$connection) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Không tìm thấy kết nối bạn bè'
                ], 404);
            }

            $connection->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Đã xóa bạn thành công'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in removeFriend: ' . $e->getMessage(), [
                'user_id' => $user->id ?? 'N/A',
                'friend_id' => $request->friend_id ?? 'N/A'
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi xóa bạn: ' . $e->getMessage()
            ], 500);
        }
    }  
}
