<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Section;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class SectionController extends Controller
{
    // Lấy danh sách sections trong một course
    public function getListByCourse(Request $request)
    {
        $courseId = $request->get('course_id');
        $limit = $request->get('limit', null);
        $perPage = $request->get('per_page', 10);
        $currentPage = $request->get('page', 1);

        $sectionsQuery = Section::where('course_id', $courseId);

        if ($limit) {
            $sections = $sectionsQuery->limit($limit)->get();
            $total = $sections->count();
            $sections = $sections->forPage($currentPage, $perPage)->values();

            $paginatedSections = new \Illuminate\Pagination\LengthAwarePaginator(
                $sections,
                $total,
                $perPage,
                $currentPage,
                ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()]
            );

            $paginationData = $paginatedSections->toArray();

            return response()->json(['status' => 'success', 'data' => $paginationData]);
        } else {
            $sections = $sectionsQuery->paginate($perPage, ['*'], 'page', $currentPage);
            return response()->json(['status' => 'success', 'data' => $sections]);
        }
    }

    // Tạo mới section
    public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'status' => 'required|in:active,inactive',
        'course_id' => 'required|exists:courses,id',
        'order' => [
            'integer',
            Rule::unique('sections')->where(function ($query) use ($request) {
                return $query->where('course_id', $request->course_id);
            }),
        ],
    ]);

    if ($validator->fails()) {
        return response()->json(['status' => 'fail', 'errors' => $validator->errors()]);
    }

    // Xác định order tối ưu nếu không có `order` truyền vào
    $order = $request->order;
    if (!$order) {
        $maxOrder = Section::where('course_id', $request->course_id)->max('order');
        $order = $maxOrder ? $maxOrder + 10 : 10; // Đặt `order` cách nhau 10 đơn vị
    }

    $section = new Section();
    $section->title = $request->title;
    $section->description = $request->description;
    $section->status = $request->status;
    $section->course_id = $request->course_id;
    $section->order = $order;
    $section->created_by = auth()->id();
    $section->save();

    return response()->json(['status' => 'success', 'data' => $section]);
}


    // Hiển thị chi tiết một section cụ thể
    public function show($id)
    {
        $section = Section::find($id);
        if (!$section) {
            return response()->json(['status' => 'fail', 'message' => 'Section not found']);
        }

        return response()->json(['status' => 'success', 'data' => $section]);
    }

    // Cập nhật section
    public function update(Request $request, $id)
{
    $section = Section::find($id);
    if (!$section) {
        return response()->json(['status' => 'fail', 'message' => 'Section not found']);
    }

    $validator = Validator::make($request->all(), [
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'status' => 'required|in:active,inactive',
        'order' => [
            'integer',
            Rule::unique('sections')->where(function ($query) use ($request, $section) {
                return $query->where('course_id', $section->course_id);
            })->ignore($section->id),
        ],
    ]);

    if ($validator->fails()) {
        return response()->json(['status' => 'fail', 'errors' => $validator->errors()]);
    }

    $section->title = $request->title;
    $section->description = $request->description;
    $section->status = $request->status;
    $section->order = $request->order ?? $section->order;
    $section->updated_by = auth()->id();
    $section->save();

    return response()->json(['status' => 'success', 'data' => $section]);
}

    // Xóa mềm section
    public function destroy($id)
    {
        $section = Section::find($id);
        if (!$section) {
            return response()->json(['status' => 'fail', 'message' => 'Section not found']);
        }

        $section->deleted_by = auth()->id();
        $section->save();
        $section->delete();

        return response()->json(['status' => 'success', 'message' => 'Section deleted successfully']);
    }

    // Khôi phục section bị xóa mềm
    public function restore($id)
    {
        $section = Section::onlyTrashed()->find($id);
        if (!$section) {
            return response()->json(['status' => 'fail', 'message' => 'Section not found or not deleted']);
        }

        $section->deleted_by = null;
        $section->save();
        $section->restore();

        return response()->json(['status' => 'success', 'message' => 'Section restored successfully']);
    }

    // Xóa vĩnh viễn section
    public function forceDelete($id)
    {
        $section = Section::onlyTrashed()->find($id);
        if (!$section) {
            return response()->json(['status' => 'fail', 'message' => 'Section not found']);
        }

        $section->forceDelete();

        return response()->json(['status' => 'success', 'message' => 'Section permanently deleted']);
    }
}
