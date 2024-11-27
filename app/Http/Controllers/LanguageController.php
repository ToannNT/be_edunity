<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Language;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Pagination\LengthAwarePaginator;

class LanguageController extends Controller
{
    public function getListAdmin(Request $request)
    {
        // Query cơ bản lấy danh sách Language
        $languagesQuery = Language::query();

        // Kiểm tra tham số `deleted`
        if ($request->has('deleted') && $request->deleted == 1) {
            // Lấy các language đã xóa
            $languagesQuery->onlyTrashed();
        } else {
            // Chỉ lấy các language chưa xóa (mặc định)
            $languagesQuery->whereNull('deleted_at');
        }

        // Lọc theo keyword (nếu có)
        if ($request->has('keyword') && !empty($request->keyword)) {
            $keyword = $request->keyword;
            $languagesQuery->where('name', 'like', '%' . $keyword . '%');
        }

        // Lọc theo status (nếu có)
        if ($request->has('status') && !is_null($request->status)) {
            $languagesQuery->where('status', $request->status);
        }

        // Sắp xếp theo `created_at` (mặc định là `desc`)
        $order = $request->get('order', 'desc'); // Giá trị mặc định là desc
        $languagesQuery->orderBy('created_at', $order);

        // Phân trang với per_page và page
        $perPage = (int) $request->get('per_page', 10); // Số lượng bản ghi mỗi trang, mặc định 10
        $page = (int) $request->get('page', 1); // Trang hiện tại, mặc định 1

        // Lấy danh sách đã lọc
        $languages = $languagesQuery->get();

        // Tổng số lượng bản ghi
        $total = $languages->count();

        // Phân trang thủ công
        $paginatedLanguages = $languages->forPage($page, $perPage)->values();

        // Tạo đối tượng LengthAwarePaginator
        $pagination = new LengthAwarePaginator(
            $paginatedLanguages, // Dữ liệu cho trang hiện tại
            $total,               // Tổng số bản ghi
            $perPage,             // Số lượng bản ghi mỗi trang
            $page,                // Trang hiện tại
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(), // Đường dẫn chính
                'query' => $request->query() // Lấy tất cả query parameters hiện tại
            ]
        );

        // Chuyển đổi dữ liệu phân trang thành mảng
        $languages = $pagination->toArray();

        // Trả về kết quả với đầy đủ thông tin filter, order và phân trang
        return formatResponse(
            STATUS_OK,
            $languages,
            '',
            __('messages.language_fetch_success')
        );
    }

    // Lấy danh sách các ngôn ngữ
    public function index(Request $request)
    {
        $languagesQuery = Language::where('status', 'active');
        if ($request->has('limit')) {
            $limit = $request->get('limit');
            $languages = $languagesQuery->limit($limit)->get();
        } else {
            $perPage = $request->get('per_page', 10);
            $currentPage = $request->get('page', 1);
            $languages = $languagesQuery->paginate($perPage, ['*'], 'page', $currentPage);
        }

        return formatResponse(STATUS_OK, $languages, '', __('messages.language_fetch_success'));
    }

    // Hiển thị chi tiết ngôn ngữ cụ thể
    public function show($id)
    {
        $language = Language::find($id);
        if (!$language) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.language_not_found'));
        }

        return formatResponse(STATUS_OK, $language, '', __('messages.language_detail_success'));
    }

    // Tạo mới ngôn ngữ
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:languages',
            'status' => 'required|in:active,inactive',
        ], [
            'name.required' => __('messages.name_language_required'),
            'name.string' => __('messages.name_language_string'),
            'name.max' => __('messages.name_language_max'),
            'name.unique' => __('messages.name_language_unique'),
            'status.required' => __('messages.status_required'),
            'status.in' => __('messages.status_invalid'),
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }

        $language = new Language();
        $language->name = $request->name;
        $language->description = $request->description;
        $language->status = $request->status;
        $language->created_by = auth()->id();
        $language->save();

        return formatResponse(STATUS_OK, $language, '', __('messages.language_create_success'));
    }

    // Cập nhật ngôn ngữ
    public function update(Request $request, $id)
    {
        $language = Language::find($id);
        if (!$language) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.language_not_found'));
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('languages')->ignore($language->id),
            ],
            'status' => 'required|in:active,inactive',
        ], [
            'name.required' => __('messages.name_language_required'),
            'name.string' => __('messages.name_language_string'),
            'name.max' => __('messages.name_language_max'),
            'name.unique' => __('messages.name_language_unique'),
            'status.required' => __('messages.status_required'),
            'status.in' => __('messages.status_invalid'),
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }

        $language->name = $request->name;
        $language->description = $request->description;
        $language->status = $request->status;
        $language->updated_by = auth()->id();
        $language->save();

        return formatResponse(STATUS_OK, $language, '', __('messages.language_update_success'));
    }

    // Xóa mềm ngôn ngữ
    public function destroy($id)
    {
        $language = Language::find($id);
        if (!$language) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.language_not_found'));
        }

        $language->deleted_by = auth()->id();
        $language->save();
        $language->delete();
        $language = Language::onlyTrashed()->find($id);

        return formatResponse(STATUS_OK, $language, '', __('messages.language_soft_delete_success'));
    }

    // Khôi phục ngôn ngữ bị xóa mềm
    public function restore($id)
    {
        $language = Language::onlyTrashed()->find($id);
        if (!$language) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.language_not_found'));
        }

        $language->deleted_by = null;
        $language->restore();
        $language = Language::find($id);
        return formatResponse(STATUS_OK, $language, '', __('messages.language_restore_success'));
    }

    // Xóa vĩnh viễn ngôn ngữ
    public function forceDelete($id)
    {
        $language = Language::onlyTrashed()->find($id);
        if (!$language) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.language_not_found'));
        }

        $language->forceDelete();

        return formatResponse(STATUS_OK, $language, '', __('messages.language_force_delete_success'));
    }
}
