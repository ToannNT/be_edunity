<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NoteController extends Controller
{
    public function index($course_id)
    {
        $user = auth()->user();
        $notes = Note::where('user_id', $user->id)
            ->where('course_id', $course_id)
            ->with(['lecture:id,title', 'section:id,title'])
            ->select('id', 'lecture_id', 'section_id', 'current_time', 'lecture_title', 'content', 'created_at', 'updated_at')
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json([
            'status' => 'OK',
            'data' => $notes,
            'message' => 'Get list success'
        ], 200);
    }

    public function show($id)
    {
        $user = auth()->user();
        $note = $user->notes()
            ->with(['lecture:id,title', 'section:id,title'])
            ->find($id);

        if (!$note) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Note not found'
            ], 404);
        }

        return response()->json([
            'status' => 'OK',
            'data' => $note,
            'message' => 'Get detail success'
        ], 200);
    }

    // Tạo mới ghi chú
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|exists:courses,id',
            'section_id' => 'required|exists:sections,id',
            'lecture_id' => 'required|exists:lectures,id',
            'current_time' => 'required|integer|min:0',
            'lecture_title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'FAIL',
                'errors' => $validator->errors()
            ], 422);
        }
        $data = $validator->validated();
        $data['user_id'] = auth()->id();
        $note = Note::create($data);
        $note->load(['lecture:id,title', 'section:id,title']);

        return response()->json([
            'status' => 'OK',
            'data' => $note,
            'message' => 'Create note success'
        ], 201);
    }

    // Cập nhật ghi chú
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $note = $user->notes()->find($id);

        if (!$note) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Note not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'course_id' => 'sometimes|required|exists:courses,id',
            'section_id' => 'sometimes|required|exists:sections,id',
            'lecture_id' => 'sometimes|required|exists:lectures,id',
            'current_time' => 'sometimes|required|integer|min:0',
            'lecture_title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'FAIL',
                'errors' => $validator->errors()
            ], 422);
        }

        $note->update($validator->validated());

        // Tải lại quan hệ lecture và section
        $note->load(['lecture:id,title', 'section:id,title']);

        return response()->json([
            'status' => 'OK',
            'data' => $note,
            'message' => 'Update note success'
        ], 200);
    }

    // Xóa ghi chú
    public function destroy($id)
    {
        $user = auth()->user();
        $note = $user->notes()->find($id);

        if (!$note) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Note not found'
            ], 404);
        }

        $note->delete();

        return response()->json([
            'status' => 'OK',
            'message' => 'Note deleted success'
        ], 200);
    }
}
