<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User; // Import User model
use Illuminate\Validation\Rule; // Import Rule for validation
use App\Http\Controllers\Controller; // Import base Controller
use Illuminate\Support\Facades\Validator; // Import Validator facade
use App\Models\Report; // Import Report model (Giả định bạn có model này)

class AdminController extends Controller
{
    /**
     * Lấy danh sách tất cả người dùng (phân trang).
     */
    public function getAllUsers(Request $request): JsonResponse
    {
        // Có thể thêm logic lọc, sắp xếp ở đây nếu cần
        // $users = User::query();
        // if ($request->has('sort')) { ... }
        // if ($request->has('role')) { ... }

        $users = User::select('id', 'name', 'email', 'role', 'lock', 'active', 'created_at', 'photo') // Chọn các cột cần thiết
            ->paginate(15); // Phân trang

        return response()->json($users); // Trả về dữ liệu phân trang chuẩn của Laravel
    }

    /**
     * Xóa người dùng theo ID.
     */
    public function deleteUser(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Có thể thêm kiểm tra: không cho xóa chính mình hoặc admin khác?
        // if ($user->id === auth()->id()) { ... }
        // if ($user->role === 'admin') { ... }

        $user->delete(); // Thực hiện xóa mềm (nếu dùng SoftDeletes) hoặc xóa cứng

        return response()->json(null, 204); // 204 No Content - Thành công, không có nội dung trả về
    }

    /**
     * Khóa hoặc mở khóa người dùng.
     */
    public function lockUser(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // 'lock' phải là boolean (true/false, 1/0)
            'lock' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422); // Unprocessable Entity
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Cập nhật trạng thái lock (giả sử cột tên là 'lock' kiểu TINYINT hoặc BOOLEAN)
        $user->lock = $request->input('lock');
        $user->save();

        return response()->json([
            'message' => $request->input('lock') ? 'User locked successfully.' : 'User unlocked successfully.',
            'data' => [ // Trả về thông tin user đã cập nhật (chỉ các trường cần thiết)
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'lock' => $user->lock,
                'role' => $user->role,
            ]
        ]);
    }

    /**
     * Thay đổi vai trò của người dùng.
     */
    public function changeRole(Request $request, string $id): JsonResponse
    {
        // Lấy danh sách các role hợp lệ từ CSDL hoặc config (ví dụ)
        // Hoặc dùng Enum nếu bạn sử dụng PHP 8.1+
        $validRoles = ['user', 'admin']; // Ví dụ: Lấy từ ENUM trong DB hoặc config

        $validator = Validator::make($request->all(), [
            // 'role' phải tồn tại trong danh sách $validRoles
            'role' => ['required', Rule::in($validRoles)],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Cập nhật vai trò (giả sử cột tên là 'role')
        $user->role = $request->input('role');
        $user->save();

        return response()->json([
            'message' => 'User role updated successfully.',
            'data' => [ // Trả về thông tin user đã cập nhật
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'lock' => $user->lock,
                'role' => $user->role,
            ]
        ]);
    }

    /**
     * Lấy báo cáo kết nối (ví dụ).
     * Cần có model Report và cấu trúc bảng reports tương ứng.
     */
    public function getConnectionReports(Request $request): JsonResponse
    {
        try {
            $connections = Connection::query() // Start query on the Connection model
                ->select(
                    // Extract year from created_at and alias it as 'year'
                    DB::raw('YEAR(created_at) as year'),
                    // Extract month from created_at and alias it as 'month'
                    DB::raw('MONTH(created_at) as month'),
                    // Count the records in each group and alias it as 'total'
                    DB::raw('COUNT(*) as total')
                )
                // Group the results by the extracted year and month
                // Use groupByRaw for database functions
                ->groupByRaw('YEAR(created_at), MONTH(created_at)')
                // Sort the results first by year ascending
                ->orderBy('year', 'asc')
                // Then sort by month ascending within each year
                ->orderBy('month', 'asc')
                // Execute the query and retrieve the results as a collection
                ->get();

            // Return the results as a JSON response
            return response()->json($connections);
        } catch (\Exception $error) {
            // Log the error for debugging purposes
            Log::error('Error fetching total connections per month: ' . $error->getMessage(), [
                'exception' => $error // Optionally log the full exception trace
            ]);

            // Return a generic error response to the client
            return response()->json([
                'message' => 'An error occurred while retrieving connection statistics.',
                // Optionally include error details in non-production environments
                // 'error' => $error->getMessage()
            ], 500); // Internal Server Error status code
        }
    }
}
