<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentStatusRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;
// -----------------------------

class AppointmentController extends Controller
{
    /**
     * POST /api/appointments
     */
    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $dataToCreate = ['senderId' => Auth::id()] + $validated;

        if ($dataToCreate['senderId'] == $dataToCreate['receiverId']) {
            return response()->json(['status' => 'error', 'message' => 'Không thể tự lên lịch học với chính mình'], Response::HTTP_BAD_REQUEST); // 400
        }

        $startTime = Carbon::parse($dataToCreate['startTime']);
        $endTime = Carbon::parse($dataToCreate['endTime']);
        $now = Carbon::now();
        if ($startTime->gte($endTime)) {
            return response()->json(['status' => 'error', 'message' => 'Thời gian bắt đầu phải nhỏ hơn thời gian kết thúc'], Response::HTTP_BAD_REQUEST); // 400
        }
        if ($startTime->lt($now) || $endTime->lt($now)) {
            return response()->json(['status' => 'error', 'message' => 'Không thể bắt đầu tại quá khứ được'], Response::HTTP_BAD_REQUEST); // 400
        }

        $conflict = Appointment::whereIn('status', ['pending', 'accepted'])
            ->where(function ($query) use ($dataToCreate) {
                $query->where('senderId', $dataToCreate['senderId'])
                    ->orWhere('receiverId', $dataToCreate['senderId'])
                    ->orWhere('senderId', $dataToCreate['receiverId'])
                    ->orWhere('receiverId', $dataToCreate['receiverId']);
            })
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('startTime', '<', $endTime)
                    ->where('endTime', '>', $startTime);
            })
            ->exists();

        if ($conflict) {
            return response()->json(['status' => 'error', 'message' => 'Thời gian này tớ đang bận, vui lòng chọn thời gian khác nhé!'], 409); // 409
        }

        // Tạo appointment dùng Mass Assignment (key camelCase khớp $fillable)
        $newAppointment = Appointment::create($dataToCreate);
        return response()->json([
            'status' => 'success',
            'data' => [
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
        ]);
    }

    /**
     * DELETE /api/appointments/{appointment}
     */
    public function destroy(Appointment $appointment): JsonResponse
    {
        $userId = Auth::id();
        if ($appointment->senderId != $userId && $appointment->receiverId != $userId) { // Truy cập camelCase
            return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền xóa lịch hẹn này.'], Response::HTTP_FORBIDDEN); // 403
        }

        $appointment->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT); // 204
    }

    /**
     * GET /api/appointments/my
     */
    public function myAppointments(Request $request): JsonResponse
    {
        $userId = Auth::id();

        $appointments = Appointment::where(function ($query) use ($userId) {
            $query->where('senderId', $userId)
                ->orWhere('receiverId', $userId);
        })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'appointments' => AppointmentResource::collection($appointments)
            ]
        ]);
    }

    /**
     * PUT /api/appointments/{appointment}/status
     */
    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validated();
        $newStatus = $validated['status'];
        $userId = Auth::id();

        if (in_array($newStatus, ['accepted', 'rejected'])) {
            if ($appointment->status !== 'pending') {
                return response()->json(['status' => 'error', 'message' => 'Không thể chấp nhận/từ chối lịch hẹn không ở trạng thái chờ.'], Response::HTTP_BAD_REQUEST); // 400
            }
        } elseif ($newStatus === 'canceled') {
            if ($appointment->senderId != $userId && $appointment->receiverId != $userId) { // <-- camelCase
                return response()->json(['status' => 'error', 'message' => 'Bạn không được phép thực hiện hành động này.'], Response::HTTP_FORBIDDEN); // 403
            }
            if (!in_array($appointment->status, ['pending', 'accepted'])) {
                return response()->json(['status' => 'error', 'message' => 'Không thể hủy lịch hẹn ở trạng thái này.'], Response::HTTP_BAD_REQUEST); // 400
            }
        }
        $appointment->status = $newStatus;
        $appointment->save();

        return response()->json([
            'status' => 'success',
            'data' => new \stdClass()
        ]);
    }
}
