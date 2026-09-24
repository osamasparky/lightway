<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\UserAccess;
use App\Models\Webinar;
use App\Models\WebinarAssignment;
use App\Models\WebinarAssignmentHistory;
use App\Models\Quiz;
use App\Models\QuizzesResult;
use App\Models\CourseLearning;
use App\Services\CourseProgressService;

use Illuminate\Http\Request;

class UserAccessController extends Controller
{
  public function giftedItems(Request $request)
  {
    $user = auth()->user();

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
      ->paginate(10)
      ->appends($request->all());

    return view('web.default.panel.progress.gifted-items', [
      'userAccesses' => $userAccesses
    ]);
  }

public function coursesProgress()
{
    $user = auth()->user();

    validator(request()->only('student'), [
        'student' => 'nullable|string|max:255',
    ])->validate();

    $student = request('student');

    $userAccesses = UserAccess::with(['receiver', 'accessible'])
        ->where('buyer_id', $user->id)

        ->when($student, function ($query) use ($student) {

            $query->whereHas('receiver', function ($q) use ($student) {

                $q->where('full_name', 'like', "%{$student}%");
            });
        })

        ->latest('id')
        ->paginate(10)
        ->appends(request()->only('student'));

    $rows = collect();

    foreach ($userAccesses as $access) {

      /*
        |--------------------------------------------------------------------------
        | Webinar
        |--------------------------------------------------------------------------
        */

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
      }

      /*
        |--------------------------------------------------------------------------
        | Bundle
        |--------------------------------------------------------------------------
        */ elseif ($access->accessible_type == 'bundle') {

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

      /*
        |--------------------------------------------------------------------------
        | Rows
        |--------------------------------------------------------------------------
        */

      foreach ($webinars as $webinar) {

        // $progress = app(WebinarStatisticController::class)
        //   ->getCourseProgressForStudent($webinar, $access->user_id);

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

        /*
            |--------------------------------------------------------------------------
            | Content Progress
            |--------------------------------------------------------------------------
            */

        $contentProgress = collect();

        /*
            |--------------------------------------------------------------------------
            | Sessions
            |--------------------------------------------------------------------------
            */

        if ($webinar->sessions->count()) {

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
        } else {

          $contentProgress->push([
            'type' => 'Session',
            'title' => 'No sessions',
            'completed' => false,
            'empty' => true,
          ]);
        }

        /*
            |--------------------------------------------------------------------------
            | Files
            |--------------------------------------------------------------------------
            */

        if ($webinar->files->count()) {

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
        } else {

          $contentProgress->push([
            'type' => 'File',
            'title' => 'No files',
            'completed' => false,
            'empty' => true,
          ]);
        }

        /*
            |--------------------------------------------------------------------------
            | Quizzes
            |--------------------------------------------------------------------------
            */

        if ($webinar->quizzes->count()) {

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
        } else {

          $contentProgress->push([
            'type' => 'Quiz',
            'title' => 'No quizzes',
            'completed' => false,
            'empty' => true,
          ]);
        }

        /*
            |--------------------------------------------------------------------------
            | Assignments
            |--------------------------------------------------------------------------
            */

        if ($webinar->assignments->count()) {

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
        } else {

          $contentProgress->push([
            'type' => 'Assignment',
            'title' => 'No assignments',
            'completed' => false,
            'empty' => true,
          ]);
        }

        /*
            |--------------------------------------------------------------------------
            | Push Row
            |--------------------------------------------------------------------------
            */

        $rows[] = (object)[
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

    $rows = new \Illuminate\Pagination\LengthAwarePaginator(
      $rows,
      $userAccesses->total(),
      $userAccesses->perPage(),
      $userAccesses->currentPage(),
      [
        'path' => request()->url(),
        'query' => request()->query(),
      ]
    );

    return view('web.default.panel.progress.courses-progress', [
      'rows' => $rows,
    ]);
  }
}
