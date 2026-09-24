<?php

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Controller;
use App\Models\UserAccess;
use Illuminate\Http\Request;
use App\Models\Bundle;
use App\Models\CourseLearning;
use App\Services\CourseProgressService;
use App\Models\Quiz;
use App\Models\QuizzesResult;
use App\Models\Webinar;
use App\Models\WebinarAssignmentHistory;
use Illuminate\Pagination\LengthAwarePaginator;

class UserAccessController extends Controller
{
    public function giftedItems(Request $request)
    {
        $user = apiAuth();

        $query = UserAccess::with(['receiver', 'accessible'])
            ->where('buyer_id', $user->id)
            ->whereColumn('buyer_id', '!=', 'user_id');

        if ($request->filled('user')) {

            $query->whereHas('receiver', function ($q) use ($request) {

                $q->where('full_name', 'like', '%' . $request->user . '%');
            });
        }

        $userAccesses = $query
            ->latest('id')
            ->paginate(10);

        return apiResponse2(1, 'success', '', [
            'items' => $userAccesses
        ]);
    }
public function coursesProgress(Request $request)
{
    $user = apiAuth();

    validator($request->only('student'), [
        'student' => 'nullable|string|max:255',
    ])->validate();

    $student = $request->student;

    $userAccesses = UserAccess::with(['receiver', 'accessible'])
        ->where('buyer_id', $user->id)

        ->when($student, function ($query) use ($student) {

            $query->whereHas('receiver', function ($q) use ($student) {

                $q->where('full_name', 'like', "%{$student}%");
            });
        })

        ->latest('id')
        ->paginate(10);

    $rows = collect();

    foreach ($userAccesses as $access) {

        if ($access->accessible_type == 'webinar') {

            $webinars = Webinar::where('id', $access->accessible_id)
                ->with([
                    'chapters' => fn($q) => $q->where('status', 'active'),
                    'sessions' => fn($q) => $q->where('status', 'active'),
                    'assignments' => fn($q) => $q->where('status', 'active'),
                    'quizzes' => fn($q) => $q->where('status', 'active'),
                    'files' => fn($q) => $q->where('status', 'active'),
                    'reviews' => fn($q) => $q->where('status', 'active'),
                ])
                ->get();

        } elseif ($access->accessible_type == 'bundle') {

            $bundle = Bundle::with('bundleWebinars.webinar')
                ->find($access->accessible_id);

            if (!$bundle) {
                continue;
            }

            $webinarIds = $bundle->bundleWebinars
                ->pluck('webinar_id')
                ->unique()
                ->values();

            $webinars = Webinar::whereIn('id', $webinarIds)
                ->with([
                    'chapters' => fn($q) => $q->where('status', 'active'),
                    'sessions' => fn($q) => $q->where('status', 'active'),
                    'assignments' => fn($q) => $q->where('status', 'active'),
                    'quizzes' => fn($q) => $q->where('status', 'active'),
                    'files' => fn($q) => $q->where('status', 'active'),
                    'reviews' => fn($q) => $q->where('status', 'active'),
                ])
                ->get();

        } else {
            continue;
        }

        foreach ($webinars as $webinar) {

            $progress = app(CourseProgressService::class)
                ->getCourseProgress($webinar, $access->user_id);

            $passedQuizzes = Quiz::whereIn(
                'quizzes.id',
                $webinar->quizzes->pluck('id')
            )
                ->join('quizzes_results', 'quizzes_results.quiz_id', 'quizzes.id')
                ->where('quizzes_results.user_id', $access->user_id)
                ->where('quizzes_results.status', QuizzesResult::$passed)
                ->count();

            $assignmentsIds = $webinar->assignments->pluck('id');

            $historyCount = WebinarAssignmentHistory::whereIn(
                'assignment_id',
                $assignmentsIds
            )
                ->where('student_id', $access->user_id)
                ->count();

            $pendingAssignments = WebinarAssignmentHistory::whereIn(
                'assignment_id',
                $assignmentsIds
            )
                ->where('student_id', $access->user_id)
                ->where('status', WebinarAssignmentHistory::$pending)
                ->count();

            $contentProgress = collect();

            /*
            |--------------------------------------------------------------------------
            | Sessions
            |--------------------------------------------------------------------------
            */

            foreach ($webinar->sessions as $session) {

                $completed = CourseLearning::query()
                    ->where('user_id', $access->user_id)
                    ->where('webinar_id', $webinar->id)
                    ->where('session_id', $session->id)
                    ->exists();

                $contentProgress->push([
                    'type' => 'Session',
                    'title' => $session->title,
                    'completed' => $completed,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Files
            |--------------------------------------------------------------------------
            */

            foreach ($webinar->files as $file) {

                $completed = CourseLearning::query()
                    ->where('user_id', $access->user_id)
                    ->where('file_id', $file->id)
                    ->exists();

                $contentProgress->push([
                    'type' => 'File',
                    'title' => $file->title,
                    'completed' => $completed,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Quizzes
            |--------------------------------------------------------------------------
            */

            foreach ($webinar->quizzes as $quiz) {

                $completed = QuizzesResult::query()
                    ->where('quiz_id', $quiz->id)
                    ->where('user_id', $access->user_id)
                    ->where('status', QuizzesResult::$passed)
                    ->exists();

                $contentProgress->push([
                    'type' => 'Quiz',
                    'title' => $quiz->title,
                    'completed' => $completed,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Assignments
            |--------------------------------------------------------------------------
            */

            foreach ($webinar->assignments as $assignment) {

                $completed = WebinarAssignmentHistory::query()
                    ->where('assignment_id', $assignment->id)
                    ->where('student_id', $access->user_id)
                    ->exists();

                $contentProgress->push([
                    'type' => 'Assignment',
                    'title' => $assignment->title,
                    'completed' => $completed,
                ]);
            }

            $rows[] = [
                'user' => $access->receiver,
                'webinar' => $webinar,
                'bundle_title' => $access->accessible_type == 'bundle'
                    ? ($bundle->title ?? null)
                    : null,
                'course_progress' => $progress,
                'passed_quizzes' => $passedQuizzes,
                'unsent_assignments' => count($assignmentsIds) - $historyCount,
                'pending_assignments' => $pendingAssignments,
                'created_at' => $access->created_at,
                'type' => $access->accessible_type,
                'content_progress' => $contentProgress,
            ];
        }
    }

    $rows = new LengthAwarePaginator(
        $rows,
        $userAccesses->total(),
        $userAccesses->perPage(),
        $userAccesses->currentPage()
    );

    return apiResponse2(1, 'success', '', [
        'items' => $rows
    ]);
}
   
} 