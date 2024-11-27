<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Wishlist;
use App\Models\Review;
use App\Models\Lecture;
use App\Models\Order;
use Carbon\Carbon;
use App\Http\Controllers\StudyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Tymon\JWTAuth\Facades\JWTAuth;

class CourseController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api',
    //         [
    //             'except' => [
    //                 'filterCourses'
    //             ]
    //         ]);
    // }
    public function getListAdmin(Request $request)
    {
        // Query để lấy danh sách Course, không kiểm tra trạng thái
        $coursesQuery = Course::with(['language', 'level', 'category']);

        // Lấy số lượng limit và thông tin phân trang từ request
        $limit = $request->get('limit', null);
        $perPage = $request->get('per_page', 10);
        $currentPage = $request->get('page', 1);

        // Nếu có limit thì giới hạn kết quả trước khi phân trang thủ công
        if ($limit) {
            // Lấy các kết quả giới hạn
            $courses = $coursesQuery->limit($limit)->get();

            $courses->makeHidden(['category_id', 'level_id', 'language_id']);

            // Lấy tổng số lượng kết quả
            $total = $courses->count();

            // Phân trang thủ công cho kết quả đã giới hạn
            $courses = $courses->forPage($currentPage, $perPage)->values();

            $paginatedCourses = new \Illuminate\Pagination\LengthAwarePaginator(
                $courses,
                $total,
                $perPage,
                $currentPage,
                ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()]
            );

            // Chuyển đổi đối tượng phân trang sang mảng với tất cả các thuộc tính chi tiết
            $paginationData = $paginatedCourses->toArray();

            return formatResponse(STATUS_OK, $paginationData, '', __('messages.course_fetch_success'));
        } else {
            // Nếu không có limit, phân trang như bình thường
            $courses = $coursesQuery->paginate($perPage, ['*'], 'page', $currentPage);
            return formatResponse(STATUS_OK, $courses, '', __('messages.course_fetch_success'));
        }
    }


    public function search(Request $request, $instructorId = '', $courseId = '')
    {
        // Lấy các tham số lọc từ request
        $category_ids = $request->input('category_ids');
        $level_ids = $request->input('level_ids');
        $language_ids = $request->input('language_ids');
        $keyword = $request->input('keyword');
        $status = $request->input('status');
        $type_sale = $request->input('type_sale');
        $min_rating = $request->input('min_rating');
        $max_rating = $request->input('max_rating');
        $duration_ranges = $request->input('duration_ranges', '');

        // Phân trang
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        // Sort
        $sort_by = $request->input('sort_by', 'created_at');
        $sort_order = $request->input('sort_order', 'desc');

        // Lấy danh sách các khóa học mới, phổ biến, đánh giá cao và yêu thích
        $limitTag = 10;
        $newCourses = Course::orderBy('created_at', 'desc')->take($limitTag)->pluck('id')->toArray();
        $popularCourses = OrderItem::select('course_id', DB::raw('COUNT(*) as purchase_count'))
            ->groupBy('course_id')->orderByDesc('purchase_count')->take($limitTag)->pluck('course_id')->toArray();
        $topRatedCourses = Course::leftJoin('reviews', 'courses.id', '=', 'reviews.course_id')
            ->select('courses.id')->groupBy('courses.id')
            ->orderByRaw('AVG(reviews.rating) DESC')->take($limitTag)->pluck('id')->toArray();
        $favoriteCourses = Wishlist::select('course_id')
            ->groupBy('course_id')->orderByRaw('COUNT(*) DESC')->take($limitTag)->pluck('course_id')->toArray();

        // Query khóa học với điều kiện lọc
        $query = Course::with(['category', 'level', 'creator:id,last_name,first_name', 'sections.lectures', 'reviews'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->where('courses.status', 'active'); // Chỉ định rõ bảng courses

        if ($instructorId && $courseId) {
            $query->where('courses.created_by', $instructorId)
                ->where('courses.id', '!=', $courseId);
        }

        // Áp dụng các bộ lọc
        if ($category_ids) {
            $categoryIds = array_map('intval', explode(',', $category_ids));
            $allCategoryIds = [];

            // Hàm đệ quy để lấy tất cả ID của các danh mục con
            function getAllChildCategoryIds($categoryId, &$allIds)
            {
                $category = Category::find($categoryId);
                if ($category) {
                    $allIds[] = $categoryId;
                    $childrenIds = $category->children()->pluck('id')->toArray();
                    foreach ($childrenIds as $childId) {
                        getAllChildCategoryIds($childId, $allIds);
                    }
                }
            }

            foreach ($categoryIds as $id) {
                getAllChildCategoryIds($id, $allCategoryIds);
            }
            $allCategoryIds = array_unique($allCategoryIds);
            $query->whereIn('courses.category_id', $allCategoryIds);
        }

        if ($level_ids) {
            $level_ids = array_map('intval', explode(',', $level_ids));
            $query->whereIn('courses.level_id', $level_ids);
        }

        if ($language_ids) {
            $language_ids = array_map('intval', explode(',', $language_ids));
            $query->whereIn('courses.language_id', $language_ids);
        }

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('courses.title', 'like', '%' . $keyword . '%')
                    ->orWhereRaw("CONCAT(users.last_name, ' ', users.first_name) LIKE ?", ['%' . $keyword . '%']);
            })
            ->join('users', 'users.id', '=', 'courses.created_by');
        }

        if ($min_rating) {
            $query->whereHas('reviews', function ($q) use ($min_rating) {
                $q->havingRaw('AVG(reviews.rating) >= ?', [$min_rating]);
            });
        }

        if ($max_rating) {
            $query->whereHas('reviews', function ($q) use ($max_rating) {
                $q->havingRaw('AVG(reviews.rating) <= ?', [$max_rating]);
            });
        }

        if (!empty($duration_ranges)) {
            $duration_ranges = explode(',', $duration_ranges);
            $query->where(function ($q) use ($duration_ranges) {
                foreach ($duration_ranges as $range) {
                    [$min, $max] = explode('-', $range) + [null, null];
                    if ($min !== null && $max !== null) {
                        $q->orWhereBetween('courses.total_duration', [(int)$min, (int)$max]);
                    }
                }
            });
        }

        // Sắp xếp và phân trang
        $query->orderBy($sort_by, $sort_order);

        $total = $query->count();
        $courses = $query->paginate($perPage, ['*'], 'page', $page);

        // Xử lý các tag và tính toán dữ liệu bổ sung
        $courses->getCollection()->transform(function ($course) use ($newCourses, $popularCourses, $topRatedCourses, $favoriteCourses) {
            $tag = 'none';
            if (in_array($course->id, $newCourses)) {
                $tag = __('messages.tag_new');
            } elseif (in_array($course->id, $topRatedCourses)) {
                $tag = __('messages.tag_top_rated');
            } elseif (in_array($course->id, $popularCourses)) {
                $tag = __('messages.tag_popular');
            } elseif (in_array($course->id, $favoriteCourses)) {
                $tag = __('messages.tag_favorite');
            }

            return [
                'id' => $course->id,
                'title' => $course->title,
                'old_price' => round($course->price, 0),
                'current_price' => round($course->price - ($course->sale_value ?? 0), 0),
                'thumbnail' => $course->thumbnail,
                'level' => $course->level->name ?? '',
                'creator' => $course->creator ? trim($course->creator->last_name . ' ' . $course->creator->first_name) : '',
                'lectures_count' => $course->sections->sum(fn($s) => $s->lectures->count()),
                'total_duration' => round($course->sections->sum(fn($s) => $s->lectures->sum('duration')) / 3600, 1),
                'rating_avg' => round($course->reviews_avg_rating ?? 0, 2),
                'reviews_count' => $course->reviews_count ?? 0,
                'status' => $course->status,
                'tag' => $tag,
            ];
        });

        return formatResponse(STATUS_OK, $courses, '', __('messages.course_fetch_success'));
    }



    public function uploadThumbnail(Request $request)
    {
        // Tải lên tệp hình ảnh
        $path = $request->file('thumbnail')->storePublicly('course-thumbnails');
        if (!$path) {
            return '';
        }

        // Trả về đường dẫn hình ảnh
        $imageUrl = env('URL_IMAGE_S3') . $path;
        return $imageUrl;
    }

    public function deleteThumbnail($thumbnailUrl)
    {
        $currentFilePath = str_replace(env('URL_IMAGE_S3'), '', $thumbnailUrl);

        // Kiểm tra xem tệp có tồn tại trên S3 không
        if (Storage::disk('s3')->exists($currentFilePath)) {
            // Xóa tệp
            Storage::disk('s3')->delete($currentFilePath);
            return formatResponse(STATUS_OK, '', '', __('messages.thumbnail_delete_success'));
        }

        return formatResponse(STATUS_FAIL, '', '', __('messages.thumbnail_not_found'));
    }


    // Tạo mới một khóa học
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'level_id' => 'required|exists:course_levels,id',
            'language_id' => 'required|exists:languages,id',
            'title' => 'required|string|max:100',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'thumbnail' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'price' => 'required|numeric',
            'type_sale' => 'required|in:percent,price',
            'sale_value' => 'nullable|numeric',
            'status' => 'required|in:active,inactive',
        ], [
            'title.required' => __('messages.title_required'),
            'category_id.required' => __('messages.category_id_required'),
            'category_id.exists' => __('messages.category_id_invalid'),
            'level_id.required' => __('messages.level_id_required'),
            'level_id.exists' => __('messages.level_id_invalid'),
            'language_id.required' => __('messages.language_id_required'),
            'language_id.exists' => __('messages.language_id_invalid'),
            'thumbnail.required' => __('messages.thumbnail_required'),
            'thumbnail.image' => __('messages.thumbnail_image'),
            'thumbnail.mimes' => __('messages.thumbnail_mimes'),
            'thumbnail.max' => __('messages.thumbnail_max'),
            'price.required' => __('messages.price_required'),
            'type_sale.required' => __('messages.type_sale_required'),
            'status.required' => __('messages.status_required'),
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }
        $thumbnailPath = $this->uploadThumbnail($request);
        $course = new Course();
        $course->fill($request->all());
        $course->thumbnail = $thumbnailPath;
        $course->created_by = auth()->id();
        $course->save();

        return formatResponse(STATUS_OK, $course, '', __('messages.course_create_success'));
    }

    // Hiển thị một khóa học cụ thể
    public function detail($id, Request $request)
    {
        $course = Course::with([
            'category' => function ($query) {
                $query->where('status', 'active');
            },
            'level' => function ($query) {
                $query->where('status', 'active');
            },
            'creator' => function ($query) {
                $query->where('status', 'active');
            },
            'sections' => function ($query) {
                $query->where('status', 'active')->with([
                    'lectures' => function ($query) {
                        $query->where('status', 'active');
                    },
                ]);
            },
            'reviews' => function ($query) {
                $query->where('status', 'active');
            },
            'language' => function ($query) {
                $query->where('status', 'active');
            },
        ])
            ->where('status', 'active')
            ->where('id', $id)
            ->first();

        if (!$course) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.course_not_found'));
        }

        // Tính toán thông tin khóa học
        $old_price = $course->price;
        $sale_value = $course->sale_value;
        $type_sale = $course->type_sale;

        $sections = $course->sections->where('status', 'active');
        $sections_count = $sections->count();
        $lectures_count = $sections->reduce(function ($carry, $section) {
            return $carry + $section->lectures->where('status', 'active')->count();
        }, 0);

        $total_duration = $this->formatDuration($sections->reduce(function ($carry, $section) {
            return $carry + $section->lectures->where('status', 'active')->sum('duration');
        }));

        $preview_videos = Lecture::select('title', 'content_link')
            ->where('status', 'active')
            ->whereHas('section', function ($query) use ($course) {
                $query->where('course_id', $course->id)
                    ->where('preview', 'can')
                    ->where('status', 'active');
            })
            ->where('type', 'video')
            ->get();


        // Thống kê instructor
        $creatorId = $course->creator->id;
        $courseIds = Course::where('created_by', $creatorId)->where('status', 'active')->pluck('id');
        $totalCourses = $courseIds->count();
        $orderIds = OrderItem::whereIn('course_id', $courseIds)->pluck('order_id');
        $usersCount = Order::whereIn('id', $orderIds)->distinct()->count();

        $reviewsData = Review::whereIn('course_id', $courseIds)
            ->where('status', 'active')
            ->selectRaw('COUNT(*) as total_reviews, AVG(rating) as average_rating')
            ->first();

        $totalReviews = $reviewsData->total_reviews ?? 0;
        $averageRating = $reviewsData->average_rating ?? 0;

        $instructor = [
            'info' => $course->creator,
            'courses_count' => $totalCourses,
            'students_count' => $usersCount,
            'total_reviews' => $totalReviews,
            'average_rating' => round($averageRating, 2),
        ];

        // Truy vấn reviews
        $reviewsQuery = DB::table('reviews')
            ->join('users', 'reviews.user_id', '=', 'users.id')
            ->where('reviews.course_id', $id)
            ->where('reviews.status', 'active')
            ->select(
                'reviews.rating',
                'reviews.comment',
                'reviews.created_at',
                'users.first_name',
                'users.last_name',
                'users.avatar'
            );
        $comment_keyword = null;

        // Kiểm tra và gán giá trị từ $request nếu tồn tại
        if ($request->comment_keyword) {
            $comment_keyword = $request->comment_keyword;
        }
        if ($comment_keyword) {
            $reviewsQuery->where(function ($q) use ($comment_keyword) {
                $q->where('reviews.comment', 'LIKE', '%' . $comment_keyword . '%')
                    ->orWhere(DB::raw("CONCAT(users.last_name, ' ', users.first_name)"), 'LIKE', '%' . $comment_keyword . '%');
            });
        }

        $reviews = $reviewsQuery->get();

        // Xử lý dữ liệu reviews
        $total_reviews = $reviews->count();
        $average_rating = $total_reviews > 0 ? round($reviews->avg('rating'), 1) : null;

        $rating_counts = $reviews->groupBy('rating')->map(function ($group) {
            return count($group);
        });

        $rating_percentages = [];
        for ($i = 1; $i <= 5; $i++) {
            $count = $rating_counts->get($i, 0);
            $rating_percentages[$i] = $total_reviews > 0 ? floor(($count / $total_reviews) * 100) : 0;
        }

        $review_list = $reviews->map(function ($review) {
            $last_name = $review->last_name ?? '';
            $first_name = $review->first_name ?? '';
            $name = trim($last_name . ' ' . $first_name) ?: 'Người dùng không xác định';

            $avatar = $review->avatar;

            $created_at = Carbon::parse($review->created_at);
            $now = Carbon::now();
            $diff = $created_at->diff($now);

            if ($diff->y > 0) {
                $time_diff = $diff->y . ' năm trước';
            } elseif ($diff->m > 0) {
                $time_diff = $diff->m . ' tháng trước';
            } elseif ($diff->d > 0) {
                $time_diff = $diff->d . ' ngày trước';
            } elseif ($diff->h > 0) {
                $time_diff = $diff->h . ' giờ trước';
            } elseif ($diff->i > 0) {
                $time_diff = $diff->i . ' phút trước';
            } else {
                $time_diff = $diff->s . ' giây trước';
            }

            return [
                'user_avatar' => $avatar,
                'user_name' => $name,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'time_diff' => $time_diff,
            ];
        });
        $study=new StudyController;
        // Chuẩn bị dữ liệu trả về
        $data = $this->search($request, $course->creator->id, $id)->getData(); // Lấy dữ liệu dạng object
        $orderCourse = json_decode(json_encode($data), true)['data']['data'];

        $course_data = [
            'id' => $course->id,
            'title' => $course->title,
            'category' => $course->category->name,
            'level' => $course->level->name,
            'thumbnail' => $course->thumbnail,
            'language' => $course->language->name,
            'old_price' => round($course->price, 0),
            'current_price' => round(
                $course->type_sale === 'price'
                    ? $course->price - $course->sale_value
                    : $course->price * (1 - $course->sale_value / 100),
                0
            ),
            'type_sale' => $type_sale,
            'sale_value' => $sale_value,
            'sections_count' => $sections_count,
            'lectures_count' => $lectures_count,
            'total_duration' => $total_duration,
            'creator' => ($course->creator && ($course->creator->last_name || $course->creator->first_name)
                ? trim($course->creator->last_name . ' ' . $course->creator->first_name)
                : ''),
            'average_rating' => $average_rating,
            'total_reviews' => $total_reviews,
            'preview_videos' => $preview_videos,
            'course_contents' => $study->getAllContent(0, $id, '')['allContent'],
            'order_course_of_instructor' => $orderCourse,
            'instructor' => $instructor,
            'status' => $course->status,
            'reviews' => [
                'total_reviews' => $total_reviews,
                'average_rating' => $average_rating,
                'rating_percentages' => $rating_percentages,
                'review_list' => $review_list,
            ],
        ];

        return formatResponse(STATUS_OK, $course_data, '', __('messages.course_detail_success'));
    }



    private function formatDuration($seconds)
    {
        if ($seconds < 60) {
            return "{$seconds} giây"; // Dưới 1 phút
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60; // Tính số giây lẻ

        if ($minutes < 60) {
            return $remainingSeconds > 0
                ? "{$minutes} phút {$remainingSeconds} giây" // Hiển thị phút và giây lẻ nếu có
                : "{$minutes} phút"; // Chỉ hiển thị phút
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes > 0 || $remainingSeconds > 0) {
            return "{$hours} giờ"
                . ($remainingMinutes > 0 ? " {$remainingMinutes} phút" : ""); // Chỉ thêm giây nếu khác 0
        }

        return "{$hours} giờ"; // Chỉ hiển thị giờ nếu không có phút và giây
    }


    // Cập nhật thông tin khóa học
    public function update(Request $request, $id)
    {
        $course = Course::find($id);
        if (!$course) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.course_not_found'));
        }
        $user = auth()->user();

        if ($user->role === 'instructor') {
            // Kiểm tra xem user có phải là người tạo khóa học không
            if ($course->created_by !== $user->id) {
                return formatResponse(STATUS_FAIL, '', '', __('messages.not_your_course'));
            }
        }
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'level_id' => 'required|exists:course_levels,id',
            'language_id' => 'required|exists:languages,id',
            'title' => [
                'required',
                'string',
                'max:100',
            ],
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'price' => 'required|numeric',
            'type_sale' => 'required|in:percent,price',
            'sale_value' => 'nullable|numeric',
            'status' => 'required|in:active,inactive',
        ], [
            'title.required' => __('messages.title_required'),
            'category_id.required' => __('messages.category_id_required'),
            'category_id.exists' => __('messages.category_id_invalid'),
            'thumbnail.image' => __('messages.thumbnail_image'),
            'thumbnail.mimes' => __('messages.thumbnail_mimes'),
            'thumbnail.max' => __('messages.thumbnail_max'),
            'level_id.required' => __('messages.level_id_required'),
            'level_id.exists' => __('messages.level_id_invalid'),
            'language_id.required' => __('messages.language_id_required'),
            'language_id.exists' => __('messages.language_id_invalid'),
            'price.required' => __('messages.price_required'),
            'type_sale.required' => __('messages.type_sale_required'),
            'status.required' => __('messages.status_required'),
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }
        $thumbnail = $course->thumbnail;
        $course->fill($request->all());
        $course->thumbnail = $thumbnail;
        if ($request->thumbnail) {
            if ($course->thumbnail) {
                $this->deleteThumbnail($course->thumbnail);
            }
            $thumbnailPath = $this->uploadThumbnail($request);
            $course->thumbnail = $thumbnailPath;
        }
        $course->updated_by = auth()->id();
        $course->save();

        return formatResponse(STATUS_OK, $course, '', __('messages.course_update_success'));
    }

    // Xóa mềm khóa học

    public function destroy($id)
    {
        $course = Course::find($id);
        if (!$course) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.course_not_found'));
        }
        $user = auth()->user();

        if ($user->role === 'instructor') {
            // Kiểm tra xem user có phải là người tạo khóa học không
            if ($course->created_by !== $user->id) {
                return formatResponse(STATUS_FAIL, '', '', __('messages.not_your_course'));
            }
        }
        $course->deleted_by = auth()->id();
        $course->save();

        $course->delete();

        return formatResponse(STATUS_OK, '', '', __('messages.course_soft_delete_success'));
    }

    // Khôi phục khóa học đã bị xóa mềm
    public function restore($id)
    {
        $course = Course::onlyTrashed()->find($id);

        if (!$course) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.course_not_found'));
        }

        $user = auth()->user();

        if ($user->role === 'instructor') {
            // Kiểm tra xem user có phải là người tạo khóa học không
            if ($course->created_by !== $user->id) {
                return formatResponse(STATUS_FAIL, '', '', __('messages.not_your_course'));
            }
        }
        $course->deleted_by = null;
        $course->save();

        $course->restore();

        return formatResponse(STATUS_OK, '', '', __('messages.course_restore_success'));
    }

    // Xóa cứng khóa học
    public function forceDelete($id)
    {
        $course = Course::onlyTrashed()->find($id);
        if (!$course) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.course_not_found'));
        }
        $user = auth()->user();

        if ($user->role === 'instructor') {
            // Kiểm tra xem user có phải là người tạo khóa học không
            if ($course->created_by !== $user->id) {
                return formatResponse(STATUS_FAIL, '', '', __('messages.not_your_course'));
            }
        }
        if ($course->thumbnail) {
            $this->deleteThumbnail($course->thumbnail);
        }
        $course->forceDelete();

        return formatResponse(STATUS_OK, '', '', __('messages.course_force_delete_success'));
    }

    public function getPopularCourses(Request $request)
    {
        // Kiểm tra xem có giới hạn không, nếu không, mặc định là 10
        $limit = $request->limit ?? 10;
        $category_ids = $request->input('category_ids');
        $allCategoryIds = [];
        if ($category_ids) {
            // Chia nhỏ danh sách category_id thành mảng
            $categoryIds = array_map('intval', explode(',', $category_ids));
            // Mảng để lưu tất cả category ID bao gồm cả ID con


            // Hàm đệ quy để lấy tất cả ID của các danh mục con
            function getAllChildCategoryIds($categoryId, &$allIds)
            {
                $category = Category::find($categoryId);
                if ($category) {
                    $allIds[] = $categoryId; // Thêm ID của danh mục hiện tại vào mảng
                    $childrenIds = $category->children()->pluck('id')->toArray(); // Lấy ID của các danh mục con

                    foreach ($childrenIds as $childId) {
                        getAllChildCategoryIds($childId, $allIds); // Gọi đệ quy cho các danh mục con
                    }
                }
            }

            // Lặp qua các category ID và thêm ID của các danh mục con
            foreach ($categoryIds as $id) {
                getAllChildCategoryIds($id, $allCategoryIds);
            }
            $allCategoryIds = array_unique($allCategoryIds); // Loại bỏ các ID trùng
        }

        // Lấy các khóa học được mua nhiều nhất từ bảng order_items
        $popularCourses = OrderItem::select('course_id', DB::raw('COUNT(*) as purchase_count'))
            ->groupBy('course_id')
            ->orderByDesc('purchase_count')
            ->limit($limit)
            ->get();

        // Lấy chi tiết các khóa học cùng với category và level dựa trên course_id đã gom nhóm
        $courses = Course::with('category', 'level', 'creator')
            ->where('status', 'active')
            ->whereIn('id', $popularCourses->pluck('course_id'))
            ->when($allCategoryIds, function ($query) use ($allCategoryIds) {
                return $query->whereIn('category_id', $allCategoryIds);
            })
            ->get();

        if ($courses->isEmpty()) {
            // Nếu không có khóa học nào phổ biến
            return formatResponse(STATUS_FAIL, '', '', __('messages.no_popular_courses'));
        }
        return formatResponse(STATUS_OK, $this->transform($courses, __('messages.tag_popular')), '', __('messages.popular_courses_found'));
    }

    public function transform($courses, $tag)
    {
        // dd($courses[0]->creator);
        return $courses->map(function ($course) use ($tag) {
            // Tính giá hiện tại
            $current_price = $course->type_sale === 'percent'
                ? round($course->price - ($course->price * ($course->sale_value / 100)), 0)
                : round($course->price - $course->sale_value, 0);

            // Tính tổng số lượng bài giảng và tổng thời lượng
            $lectures_count = $course->sections->reduce(function ($carry, $section) {
                return $carry + $section->lectures->count();
            }, 0);

            $total_duration = $course->sections->reduce(function ($carry, $section) {
                    return $carry + $section->lectures->sum('duration');
                }, 0) / 3600; // Đổi tổng thời gian thành giờ

            // Tính trung bình đánh giá và số lượng reviews
            $reviews_count = $course->reviews->count();
            $rating_avg = $reviews_count > 0 ? round($course->reviews->avg('rating'), 1) : null;

            // Trả về dữ liệu đã format
            return [
                'id' => $course->id,
                'title' => $course->title,
                'old_price' => round($course->price, 0), // Giá ban đầu
                'current_price' => $current_price, // Giá hiện tại
                'thumbnail' => $course->thumbnail, // Ảnh thumbnail
                'level' => $course->level->name ?? null, // Mức độ khóa học
                'creator' => ($course->creator && ($course->creator->last_name || $course->creator->first_name)
                    ? trim($course->creator->last_name . ' ' . $course->creator->first_name)
                    : ''),
                'lectures_count' => $lectures_count, // Số bài giảng
                'total_duration' => round($total_duration, 1), // Tổng thời lượng (giờ)
                'rating_avg' => $rating_avg, // Trung bình đánh giá
                'reviews_count' => $reviews_count, // Tổng số reviews
                'status' => $course->status,
                'tag' => $tag, // Thẻ
            ];
        });
    }


    public function getNewCourses(Request $request)
    {
        // Kiểm tra xem có giới hạn không, nếu không, mặc định là 10
        $category_ids = $request->input('category_ids');
        $allCategoryIds = [];
        if ($category_ids) {
            // Chia nhỏ danh sách category_id thành mảng
            $categoryIds = array_map('intval', explode(',', $category_ids));
            // Mảng để lưu tất cả category ID bao gồm cả ID con

            // Hàm đệ quy để lấy tất cả ID của các danh mục con
            function getAllChildCategoryIds($categoryId, &$allIds)
            {
                $category = Category::find($categoryId);
                if ($category) {
                    $allIds[] = $categoryId; // Thêm ID của danh mục hiện tại vào mảng
                    $childrenIds = $category->children()->pluck('id')->toArray(); // Lấy ID của các danh mục con

                    foreach ($childrenIds as $childId) {
                        getAllChildCategoryIds($childId, $allIds); // Gọi đệ quy cho các danh mục con
                    }
                }
            }

            // Lặp qua các category ID và thêm ID của các danh mục con
            foreach ($categoryIds as $id) {
                getAllChildCategoryIds($id, $allCategoryIds);
            }
            $allCategoryIds = array_unique($allCategoryIds); // Loại bỏ các ID trùng
        }

        $limit = $request->limit ?? 10;

        // Lấy các khóa học mới nhất theo ngày tạo và lọc theo category_ids
        $courses = Course::with('category', 'level')
            ->when($allCategoryIds, function ($query) use ($allCategoryIds) {
                return $query->whereIn('category_id', $allCategoryIds);
            })
            ->where('status', 'active')
            ->orderBy('created_at', 'desc') // Sắp xếp theo ngày tạo giảm dần
            ->limit($limit)
            ->get();

        if ($courses->isEmpty()) {
            // Nếu không có khóa học nào mới
            return formatResponse(STATUS_FAIL, '', '', __('messages.no_new_courses'));
        }

        return formatResponse(STATUS_OK, $this->transform($courses, __('messages.tag_new')), '', __('messages.new_courses_found'));
    }

    public function getTopRatedCourses(Request $request)
    {
        // Kiểm tra xem có giới hạn không, nếu không, mặc định là 10
        $limit = $request->limit ?? 10;
        $category_ids = $request->input('category_ids');
        $allCategoryIds = [];
        if ($category_ids) {
            // Chia nhỏ danh sách category_id thành mảng
            $categoryIds = array_map('intval', explode(',', $category_ids));
            // Mảng để lưu tất cả category ID bao gồm cả ID con

            // Hàm đệ quy để lấy tất cả ID của các danh mục con
            function getAllChildCategoryIds($categoryId, &$allIds)
            {
                $category = Category::find($categoryId);
                if ($category) {
                    $allIds[] = $categoryId; // Thêm ID của danh mục hiện tại vào mảng
                    $childrenIds = $category->children()->pluck('id')->toArray(); // Lấy ID của các danh mục con

                    foreach ($childrenIds as $childId) {
                        getAllChildCategoryIds($childId, $allIds); // Gọi đệ quy cho các danh mục con
                    }
                }
            }

            // Lặp qua các category ID và thêm ID của các danh mục con
            foreach ($categoryIds as $id) {
                getAllChildCategoryIds($id, $allCategoryIds);
            }
            $allCategoryIds = array_unique($allCategoryIds); // Loại bỏ các ID trùng
        }

        // Lấy các khóa học được đánh giá cao nhất từ bảng reviews
        $topRatedCourses = Review::select('course_id', DB::raw('AVG(rating) as average_rating'))
            ->groupBy('course_id')
            ->orderByDesc('average_rating')
            ->limit($limit)
            ->get();

        // Lấy chi tiết các khóa học cùng với category và level dựa trên course_id đã gom nhóm
        $courses = Course::with('category', 'level', 'creator')
            ->where('status', 'active')
            ->whereIn('id', $topRatedCourses->pluck('course_id'))
            ->when($allCategoryIds, function ($query) use ($allCategoryIds) {
                return $query->whereIn('category_id', $allCategoryIds);
            })
            ->get();

        if ($courses->isEmpty()) {
            // Nếu không có khóa học nào
            return formatResponse(STATUS_FAIL, '', '', __('messages.no_top_rated_courses'));
        }

        return formatResponse(STATUS_OK, $this->transform($courses, __('messages.tag_top_rated')), '', __('messages.top_rated_courses_found'));
    }


    public function getFavouriteCourses(Request $request)
    {
        // Kiểm tra xem có giới hạn không, nếu không, mặc định là 10
        $limit = $request->limit ?? 10;
        $category_ids = $request->input('category_ids');
        $allCategoryIds = [];
        if ($category_ids) {
            // Chia nhỏ danh sách category_id thành mảng
            $categoryIds = array_map('intval', explode(',', $category_ids));
            // Mảng để lưu tất cả category ID bao gồm cả ID con

            // Hàm đệ quy để lấy tất cả ID của các danh mục con
            function getAllChildCategoryIds($categoryId, &$allIds)
            {
                $category = Category::find($categoryId);
                if ($category) {
                    $allIds[] = $categoryId; // Thêm ID của danh mục hiện tại vào mảng
                    $childrenIds = $category->children()->pluck('id')->toArray(); // Lấy ID của các danh mục con

                    foreach ($childrenIds as $childId) {
                        getAllChildCategoryIds($childId, $allIds); // Gọi đệ quy cho các danh mục con
                    }
                }
            }

            // Lặp qua các category ID và thêm ID của các danh mục con
            foreach ($categoryIds as $id) {
                getAllChildCategoryIds($id, $allCategoryIds);
            }
            $allCategoryIds = array_unique($allCategoryIds); // Loại bỏ các ID trùng
        }

        // Lấy các khóa học được yêu thích nhất từ bảng wishlist
        $favoriteCourses = Wishlist::select('course_id', DB::raw('COUNT(*) as wishlist_count'))
            ->groupBy('course_id')
            ->orderByDesc('wishlist_count')
            ->limit($limit)
            ->get();

        // Lấy chi tiết các khóa học cùng với category, level, và creator dựa trên course_id đã gom nhóm
        $courses = Course::with('category', 'level', 'creator')
            ->where('status', 'active')
            ->whereIn('id', $favoriteCourses->pluck('course_id'))
            ->when($allCategoryIds, function ($query) use ($allCategoryIds) {
                return $query->whereIn('category_id', $allCategoryIds);
            })
            ->get();


        if ($courses->isEmpty()) {
            // Nếu không có khóa học nào
            return formatResponse(STATUS_FAIL, '', '', __('messages.no_favorite_courses'));
        }

        return formatResponse(STATUS_OK, $this->transform($courses, __('messages.tag_favorite')), '', __('messages.favorite_courses_found'));
    }



//     public function filterCourses(Request $request)
//     {
//         $category_id = $request->input('category_id');
//         $title = $request->input('title');
//         $min_price = $request->input('min_price');
//         $max_price = $request->input('max_price');
//         $status = $request->input('status');
//         $type_sale = $request->input('type_sale');
//         $rating = $request->input('rating');
//         $duration_range = $request->input('duration_range');
//
//
//         $page = $request->input('page', 1);
//         $perPage = $request->input('per_page', 10);
//
//         $sort_by = $request->input('sort_by', 'created_at');
//         $sort_order = $request->input('sort_order', 'desc');
//
//         $query = Course::with('reviews');
//         if ($category_id) {
//             $categoryIds = explode(',', $category_id);
//             $query->whereIn('category_id', $categoryIds);
//         }
//         if ($title) {
//             $query->where('title', 'like', '%' . $title . '%');
//         }
//         if ($min_price) {
//             $query->where('price', '>=', $min_price);
//         }
//         if ($max_price) {
//             $query->where('price', '<=', $max_price);
//         }
//         if ($status) {
//             $query->where('status', $status);
//         }
//
//         if ($rating) {
//             $query->whereHas('reviews', function ($q) use ($rating) {
//                 $q->havingRaw('ROUND(AVG(rating),0) = ?', [$rating]);
//             });
//         }
//
//         if ($duration_range) {
//             $query->whereHas('sections.lectures', function ($q) use ($duration_range) {
//                 switch ($duration_range) {
//                     case '0-2':
//                         $q->havingRaw('SUM(duration) <= 120');
//                         break;
//                     case '3-5':
//                         $q->havingRaw('SUM(duration) BETWEEN 180 AND 300');
//                         break;
//                     case '6-12':
//                         $q->havingRaw('SUM(duration) BETWEEN 360 AND 720');
//                         break;
//                     case '12+':
//                         $q->havingRaw('SUM(duration) > 720');
//                         break;
//                 }
//             });
//         }
//
//         $query->orderBy($sort_by, $sort_order);
//         $courses = $query->paginate($perPage, ['*'], 'page', $page);
//         return response()->json([
//             'status' => 'success',
//             'data' => $courses->items(),
//             'pagination' => [
//                 'total' => $courses->total(),
//                 'current_page' => $courses->currentPage(),
//                 'last_page' => $courses->lastPage(),
//                 'per_page' => $courses->perPage(),
//             ],
//         ]);
//     }

// get list of instructors list of their courses.
    public function getListInstructorCourses(Request $request)
    {
        $user = auth()->user();
        $title = $request->input('title', null);
        $status = $request->input('status', null);
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);
        $priceOrder = $request->input('price_order', null);


        $query = Course::where('created_by', $user->id);
        if ($status) {
            $query->where('status', $status);
        }
        if ($title) {
            $query->where('title', 'like', '%' . $title . '%');
        }
        if ($priceOrder && in_array(strtolower($priceOrder), ['asc', 'desc'])) {
            $query->orderBy('price', strtolower($priceOrder));
        } else {
            $query->orderBy('created_at', 'desc');
        }
        $courses = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'OK',
            'data' => $courses->items(),
            'pagination' => [
                'total' => $courses->total(),
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
            ],
            'code' => 200
        ]);
    }

}
