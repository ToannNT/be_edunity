<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lecture;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use FFMpeg\FFMpeg;

class LectureController extends Controller
{
    public function index(Request $request)
    {
        // Pagination setup
        $perPage = $request->get('per_page', 10);
        $currentPage = $request->get('page', 1);

        // Fetch lectures with related section
        $lectures = Lecture::with('section')->get();

        $total = $lectures->count();
        $paginatedLectures = $lectures->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginationData = [
            'data' => $paginatedLectures,
            'current_page' => $currentPage,
            'last_page' => (int) ceil($total / $perPage),
            'per_page' => $perPage,
            'total' => $total,
        ];

        return formatResponse(STATUS_OK, $paginationData, '', __('messages.lecture_fetch_success'));
    }

    public function show($id)
    {
        $lecture = Lecture::find($id);
        if (!$lecture) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.lecture_not_found'));
        }

        return formatResponse(STATUS_OK, $lecture, '', __('messages.lecture_detail_success'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        // Kiểm tra nếu là instructor thì section phải thuộc sở hữu của họ
        if ($user->role === 'instructor') {
            $section = \App\Models\Section::where('id', $request->section_id)
                ->where('created_by', $user->id)
                ->first();

            if (!$section) {
                return formatResponse(
                    STATUS_FAIL,
                    '',
                    __('messages.section_not_owned'),
                    __('messages.unauthorized_action')
                );
            }
        }

        $validator = Validator::make($request->all(), [
            'section_id' => 'required|exists:sections,id',
            'type' => 'required|in:video,file',
            'title' => 'required|string|max:255',
            'content' => 'required|file|mimes:mp4,pdf|max:20480',
            'preview' => 'required|in:can,cant',
            'status' => 'required|in:active,inactive',
            'order' => [
                'required',
                'integer',
                'min:0',
                Rule::unique('lectures')->where(function ($query) use ($request) {
                    return $query->where('section_id', $request->section_id);
                })
            ],
        ], [
            'section_id.required' => __('messages.section_id_required'),
            'section_id.exists' => __('messages.section_id_invalid'),
            'type.required' => __('messages.type_required'),
            'type.in' => __('messages.type_invalid'),
            'title.required' => __('messages.title_required'),
            'title.max' => __('messages.title_max'),
            'content.required' => __('messages.content_required'),
            'content.file' => __('messages.content_file'),
            'content.mimes' => __('messages.content_mimes'),
            'content.max' => __('messages.content_max'),
            'preview.required' => __('messages.preview_required'),
            'preview.in' => __('messages.preview_invalid'),
            'status.required' => __('messages.status_required'),
            'status.in' => __('messages.status_invalid'),
            'order.required' => __('messages.order_required'),
            'order.unique' => __('messages.order_unique'),
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }

        $contentPath = $this->uploadContent($request);

        $lecture = new Lecture();
        $lecture->section_id = $request->section_id;
        $lecture->type = $request->type;
        $lecture->title = $request->title;
        $lecture->content_link = $contentPath;

        if ($request->type === 'video') {
            $lecture->duration = $this->getVideoDuration($contentPath);
        } else {
            $lecture->duration = $this->getPdfPageCount($contentPath);
        }

        $lecture->preview = $request->preview;
        $lecture->status = $request->status;
        $lecture->order = $request->order;
        $lecture->created_by = $user->id;
        $lecture->save();

        return formatResponse(STATUS_OK, $lecture, '', __('messages.lecture_create_success'));
    }


    private function uploadContent(Request $request)
    {
        // Xác định thư mục lưu trữ dựa trên loại file
        $folder = $request->type === 'video' ? 'lectures/videos' : 'lectures/files';

        // Tải lên file vào thư mục tương ứng
        $path = $request->file('content')->storePublicly($folder);

        if (!$path) {
            throw new \Exception(__('messages.content_upload_failed'));
        }

        // Trả về đường dẫn đầy đủ của file
        $contentUrl = env('URL_IMAGE_S3') . $path;
        return $contentUrl;
    }


    private function getVideoDuration($path)
    {
        $ffmpeg = FFMpeg::create();
        $video = $ffmpeg->open(Storage::path($path));
        $duration = $video->getStreams()->videos()->first()->get('duration');
        return (int) $duration;
    }

    private function getPdfPageCount($path)
    {
        $pdf = new \setasign\Fpdi\Fpdi();
        $file = Storage::path($path);
        $pdf->setSourceFile($file);
        $pageCount = $pdf->setSourceFile($file);
        return $pageCount;
    }


    public function update(Request $request, $id)
    {
        $user = auth()->user();

        $lecture = Lecture::find($id);
        if (!$lecture) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.lecture_not_found'));
        }

        // Kiểm tra quyền sở hữu section nếu là instructor
        if ($user->role === 'instructor') {
            $section = \App\Models\Section::where('id', $request->section_id ?? $lecture->section_id)
                ->where('created_by', $user->id)
                ->first();

            if (!$section) {
                return formatResponse(
                    STATUS_FAIL,
                    '',
                    __('messages.section_not_owned'),
                    __('messages.unauthorized_action')
                );
            }
        }

        $validator = Validator::make($request->all(), [
            'section_id' => 'nullable|exists:sections,id',
            'type' => 'nullable|in:video,file',
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|file|mimes:mp4,pdf|max:20480',
            'preview' => 'nullable|in:can,cant',
            'status' => 'nullable|in:active,inactive',
            'order' => [
                'nullable',
                'integer',
                'min:0',
                Rule::unique('lectures')->where(function ($query) use ($request) {
                    return $query->where('section_id', $request->section_id);
                })->ignore($id),
            ],
        ], [
            'section_id.exists' => __('messages.section_id_invalid'),
            'type.in' => __('messages.type_invalid'),
            'title.max' => __('messages.title_max'),
            'content.file' => __('messages.content_file'),
            'content.mimes' => __('messages.content_mimes'),
            'content.max' => __('messages.content_max'),
            'preview.in' => __('messages.preview_invalid'),
            'status.in' => __('messages.status_invalid'),
            'order.unique' => __('messages.order_unique'),
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }

        // Kiểm tra và xử lý nội dung tệp mới nếu được cung cấp
        if ($request->hasFile('content')) {
            Storage::delete($lecture->content_link);
            $contentPath = $this->uploadContent($request);
            $lecture->content_link = $contentPath;

            if ($request->type === 'video') {
                $lecture->duration = $this->getVideoDuration($contentPath);
            } else {
                $lecture->duration = $this->getPdfPageCount($contentPath);
            }
        }

        // Cập nhật các trường khác
        $lecture->fill($request->only(['section_id', 'type', 'title', 'preview', 'status', 'order']));
        $lecture->updated_by = $user->id;
        $lecture->save();

        return formatResponse(STATUS_OK, $lecture, '', __('messages.lecture_update_success'));
    }


    public function destroy($id)
    {
        $lecture = Lecture::find($id);
        if (!$lecture) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.lecture_not_found'));
        }

        $lecture->deleted_by = auth()->id();
        $lecture->save();
        $lecture->delete();

        return formatResponse(STATUS_OK, '', '', __('messages.lecture_soft_delete_success'));
    }

    public function restore($id)
    {
        $lecture = Lecture::onlyTrashed()->find($id);
        if (!$lecture) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.lecture_not_found'));
        }

        $lecture->deleted_by = null;
    }
}
