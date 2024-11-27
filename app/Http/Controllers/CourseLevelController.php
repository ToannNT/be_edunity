<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CourseLevel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Pagination\LengthAwarePaginator;

class CourseLevelController extends Controller
{
    public function getListAdmin(Request $request)
    {
        // Query cơ bản lấy danh sách CourseLevel
        $courseLevelsQuery = CourseLevel::query();

        // Kiểm tra tham số `deleted`
        if ($request->has('deleted') && $request->deleted == 1) {
            // Lấy các course level đã xóa
            $courseLevelsQuery->onlyTrashed();
        } else {
            // Chỉ lấy các course level chưa xóa (mặc định)
            $courseLevelsQuery->whereNull('deleted_at');
        }

        // Lọc theo keyword (nếu có)
        if ($request->has('keyword') && !empty($request->keyword)) {
            $keyword = $request->keyword;
            $courseLevelsQuery->where('name', 'like', '%' . $keyword . '%');
        }

        // Lọc theo status (nếu có)
        if ($request->has('status') && !is_null($request->status)) {
            $courseLevelsQuery->where('status', $request->status);
        }

        // Sắp xếp theo `created_at` (mặc định là `desc`)
        $order = $request->get('order', 'desc'); // Giá trị mặc định là desc
        $courseLevelsQuery->orderBy('created_at', $order);

        // Phân trang với per_page và page
        $perPage = (int) $request->get('per_page', 10); // Số lượng bản ghi mỗi trang, mặc định 10
        $page = (int) $request->get('page', 1); // Trang hiện tại, mặc định 1

        // Lấy danh sách đã lọc
        $courseLevels = $courseLevelsQuery->get();

        // Tổng số lượng bản ghi
        $total = $courseLevels->count();

        // Phân trang thủ công
        $paginatedCourseLevels = $courseLevels->forPage($page, $perPage)->values();

        // Tạo đối tượng LengthAwarePaginator
        $pagination = new LengthAwarePaginator(
            $paginatedCourseLevels, // Dữ liệu cho trang hiện tại
            $total,                 // Tổng số bản ghi
            $perPage,               // Số lượng bản ghi mỗi trang
            $page,                  // Trang hiện tại
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(), // Đường dẫn chính
                'query' => $request->query() // Lấy tất cả query parameters hiện tại
            ]
        );

        // Chuyển đổi dữ liệu phân trang thành mảng
        $courseLevels = $pagination->toArray();

        // Trả về kết quả với đầy đủ thông tin filter, order và phân trang
        return formatResponse(
            STATUS_OK,
            $courseLevels,
            '',
            __('messages.course_level_fetch_success')
        );
    }

    // Lấy danh sách các cấp độ khóa học
    public function index(Request $request)
    {
        // Query cơ bản lấy danh sách CourseLevel và lọc theo status
        $courseLevelsQuery = CourseLevel::query()->where('status', 'active');

        // Kiểm tra tham số limit để lấy dữ liệu giới hạn hoặc phân trang
        if ($request->has('limit')) {
            $limit = $request->get('limit');
            $courseLevels = $courseLevelsQuery->limit($limit)->get();
        } else {
            $perPage = $request->get('per_page', 10); // Số lượng bản ghi mỗi trang (mặc định: 10)
            $currentPage = $request->get('page', 1); // Trang hiện tại (mặc định: 1)
            $courseLevels = $courseLevelsQuery->paginate($perPage, ['*'], 'page', $currentPage);
        }

        // Trả về dữ liệu
        return formatResponse(
            STATUS_OK,
            $courseLevels,
            '',
            __('messages.course_level_fetch_success')
        );
    }


    // Hiển thị một cấp độ cụ thể
    public function show($id)
    {
        $courseLevel = CourseLevel::find($id);
        if (!$courseLevel) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.course_level_not_found'));
        }

        return formatResponse(STATUS_OK, $courseLevel, '', __('messages.course_level_detail_success'));
    }

    // Tạo mới cấp độ khóa học
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:course_levels',
            'status' => 'required|in:active,inactive',
        ], [
            'name.required' => __('messages.name_course_level_required'),
            'name.string' => __('messages.name_course_level_string'),
            'name.max' => __('messages.name_course_level_max'),
            'name.unique' => __('messages.name_course_level_unique'),
            'status.required' => __('messages.status_required'),
            'status.in' => __('messages.status_invalid'),
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }

        $courseLevel = new CourseLevel();
        $courseLevel->name = $request->name;
        $courseLevel->status = $request->status;
        $courseLevel->created_by = auth()->id();
        $courseLevel->save();

        return formatResponse(STATUS_OK, $courseLevel, '', __('messages.course_level_create_success'));
    }

    // Cập nhật cấp độ khóa học
    public function update(Request $request, $id)
    {
        $courseLevel = CourseLevel::find($id);
        if (!$courseLevel) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.course_level_not_found'));
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('course_levels')->ignore($courseLevel->id),
            ],
            'status' => 'required|in:active,inactive',
        ], [
            'name.required' => __('messages.name_course_level_required'),
            'name.string' => __('messages.name_course_level_string'),
            'name.max' => __('messages.name_course_level_max'),
            'name.unique' => __('messages.name_course_level_unique'),
            'status.required' => __('messages.status_required'),
            'status.in' => __('messages.status_invalid'),
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }

        $courseLevel->name = $request->name;
        $courseLevel->status = $request->status;
        $courseLevel->created_by = $request->created_by;

        $courseLevel->updated_by = auth()->id();
        $courseLevel->save();

        return formatResponse(STATUS_OK, $courseLevel, '', __('messages.course_level_update_success'));
    }

    // Xóa mềm cấp độ khóa học
    public function destroy($id)
    {
        $courseLevel = CourseLevel::find($id);
        if (!$courseLevel) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.course_level_not_found'));
        }

        $courseLevel->deleted_by = auth()->id();
        $courseLevel->save();
        $courseLevel->delete();
        $courseLevel = CourseLevel::onlyTrashed()->find($id);
        return formatResponse(STATUS_OK, $courseLevel, '', __('messages.course_level_soft_delete_success'));
    }

    // Khôi phục cấp độ khóa học bị xóa mềm
    public function restore($id)
    {
        $courseLevel = CourseLevel::onlyTrashed()->find($id);
        if (!$courseLevel) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.course_level_not_found'));
        }

        $courseLevel->deleted_by = null;
        $courseLevel->restore();
        $courseLevel = CourseLevel::find($id);
        return formatResponse(STATUS_OK, $courseLevel, '', __('messages.course_level_restore_success'));
    }

    // Xóa vĩnh viễn cấp độ khóa học
    public function forceDelete($id)
    {
        $courseLevel = CourseLevel::onlyTrashed()->find($id);
        if (!$courseLevel) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.course_level_not_found'));
        }

        $courseLevel->forceDelete();

        return formatResponse(STATUS_OK, $courseLevel, '', __('messages.course_level_force_delete_success'));
    }
}
