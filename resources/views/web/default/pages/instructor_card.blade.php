{{-- Kept as the entry point used by the instructors list, the AJAX "load more" (UserController) and profile tabs. --}}
@include('web.default.includes.lightway.instructor_card', ['instructor' => $instructor])
