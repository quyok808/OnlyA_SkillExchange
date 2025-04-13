<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Skill;
use App\Utils\APIFeatures;
use Illuminate\Http\Request;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateMeRequest;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function register(RegisterRequest $request)
    {
        try {
            $result = $this->userService->register($request->validated());
            return response()->json([
                'status' => 'success',
                'data' => $result
            ], 201);
        } catch (\Exception $e) {
            $statusCode = (int) $e->getCode();
            if ($statusCode < 100 || $statusCode > 599) {
                $statusCode = 400;
                Log::warning('Invalid HTTP status code from exception: ' . $e->getCode() . '. Using 400 instead.');
            }

            Log::error('Registration error: ' . $e->getMessage(), ['code' => $statusCode]);
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    public function login(LoginRequest $request)
    {
        try {
            $token = $this->userService->login($request->validated());
            return response()->json(['status' => 'success', 'token' => $token], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getAllUsers(Request $request)
    {
        try {
            $result = $this->userService->getAllUsers($request->all());
            return response()->json([
                'status' => 'success',
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function verifyEmail($token)
    {
        try {
            $this->userService->verifyEmail($token);
            // return response()->json(['status' => 'success', 'message' => 'Email verified successfully.'], 200);
            return redirect('http://localhost:5173/');
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function me()
    {
        try {
            $user = $this->userService->me();
            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => $user
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ], 400);
        }
    }

    public function logout()
    {
        try {
            $this->userService->logout();
            return response()->json(['status' => 'success', 'message' => 'Logout successful.'], 200);
        } catch (JWTException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function uploadAvatar(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $photoUrl = $this->userService->uploadAvatar($user, $request); // Truyền $request

            return response()->json(['status' => 'success', 'message' => 'Photo uploaded successfully.', 'photo_url' => $photoUrl], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $email = $request->email;

        $this->userService->sendResetPasswordEmail($email, 'http', 'localhost:5008');

        return response()->json(['status' => 'success', 'message' => 'Reset password email sent.'], 200);
    }

    public function resetPassword(Request $request, string $token)
    {
        $request->validate([
            'password' => 'required|min:6',
            'confirmPassword' => 'required|same:password',
        ]);

        $this->userService->resetPassword($request->password, $token);

        return response()->json(['message' => 'Password reset successfully.'], 200);
    }

    /**
     * Update the authenticated user's information.
     */
    public function updateMe(UpdateMeRequest $request) // Use Form Request for validation
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $updatedUser = $this->userService->updateMe($user, $request->validated());
            return response()->json([
                'status' => 'success',
                'message' => 'User updated successfully.',
                'data' => $updatedUser
            ], 200);
        } catch (\Exception $e) {
            Log::error('Update Me error: ' . $e->getMessage());
            $statusCode = ($e->getCode() >= 100 && $e->getCode() < 600) ? $e->getCode() : 400;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(ChangePasswordRequest $request) // Use Form Request
    {
        try {
            $user = auth('api')->user();
            $this->userService->changePassword($user, $request->validated());
            return response()->json([
                'status' => 'success',
                'message' => 'Password changed successfully.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Change Password error: ' . $e->getMessage());
            $statusCode = ($e->getCode() >= 100 && $e->getCode() < 600) ? $e->getCode() : 400;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * Get user information by ID.
     */
    public function getUserById($id)
    {
        try {
            $user = $this->userService->getUserById($id);
            return response()->json([
                'status' => 'success',
                'data' => $user
            ], 200);
        } catch (ModelNotFoundException $e) {
            Log::warning('User not found for ID: ' . $id);
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get User by ID error: ' . $e->getMessage());
            $statusCode = ($e->getCode() >= 100 && $e->getCode() < 600) ? $e->getCode() : 500;
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while retrieving the user.'
            ], $statusCode);
        }
    }

    public function getIDUsers()
    {
        $user = auth('api')->user();
        $connections = $this->userService->getRelatedUserIds($user->id);
        return response()->json([
            'status' => 'success',
            'data' => $connections
        ], 200);
    }

    /**
     * Search for users.
     */
    public function searchUser(Request $request): JsonResponse
    {
        try {
            // 1. Get all query parameters from the request
            $query = $request->query();

            // 2. Prepare the query array for the service
            $serviceQuery = $query; // Start with all request query params

            // Add the current user ID for exclusion if authenticated
            $currentUser = Auth::guard('api')->user(); // Use the specific guard if needed
            if ($currentUser) {
                $serviceQuery['exclude_user_id'] = $currentUser->id;
            }

            $result = $this->userService->searchUsers($serviceQuery);
            if (isset($result['status']) && $result['status'] === 'success_empty') {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No skills found matching the criteria, resulting in no users.',
                    'users' => [],
                    'totalUsers' => 0,
                    'totalPages' => 0,
                    'page' => 1,
                    'limit' => $result['limit'] ?? $request->query('limit', 10),
                ]);
            }
            return response()->json([
                'status' => 'success',
                'users' => $result['users'],
                'totalUsers' => $result['totalUsers'],
                'totalPages' => $result['totalPages'],
                'page' => $result['page'],
                'limit' => $result['limit'],
            ]);
        } catch (\Exception $error) {
            Log::error('User search failed: ' . $error->getMessage(), [
                'exception' => $error
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi tìm kiếm người dùng: ' . $error->getMessage()
            ], 500);
        }
    }

    /**
     * Search for users within the network (requires definition of "network").
     * NOTE: Currently implemented same as searchUser. Define network logic in UserService.
     */
    public function searchUserInNetwork(Request $request)
    {
        try {
            $user = auth('api')->user();
            $results = $this->userService->searchUsersInNetwork($user->id, $request->query());
            return response()->json([
                'status' => 'success',
                'data' => $results
            ], 200);
        } catch (\Exception $e) {
            Log::error('Search User in Network error: ' . $e->getMessage());
            $statusCode = ($e->getCode() >= 100 && $e->getCode() < 600) ? $e->getCode() : 400;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * Add skills to the authenticated user.
     */
    public function addSkillToUser(Request $request)
    {
        try {
            $userId = auth('api')->user()->id;
            $skillData = $request->all(); // Lấy toàn bộ dữ liệu từ request
            $skillList = $skillData['skills'] ?? [];

            // Kiểm tra skillList có phải là array không
            if (!is_array($skillList)) {
                throw new \Error('skillData.skills must be an array', 400);
            }

            // Tìm user
            $user = User::find($userId);
            if (!$user) {
                throw new \Error('User not found', 404);
            }

            // 1) Xử lý danh sách kỹ năng gửi lên
            $skillIds = [];
            foreach ($skillList as $skillItem) {
                $skillName = null;

                // Nếu là object, chỉ lấy name; nếu là string, dùng trực tiếp
                if (is_array($skillItem) && isset($skillItem['name'])) {
                    $skillName = $skillItem['name'];
                } elseif (is_string($skillItem)) {
                    $skillName = $skillItem;
                } else {
                    continue;
                }

                // Kiểm tra xem skillName có phải là ObjectId không (24 ký tự hex)
                if (preg_match('/^[0-9a-fA-F]{24}$/', $skillName)) {
                    continue;
                }

                // Tìm hoặc tạo skill
                $skill = Skill::whereRaw('LOWER(name) = ?', [strtolower($skillName)])->first();
                if (!$skill) {
                    $skill = Skill::create(['name' => $skillName]);
                }
                $skillIds[] = $skill->id;
            }

            // 2) Cập nhật danh sách skills cho user
            $user->skills()->sync($skillIds);

            // 3) Trả về user đã cập nhật với skills được load
            $updatedUser = User::with('skills')
                ->select(['id', 'name', 'email'])
                ->find($userId);

            return $updatedUser;
        } catch (\Exception $error) {
            throw $error;
        }
    }

    public function getProfileImage()
    {
        $user = auth('api')->user();
        return response()->json([
            'status' => 'success',
            'data' => [
                'image' => asset('storage/' . $user->photo)
            ]
        ], 200);
    }

    public function getProfileImageById($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.'
            ], 404);
        }
        return response()->json([
            'status' => 'success',
            'data' => [
                'image' => asset('storage/' . $user->photo)
            ]
        ], 200);
    }

    public function getRelatedUserIds()
    {
        $user = auth('api')->user();
        $userIds = $this->userService->getRelatedUserIds($user->id);
        return response()->json([
            'status' => 'success',
            'data' => $userIds
        ], 200);
    }

    public function getNameById($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.'
            ], 404);
        }
        return response()->json([
            'status' => 'success',
            'data' => [
                'name' => $user->name
            ]
        ], 200);
    }
}
