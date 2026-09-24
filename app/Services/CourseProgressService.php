<?php

namespace App\Services;

class CourseProgressService
{
  public function getCourseProgress($webinar, $userId)
  {
    $progress = 0;

    $filesStat = $webinar->getFilesLearningProgressStat($userId);
    $sessionsStat = $webinar->getSessionsLearningProgressStat($userId);
    $textLessonsStat = $webinar->getTextLessonsLearningProgressStat($userId);
    $assignmentsStat = $webinar->getAssignmentsLearningProgressStat($userId);
    $quizzesStat = $webinar->getQuizzesLearningProgressStat($userId);

    $passed =
      $filesStat['passed'] +
      $sessionsStat['passed'] +
      $textLessonsStat['passed'] +
      $assignmentsStat['passed'] +
      $quizzesStat['passed'];

    $count =
      $filesStat['count'] +
      $sessionsStat['count'] +
      $textLessonsStat['count'] +
      $assignmentsStat['count'] +
      $quizzesStat['count'];

    if ($passed > 0 && $count > 0) {
      $progress = ($passed * 100) / $count;
    }

    return round($progress, 2);
  }
}
