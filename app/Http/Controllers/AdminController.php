<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use App\Models\Report;

class AdminController extends Controller
{
    /**
     * Lấy danh sách tất cả người dùng (phân trang).
     */
    public function getAllUsers(Request $request): JsonResponse
    {

        try {
            $perPage = $request->query('limit', 15);
            $sortParam = $request->query('sort', 'created_at');
            $filters = $request->query('filter', []);

            $query = User::query();

            $query->select([
                'id',
                'name',
                'email',
                'role',
                'address',
                'phone',
                'lock',
                'active',
                'created_at',
                'photo'
            ]);

            $query->withCount([
                'createdReports as createdReportCount',
                'receivedReports as reportCount'
            ]);

            if (!empty($filters)) {
                if (isset($filters['name'])) {
                    $query->where('name', 'like', '%' . $filters['name'] . '%');
                }
                if (isset($filters['email'])) {
                    $query->where('email', $filters['email']);
                }

                if (isset($filters['role'])) {
                    $query->where('role', $filters['role']);
                }
                if (isset($filters['lock']) && $filters['lock'] !== '') {
                    $lockValue = filter_var($filters['lock'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($lockValue !== null) {
                        $query->where('lock', $lockValue);
                    }
                }
                if (isset($filters['active']) && $filters['active'] !== '') {
                    $activeValue = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($activeValue !== null) {
                        $query->where('active', $activeValue);
                    }
                }
            }


            $sortField = ltrim($sortParam, '-');
            $sortDirection = Str::startsWith($sortParam, '-') ? 'desc' : 'asc';

            $allowedSorts = [
                'id',
                'name',
                'email',
                'role',
                'lock',
                'active',
                'created_at',
                'createdReportCount',
                'reportCount'
            ];
            if (!in_array($sortField, $allowedSorts)) {
                $sortField = 'created_at';
                $sortDirection = 'asc';
            }
            $query->orderBy($sortField, $sortDirection);

            $paginator = $query->paginate($perPage);

            return response()->json($paginator);
        } catch (\Exception $error) {
            Log::error('Lỗi khi lấy danh sách người dùng: ' . $error->getMessage(), [
                'exception' => $error,
                'request_data' => $request->all()
            ]);
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi truy vấn danh sách người dùng.',
            ], 500);
        }
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

        $user->delete();

        return response()->json(null, 204);
    }

    /**
     * Khóa hoặc mở khóa người dùng.
     */
    public function lockUser(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lock' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422); // Unprocessable Entity
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->lock = $request->input('lock');
        $user->save();

        return response()->json([
            'message' => $request->input('lock') ? 'User locked successfully.' : 'User unlocked successfully.',
            'data' => [
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
        $validRoles = ['user', 'admin'];

        $validator = Validator::make($request->all(), [
            'role' => ['required', Rule::in($validRoles)],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->role = $request->input('role');
        $user->save();

        return response()->json([
            'message' => 'User role updated successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'lock' => $user->lock,
                'role' => $user->role,
            ]
        ]);
    }

    /**
     * Lấy báo cáo kết nối.
     */
    public function getConnectionReports(Request $request): JsonResponse
    {
        try {
            $connections = Connection::query()
                ->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as total')
                )
                ->groupByRaw('YEAR(created_at), MONTH(created_at)')
                ->orderBy('year', 'asc')
                ->orderBy('month', 'asc')
                ->get();

            return response()->json(['status' => 'success', 'data' => $connections], 200);
        } catch (\Exception $error) {
            Log::error('Error fetching total connections per month: ' . $error->getMessage(), [
                'exception' => $error
            ]);

            return response()->json([
                'message' => 'An error occurred while retrieving connection statistics.',
            ], 500);
        }
    }
}
