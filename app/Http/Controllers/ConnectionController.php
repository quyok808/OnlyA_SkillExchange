<?php

namespace App\Http\Controllers; // Điều chỉnh namespace nếu cần

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Cho transaction
use Illuminate\Validation\ValidationException; // Xử lý lỗi validate thủ công nếu cần
use Symfony\Component\HttpKernel\Exception\HttpException; // Cho abort()

class ConnectionController extends Controller
{
    /**
     * Gửi yêu cầu kết nối.
     * POST /connections
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'receiverId' => 'required|exists:users,id', // Đảm bảo receiver tồn tại
        ]);

        $senderId = Auth::id();
        $receiverId = $validated['receiverId'];

        if ($senderId == $receiverId) {
            return response()->json(['message' => 'Không thể kết nối với chính mình!'], 400);
        }

        // Kiểm tra kết nối đã tồn tại (bất kể trạng thái)
        $existingConnection = Connection::where(function ($query) use ($senderId, $receiverId) {
            $query->where('senderId', $senderId)->where('receiverId', $receiverId);
        })->orWhere(function ($query) use ($senderId, $receiverId) {
            $query->where('senderId', $receiverId)->where('receiverId', $senderId);
        })->exists(); // Dùng exists() hiệu quả hơn first() nếu chỉ cần kiểm tra

        if ($existingConnection) {
            return response()->json(['message' => 'Kết nối hoặc yêu cầu kết nối đã tồn tại!'], 400);
        }

        $newConnection = Connection::create([
            'senderId' => $senderId,
            'receiverId' => $receiverId,
            'status' => 'pending',
        ]);

        // Có thể trả về ConnectionResource nếu bạn dùng API Resources
        return response()->json([
            'status' => 'success',
            'data' => $newConnection,
        ], 201);
    }

    /**
     * Chấp nhận yêu cầu kết nối.
     * PATCH /connections/{connection}/accept
     */
    public function accept(Request $request, Connection $connection) // Route model binding
    {
        $currentUserId = Auth::id();

        // Kiểm tra người nhận có phải là user hiện tại không
        if ($connection->receiverId != $currentUserId) {
            abort(403, 'Không có quyền xử lý yêu cầu này!');
        }

        // Kiểm tra trạng thái
        if ($connection->status !== 'pending') {
            abort(400, 'Yêu cầu này không ở trạng thái chờ hoặc đã được xử lý!');
        }

        try {
            // Sử dụng transaction để đảm bảo cả hai thao tác thành công hoặc không
            DB::beginTransaction();

            // 1. Tạo phòng chat
            $chatRoom = ChatRoom::create(); // Tạo phòng trống trước
            // Gắn participants vào phòng chat
            $chatRoom->participants()->attach([$connection->senderId, $connection->receiverId]);

            // 2. Cập nhật Connection
            $connection->status = 'accepted';
            $connection->chatRoomId = $chatRoom->id;
            $connection->save();

            DB::commit(); // Hoàn tất transaction

            // Lấy thông tin phòng chat với participants để trả về (tương tự populate)
            // Chỉ lấy các trường cần thiết của participants
            $chatRoom->load('participants:id,name,email');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'connectionId' => $connection->id,
                    'senderId' => $connection->senderId,
                    'receiverId' => $connection->receiverId,
                    'chat' => $chatRoom, // Gửi cả object ChatRoom đã load participants
                    'chatRoomId' => $chatRoom->id,
                    'status' => 'accepted',
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack(); // Hoàn tác nếu có lỗi
            // Ghi log lỗi ở đây nếu cần: Log::error($e);
            // abort(500, 'Đã xảy ra lỗi khi chấp nhận yêu cầu.');
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hủy kết nối đã được chấp nhận.
     * DELETE /connections/disconnect
     */
    public function disconnect(Request $request)
    {
        $validated = $request->validate([
            'userId' => 'required|integer|exists:users,id',
        ]);

        $currentUserId = Auth::id();
        $otherUserId = $validated['userId'];

        $connection = Connection::where('status', 'accepted')
            ->where(function ($query) use ($currentUserId, $otherUserId) {
                $query->where(['senderId' => $currentUserId, 'receiverId' => $otherUserId])
                    ->orWhere(['senderId' => $otherUserId, 'receiverId' => $currentUserId]);
            })
            ->first(); // Dùng first() để lấy object connection

        if (!$connection) {
            abort(404, 'Không tìm thấy kết nối đã được chấp nhận giữa hai người dùng này!');
        }

        try {
            DB::beginTransaction();

            // Xóa phòng chat liên quan (nếu có)
            if ($connection->chatRoomId) {
                // Cẩn thận: Cân nhắc xem có nên xóa phòng chat hay chỉ xóa connection
                // Nếu phòng chat có thể dùng cho việc khác, không nên xóa
                // Giả sử xóa theo logic gốc:
                ChatRoom::destroy($connection->chatRoomId);
                // Hoặc nếu muốn giữ chat room:
                // $connection->chatRoomId = null;
                // $connection->save();
            }

            // Xóa connection
            $connection->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'data' => [ // Trả về data object như gốc
                    'message' => 'Kết nối đã được hủy thành công!',
                ],
            ]);
            // Hoặc trả về 204 No Content nếu không cần message
            // return response()->noContent();

        } catch (\Exception $e) {
            DB::rollBack();
            // Log::error($e);
            abort(500, 'Đã xảy ra lỗi khi hủy kết nối.');
        }
    }

    /**
     * Từ chối (xóa) yêu cầu kết nối đang chờ.
     * DELETE /connections/{connection}/reject
     */
    public function reject(Request $request, Connection $connection) // Route model binding
    {
        $currentUserId = Auth::id();

        // Chỉ người nhận mới được từ chối
        if ($connection->receiverId != $currentUserId) {
            abort(403, 'Bạn không có quyền từ chối yêu cầu này!');
        }

        // Chỉ từ chối yêu cầu đang chờ
        if ($connection->status !== 'pending') {
            abort(400, 'Không thể từ chối yêu cầu không ở trạng thái chờ.');
        }

        // Logic gốc là xóa luôn, không chuyển sang 'rejected'
        $deleted = $connection->delete();

        if ($deleted) {
            // Trả về connection đã bị xóa như logic gốc
            return response()->json([
                'status' => 'success',
                'message' => 'Yêu cầu kết nối đã bị xóa.',
                'data' => [
                    'connection' => $connection, // Trả về object đã bị xóa
                ],
            ]);
        } else {
            abort(500, 'Không thể xóa yêu cầu kết nối.');
        }

        // *** LƯU Ý ***: Nếu bạn muốn triển khai trạng thái 'rejected' và cron job
        // thì thay vì ->delete(), bạn sẽ làm:
        // $connection->status = 'rejected';
        // $connection->rejected_at = now(); // Thêm field rejected_at vào model/migration
        // $connection->save();
        // Và response sẽ khác đi. Cron job sẽ được tạo riêng.
    }

    /**
     * Lấy danh sách tất cả connection liên quan đến người dùng hiện tại.
     * GET /connections
     */
    public function index(Request $request)
    {
        $currentUserId = Auth::id();
        $connections = Connection::where('senderId', $currentUserId)
            ->orWhere('receiverId', $currentUserId)
            ->with([
                'sender:id,name,email,phone', // Chỉ định rõ các trường cần lấy
                'receiver:id,name,email,phone' // Bổ sung address, skills nếu cần và có relationship
                // 'sender.skills:id,name' // Nếu cần load skills của sender
            ])
            ->latest() // Sắp xếp theo thời gian tạo mới nhất (tùy chọn)
            ->get();

        // Có thể dùng API Resource Collection ở đây
        return response()->json([
            'status' => 'success',
            'data' => [
                'connections' => $connections, // Đổi tên key nếu muốn giống gốc (pendingRequests)
            ],
        ]);
    }

    /**
     * Lấy danh sách yêu cầu đang chờ (mình gửi hoặc người khác gửi cho mình).
     * GET /connections/pending
     */
    public function pending(Request $request)
    {
        $currentUserId = Auth::id();
        $pendingRequests = Connection::where('status', 'pending')
            ->where(function ($query) use ($currentUserId) {
                $query->where('senderId', $currentUserId)
                    ->orWhere('receiverId', $currentUserId);
            })
            ->with(['sender:id,name,email', 'receiver:id,name,email'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'pendingRequests' => $pendingRequests,
            ],
        ]);
    }

    /**
     * Lấy danh sách kết nối đã chấp nhận.
     * GET /connections/accepted
     */
    public function accepted(Request $request)
    {
        $currentUserId = Auth::id();
        $acceptedConnections = Connection::where('status', 'accepted')
            ->where(function ($query) use ($currentUserId) {
                $query->where('senderId', $currentUserId)
                    ->orWhere('receiverId', $currentUserId);
            })
            ->with(['sender:id,name,email', 'receiver:id,name,email'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'acceptedConnections' => $acceptedConnections, // Đổi tên key nếu muốn
            ],
        ]);
    }


    /**
     * Kiểm tra trạng thái kết nối với một người dùng khác.
     * GET /connections/status/{user}
     */
    public function status(Request $request, $userID) // Route model binding cho user kia
    {
        $currentUserId = Auth::id();
        $otherUserId = $userID; // Lấy id từ User model đã được bind

        if ($currentUserId == $otherUserId) {
            // Hoặc trả về trạng thái đặc biệt nếu cần
            return response()->json(['status' => 'self', 'connectionId' => null, 'chatRoomId' => null]);
        }


        $connection = Connection::where(function ($query) use ($otherUserId) {
            $query->where(['senderId' => $otherUserId, 'receiverId' => $otherUserId])
                ->orWhere(['senderId' => $otherUserId, 'receiverId' => $otherUserId]);
        })
            ->first(); // Lấy bản ghi connection nếu có

        if (!$connection) {
            return response()->json(['status' => 'none', 'connectionId' => null, 'chatRoomId' => null]);
        }

        $status = 'unknown'; // Trạng thái mặc định
        $received = false;   // Yêu cầu này có phải do user hiện tại nhận không?
        $chatRoomId = null;

        switch ($connection->status) {
            case 'pending':
                $received = ($connection->receiverId == $currentUserId);
                $status = $received ? 'pending_received' : 'pending_sent';
                break;
            case 'accepted':
                $status = 'connected'; // Hoặc 'accepted' tùy theo ý bạn
                $chatRoomId = $connection->chatRoomId;
                $received = ($connection->receiverId == $currentUserId); // Vẫn xác định ai là người nhận ban đầu
                break;
            case 'rejected': // Nếu bạn có dùng trạng thái này
                $status = 'rejected';
                $received = ($connection->receiverId == $currentUserId);
                break;
        }

        return response()->json([
            'status' => $status,
            'connectionId' => $connection->id,
            'received' => $received, // Thêm thông tin này như trong code gốc
            'chatRoomId' => $chatRoomId,
        ]);
    }

    /**
     * Hủy yêu cầu kết nối đã gửi đi (chưa được chấp nhận/từ chối).
     * DELETE /connections/cancel/{receiver}
     */
    public function cancel(Request $request, User $receiver) // Route model binding cho receiver
    {
        $currentUserId = Auth::id();
        $receiverId = $receiver->id;

        // Tìm và xóa yêu cầu 'pending' mà user hiện tại đã gửi cho receiver
        $deletedCount = Connection::where('senderId', $currentUserId)
            ->where('receiverId', $receiverId)
            ->where('status', 'pending')
            ->delete(); // Trả về số lượng bản ghi đã xóa

        if ($deletedCount === 0) {
            // Không tìm thấy yêu cầu phù hợp để hủy
            abort(404, 'Không tìm thấy yêu cầu kết nối đang chờ để hủy!');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đã hủy yêu cầu kết nối!',
        ]);
        // Hoặc return response()->noContent(); // 204
    }
}
