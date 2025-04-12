<?php

namespace App\Http\Controllers;

// --- Đầy đủ Use Statements ---
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentStatusRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\Rule;
// -----------------------------

class AppointmentController extends Controller
{
    /**
     * POST /api/appointments
     * Tương đương: Node exports.createAppointment + CreateAppointmentHandler
     */
    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $validated = $request->validated(); // Dữ liệu vào key camelCase

        // Chuẩn bị dữ liệu để tạo (thêm senderId)
        $dataToCreate = ['senderId' => Auth::id()] + $validated;

        // --- Logic Validation & Conflict Check từ Handler ---
        // Không tự đặt lịch
        if ($dataToCreate['senderId'] == $dataToCreate['receiverId']) {
            return response()->json(['status' => 'error', 'message' => 'Không thể tự lên lịch học với chính mình'], Response::HTTP_BAD_REQUEST); // 400
        }

        // Thời gian hợp lệ
        $startTime = Carbon::parse($dataToCreate['startTime']); // Dùng Carbon để so sánh
        $endTime = Carbon::parse($dataToCreate['endTime']);
        if ($startTime->gte($endTime)) { // Kiểm tra startTime >= endTime
            return response()->json(['status' => 'error', 'message' => 'Thời gian bắt đầu phải nhỏ hơn thời gian kết thúc'], Response::HTTP_BAD_REQUEST); // 400
        }

        // Kiểm tra conflict (Query dùng tên cột camelCase)
        $conflict = Appointment::whereIn('status', ['pending', 'accepted'])
            ->where(function ($query) use ($dataToCreate) {
                $query->where('senderId', $dataToCreate['senderId'])
                    ->orWhere('receiverId', $dataToCreate['senderId'])
                    ->orWhere('senderId', $dataToCreate['receiverId'])
                    ->orWhere('receiverId', $dataToCreate['receiverId']);
            })
            ->where(function ($query) use ($startTime, $endTime) {
                // Điều kiện thời gian trùng lặp: (start1 < end2) AND (end1 > start2)
                $query->where('startTime', '<', $endTime) // <-- Query camelCase
                    ->where('endTime', '>', $startTime); // <-- Query camelCase
            })
            ->exists(); // Chỉ cần biết có tồn tại hay không

        if ($conflict) {
            // Trả về lỗi 409 Conflict giống Node.js
            return response()->json(['status' => 'error', 'message' => 'Thời gian này tớ đang bận, vui lòng chọn thời gian khác nhé!'], 409); // 409
        }
        // --- Kết thúc Conflict Check ---

        // Tạo appointment dùng Mass Assignment (key camelCase khớp $fillable)
        // Laravel sẽ dùng $casts để xử lý 'startTime', 'endTime' thành đối tượng DateTime khi lưu
        $newAppointment = Appointment::create($dataToCreate);
        return response()->json([
            'status' => 'success', // status ở ngoài cùng
            'data' => [
                // Dữ liệu từ Resource được đặt vào key 'appointment'
                'appointment' => new AppointmentResource($newAppointment->fresh())
            ]
        ], 201);
    }

    /**
     * GET /api/appointments/{appointment}
     */
    public function show(Appointment $appointment): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'appointment' => new AppointmentResource($appointment)
            ]
        ]); // Mặc định 200 OK
    }

    /**
     * DELETE /api/appointments/{appointment}
     * Tương đương: Node exports.deleteAppointment + DeleteAppointmentHandler
     */
    public function destroy(Appointment $appointment): JsonResponse
    {
        // Kiểm tra quyền hạn (ví dụ: chỉ sender hoặc receiver được xóa)
        $userId = Auth::id();
        if ($appointment->senderId != $userId && $appointment->receiverId != $userId) { // Truy cập camelCase
            return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền xóa lịch hẹn này.'], Response::HTTP_FORBIDDEN); // 403
        }
        // Optional: Kiểm tra trạng thái trước khi xóa (ví dụ: không cho xóa nếu đã 'accepted'?)

        $appointment->delete();

        // Trả về 204 No Content, không cần body
        return response()->json(null, Response::HTTP_NO_CONTENT); // 204
    }

    /**
     * GET /api/appointments/my
     * Tương đương: Node exports.getMyAppointments + GetMyAppointmentsHandler
     */
    public function myAppointments(Request $request): JsonResponse
    {
        $userId = Auth::id();

        // Query dùng tên cột camelCase
        $appointments = Appointment::where(function ($query) use ($userId) {
            $query->where('senderId', $userId)      // <-- Query camelCase
                ->orWhere('receiverId', $userId); // <-- Query camelCase
        })
            ->orderBy('created_at', 'desc') // Sắp xếp theo thời gian tạo giảm dần
            ->get();

        // Trả về collection resource, cần tùy chỉnh cấu trúc response
        // Cách 1: Dùng response() trực tiếp
        return response()->json([
            'status' => 'success',
            'data' => [
                // Kết quả từ collection được đặt vào key 'appointments'
                'appointments' => AppointmentResource::collection($appointments)
            ]
        ]);
    }

    /**
     * PATCH /api/appointments/{appointment}/status
     */
    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validated(); // ['status' => 'accepted/rejected/canceled']
        $newStatus = $validated['status'];
        $userId = Auth::id();

        // --- Logic Authorization từ Handler ---
        if (in_array($newStatus, ['accepted', 'rejected'])) {
            // Chỉ receiver mới được accept/reject
            // if ($appointment->receiverId != $userId) { // <-- camelCase
            //     return response()->json(['status' => 'error', 'message' => 'Bạn không được phép thực hiện hành động này.'], Response::HTTP_FORBIDDEN); // 403
            // }
            // if ($appointment->senderId != $userId) {
            //     return response()->json(['status' => 'error', 'message' => 'Bạn không được phép thực hiện hành động này.'], Response::HTTP_FORBIDDEN); // 403
            // }
            // Chỉ accept/reject được khi đang 'pending'
            if ($appointment->status !== 'pending') {
                return response()->json(['status' => 'error', 'message' => 'Không thể chấp nhận/từ chối lịch hẹn không ở trạng thái chờ.'], Response::HTTP_BAD_REQUEST); // 400
            }
        } elseif ($newStatus === 'canceled') {
            // Cả sender và receiver đều được cancel
            if ($appointment->senderId != $userId && $appointment->receiverId != $userId) { // <-- camelCase
                return response()->json(['status' => 'error', 'message' => 'Bạn không được phép thực hiện hành động này.'], Response::HTTP_FORBIDDEN); // 403
            }
            // Có thể cancel từ 'pending' hoặc 'accepted'
            if (!in_array($appointment->status, ['pending', 'accepted'])) {
                return response()->json(['status' => 'error', 'message' => 'Không thể hủy lịch hẹn ở trạng thái này.'], Response::HTTP_BAD_REQUEST); // 400
            }
        }
        // --- Hết Logic Authorization ---

        // Cập nhật status và lưu
        $appointment->status = $newStatus;
        $appointment->save();

        // --- Trả về response giống Node.js khi xác nhận ---
        // { "status": "success", "data": {} }
        return response()->json([
            'status' => 'success',
            'data' => new \stdClass() // Tạo object rỗng {} trong PHP
        ]); // Mặc định 200 OK
    }
}
