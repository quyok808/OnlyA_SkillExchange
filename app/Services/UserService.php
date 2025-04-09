<?php

namespace App\Services;

use Exception;
use App\Models\User;
use App\Models\Skill;
use App\Models\Connection;
use App\Utils\APIFeatures;
use Illuminate\Http\Request;
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
                'photo' => 'photos/defaultAvatar.jpg',
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
        $ttl = (int) ($data["remember"] == true ? config('jwt.ttl_remember') : config('jwt.ttl'));
        JWTAuth::factory()->setTTL($ttl);
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

    public function sendResetPasswordEmail($email, $protocol, $host)
    {
        $user = User::where('email', $email)->firstOrFail();
        $token = $user->createPasswordResetToken();
        $user->save();

        $url = "$protocol://$host/api/users/reset-password/$token";
        $message = "Follow this link to reset your password: $url";

        Mail::raw($message, function ($mail) use ($user) {
            $mail->to($user->email)->subject('Email Reset Password');
        });
    }

    public function resetPassword($password, $token)
    {
        $hashedToken = hash('sha256', $token);
        $user = User::where('passwordResetToken', $hashedToken)
            ->where('passwordResetExpires', '>', now())
            ->first();
        if (!$user) {
            throw new \Exception('Invalid or expired token.', 400);
        }
        $user->password = Hash::make($password);
        $user->passwordResetToken = null;
        $user->passwordResetExpires = null;
        $user->save();
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
            $token = JWTAuth::getToken();
            if (!$token) {
                return response()->json(['status' => 'error', 'message' => 'Token not provided.'], 400);
            }

            JWTAuth::invalidate($token);

            return response()->json(['status' => 'success', 'message' => 'Logout successful.'], 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            Log::warning('Token already invalid or expired during logout.', ['exception' => $e]);
            return response()->json(['status' => 'success', 'message' => 'Logout successful.'], 200); // Vẫn trả về thành công
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            Log::error('JWT Exception during logout.', ['exception' => $e]);
            return response()->json(['status' => 'error', 'message' => 'Failed to logout, please try again.'], 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error during logout.', ['exception' => $e]);
            return response()->json(['status' => 'error', 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function uploadAvatar(User $user, Request $request): string
    {
        var_dump(
            $request->all(),
            $request->file('photo'),
            $request->hasFile('photo'),
            $_FILES
        );
        if (!$request->hasFile('photo')) {
            throw new \Exception('The photo không tồn tại.', 400);
        }
        // Validate file trực tiếp
        $validator = Validator::make(['photo' => $request->file('photo')], [
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first(), 400);
        }

        $file = $request->file('photo');
        $filename = 'user-' . $user->id . '-' . time() . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs('photos', $filename, 'public');

        if ($user->photo && $user->photo !== 'default.jpg') {
            Storage::disk('public')->delete($user->photo);
        }

        $user->photo = $path;
        $user->save();

        return asset('storage/' . $path);
    }

    /**
     * Update user profile information.
     * Only allows updating specific fields.
     */
    public function updateMe(User $user, array $data): User
    {
        // Define fillable attributes for updateMe
        $fillable = ['name', 'phone', 'address'];
        $updateData = [];
        foreach ($fillable as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (empty($updateData)) {
            throw new \Exception('No valid fields provided for update.', 400);
        }

        // Update user
        $user->name = $updateData['name'];
        $user->phone = $updateData['phone'];
        $user->address = $updateData['address'];
        $user->save();

        // Reload relations if needed, e.g., skills
        $user->load('skills');

        return $user; // Return updated user
    }

    /**
     * Change the user's password.
     */
    public function changePassword(User $user, array $data): void // Return void or bool
    {
        // Check current password
        if (!Hash::check($data['passwordCurrent'], $user->password)) {
            throw new \Exception(' current pasIncorrectsword.', 401); // 401 Unauthorized or 400 Bad Request
        }

        // Check if new password is same as old
        if ($data['password'] === $data['passwordCurrent']) {
            throw new \Exception('New password cannot be the same as the current password.', 400);
        }

        // Update password
        $user->password = Hash::make($data['password']);
        $user->passwordChangedAt = now();
        $user->save();
    }

    /**
     * Get a single user by their ID.
     */
    public function getUserById($id): User
    {
        $user = User::findOrFail($id); // Eager load skills
        $user->load('skills');

        return $user;
    }

    /**
     * Search users based on query parameters.
     */
    public function searchUsers(array $query): array
    {
        $queryBuilder = User::query()->with('skills'); // Eager load skills

        // --- Handle 'skillName' parameter ---
        if (isset($query['skillName']) && !empty($query['skillName'])) {
            $skillName = $query['skillName'];
            $skillIds = Skill::where('name', 'like', '%' . $skillName . '%')
                ->pluck('id')
                ->toArray();

            if (empty($skillIds)) {
                // No skills match the criteria. Return a specific indicator or empty result.
                // Option 1: Return an indicator
                // return [
                //     'status' => 'success_empty', // Custom status controller can check
                //     'limit' => (new APIFeatures($queryBuilder, $query))->getLimit() // Still provide limit info
                // ];
                // Option 2: Return empty result structure directly (controller won't need special check)
                return [
                    'users' => [],
                    'page' => 1,
                    'limit' => (new APIFeatures($queryBuilder, $query))->getLimit(),
                    'totalPages' => 0,
                    'totalUsers' => 0,
                ];
            }
            $queryBuilder->whereHas('skills', function ($q) use ($skillIds) {
                $q->whereIn('skills.id', $skillIds);
            });
        }

        // --- Handle 'name' parameter ---
        if (isset($query['name']) && !empty($query['name'])) {
            $searchTerm = $query['name'];
            // Search name and potentially other fields
            $queryBuilder->where(function ($q) use ($searchTerm) {
                $q->where('users.name', 'like', '%' . $searchTerm . '%');
                // ->orWhere('users.email', 'like', '%' . $searchTerm . '%'); // Uncomment to search email too
            });
        }

        // --- Handle excluding a specific user ID ---
        if (isset($query['exclude_user_id']) && !empty($query['exclude_user_id'])) {
            $queryBuilder->where('users.id', '!=', $query['exclude_user_id']);
        }

        // --- Apply general filtering, sorting, pagination via APIFeatures ---
        // Pass the already constrained queryBuilder to APIFeatures
        $features = new APIFeatures($queryBuilder, $query);

        // Apply sorting and *generic* filters from APIFeatures
        // Make sure APIFeatures filter() doesn't conflict with your specific name/skill filters
        $features->filter()->sort();

        // Get Total Count *after* all filters are applied
        // Clone to avoid issues with pagination state if count is needed before get()
        $totalUsers = (clone $features->getQuery())->count();

        // Apply pagination (limit/offset)
        $users = $features->paginate()->getQuery()
            ->select('users.id', 'users.name', 'users.email', 'users.photo', 'users.role') // Select desired columns
            ->get(); // Execute the final query


        // Format the results (similar to your original searchUsers, but using the fetched $users)
        $formattedUsers = $users->map(function ($user) {
            return [
                '_id' => $user->id, // Use id or _id consistently
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'photo' => $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/defaultAvatar.jpg'),
                'skills' => $user->skills, // Skills are already eager-loaded
            ];
        });


        // Calculate pagination details
        $limit = $features->getLimit();
        $page = $features->getPage();
        $totalPages = $limit > 0 ? ceil($totalUsers / $limit) : ($totalUsers > 0 ? 1 : 0);

        // Return data structure expected by the controller
        return [
            'users' => $formattedUsers,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'totalUsers' => $totalUsers,
        ];
    }

    /**
     * Search for users within the network.
     * FIXME: Requires a proper definition of "network".
     * Currently behaves identically to searchUsers.
     */
    public function searchUsersInNetwork($currentUserId, array $query): array
    {
        // --- Xác định ID của những người dùng đã kết nối ---
        $acceptedStatus = 'accepted'; // Hoặc giá trị trạng thái chấp nhận của bạn

        // Lấy ID của những người mà currentUserId đã gửi request và được chấp nhận
        $receiverIds = Connection::where('senderId', $currentUserId)
            ->where('status', $acceptedStatus)
            ->pluck('receiverId');

        // Lấy ID của những người đã gửi request cho currentUserId và được chấp nhận
        $senderIds = Connection::where('receiverId', $currentUserId)
            ->where('status', $acceptedStatus)
            ->pluck('senderId');

        // Gộp và lấy các ID duy nhất của những người dùng đã kết nối
        $connectedUserIds = $receiverIds->merge($senderIds)->unique()->values()->all();

        // Nếu không có ai trong network, trả về kết quả rỗng ngay lập tức
        if (empty($connectedUserIds)) {
            // Lấy limit từ query để trả về cấu trúc nhất quán
            $limit = (new APIFeatures(User::query(), $query))->getLimit();
            return [
                'users' => [],
                'page' => 1,
                'limit' => $limit,
                'totalPages' => 0,
                'totalUsers' => 0,
            ];
        }

        // --- Bắt đầu xây dựng query cho User ---
        // Chỉ lấy những user có ID nằm trong danh sách đã kết nối
        // và loại trừ chính người dùng hiện tại
        $queryBuilder = User::query()
            ->with('skills') // Eager load skills
            ->whereIn('id', $connectedUserIds) // *** Chỉ lấy user trong network ***
            ->where('id', '!=', $currentUserId); // *** Loại trừ chính mình ***

        // --- Handle 'skillName' parameter ---
        if (isset($query['skillName']) && !empty($query['skillName'])) {
            $skillName = $query['skillName'];
            $skillIds = Skill::where('name', 'like', '%' . $skillName . '%')
                ->pluck('id')
                ->toArray();

            if (empty($skillIds)) {
                // Không có skill nào khớp -> không có user nào khớp trong network thỏa mãn skill
                $limit = (new APIFeatures(clone $queryBuilder, $query))->getLimit(); // Clone để lấy limit
                return [
                    'users' => [],
                    'page' => 1,
                    'limit' => $limit,
                    'totalPages' => 0,
                    'totalUsers' => 0,
                ];
            }
            $queryBuilder->whereHas('skills', function ($q) use ($skillIds) {
                $q->whereIn('skills.id', $skillIds);
            });
        }

        // --- Handle 'name' parameter ---
        if (isset($query['name']) && !empty($query['name'])) {
            $searchTerm = $query['name'];
            $queryBuilder->where(function ($q) use ($searchTerm) {
                // Laravel >= 9 không cần chỉ định users.name khi không có join phức tạp
                $q->where('name', 'like', '%' . $searchTerm . '%');
                // ->orWhere('email', 'like', '%' . $searchTerm . '%');
            });
        }

        // --- Handle excluding a specific user ID (ngoài currentUserId) ---
        // Điều này hữu ích nếu bạn muốn lọc bỏ một người dùng cụ thể *khác* khỏi kết quả tìm kiếm network
        if (isset($query['exclude_user_id']) && !empty($query['exclude_user_id'])) {
            // Đảm bảo không loại trừ chính người đang tìm kiếm nếu họ vô tình truyền vào
            if ($query['exclude_user_id'] != $currentUserId) {
                $queryBuilder->where('id', '!=', $query['exclude_user_id']);
            }
        }

        // --- Apply general filtering, sorting, pagination via APIFeatures ---
        $features = new APIFeatures($queryBuilder, $query);
        $features->filter()->sort(); // Apply generic filters and sorting

        // Get Total Count *after* all filters are applied
        $totalUsers = (clone $features->getQuery())->count(); // Clone before pagination

        // Apply pagination and select columns
        $users = $features->paginate()->getQuery()
            ->select('users.id', 'users.name', 'users.email', 'users.photo', 'users.role')
            ->get();

        // Format the results
        $formattedUsers = $users->map(function ($user) {
            return [
                '_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'photo' => $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/defaultAvatar.jpg'),
                // Map skills để trả về cấu trúc mong muốn (nếu cần)
                'skills' => $user->skills->map(function ($skill) {
                    return [
                        'id' => $skill->id,
                        'name' => $skill->name,
                        // 'description' => $skill->description, // Ví dụ thêm trường khác
                    ];
                }),
            ];
        });

        // Calculate pagination details
        $limit = $features->getLimit();
        $page = $features->getPage();
        $totalPages = $limit > 0 ? ceil($totalUsers / $limit) : ($totalUsers > 0 ? 1 : 0);

        // Return data structure
        return [
            'users' => $formattedUsers,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'totalUsers' => $totalUsers,
        ];
    }

    /**
     * Add skills to a user.
     * Assumes a Many-to-Many relationship named 'skills' exists on the User model,
     * and the input data contains an array of skill IDs under the key 'skills'.
     */
    public function addSkillsToUser(User $user, array $data): User
    {
        // Validate that the skill IDs exist in the 'skills' table
        $skillIds = $data['skills'] ?? [];
        if (empty($skillIds)) {
            throw new \Exception('No skills provided.', 400);
        }

        // Ensure skill IDs actually exist in the database
        $existingSkillsCount = Skill::whereIn('id', $skillIds)->count();
        if ($existingSkillsCount !== count($skillIds)) {
            throw new \Exception('One or more provided skills are invalid.', 400);
        }

        // Attach the skills (syncWithoutDetaching prevents duplicates and adding existing ones)
        $user->skills()->syncWithoutDetaching($skillIds);

        // Reload the skills relationship to include the newly added ones
        $user->load('skills');

        return $user;
    }

    public function getRelatedUserIds($currentUserId)
    {
        try {
            // 1. Tìm tất cả các connections mà currentUserId là sender hoặc receiver
            $connections = Connection::where(function ($query) use ($currentUserId) {
                $query->where('senderId', $currentUserId)
                    ->orWhere('receiverId', $currentUserId);
            })
                ->select('senderId', 'receiverId') // Chỉ lấy các trường cần thiết
                ->get(); // Lấy kết quả dưới dạng Collection

            // 2. Duyệt qua các connections và lấy userId còn lại
            $relatedUserIds = $connections->map(function ($connection) use ($currentUserId) {
                // Sử dụng so sánh không chặt chẽ (==) để linh hoạt với kiểu dữ liệu (int/string)
                // Hoặc ép kiểu nếu bạn chắc chắn về kiểu dữ liệu: (string)$connection->senderId === (string)$currentUserId
                if ($connection->senderId == $currentUserId) {
                    return $connection->receiverId;
                } else { // ($connection->receiverId == $currentUserId)
                    return $connection->senderId;
                }
            });

            // 3. Loại bỏ các giá trị trùng lặp và trả về Collection đã được sắp xếp lại key
            return $relatedUserIds->unique()->values();
        } catch (Exception $error) {
            // Ghi log lỗi để dễ dàng debug
            Log::error("Lỗi khi lấy related user IDs cho user {$currentUserId}: " . $error->getMessage());
            // Ném lại lỗi để tầng gọi có thể xử lý
            throw $error;
        }
    }
}
