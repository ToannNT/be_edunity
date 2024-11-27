<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Question;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Pagination\LengthAwarePaginator;

class QuestionController extends Controller
{
    // Lấy danh sách câu hỏi
    public function getListAdmin(Request $request)
    {
        $questionsQuery = Question::query();

        if ($request->has('deleted') && $request->deleted == 1) {
            // Lấy các language đã xóa
            $questionsQuery->onlyTrashed();
        } else {
            // Chỉ lấy các language chưa xóa (mặc định)
            $questionsQuery->whereNull('deleted_at');
        }
        // Lọc theo quiz_id
        if ($request->has('quiz_id')) {
            $questionsQuery->where('quiz_id', $request->quiz_id);
        }

        // Lọc theo keyword
        if ($request->has('keyword') && !empty($request->keyword)) {
            $questionsQuery->where('question', 'like', '%' . $request->keyword . '%');
        }

        // Lọc theo status
        if ($request->has('status')) {
            $questionsQuery->where('status', $request->status);
        }

        // Sắp xếp
        $order = $request->get('order', 'asc'); // Mặc định là asc
        $questionsQuery->orderBy('created_at', $order);

        // Phân trang
        $perPage = (int)$request->get('per_page', 10);
        $page = (int)$request->get('page', 1);

        $questions = $questionsQuery->get();
        $total = $questions->count();
        $paginatedQuestions = $questions->forPage($page, $perPage)->values();

        $pagination = new LengthAwarePaginator(
            $paginatedQuestions,
            $total,
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]
        );

        return formatResponse(
            STATUS_OK,
            $pagination->toArray(),
            '',
            __('messages.question_fetch_success')
        );
    }
    public function editForm($id)
    {
        // Tìm question theo ID
        $question = Question::find($id);

        if (!$question) {
            return formatResponse(
                STATUS_FAIL,
                '',
                '',
                __('messages.question_not_found')
            );
        }

        // Chuẩn bị dữ liệu trả về
        $response = [
            'id' => $question->id,
            'quiz_id' => $question->quiz_id,
            'question' => $question->question,
            'options' => json_decode($question->options), // Giải mã JSON để trả về dạng mảng
            'answer' => $question->answer,
            'status' => $question->status,
            'order' => $question->order,
            'created_at' => $question->created_at,
            'updated_at' => $question->updated_at,
        ];

        return formatResponse(
            STATUS_OK,
            $response,
            '',
            __('messages.question_edit_form_success')
        );
    }


    // Thêm mới câu hỏi
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'quiz_id' => 'required|exists:quizzes,id',
            'question' => 'required|string',
            'options' => 'required|json',
            'answer' => 'required|string',
            'status' => 'required|in:active,inactive',
        ], [
            'quiz_id.required' => __('messages.quiz_required'), // Sửa message
            'quiz_id.exists' => __('messages.quiz_not_found'), // Sửa message khi không tồn tại
            'question.required' => __('messages.question_required'),
            'options.required' => __('messages.options_required'),
            'answer.required' => __('messages.answer_required'),
            'status.required' => __('messages.status_required'),
            'status.in' => __('messages.status_invalid'),
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }

        $maxOrder = Question::where('quiz_id', $request->quiz_id)->max('order') ?? 0;

        $question = new Question();
        $question->quiz_id = $request->quiz_id;
        $question->question = $request->question;
        $question->options = $request->options;
        $question->answer = $request->answer;
        $question->status = $request->status;
        $question->order = $request->order ?? $maxOrder + 1; // Lấy order lớn nhất + 1 nếu không nhập
        $question->created_by = auth()->id();
        $question->save();

        return formatResponse(STATUS_OK, $question, '', __('messages.question_create_success'));
    }


    // Cập nhật câu hỏi
    public function update(Request $request, $id)
    {
        $question = Question::find($id);
        if (!$question) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.question_not_found'));
        }

        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'options' => 'required|json',
            'answer' => 'required|string',
            'status' => 'required|in:active,inactive',
        ], [
            'question.required' => __('messages.question_required'),
            'options.required' => __('messages.options_required'),
            'answer.required' => __('messages.answer_required'),
            'status.required' => __('messages.status_required'),
            'status.in' => __('messages.status_invalid'),
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }

        $question->question = $request->question;
        $question->options = $request->options;
        $question->answer = $request->answer;
        $question->status = $request->status;
        $question->order = $request->order ?? $question->order;
        $question->updated_by = auth()->id();
        $question->save();

        return formatResponse(STATUS_OK, $question, '', __('messages.question_update_success'));
    }

    // Xóa mềm câu hỏi
    public function destroy($id)
    {
        $question = Question::find($id);
        if (!$question) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.question_not_found'));
        }

        $question->deleted_by = auth()->id();
        $question->save();
        $question->delete();

        return formatResponse(STATUS_OK, $question, '', __('messages.question_soft_delete_success'));
    }

    // Khôi phục câu hỏi
    public function restore($id)
    {
        $question = Question::onlyTrashed()->find($id);
        if (!$question) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.question_not_found'));
        }

        $question->deleted_by = null;
        $question->restore();

        return formatResponse(STATUS_OK, $question, '', __('messages.question_restore_success'));
    }

    // Xóa vĩnh viễn câu hỏi
    public function forceDelete($id)
    {
        $question = Question::onlyTrashed()->find($id);
        if (!$question) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.question_not_found'));
        }

        $question->forceDelete();

        return formatResponse(STATUS_OK, $question, '', __('messages.question_force_delete_success'));
    }
}
