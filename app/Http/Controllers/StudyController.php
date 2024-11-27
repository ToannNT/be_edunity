<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Course;
use App\Models\Section;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Lecture;
use App\Models\AnswerUser;
use App\Models\ProgressLecture;
use App\Models\ProgressQuiz;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudyController extends Controller
{

    public function getUserCourses(Request $request)
    {
        $userId = Auth::id();

        if (!$userId) {
            return formatResponse(
                STATUS_FAIL,
                '',
                '',
                __('messages.user_not_logged_in')
            );
        }

        // Lấy các đơn hàng với trạng thái thanh toán là 'paid'
        $orderIds = Order::where('user_id', $userId)
            ->where('payment_status', 'paid')
            ->pluck('id');

        if ($orderIds->isEmpty()) {
            return formatResponse(
                STATUS_FAIL,
                '',
                '',
                __('messages.no_courses_found') // Thông báo: không có khóa học nào
            );
        }

        // Lấy course_id từ OrderItem liên kết với các orderIds
        $courseIds = OrderItem::whereIn('order_id', $orderIds)
            ->pluck('course_id');

        if ($courseIds->isEmpty()) {
            return formatResponse(
                STATUS_FAIL,
                '',
                '',
                __('messages.no_courses_found') // Thông báo: không có khóa học nào
            );
        }

        // Lấy tất cả các khóa học đã mua và có trạng thái active
        $courses = Course::whereIn('id', $courseIds)
            ->where('status', 'active')
            ->get()
            ->map(function ($course) use ($userId) {
                // Lấy tổng số lecture của course qua Section và Lecture
                $totalLectures = Lecture::where('status', 'active')
                    ->whereIn('section_id', Section::where('course_id', $course->id)->pluck('id'))
                    ->count();

                // Lấy số lượng lecture đã hoàn thành trong ProgressLecture
                $completedLectures = ProgressLecture::where('user_id', $userId)
                    ->where('percent', '>=', 100) // Thêm điều kiện percent >= 100
                    ->whereIn('lecture_id', Lecture::where('status', 'active')
                        ->whereIn('section_id', Section::where('course_id', $course->id)->pluck('id'))
                        ->pluck('id'))
                    ->count();


                // Lấy thông tin creator
                $creatorName = $course->creator && ($course->creator->last_name || $course->creator->first_name)
                    ? trim($course->creator->last_name . ' ' . $course->creator->first_name)
                    : '';

                return [
                    'id' => $course->id ?? null,
                    'thumbnail' => $course->thumbnail ?? null,
                    'title' => $course->title ?? null,
                    'creator' => $creatorName ?? null,
                    'total_lectures' => $totalLectures ?? null,
                    'completed_lectures' => $completedLectures ?? null,
                    'progress_percent' => $totalLectures > 0
                        ? round(($completedLectures / $totalLectures) * 100)
                        : 0, // Tính phần trăm và làm tròn đến số nguyên
                ];
            });

        if ($courses->isEmpty()) {
            return formatResponse(
                STATUS_FAIL,
                '',
                '',
                __('messages.no_courses_found') // Thông báo: không có khóa học nào
            );
        }

        // Lọc các khóa học dựa trên request search
        $titleSearch = $request->input('title', null); // Tìm theo title
        $creatorSearch = $request->input('creator', null); // Tìm theo creator

        if ($titleSearch || $creatorSearch) {
            $courses = $courses->filter(function ($course) use ($titleSearch, $creatorSearch) {
                $matchTitle = $titleSearch ? stripos($course['title'], $titleSearch) !== false : false;
                $matchCreator = $creatorSearch ? stripos($course['creator'], $creatorSearch) !== false : false;
                return $matchTitle || $matchCreator; // Sử dụng OR để gộp kết quả
            })->values(); // Reset index của collection
        }

        if ($courses->isEmpty()) {
            return formatResponse(
                STATUS_FAIL,
                '',
                '',
                __('messages.no_courses_found') // Thông báo: không có khóa học nào
            );
        }

        return formatResponse(
            STATUS_OK,
            $courses,
            '',
            __('messages.courses_retrieved_successfully') // Thông báo: đã lấy thành công các khóa học của bạn
        );
    }



    public function getAllContent($userId, $courseId, $contentKeyword)
    {
        $sections = Section::where('course_id', $courseId)
            ->where('status', 'active')
            ->orderBy('order', 'asc')
            ->with([
                'lectures' => function ($query) use ($userId) {
                    $query->where('status', 'active')
                        ->select('id', 'section_id', 'title', 'order', 'duration', 'type')
                        ->orderBy('order', 'asc')
                        ->addSelect([
                            'content_section_type' => DB::raw('"lecture"'),
                        ])
                        ->with(['progress' => function ($progressQuery) use ($userId) {
                            $progressQuery->select('lecture_id', 'learned', 'percent')
                                ->where('status', 'active')
                                ->where('user_id', $userId);
                        }]);
                },
                'quizzes' => function ($query) use ($userId) {
                    $query->where('status', 'active')
                        ->select('id', 'section_id', 'title', 'order')
                        ->orderBy('order', 'asc')
                        ->addSelect([
                            'content_section_type' => DB::raw('"quiz"'),
                        ])
                        ->withCount('questions') // Đếm số lượng câu hỏi
                        ->with(['progress' => function ($progressQuery) use ($userId) {
                            $progressQuery->select('quiz_id', 'questions_done', 'percent')
                                ->where('status', 'active')
                                ->where('user_id', $userId);
                        }]);
                }
            ])->get();

        $sections = $sections->map(function ($section) {
            $sectionContent = collect($section->lectures)
                ->merge($section->quizzes)
                ->sortBy('order')
                ->values()
                ->map(function ($item) {
                    $progress = $item->progress->first() ?? null;

                    if ($item instanceof Lecture) {
                        $durationDisplay = null;

                        if ($item->type === 'video') {
                            $durationDisplay = $this->formatDuration($item->duration); // Chuyển đổi thời gian
                        } elseif ($item->type === 'file') {
                            $durationDisplay = $item->duration . " trang"; // Gắn thêm "trang"
                        }

                        return [
                            'id' => $item->id,
                            'title' => $item->title,
                            'order' => $item->order,
                            'content_section_type' => 'lecture',
                            'type' => $item->type,
                            'duration' => $item->duration, // Giữ nguyên duration
                            'duration_display' => $durationDisplay, // Tùy theo type
                            'learned' => $progress && isset($progress->learned) ? $progress->learned : null,
                            'percent' => $progress && isset($progress->percent) ? $progress->percent : null,
                        ];
                    } elseif ($item instanceof Quiz) {
                        return [
                            'id' => $item->id,
                            'title' => $item->title,
                            'order' => $item->order,
                            'content_section_type' => 'quiz',
                            'total_question_count' => $item->questions_count,
                            'percent' => $progress && isset($progress->percent) ? $progress->percent : null,
                            'questions_done' => $progress && isset($progress->questions_done) ? $progress->questions_done : null,
                        ];
                    }
                    return [];
                });

            // Tính tổng duration cho các lecture có type là 'video'
            $totalVideoDuration = $section->lectures
                ->where('type', 'video')
                ->sum('duration');

            return [
                'id' => $section->id,
                'title' => $section->title,
                'order' => $section->order,
                'content_course_type' => 'section',
                'content_count' => null, // Ban đầu là null
                'content_done' => null, // Ban đầu là null
                'duration_display' => $this->formatDuration($totalVideoDuration), // Tổng thời lượng định dạng
                'section_content' => $sectionContent,
            ];
        });



        // $quizzesForCourse = Quiz::where('course_id', $courseId)
        //     ->whereNull('section_id')
        //     ->where('status', 'active')
        //     ->select('id', 'course_id', 'title', 'order')
        //     ->orderBy('order', 'asc')
        //     ->addSelect([
        //         'content_course_type' => DB::raw('"quiz"'),
        //     ])
        //     ->withCount('questions') // Đếm số lượng câu hỏi
        //     ->with(['progress' => function ($query) use ($userId) {
        //         $query->select('quiz_id', 'questions_done', 'percent')
        //             ->where('status', 'active')
        //             ->where('user_id', $userId);
        //     }])
        //     ->get()
        //     ->map(function ($quiz) {
        //         $progress = $quiz->progress ?? null;

        //         return [
        //             'id' => $quiz->id,
        //             'title' => $quiz->title,
        //             'order' => $quiz->order,
        //             'content_course_type' => 'quiz',
        //             'total_question_count' => $quiz->questions_count, // Sử dụng questions_count từ withCount
        //             'section_content' => [],
        //             'questions_done' => $progress && isset($progress->questions_done) ? $progress->questions_done : null,
        //             'percent' => $progress && isset($progress->percent) ? $progress->percent : null,
        //         ];
        //     });

        $sections = $sections->map(function ($section) {
            // Lọc chỉ các lecture trong section_content
            $lectures = $section['section_content']->where('content_section_type', 'lecture');

            $contentCount = $lectures->count(); // Tổng số lecture trong section
            $contentDone = $lectures->where('percent', '>=', 100)->count(); // Tổng số lecture hoàn thành

            $section['content_count'] = $contentCount;
            $section['content_done'] = $contentDone;

            return $section;
        });
        $sections = $sections->map(function ($section) {
            // Lọc lecture và quiz trong section_content
            $lectures = $section['section_content']->where('content_section_type', 'lecture');
            $quizzes = $section['section_content']->where('content_section_type', 'quiz');
        
            // Tổng số lecture và lecture hoàn thành
            $contentCount = $lectures->count();
            $contentDone = $lectures->where('percent', '>=', 100)->count();
        
            // Tổng số quiz và quiz hoàn thành
            $quizCount = $quizzes->count();
            $quizDone = $quizzes->where('percent', '>=', 100)->count();
        
            // Tổng cộng tất cả nội dung và hoàn thành
            $totalCount = $contentCount + $quizCount; // Tổng số nội dung (lecture + quiz)
            $totalDone = $contentDone + $quizDone;   // Tổng số nội dung hoàn thành
        
            // Gắn thông tin vào section
            $section['content_count'] = $contentCount;
            $section['content_done'] = $contentDone;
            $section['quiz_count'] = $quizCount;
            $section['quiz_done'] = $quizDone;
            $section['total_count'] = $totalCount;
            $section['total_done'] = $totalDone;
        
            return $section;
        });
        

        // Tổng hợp từ tất cả các section
        $totalContentCount = $sections->sum('total_count'); // Tổng số lecture
        $totalContentDone = $sections->sum('total_done');  // Tổng số lecture hoàn thành

        // Tính phần trăm tiến độ
        $progress = $totalContentCount > 0 ? ($totalContentDone / $totalContentCount) * 100 : 0;

        // Gộp tất cả nội dung (section và quiz)
        $allContent = $sections->sortBy('order')->values();
        $allContent = $allContent->map(function ($section) use ($contentKeyword) {
            // Nếu $contentKeyword rỗng hoặc null, trả về toàn bộ section
            if (empty($contentKeyword)) {
                return $section;
            }
        
            // Kiểm tra từ khóa có khớp với tiêu đề section không
            $sectionMatches = stripos($section['title'], $contentKeyword) !== false;
        
            // Lọc lecture và quiz trong section_content dựa trên từ khóa
            $filteredLectures = $section['section_content']->filter(function ($content) use ($contentKeyword) {
                return stripos($content['title'], $contentKeyword) !== false;
            });
        
            // Nếu từ khóa khớp với tiêu đề section, trả về toàn bộ section
            if ($sectionMatches) {
                return $section; // Trả về toàn bộ section và nội dung bên trong
            }
        
            // Nếu từ khóa khớp với nội dung lecture hoặc quiz
            if ($filteredLectures->isNotEmpty()) {
                // Trả về section, nhưng chỉ giữ các nội dung khớp
                return [
                    'id' => $section['id'],
                    'title' => $section['title'],
                    'order' => $section['order'],
                    'content_course_type' => $section['content_course_type'],
                    'section_content' => $filteredLectures->values(), // Nội dung khớp
                ];
            }
        
            // Nếu không có gì khớp, bỏ qua section này (trả về null)
            return null;
        })->filter(); // Lọc bỏ các giá trị null
         // Lọc bỏ các giá trị null
        

        // Chuẩn bị dữ liệu trả về
        $responseData = [
            'allContent' => $allContent,
            'total_lecture_count' => $totalContentCount,
            'total_lecture_done' => $totalContentDone,
            'progress_percent' => round($progress, 2),
        ];

        return $responseData;
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
    public function searchContent(Request $request){
        $userId = Auth::user()->id;
        $courseId = $request->input('course_id');
        $contentKeyword='';
        $contentKeyword = $request->input('content_keyword');
        return $this->getAllContent($userId, $courseId, $contentKeyword);
    }




    public function studyCourse(Request $request)
    {
        // Lấy user hiện tại
        $currentUser = Auth::user();

        // Lấy course_id từ request
        $courseId = $request->input('course_id');
        $contentKeyword='';
        $contentKeyword = $request->input('content_keyword');

        // Kiểm tra xem user đã mua khóa học chưa
        $orderItem = OrderItem::where('course_id', $courseId)
            ->where('status', 'active')
            ->whereHas('order', function ($query) use ($currentUser) {
                $query->where('user_id', $currentUser->id)
                    ->where('status', 'active')
                    ->where('payment_status', 'paid');
            })
            ->first();


        // Nếu không tìm thấy orderItem, báo lỗi
        if (!$orderItem) {
            return formatResponse(
                STATUS_FAIL,
                '',
                '',
                __('messages.course_not_purchased')
            );
        }

        $userId = $currentUser->id;
        $data = $this->getAllContent($userId, $courseId, $contentKeyword);
        $allContent = $data['allContent'];
        $totalContentCount = $data['total_lecture_count'];
        $totalContentDone = $data['total_lecture_done'];
        $progress = $data['progress_percent'];


        $currentContent = null;

        foreach ($allContent as $content) {
            // Nếu là section, duyệt qua section_content
            if ($content['content_course_type'] === 'section') {
                foreach ($content['section_content'] as $sectionContent) {
                    // Chỉ lấy content nếu percent khác 100 hoặc là null
                    if (!isset($sectionContent['percent']) || $sectionContent['percent'] < 100) {
                        if ($sectionContent['content_section_type'] === 'lecture') {
                            $lecture = Lecture::find($sectionContent['id']);
                            if ($lecture) {
                                $durationDisplay = $lecture->type === 'video'
                                    ? $this->formatDuration($lecture->duration)
                                    : ($lecture->type === 'file' ? $lecture->duration . " trang" : null);

                                $currentContent = array_merge(
                                    $lecture->toArray(),
                                    [
                                        'current_content_type' => 'lecture',
                                        'learned' => $sectionContent['learned'] ?? null,
                                        'percent' => $sectionContent['percent'] ?? null,
                                        'duration_display' => $durationDisplay, // Gắn thêm duration_display
                                    ]
                                );
                            }
                        } elseif ($sectionContent['content_section_type'] === 'quiz') {
                            $quiz = Quiz::with('questions')
                                ->where('id', $sectionContent['id'])
                                ->where('status', 'active')
                                ->first();

                            if ($quiz) {
                                $questions = $quiz->questions;
                                $nextQuestionIndex = $sectionContent['questions_done'] ?? 0;
                                $nextQuestion = $questions[$nextQuestionIndex] ?? null;

                                $currentQuestionContent = null;

                                // Kiểm tra nếu `nextQuestion` tồn tại và là `active`
                                if ($nextQuestion && $nextQuestion->status === 'active') {
                                    // Tìm content trong bảng dựa trên user_id và question_id
                                    $questionContent = AnswerUser::where('user_id', $userId)
                                        ->where('question_id', $nextQuestion->id)
                                        ->where('status', 'active')
                                        ->value('content'); // Lấy giá trị cột `content`

                                    $currentQuestionContent = array_merge(
                                        $nextQuestion->toArray(),
                                        ['answer_user' => $questionContent] // Thêm `content` vào mảng
                                    );
                                }

                                $currentContent = array_merge(
                                    $quiz->toArray(),
                                    [
                                        'current_content_type' => 'quiz',
                                        'questions_done' => $sectionContent['questions_done'] ?? null,
                                        'percent' => $sectionContent['percent'] ?? null,
                                        'current_question' => $currentQuestionContent // Gán giá trị đã xử lý
                                    ]
                                );
                            }
                        }

                        break 2; // Dừng cả hai vòng lặp
                    }
                }
            }

            // Nếu là quiz bên ngoài section
            if ($content['content_course_type'] === 'quiz') {
                if (!isset($content['percent']) || $content['percent'] < 100) {
                    $quiz = Quiz::with('questions')
                        ->where('id', $content['id'])
                        ->where('status', 'active')
                        ->first();

                    if ($quiz) {
                        $questions = $quiz->questions;
                        $nextQuestionIndex = $content['questions_done'] ?? 0;
                        $nextQuestion = $questions[$nextQuestionIndex] ?? null;

                        $currentContent = array_merge(
                            $quiz->toArray(),
                            [
                                'current_content_type' => 'quiz',
                                'questions_done' => $content['questions_done'] ?? null,
                                'percent' => $content['percent'] ?? null,
                                'current_question' => $nextQuestion && $nextQuestion->status === 'active'
                                    ? $nextQuestion->toArray()
                                    : null
                            ]
                        );
                    }
                    break; // Dừng vòng lặp
                }
            }
        }

        // Nếu không tìm thấy content nào phù hợp, lấy phần tử đầu tiên
        if (!$currentContent) {
            foreach ($allContent as $content) {
                if ($content['content_course_type'] === 'section' && !empty($content['section_content'])) {
                    $sectionContent = $content['section_content'][0];
                    if ($sectionContent['content_section_type'] === 'lecture') {
                        $lecture = Lecture::find($sectionContent['id']);
                        if ($lecture) {
                            $durationDisplay = $lecture->type === 'video'
                                ? $this->formatDuration($lecture->duration)
                                : ($lecture->type === 'file' ? $lecture->duration . " trang" : null);

                            $currentContent = array_merge(
                                $lecture->toArray(),
                                [
                                    'current_content_type' => 'lecture',
                                    'learned' => $sectionContent['learned'] ?? null,
                                    'percent' => $sectionContent['percent'] ?? null,
                                    'duration_display' => $durationDisplay, // Gắn thêm duration_display
                                ]
                            );
                        }
                    } elseif ($sectionContent['content_section_type'] === 'quiz') {
                        $quiz = Quiz::with('questions')
                            ->where('id', $sectionContent['id'])
                            ->where('status', 'active')
                            ->first();

                        if ($quiz) {
                            $questions = $quiz->questions;
                            $nextQuestionIndex = $sectionContent['questions_done'] ?? 0;
                            $nextQuestion = $questions[$nextQuestionIndex] ?? null;

                            $currentContent = array_merge(
                                $quiz->toArray(),
                                [
                                    'current_content_type' => 'quiz',
                                    'questions_done' => $sectionContent['questions_done'] ?? null,
                                    'percent' => $sectionContent['percent'] ?? null,
                                    'current_question' => $nextQuestion && $nextQuestion->status === 'active'
                                        ? $nextQuestion->toArray()
                                        : null
                                ]
                            );
                        }
                    }
                    break;
                }

                if ($content['content_course_type'] === 'quiz') {
                    $quiz = Quiz::with('questions')
                        ->where('id', $content['id'])
                        ->where('status', 'active')
                        ->first();

                    if ($quiz) {
                        $questions = $quiz->questions;
                        $nextQuestionIndex = $content['questions_done'] ?? 0;
                        $nextQuestion = $questions[$nextQuestionIndex] ?? null;

                        $currentContent = array_merge(
                            $quiz->toArray(),
                            [
                                'current_content_type' => 'quiz',
                                'questions_done' => $content['questions_done'] ?? null,
                                'percent' => $content['percent'] ?? null,
                                'current_question' => $nextQuestion && $nextQuestion->status === 'active'
                                    ? $nextQuestion->toArray()
                                    : null
                            ]
                        );
                    }
                    break;
                }
            }
        }

        $course = Course::where('id', $courseId)
            ->where('status', 'active')
            ->first();
        $responseData = [
            'course_title' => $course->title,
            'currentContent' => $currentContent,
            'allContent' => $allContent,
            'total_lecture_count' => $totalContentCount,
            'total_lecture_done' => $totalContentDone,
            'progress_percent' => round($progress, 2), // Làm tròn đến 2 chữ số thập phân
        ];


        // Nếu đã mua khóa học, tiếp tục xử lý logic khác
        return formatResponse(STATUS_OK, $responseData, '', __('messages.course_access_granted'));
    }
    public function changeContent(Request $request)
    {
        // Lấy user hiện tại
        $currentUser = Auth::user();

        // Lấy course_id từ request
        $courseId = $request->input('course_id');
        $contentKeyword='';
        $contentKeyword = $request->input('content_keyword');

        // Kiểm tra xem user đã mua khóa học chưa
        $orderItem = OrderItem::where('course_id', $courseId)
            ->where('status', 'active')
            ->whereHas('order', function ($query) use ($currentUser) {
                $query->where('user_id', $currentUser->id)
                    ->where('status', 'active')
                    ->where('payment_status', 'paid');
            })
            ->first();

        // Nếu không tìm thấy orderItem, báo lỗi
        if (!$orderItem) {
            return formatResponse(
                STATUS_FAIL,
                '',
                '',
                __('messages.course_not_purchased')
            );
        }

        $userId = $currentUser->id;

        // Dữ liệu từ request
        $contentType = $request->input('content_type');
        $contentId = $request->input('content_id');
        $contentOldType = $request->input('content_old_type');
        $contentOldId = $request->input('content_old_id');
        $learned = $request->input('learned');
        $questionsDone = $request->input('questions_done');
        $answerUser = $request->input('answer_user');
        $questionId = $request->input('question_id');
        $redoQuiz = $request->input('redo_quiz');

        // Xử lý nội dung cũ


        if ($redoQuiz) {
            // Xóa bản ghi trong ProgressQuiz
            ProgressQuiz::where('user_id', $userId)
                ->where('quiz_id', $contentOldId)
                ->delete();

            // Lấy danh sách question_id của Quiz có id là $contentOldId
            $questionIds = Question::where('quiz_id', $contentOldId)
                ->pluck('id'); // Lấy mảng các id

            // Xóa bản ghi trong AnswerUser
            AnswerUser::where('user_id', $userId)
                ->whereIn('question_id', $questionIds) // Kiểm tra question_id thuộc danh sách $questionIds
                ->delete();
        } else {
            if ($contentOldType === 'lecture') {
                $lecture = Lecture::where('id', $contentOldId)
                    ->where('status', 'active')
                    ->first();

                if ($lecture) {
                    $percent = ($learned / $lecture->duration) * 100;
                    $progressLecture = ProgressLecture::where('lecture_id', $contentOldId)
                        ->where('user_id', $userId)
                        ->first();

                    // if (!$progressLecture || $progressLecture->percent < 100) {
                    ProgressLecture::updateOrCreate(
                        [
                            'lecture_id' => $contentOldId,
                            'user_id' => $userId,
                        ],
                        [
                            'learned' => $learned,
                            'percent' => $progressLecture && $percent > ($progressLecture->percent ?? 0)
                                ? round($percent, 2)
                                : ($progressLecture->percent ?? round($percent, 2)),
                            'status' => 'active',
                        ]
                    );
                    // }
                }
            } elseif ($contentOldType === 'quiz') {
                if ($questionId) {
                    $quiz = Quiz::withCount('questions')
                        ->where('id', $contentOldId)
                        ->where('status', 'active')
                        ->first();

                    AnswerUser::updateOrCreate(
                        [
                            'user_id' => $userId,
                            'question_id' => $questionId
                        ],
                        [
                            'content' => $answerUser,
                            'status' => 'active',
                        ]
                    );
                    if ($quiz) {
                        $percent = ($questionsDone / $quiz->questions_count) * 100;
                        $progressQuiz = ProgressQuiz::where('quiz_id', $contentOldId)
                            ->where('user_id', $userId)
                            ->where('status', 'active')
                            ->first();

                        // if (!$progressQuiz || $progressQuiz->percent < 100) {
                        ProgressQuiz::updateOrCreate(
                            [
                                'quiz_id' => $contentOldId,
                                'user_id' => $userId,
                            ],
                            [
                                'questions_done' => $questionsDone,
                                'percent' => $progressQuiz && $percent > ($progressQuiz->percent ?? 0)
                                    ? round($percent, 2)
                                    : ($progressQuiz->percent ?? round($percent, 2)),
                                'status' => 'active',
                            ]
                        );
                        // }
                    }
                }
            }
        }

        // Hiển thị current_content cho nội dung mới
        $currentContent = null;
        if ($contentType === 'lecture') {
            $lecture = Lecture::where('id', $contentId)
                ->where('status', 'active')
                ->first();

            if ($lecture) {
                $progressLecture = ProgressLecture::where('lecture_id', $contentId)
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->first();

                $currentContent = array_merge(
                    $lecture->toArray(),
                    [
                        'current_content_type' => 'lecture',
                        'learned' => $progressLecture->learned ?? null,
                        'percent' => $progressLecture->percent ?? null,
                    ]
                );
            }
        } elseif ($contentType === 'quiz') {
            $quiz = Quiz::with('questions')
                ->where('id', $contentId)
                ->where('status', 'active')
                ->first();

            if ($quiz) {
                $progressQuiz = ProgressQuiz::where('quiz_id', $contentId)
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->first();

                $questionsDone = $progressQuiz->questions_done ?? 0;
                $totalQuestions = $quiz->questions->count();

                if ($questionsDone >= $totalQuestions) {
                    // Lấy câu hỏi cuối cùng
                    $currentQuestion = $quiz->questions->last();
                } else {
                    // Lấy câu hỏi tiếp theo
                    $currentQuestion = $quiz->questions[$questionsDone] ?? null;
                }

                // Kiểm tra và thêm `content` từ bảng liên quan
                $currentQuestionContent = null;
                if ($currentQuestion) {
                    $questionContent = AnswerUser::where('user_id', $userId)
                        ->where('question_id', $currentQuestion->id)
                        ->where('status', 'active')
                        ->value('content'); // Lấy giá trị `content`

                    // Thêm `content` vào câu hỏi hiện tại nếu có
                    $currentQuestionContent = array_merge(
                        $currentQuestion->toArray(),
                        ['answer_user' => $questionContent] // Thêm trường `content`
                    );
                }

                $currentContent = array_merge(
                    $quiz->toArray(),
                    [
                        'current_content_type' => 'quiz',
                        'questions_done' => $questionsDone,
                        'percent' => $progressQuiz->percent ?? null,
                        'current_question' => $currentQuestionContent, // Gán câu hỏi đã xử lý
                    ]
                );
            }
        }

        // Lấy dữ liệu tổng quan
        $data = $this->getAllContent($userId, $courseId, $contentKeyword);
        $allContent = $data['allContent'];
        $totalContentCount = $data['total_lecture_count'];
        $totalContentDone = $data['total_lecture_done'];
        $progress = $data['progress_percent'];

        $course = Course::where('id', $courseId)
            ->where('status', 'active')
            ->first();
        $responseData = [
            'course_title' => $course->title,
            'currentContent' => $currentContent,
            'allContent' => $allContent,
            'total_lecture_count' => $totalContentCount,
            'total_lecture_done' => $totalContentDone,
            'progress_percent' => round($progress, 2),
        ];

        // Trả về response
        return formatResponse(STATUS_OK, $responseData, '', __('messages.course_access_granted'));
    }
}
