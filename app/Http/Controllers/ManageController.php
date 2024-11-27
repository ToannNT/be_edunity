<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Order;
use App\Models\Wishlist;
use App\Models\Course;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Validator;
use Monolog\Formatter\WildfireFormatter;
use const Grpc\STATUS_ABORTED;

class ManageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
    /*Admin site*/

    // Lấy tất cả user có role là 'admin'
    public function getAdmin(Request $request)
    {
        $perPage = $request->input('per_page', 10); // Số lượng admin trên mỗi trang, mặc định là 10
        $page = $request->input('page', 1); // Trang hiện tại, mặc định là trang 1

        $admins = User::where('role', 'admin')->paginate($perPage, ['*'], 'page', $page);

        $pagination = [
            'total' => $admins->total(),
            'current_page' => $admins->currentPage(),
            'last_page' => $admins->lastPage(),
            'per_page' => $admins->perPage(),
        ];
        return formatResponse(STATUS_OK, [
            'data' => $admins,
            'pagination' => $pagination,
        ], '', __('messages.getUsers'));
    }

    //Sửa tài khoản và mật khẩu
    public function updateUserAccount(Request $request, $id)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'required|min:8',
        ]);
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'status' => 'fail',
                'message' => 'User không tồn tại',
            ], 404);
        }
        $user->email = $request->input('email');
        $user->password = bcrypt($request->input(
            'password'

        ));
        $user->save();
        return formatResponse(STATUS_OK, $user, '', __('messages.updateUser'));
    }

    //Sửa, thêm thông tin nền tảng user role "admin"
    public function updateFoundationAccount(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:511',
            'biography' => 'nullable|string',
            'phone_number' => 'nullable|string|max:15',
            'address' => 'nullable|string|max:255',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        $nameParts = explode(' ', trim($request->input('name')));
        $firstName = array_shift($nameParts); // Lấy phần tử đầu tiên làm first_name
        $lastName = implode(' ', $nameParts); // Các phần tử còn lại làm last_name

        $user = User::find($id);

        if (!$user) {
            return formatResponse(STATUS_FAIL, null, '', __('messages.user_not_found'));
        }

        $user->first_name = $firstName;
        $user->last_name = $lastName;
        $user->biography = $request->input('biography', $user->biography);
        $user->phone_number = $request->input('phone_number', $user->phone_number);
        $user->address = $request->input('address', $user->address);
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('images'), $fileName); // Di chuyển file vào thư mục images
            $user->background_image = $fileName;
        }
        $user->save();

        return formatResponse(STATUS_OK, $user, '', __('messages.updateUser'));
    }

    //Sửa, thêm thông tin liên lạc
    public function updateContactInfo(Request $request, $id)
    {
        $request->validate([
            'facebook' => 'nullable|string|url',
            'linkedin' => 'nullable|string|url',
        ]);

        $user = User::find($id);

        if ($user) {
            $contactInfo = $user->contact_info ?: [];

            if ($request->has('facebook')) {
                $contactInfo['facebook'] = $request->input('facebook');
            }

            if ($request->has('linkedin')) {
                $contactInfo['linkedin'] = $request->input('linkedin');
            }

            $user->contact_info = $contactInfo;

            $user->save();

            return formatResponse(STATUS_OK, $user->contact_info, '', __('messages.update_success'));
        }
        return formatResponse(CODE_NOT_FOUND, null, 404, __('messages.user_not_found'));
    }

    //Delete user follow id
    public function delUser($id)
    {
        $user = User::find($id);

        if (!$user) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.user_not_found'));
        }
        $user->save();
        $user->delete();

        return formatResponse(STATUS_OK, '', '', __('messages.user_soft_delete_success'));
    }

    //Report Payment
    public function getAdminRpPayment(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $adminCount = User::where('role', 'admin')->count();

        $orderItems = OrderItem::with(['course'])
            ->paginate($perPage, ['*'], 'page', $page);

        $result = $orderItems->getCollection()->map(function ($item) use ($adminCount) {
            $totalPrice = $item->price;
            $adminRevenue = $adminCount > 0 ? ($totalPrice * 0.3) / $adminCount : 0;
            return [
                'orderItem_id' => $item->id,
                'course_name' => $item->course->title,
                'instructor_name' => $item->course->creator->last_name . ' ' . $item->course->creator->first_name ?? 'N/A',
                'total_price' => number_format($totalPrice, 0, ',', '.'),
                'admin_revenue' => number_format($adminRevenue, 0, ',', '.'),
                'instructor_email' => $item->course->creator->email ?? 'N/A',
                'created_date' => $item->created_at->format('d/m/Y'),
            ];
        });
        $closing_price = number_format($orderItems->getCollection()->sum('price'), 0, ',', '.');
        $totalAdminRevenue = number_format($orderItems->getCollection()->sum(function ($item) use ($adminCount) {
            return $adminCount > 0 ? ($item->price * 0.3) / $adminCount : $adminCount == 0;
        }), 0, ',', '.');
        $pagination = [
            'total' => $orderItems->total(),
            'current_page' => $orderItems->currentPage(),
            'last_page' => $orderItems->lastPage(),
            'per_page' => $orderItems->perPage(),
        ];
        return formatResponse(STATUS_OK, [
            'data' => $result,
            'pagination' => $pagination,
            'closing_price' => $closing_price,
            'total_admin_price' => $totalAdminRevenue,
        ], '', __('messages.getUsers'));
    }

    public function getInstructorRp(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $orderItems = OrderItem::with(['course'])
            ->paginate($perPage, ['*'], 'page', $page);

        $result = $orderItems->getCollection()->map(function ($item) {
            return [
                'orderItem_id' => $item->id,
                'course_name' => $item->course->title,
                'instructor_name' => $item->course->creator->last_name . ' ' . $item->course->creator->first_name ?? 'N/A',
                'total_price' => number_format($item->price, 0, ',', '.'),
                'admin_revenue' => number_format($item->price * 0.3, 0, ',', '.'),
                'instructor_email' => $item->course->creator->email ?? 'N/A',
                'created_date' => $item->created_at->format('d/m/Y'),
            ];
        });
        $closing_price = number_format($orderItems->getCollection()->sum('price'), 0, ',', '.');
        $totalAdminRevenue = number_format($orderItems->getCollection()->sum(function ($item) {
            return $item->price * 0.3;
        }), 0, ',', '.');
        $pagination = [
            'total' => $orderItems->total(),
            'current_page' => $orderItems->currentPage(),
            'last_page' => $orderItems->lastPage(),
            'per_page' => $orderItems->perPage(),
        ];
        return formatResponse(STATUS_OK, [
            'data' => $result,
            'pagination' => $pagination,
            'closing_price' => $closing_price,
            'total_admin_price' => $totalAdminRevenue,
        ], '', __('messages.getUsers'));
    }

    //Filter cho Report Admin (Đang sai, chưa hoàn thành)
    /*private function applyDateFilter($query, Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $query = Order::where('created_at');
        if ($startDate && $endDate) {
            // Đảm bảo định dạng ngày trước khi lọc
            $startDate = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
            $endDate = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
    }*/

    //Xóa báo cáo doanh thu
    public function deleteReportPayment($id)
    {
        $orderItem = OrderItem::find($id);

        if (!$orderItem) {
            return formatResponse(STATUS_FAIL, [], 'Order item not found', __('messages.course_not_found'));
        }

        // Thực hiện xóa
        $orderItem->delete();

        return formatResponse(STATUS_OK, [], 'Order item deleted successfully', __('messages.course_soft_delete_success'));
    }

    //Order history, Order detail
    public function getOrderHistory(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $orders = Order::with(['student', 'orderItems.course'])
            ->paginate($perPage, ['*'], 'page', $page);

        $result = $orders->getCollection()->map(function ($item) {
            return [
                'order_id' => $item->id,
                'user_name' => $item->student->last_name . ' ' . $item->student->first_name ?? 'N/A',
                'user_email' => $item->student->email ?? 'N/A',
                'course_name' => $item->orderItems->first()->course->title ?? 'N/A',
                'total_price' => number_format($item->total_price, 0, ',', '.'),
                'payment_method' => $item->payment_method,
                'created_date' => $item->created_at->format('d/m/Y'),
            ];
        });
        $closing_price = number_format($orders->getCollection()->sum('total_price'), 0, ',', '.');
        $pagination = [
            'total' => $orders->total(),
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'per_page' => $orders->perPage(),
        ];
        return formatResponse(STATUS_OK, [
            'data' => $result,
            'pagination' => $pagination,
            'closing_price' => $closing_price,
        ], '', __('messages.getUsers'));
    }

    public function getOrderDetail($orderId)
    {
        $order = Order::with(['student', 'orderItems.course'])->findOrFail($orderId);

        $orderDetail = [
            'order_id' => $order->id,
            'user_name' => $order->student->last_name . ' ' . $order->student->first_name ?? 'N/A',
            'user_email' => $order->student->email ?? 'N/A',
            'courses' => $order->orderItems->map(function ($orderItem) {
                return [
                    'course_name' => $orderItem->course->title ?? 'N/A',
                    'course_id' => $orderItem->course->id ?? null,
                    'instructor_name' => $orderItem->course->creator->last_name . ' ' . $orderItem->course->creator->first_name ?? 'N/A',
                ];
            }),
            'total_price' => number_format($order->total_price, 0, ',', '.'),
            'final_amount' => number_format($order->total_price * 0.1 + $order->total_price, 0, ',', '.'),
            'created_date' => $order->created_at->format('d/m/Y'),
        ];

        return formatResponse(STATUS_OK, [
            'data' => $orderDetail,
        ], '', __('messages.getOrderDetail'));
    }

    //Lấy user role "instructor"
    public function getInstructor(Request $request)
    {
        $perPage = $request->input('per_page', 10); // Số lượng admin trên mỗi trang, mặc định là 10
        $page = $request->input('page', 1); // Trang hiện tại, mặc định là trang 1

        $instructors = User::where('role', 'instructor')->paginate($perPage, ['*'], 'page', $page);
        $pagination = [
            'total' => $instructors->total(),
            'current_page' => $instructors->currentPage(),
            'last_page' => $instructors->lastPage(),
            'per_page' => $instructors->perPage(),
        ];
        return formatResponse(STATUS_OK, [
            'data' => $instructors,
            'pagination' => $pagination,
        ], '', __('messages.getUsers'));
    }

    //Lấy user role "student"
    public function getStudent(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $studens = User::where('role', 'student')->paginate($perPage, ['*'], 'page', $page);
        $pagination = [
            'total' => $studens->total(),
            'current_page' => $studens->currentPage(),
            'last_page' => $studens->lastPage(),
            'per_page' => $studens->perPage(),
        ];
        return formatResponse(STATUS_OK, ['data' => $studens, 'pagination' => $pagination], '', __('messages.getUsers'));
    }

    // Wishlist
    public function addToWishlist(Request $request)
    {
        $userId = Auth::id();
        $validator = Validator::make(
            request()->all(),
            [
                'course_id' => 'required|integer|exists:courses,id',
            ],
            [
                'course_id.required' => 'Mã khóa học không được để trống',
                'course_id.integer' => 'Mã khóa học phải là số',
                'course_id.exists' => 'Mã khóa học không tồn tại',
            ]
        );
        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }
        $courseId = $request->input('course_id');
        $exists = Wishlist::where('user_id', $userId)->where('course_id', $courseId)->exists();
        if ($exists) {
            return formatResponse(STATUS_OK, '', '', 'Khóa học đã được yêu thích', CODE_FAIL);
        }
        // Tạo mới wishlist
        $createWishlist = Wishlist::create([
            'user_id' => $userId,
            'course_id' => $courseId
        ]);
        return formatResponse(STATUS_OK, $createWishlist, '', __('messages.course_added_success'));
    }

    public function getWishlist()
    {
        try {
            $userId = Auth::id();
            $wishlistItems = Wishlist::where('user_id', $userId)->with([
                'course' => function ($query) {
                    $query->with([
                        'category',
                        'level',
                        'language',
                        'creator:id,first_name,last_name',
                        'sections.lectures',
                        'reviews'
                    ])->withCount('reviews')
                        ->withAvg('reviews', 'rating');
                }])->get();
            // Xử lý format dữ liệu nếu cần
            $formattedWishlist = $wishlistItems->map(function ($wishlistItem) {
                $course = $wishlistItem->course;
                if ($course) {
                    $lectures_count = $course->sections->sum(fn($section) => $section->lectures->count());
                    $total_duration = $course->sections->sum(fn($section) => $section->lectures->sum('duration'));
                    return [
                        'wishlist_id' => $wishlistItem->id,
                        'course' => [
                            'id' => $course->id,
                            'title' => $course->title,
                            'old_price' => round($course->price, 0),
                            'current_price' => round(
                                $course->type_sale === 'price' ? $course->price - $course->sale_value : $course->price * (1 - $course->sale_value / 100),
                                0
                            ),
                            'thumbnail' => $course->thumbnail,
                            'level' => $course->level->name ?? null,
                            'language' => $course->language->name ?? null,
                            'creator' => ($course->creator && ($course->creator->last_name || $course->creator->first_name)
                                ? trim($course->creator->last_name . ' ' . $course->creator->first_name)
                                : ''),
                            'lectures_count' => $lectures_count,
                            'total_duration' => round($total_duration / 60 / 60, 1), // Đổi từ giây sang giờ
                            'rating_avg' => round($course->reviews_avg_rating, 2) ?? 0,
                            'reviews_count' => $course->reviews_count ?? 0,
                            'status' => $course->status,
                        ]
                    ];
                }
                return null;
            })->filter(); // Loại bỏ các mục null
            return formatResponse(STATUS_OK, $formattedWishlist, '', 'Lấy danh sách khóa học thành công');
        } catch (\Exception $e) {
            return formatResponse(STATUS_FAIL, [], $e->getMessage(), 'Lỗi khi lấy danh sách khóa học yêu thích.');
        }
    }

    function deletWishlist(Request $request)
    {
        $userId = Auth::id();
        $validator = Validator::make(
            request()->all(),
            [
                'course_id' => 'required|integer|exists:courses,id',
            ],
            [
                'course_id.required' => 'Mã khóa học không được để trống',
                'course_id.integer' => 'Mã khóa học phải là số',
                'course_id.exists' => 'Mã khóa học không tồn tại',
            ]
        );
        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }
        $course_id = $request->input('course_id');
        $delWishlist = Wishlist::where(['user_id' => $userId, 'course_id' => $course_id]);
        if ($delWishlist) {
            $delWishlist->delete();
            return formatResponse(STATUS_OK, '', '', 'Bỏ yêu thích khóa học thành công');
        }
        return formatResponse(STATUS_FAIL, '', '', 'Bỏ yêu thích khóa học thất bại.', CODE_FAIL);
    }
}
