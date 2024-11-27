<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmailWelcome;
use App\Models\User;
use App\Jobs\SendEmailForgotPassword;
use App\Jobs\SendEmailVerification;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;
use function Termwind\render;


class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api',
            [
                'except' => [
                    'login',
                    'register',
                    'refresh',
                    'verifyEmail',
                    'forgotPassword',
                    'resetPassword',
                    'getGoogleSignInUrl',
                    'loginGoogleCallback',
                    'updateProfile',
                    'checkTokenResetPassword'
                ]
            ]);
    }

    public function register()
    {
        $validator = Validator::make(request()->all(), [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,instructor,student',
        ], [
            'first_name.required' => __('messages.first_name_required'),
            'first_name.string' => __('messages.first_name_string'),
            'first_name.max' => __('messages.first_name_max'),
            'last_name.required' => __('messages.last_name_required'),
            'last_name.string' => __('messages.last_name_string'),
            'last_name.max' => __('messages.last_name_max'),

            'email.required' => __('messages.email_required'),
            'email.string' => __('messages.email_string'),
            'email.email' => __('messages.email_email'),
            'email.max' => __('messages.email_max'),
            'email.unique' => __('messages.email_unique'),

            'password.required' => __('messages.password_required'),
            'password.string' => __('messages.password_string'),
            'password.min' => __('messages.password_min'),

            'role.required' => __('messages.role_required'),
            'role.in' => __('messages.role_in'),

        ]);
        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }
        $currentUser = auth()->user();
        $role = request()->input('role');

        if ($currentUser) {
            if ($currentUser->role !== 'admin') {
                return formatResponse(STATUS_FAIL, '', '', __('messages.validation_error_role'));
            }
        } else {
            if (!in_array($role, ['instructor', 'student'])) {
                return formatResponse(STATUS_FAIL, '', '', __('messages.validation_error_role'));
            }
        }
        $user = User::create([
            'first_name' => request()->input('first_name'),
            'last_name' => request()->input('last_name'),
            'email' => request()->input('email'),
            'password' => Hash::make(request()->input('password')),
            'role' => $role,
            'verification_token' => Str::random(60),
        ]);

        SendEmailVerification::dispatch($user);
        return formatResponse(STATUS_OK, $user, '', __('messages.user_signup_success'));
    }

    public function getGoogleSignInUrl(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'role' => 'required|in:instructor,student,admin',
            ], [
                'role.required' => __('messages.role_required'),
                'role.in' => __('messages.role_in'),
            ]);

            if ($validator->fails()) {
                return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
            }
            $role = $request->input('role');

            $url = Socialite::driver('google')->stateless()->with(['state' => http_build_query(['role' => $role])])
                ->redirect()->getTargetUrl();
            return formatResponse(STATUS_OK, ['url' => $url], '', __('messages.get_url_ok'), CODE_OK);
        } catch (\Exception $exception) {
            return $exception;
        }
    }

    public function loginGoogleCallback(Request $request)
    {
        try {
            $state = $request->input('state');
            parse_str($state, $result);
            $googleUser = Socialite::driver('google')->stateless()->user();

            $role = $result['role'] ?? User::ROLE_STUDENT;

            $user = User::where('email', $googleUser->email)->first();
            if ($user) {
                if (!$token = auth('api')->login($user)) {
                    return formatResponse(STATUS_FAIL, '', '', __('messages.create_token_failed'), CODE_FAIL);
                }
                $redirectUrl = env('URL_DOMAIN') . "/google/call-back/{$token}";
                return redirect($redirectUrl);
//                $refreshToken = $this->createRefreshToken();
//                return formatResponse(STATUS_OK, $user, '', __('messages.user_login_success'), CODE_OK, $token, $refreshToken);
            }

            $user = User::create(
                [
                    'avatar' => $googleUser->avatar,
                    'email' => $googleUser->email,
                    'last_name' => $googleUser->name,
                    'role' => $role,
                    'status' => User::USER_ACTIVE,
                    'email_verified' => $googleUser->user['email_verified'],
                    'provider' => 'google',
                    'provider_id' => $googleUser->id,
                    'password' => Hash::make(Str::random(12)),
                ]
            );

            if (!$token = auth('api')->login($user)) {
                return formatResponse(STATUS_FAIL, '', '', __('messages.create_token_failed'), CODE_FAIL);
            }
//            $refreshToken = $this->createRefreshToken();
            SendEmailWelcome::dispatch($user);
            $redirectUrl = env('URL_DOMAIN') . "/google/call-back/{$token}";
            return redirect($redirectUrl);
//            return formatResponse(STATUS_OK, $user, '', __('messages.user_login_success'), CODE_OK, $token, $refreshToken);
        } catch (\Exception $exception) {
            return formatResponse(STATUS_FAIL, '', $exception, __('messages.login_google_success'), CODE_BAD);
        }
    }

    public function verifyEmail($token)
    {
        $user = User::where('verification_token', $token)->first();
        if (!$user) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.url_not_found'), CODE_NOT_FOUND);
        }
        $user->email_verified = true;
        $user->status = USER::USER_ACTIVE;
        $user->verification_token = null;
        $user->save();
        return formatResponse(STATUS_OK, $user, '', __('messages.verify_email_ok'));
    }


    public function forgotPassword()
    {
        $validator = Validator::make(request()->all(), [
            'email' => 'required|string|email|max:100',
        ], [
            'email.required' => __('messages.email_required'),
            'email.string' => __('messages.email_string'),
            'email.email' => __('messages.email_email'),
            'email.max' => __('messages.email_max'),
            'email.unique' => __('messages.email_unique'),
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }

        $user = User::where('email', request()->input('email'))->first();
        if (!$user) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.email_exist'));
        }
        $user->reset_token = Str::random(60);

        if (!$user->save()) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.error_save'));
        }
        SendEmailForgotPassword::dispatch($user);
        return formatResponse(STATUS_OK, '', '', __('messages.email_send_ok'), CODE_OK);
    }

    public function checkTokenResetPassword($token)
    {
        $user = User::where('reset_token', $token)->first();
        if (!$user) {
            return formatResponse(STATUS_FAIL, '', '', 'Đường dẫn đổi mật khẩu sai.', CODE_NOT_FOUND);
        }
        return formatResponse(STATUS_OK, '', '', 'Đường dẫn đổi mật khẩu đúng.');
    }

    public function resetPassword($token)
    {
        $user = User::where('reset_token', $token)->first();
        if (!$user) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.url_not_found'), CODE_NOT_FOUND);
        }

        $validator = Validator::make(request()->all(), [
            'password' => 'required|string|min:8',
        ], [
            'password.required' => __('messages.password_required'),
            'password.string' => __('messages.password_string'),
            'password.min' => __('messages.password_min'),
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }

        $user->reset_token = null;
        $user->password = Hash::make(request()->input('password'));
        $user->save();
        return formatResponse(STATUS_OK, $user, '', 'Thay đổi mật khẩu thành công', CODE_OK);
    }

    public function login()
    {
        $validator = Validator::make(request()->all(), [
            'email' => 'required|string|email|max:100',
            'password' => 'required|string|min:8',
        ], [
            'email.required' => __('messages.email_required'),
            'email.string' => __('messages.email_string'),
            'email.email' => __('messages.email_email'),
            'email.max' => __('messages.email_max'),
            'email.unique' => __('messages.email_unique'),

            'password.required' => __('messages.password_required'),
            'password.string' => __('messages.password_string'),
            'password.min' => __('messages.password_min'),
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }

        $user = User::where(['email' => request()->input('email')])->first();
        if (!$user) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.email_exist'));
        }
        if (!$user->email_verified) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.email_not_verified'));
        }
        if ($user->status != USER::USER_ACTIVE) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.user_has_blocked'));
        }
        $credentials = request(['email', 'password']);
        if (!$token = auth('api')->attempt($credentials)) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.password_incorrect'));
        }
        $refreshToken = $this->createRefreshToken();
        return formatResponse(STATUS_OK, $user, '', __('messages.user_login_success'), CODE_OK, $token, $refreshToken);
    }

    public function logout()
    {
        auth('api')->logout();
        return formatResponse(STATUS_OK, '', '', __('messages.user_logout_success'));
    }

    public function profile()
    {
        $user = auth()->user();
        return formatResponse(STATUS_OK, $user, '', 'Lấy thông tin thành công');
    }


    public function refresh()
    {
        try {
            $refresh_token = request()->input('refresh_token');
            if (!$refresh_token) {
                return formatResponse(STATUS_FAIL, '', '', 'Vui lòng nhập refresh token');
            }

            $decode = JWTAuth::getJWTProvider()->decode($refresh_token);

            // Invalidate current access token
//            auth('api')->invalidate();

            $user = User::find($decode['user_id']);
            if (!$user) {
                return formatResponse(STATUS_FAIL, '', '', 'Tài khoản không tồn tại');
            }
            // Generate new tokens
            $token = auth('api')->login($user);
            $refreshToken = $this->createRefreshToken();

            return formatResponse(STATUS_OK, $user, '', 'Refresh access token thành công', CODE_OK, $token,
                $refreshToken);

        } catch (TokenExpiredException $e) {
            return formatResponse(STATUS_FAIL, '', '', 'Refresh token đã hết hạn');
        } catch (TokenInvalidException $e) {
            return formatResponse(STATUS_FAIL, '', '', 'Refresh token không hợp lệ');
        } catch (Exception $e) {
            return formatResponse(STATUS_FAIL, '', '', 'Lỗi xảy ra trong quá trình làm mới token');
        }
    }

    public function updateProfile()
    {
        $user = auth()->user();
        if (!$user) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.user_not_found'));
        }
        $validator = Validator::make(request()->all(), [
            'first_name' => 'string|max:50',
            'last_name' => 'string|max:50',
            'email' => 'string|email|max:100|unique:users,email,' . $user->id,
            'phone_number' => 'regex:/^[0-9]+$/',
            'address' => 'string',
            'biography' => 'string',
            'contact_info' => 'array',
            'gender' => 'nullable|string|in:male,female,unknown',
            'date_of_birth' => 'nullable|date',
            'password' => 'string|min:8',
        ], [
            'first_name.required' => __('messages.first_name_required'),
            'first_name.string' => __('messages.first_name_string'),
            'first_name.max' => __('messages.first_name_max'),
            'last_name.required' => __('messages.last_name_required'),
            'last_name.string' => __('messages.last_name_string'),
            'last_name.max' => __('messages.last_name_max'),

            'phone_number.regex' => __('messages.phone_number_update'),
            'address.string' => __('messages.address_update'),
            'contact_info.array' => __('messages.contactInfo_update'),

            'email.required' => __('messages.email_required'),
            'email.string' => __('messages.email_string'),
            'email.email' => __('messages.email_email'),
            'email.max' => __('messages.email_max'),
            'email.unique' => __('messages.email_unique'),

            'password.required' => __('messages.password_required'),
            'password.string' => __('messages.password_string'),
            'password.min' => __('messages.password_min'),

            'gender.in' => __('messages.gender_invalid'),
            'date_of_birth.date' => __('messages.date_of_birth_invalid'),
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }
        $data = request()->except(['role', 'email_verified', 'reset_token', 'status']);
        if (isset($data['password'])) {
            $data['password'] = Hash::make(request()->input('password'));
        }
        if (!$user->update($data)) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.update_fail'));
        }
        return formatResponse(STATUS_OK, $user, '', __('messages.update_success'));
    }

    public function adminUpdateUser(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'first_name' => 'sometimes|string|max:50',
            'last_name' => 'sometimes|string|max:50',
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($request->input('user_id')),
            ],
            'role' => 'sometimes|in:admin,instructor,student',
            'status' => 'sometimes|in:active,inactive',
        ]);
//        , [
//        'user_id.required' => 'User ID là bắt buộc.',
//        'user_id.integer' => 'User ID phải là số nguyên.',
//        'user_id.exists' => 'Người dùng không tồn tại.',
//        'first_name.string' => 'First name phải là chuỗi.',
//        'first_name.max' => 'First name không được vượt quá 50 ký tự.',
//        'last_name.string' => 'Last name phải là chuỗi.',
//        'last_name.max' => 'Last name không được vượt quá 50 ký tự.',
//        'email.email' => 'Email không hợp lệ.',
//        'email.max' => 'Email không được vượt quá 255 ký tự.',
//        'email.unique' => 'Email đã được sử dụng.',
//        'role.in' => 'Role không hợp lệ.',
//        'status.in' => 'Status không hợp lệ.',
//    ]
        // Kiểm tra nếu validation thất bại
        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), 'Xác thực thất bại');
        }

        // Lấy dữ liệu cần cập nhật, loại bỏ password nếu có
        $data = $request->only(['first_name', 'last_name', 'email', 'role', 'status']);

        $user = User::find($request->input('user_id'));
        if (!$user) {
            return formatResponse(STATUS_FAIL, '', '', 'Tài khoản không tồn tại');
        }

        if ($user->update($data)) {
            return formatResponse(STATUS_OK, $user, '', 'Cập nhật thông tin thành công');
        } else {
            return formatResponse(STATUS_FAIL, '', '', 'Cập nhật thông tin thất bại');
        }
    }


    public function deleteUser($id)
    {
        $user = User::where('id', $id)->first();

        if (!$user) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.user_not_found'));
        }

        if ($user->delete()) {
            $user->is_deleted = User::STATUS_DELETED;
            $user->save();
            return formatResponse(STATUS_OK, '', '', 'Xóa tài khoản thành công');
        }
        return formatResponse(STATUS_FAIL, '', '', 'Xóa tài khoản thất bại');

    }

    public function restoreUser($id)
    {
        $user = User::withTrashed()->find($id);
        if (!$user) {
            return formatResponse(STATUS_FAIL, '', '', 'Tài khoản không tồn tại');
        }
        if ($user->trashed()) {
            $user->restore();
//            $user->is_deleted = User::STATUS_DEFAULT;
            $user->save();
            return formatResponse(STATUS_OK, $user, '', 'Khôi phục thành công');
        }
        return formatResponse(STATUS_FAIL, '', '', 'Khôi phục thất bại');
    }

    public function forceDeleteUser($id)
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return formatResponse(STATUS_FAIL, '', '', 'Tài khoản không tồn tại');
        }

        // Xóa hoàn toàn khỏi DB
        $user->forceDelete();
        return formatResponse(STATUS_OK, $user, '', 'Xóa hoàn toàn thành công');
    }


    private function createRefreshToken()
    {
        $data = [
            'user_id' => auth('api')->user()->id,
            'random' => rand() . time(),
            'exp' => time() + config('jwt.refresh_ttl') * 60,
        ];
        $refreshToken = JWTAuth::getJWTProvider()->encode($data);
        return $refreshToken;
    }

    public function uploadImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), 'xác thực thất bại');
        }
        $user = JWTAuth::parseToken()->authenticate();
        if ($user->avatar) {
            $currentFilePath = str_replace(env('URL_IMAGE_S3'), '', $user->avatar);
            if (Storage::disk('s3')->exists($currentFilePath)) {
                Storage::disk('s3')->delete($currentFilePath);
            }
        }
        $path = $request->file('image')->storePublicly('image-user');
        if ($path) {
            $user->avatar = env('URL_IMAGE_S3') . $path;
            $user->save();
            return formatResponse(STATUS_OK, $user, '', 'Cập nhật hình ảnh thành công', CODE_OK);
        }
        return formatResponse(STATUS_FAIL, '', '', 'Cập nhật hình ảnh thất bại', CODE_BAD);
    }


    public function getAllUser(Request $request)
    {
        // Query cơ bản lấy danh sách User
        $userQuery = User::query();
        // Kiểm tra tham số `deleted`
        if ($request->has('deleted') && $request->deleted == 1) {
            // Lấy các  đã xóa
            $userQuery->onlyTrashed();
        } else {
            // Chỉ lấy các  chưa xóa (mặc định)
            $userQuery->whereNull('deleted_at');
        }

        // Kiểm tra tham số `status`
        if ($request->has('status') && !is_null($request->status)) {
            $userQuery->where('status', $request->status);
        }

        // Kiểm tra tham số `role`
        if ($request->has('role') && !is_null($request->role)) {
            $userQuery->where('role', $request->role);
        }

        // Lọc theo email (nếu có)
        if ($request->has('email') && !empty($request->email)) {
            $userQuery->where('email', 'like', '%' . $request->email . '%');
        }

        // Lọc theo keyword tìm kiếm trong tên
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $userQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        // Sắp xếp theo `created_at` hoặc bất kỳ trường nào được cung cấp
        $orderBy = $request->get('order_by', 'created_at'); // Trường sắp xếp (mặc định là `created_at`)
        $orderDirection = $request->get('order_direction', 'desc'); // Hướng sắp xếp (mặc định là `desc`)
        $userQuery->orderBy($orderBy, $orderDirection);

        // Phân trang với `per_page` và `page`
        $perPage = (int)$request->get('per_page', 10); // Mặc định là 10 bản ghi mỗi trang
        $page = (int)$request->get('page', 1); // Trang hiện tại, mặc định là 1

        // Lấy danh sách đã lọc
        $users = $userQuery->get();

        // Tổng số bản ghi
        $total = $users->count();

        // Phân trang thủ công
        $paginatedUsers = $users->forPage($page, $perPage)->values();

        // Tạo đối tượng LengthAwarePaginator cho phân trang
        $pagination = new LengthAwarePaginator(
            $paginatedUsers, // Dữ liệu của trang hiện tại
            $total,          // Tổng số bản ghi
            $perPage,        // Số lượng bản ghi mỗi trang
            $page,           // Trang hiện tại
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(), // Đường dẫn chính
                'query' => $request->query() // Tham số query hiện tại
            ]
        );

        // Chuyển đổi dữ liệu phân trang thành mảng
        $users = $pagination->toArray();

        // Trả về kết quả với đầy đủ thông tin lọc, phân trang và thông điệp
        return formatResponse(
            STATUS_OK,
            $users, // Dữ liệu người dùng đã phân trang
            '',
            __('messages.user_fetch_success') // Thông điệp thành công
        );
    }

    public function adminCreateUser(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,instructor,student',
            'status' => 'nullable|in:active,inactive',
            'email_verified' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'FAIL', 'errors' => $validator->errors(),], 422);
        }
        $currentUser = Auth::user();
        $user = User::create([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role' => $request->input('role'),
            'status' => $request->input('status', 'inactive'),
            'email_verified' => $request->input('email_verified', false),
            'created_by' => $currentUser->id,
        ]);
        return response()->json(['status' => 'OK', 'message' => 'User created successfully.', 'data' => $user,], 201);
    }

    public function getDetailUser($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['status' => 'FAIL', 'message' => 'User not found.',], 404);
        }
        return response()->json(['status' => 'success', 'data' => $user,], 200);
    }

    public function blockOrUnlockUser($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['status' => 'FAIL', 'message' => 'User not found.',], 404);
        }
        if ($user->status === 'inactive') {
            $user->status = 'active';
            $user->save();
            return response()->json(['status' => 'OK', 'message' => 'User has been unlocked successfully.', 'data' => $user], 200);
        }
        if ($user->status === 'active') {
            $user->status = 'inactive';
            $user->save();
            return response()->json(['status' => 'OK', 'message' => 'User has been blocked successfully.', 'data' => $user], 200);
        }
        return response()->json(['status' => 'FAIL', 'message' => 'Unable to change user status.'], 400);
    }

    protected function respondWithToken($token, $refreshToken)
    {
        return response()->json([
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }


}
