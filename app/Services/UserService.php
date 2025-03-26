<?php

namespace App\Services;

use Exception;
use App\Models\User;
use App\Utils\APIFeatures;
use App\Models\BlacklistedToken;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserService
{
    public function register(array $data)
    {
        try {
            if (!($data['password'] == $data['confirmPassword'])) {
                throw new \Exception('Passwords do not match.', 400);
            }
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'active' => false,
                'photo' => 'defaultAvatar.jpg',
                'lock' => false,
                'skill' => [],
                'role' => 'user', // Thêm role mặc định
            ]);

            // Gọi sendVerificationEmail
            $this->sendVerificationEmail($user->id, 'http', 'localhost:5008');
            return ['user' => $user];
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                throw new \Exception('Email đã tồn tại.', 409);
            }
            throw $e;
        }
    }

    public function login(array $data)
    {
        if (!isset($data['email']) || !isset($data['password'])) {
            throw new \Exception('Bạn cần phải điền đầy đủ thông tin.', 400);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Không đúng định dạng email mong muốn.', 401);
        }

        $user = User::where('email', $data['email'])->first();
        if (!$user || !$user->comparePassword($data['password'])) {
            throw new \Exception('Sai email hoặc mật khẩu.', 401);
        }

        if (!$user->active) {
            throw new \Exception('Bạn cần xác thực email trước khi đăng nhập.', 403);
        }

        if ($user->lock) {
            throw new \Exception('Tài khoản của bạn bị khoá.', 403);
        }

        return JWTAuth::fromUser($user);
    }

    public function getAllUsers($query)
    {
        try {
            // Lấy user hiện tại từ token JWT
            $currentUser = JWTAuth::parseToken()->authenticate();

            // Kiểm tra vai trò
            if ($currentUser->role !== 'admin') {
                throw new \Exception('Tài khoản của bạn không đủ quyền để làm điều này', 403);
            }

            // Logic lấy tất cả users (giữ nguyên)
            $features = new APIFeatures(User::with('skills'), $query);
            $users = $features->filter()->sort()->paginate()->getQuery()->get();
            $totalUsers = User::count();
            $totalPages = ceil($totalUsers / ($query['limit'] ?? 10));
            $page = $query['page'] ?? 1;
            $limit = $query['limit'] ?? 10;

            // Format lại dữ liệu để phù hợp với response mong muốn
            $formattedUsers = $users->map(function ($user) {
                return [
                    '_id' => $user->id, // Thay 'id' bằng '_id'
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'photo' => $user->photo,
                    'active' => $user->active,
                    'createdAt' => $user->created_at,
                    'updatedAt' => $user->updated_at,
                    'skills' => $user->skills, // Giữ nguyên skills
                ];
            });

            return [
                'users' => $formattedUsers,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => $totalPages,
                'totalUsers' => $totalUsers,
            ];
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            throw new \Exception('Token has expired.', 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            throw new \Exception('Token is invalid.', 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            throw new \Exception('Token is missing or malformed.', 400);
        }
    }

    public function sendVerificationEmail($userId, $protocol, $host)
    {
        $user = User::findOrFail($userId);
        $token = $user->createEmailVerificationToken();
        $user->save();

        $url = "$protocol://$host/api/users/verify-email/$token";
        $message = "Please verify your email: $url";

        Mail::raw($message, function ($mail) use ($user) {
            $mail->to($user->email)->subject('Email Verification');
        });
    }

    public function verifyEmail($token)
    {
        $hashedToken = hash('sha256', $token);
        $user = User::where('emailVerificationToken', $hashedToken)
            ->where('emailVerificationExpires', '>', now())
            ->first();

        if (!$user) {
            throw new \Exception('Invalid or expired token.', 400);
        }

        if ($user->active) {
            throw new \Exception('Email already verified.', 400);
        }

        $user->active = true;
        $user->emailVerificationToken = null;
        $user->emailVerificationExpires = null;
        $user->save();
    }

    public function me()
    {
        try {
            // Xác thực token và lấy user
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                throw new \Exception('User not found.', 404);
            }

            $user->load('skills');
            // Trả về thông tin user (có thể tùy chỉnh dữ liệu trả về)
            return $user;
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            throw new \Exception('Token has expired.', 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            throw new \Exception('Token is invalid.', 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            throw new \Exception('Token is missing or malformed.', 400);
        }
    }


    public function logout()
    {
        try {
            $token = JWTAuth::getToken(); // Lấy token trước khi invalidate
            if (!$token) {
                return response()->json(['status' => 'error', 'message' => 'Token not provided.'], 400);
            }

            BlacklistedToken::create([
                'token' => $token, // Lưu token vào danh sách đen
            ]);

            JWTAuth::invalidate($token); // Vô hiệu hóa token

            return response()->json(['status' => 'success', 'message' => 'Logout successful.'], 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            // Token đã bị vô hiệu hóa hoặc không hợp lệ (có thể do đã đăng xuất)
            Log::warning('Token already invalid or expired during logout.', ['exception' => $e]);
            return response()->json(['status' => 'success', 'message' => 'Logout successful.'], 200); // Vẫn trả về thành công
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            // Lỗi khác liên quan đến JWT (ví dụ: không thể tạo token)
            Log::error('JWT Exception during logout.', ['exception' => $e]);
            return response()->json(['status' => 'error', 'message' => 'Failed to logout, please try again.'], 500);
        } catch (\Exception $e) {
            // Bắt các lỗi không lường trước được
            Log::error('Unexpected error during logout.', ['exception' => $e]);
            return response()->json(['status' => 'error', 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function uploadAvatar(User $user, array $data): string
    {
        $validator = Validator::make($data, [
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first(), 400);
        }

        if (isset($data['photo'])) {
            $file = $data['photo'];
            $filename = 'user-' . $user->id . '-' . time() . '.' . $file->getClientOriginalExtension();

            $path = $file->storeAs('photos', $filename, 'public');

            // Xóa ảnh cũ nếu có
            if ($user->photo && $user->photo !== 'default.jpg') {
                Storage::disk('public')->delete($user->photo);
            }

            $user->photo = $path;
            $user->save();

            return asset('storage/' . $path);
        }

        throw new \Exception('No photo uploaded.', 400);
    }
}
