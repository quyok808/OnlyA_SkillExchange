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
                'role' => 'user',
            ]);

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
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                throw new \Exception('User not found.', 404);
            }

            $user->load('skills');
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

        if ($user->photo && $user->photo !== 'photos/defaultAvatar.jpg') {
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

        $user->name = $updateData['name'];
        $user->phone = $updateData['phone'];
        $user->address = $updateData['address'];
        $user->save();

        $user->load('skills');

        return $user;
    }

    /**
     * Change the user's password.
     */
    public function changePassword(User $user, array $data): void
    {
        if (!Hash::check($data['passwordCurrent'], $user->password)) {
            throw new \Exception(' current pasIncorrectsword.', 401);
        }

        if ($data['password'] === $data['passwordCurrent']) {
            throw new \Exception('New password cannot be the same as the current password.', 400);
        }

        $user->password = Hash::make($data['password']);
        $user->passwordChangedAt = now();
        $user->save();
    }

    /**
     * Get a single user by their ID.
     */
    public function getUserById($id): User
    {
        $user = User::with('skills')->findOrFail($id);
        $user->photo = $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/defaultAvatar.jpg');
        return $user;
    }

    /**
     * Search users based on query parameters.
     */
    public function searchUsers(array $query): array
    {
        $queryBuilder = User::query()->with('skills');

        if (isset($query['skillName']) && !empty($query['skillName'])) {
            $skillName = $query['skillName'];
            $skillIds = Skill::where('name', 'like', '%' . $skillName . '%')
                ->pluck('id')
                ->toArray();

            if (empty($skillIds)) {
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

        if (isset($query['name']) && !empty($query['name'])) {
            $searchTerm = $query['name'];
            $queryBuilder->where(function ($q) use ($searchTerm) {
                $q->where('users.name', 'like', '%' . $searchTerm . '%');
            });
        }

        if (isset($query['exclude_user_id']) && !empty($query['exclude_user_id'])) {
            $queryBuilder->where('users.id', '!=', $query['exclude_user_id']);
        }

        $features = new APIFeatures($queryBuilder, $query);

        $features->filter()->sort();

        $totalUsers = (clone $features->getQuery())->count();

        $users = $features->paginate()->getQuery()
            ->select('users.id', 'users.name', 'users.email', 'users.photo', 'users.role', 'users.address') // Select desired columns
            ->get();

        $formattedUsers = $users->map(function ($user) {
            return [
                '_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'photo' => $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/defaultAvatar.jpg'),
                'address' => $user->address,
                'skills' => $user->skills,
            ];
        });


        $limit = $features->getLimit();
        $page = $features->getPage();
        $totalPages = $limit > 0 ? ceil($totalUsers / $limit) : ($totalUsers > 0 ? 1 : 0);

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
        $acceptedStatus = 'accepted';

        $receiverIds = Connection::where('senderId', $currentUserId)
            ->where('status', $acceptedStatus)->orWhere('status', 'pending')
            ->pluck('receiverId');

        $senderIds = Connection::where('receiverId', $currentUserId)
            ->where('status', $acceptedStatus)->orWhere('status', 'pending')
            ->pluck('senderId');

        $connectedUserIds = $receiverIds->merge($senderIds)->unique()->values()->all();

        if (empty($connectedUserIds)) {
            $limit = (new APIFeatures(User::query(), $query))->getLimit();
            return [
                'users' => [],
                'page' => 1,
                'limit' => $limit,
                'totalPages' => 0,
                'totalUsers' => 0,
            ];
        }

        $queryBuilder = User::query()
            ->with('skills')
            ->whereIn('id', $connectedUserIds)
            ->where('id', '!=', $currentUserId);

        if (isset($query['skillName']) && !empty($query['skillName'])) {
            $skillName = $query['skillName'];
            $skillIds = Skill::where('name', 'like', '%' . $skillName . '%')
                ->pluck('id')
                ->toArray();

            if (empty($skillIds)) {
                $limit = (new APIFeatures(clone $queryBuilder, $query))->getLimit();
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

        if (isset($query['name']) && !empty($query['name'])) {
            $searchTerm = $query['name'];
            $queryBuilder->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%');
            });
        }

        if (isset($query['exclude_user_id']) && !empty($query['exclude_user_id'])) {
            if ($query['exclude_user_id'] != $currentUserId) {
                $queryBuilder->where('id', '!=', $query['exclude_user_id']);
            }
        }

        $features = new APIFeatures($queryBuilder, $query);
        $features->filter()->sort();

        $totalUsers = (clone $features->getQuery())->count(); // Clone before pagination

        $users = $features->paginate()->getQuery()
            ->select('users.id', 'users.name', 'users.email', 'users.photo', 'users.role', 'users.address')
            ->get();

        $formattedUsers = $users->map(function ($user) {
            return [
                '_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'address' => $user->address,
                'photo' => $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/defaultAvatar.jpg'),
                'skills' => $user->skills->map(function ($skill) {
                    return [
                        'id' => $skill->id,
                        'name' => $skill->name,
                    ];
                }),
            ];
        });

        $limit = $features->getLimit();
        $page = $features->getPage();
        $totalPages = $limit > 0 ? ceil($totalUsers / $limit) : ($totalUsers > 0 ? 1 : 0);

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
        $skillIds = $data['skills'] ?? [];
        if (empty($skillIds)) {
            throw new \Exception('No skills provided.', 400);
        }

        $existingSkillsCount = Skill::whereIn('id', $skillIds)->count();
        if ($existingSkillsCount !== count($skillIds)) {
            throw new \Exception('One or more provided skills are invalid.', 400);
        }

        $user->skills()->syncWithoutDetaching($skillIds);

        $user->load('skills');

        return $user;
    }

    public function getRelatedUserIds($currentUserId)
    {
        try {
            $connections = Connection::where(function ($query) use ($currentUserId) {
                $query->where('senderId', $currentUserId)
                    ->orWhere('receiverId', $currentUserId);
            })
                ->select('senderId', 'receiverId')
                ->get();

            $relatedUserIds = $connections->map(function ($connection) use ($currentUserId) {
                if ($connection->senderId == $currentUserId) {
                    return $connection->receiverId;
                } else {
                    return $connection->senderId;
                }
            });


            return $relatedUserIds->unique()->values();
        } catch (Exception $error) {

            Log::error("Lỗi khi lấy related user IDs cho user {$currentUserId}: " . $error->getMessage());

            throw $error;
        }
    }
}
