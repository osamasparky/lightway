@extends(getTemplate() . '.panel.layouts.panel_layout')

@section('content')
    <div class="section-title mb-20">
        <h2 class="font-24 font-weight-bold">
            {{ trans('update.webinars_progress') }}
        </h2>
    </div>

    <section>
        <div class="row">

            {{-- Total Students --}}
            <div class="col-6 col-md-3 mt-20">
                <div class="dashboard-stats rounded-sm panel-shadow p-15 d-flex align-items-center">
                    <div class="stat-icon stat-icon-chapters">
                        <img loading="lazy" src="/assets/default/img/activity/48.svg" width="50" alt="">
                    </div>

                    <div class="d-flex flex-column ml-15">
                        <span class="font-30 text-secondary">
                            {{ $rows->pluck('user.id')->unique()->count() }}
                        </span>

                        <span class="font-16 text-gray font-weight-500">
                            {{ trans('quiz.students') }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Total Webinars --}}
            <div class="col-6 col-md-3 mt-20">
                <div class="dashboard-stats rounded-sm panel-shadow p-15 d-flex align-items-center">
                    <div class="stat-icon stat-icon-sessions">
                        <img loading="lazy" src="/assets/default/img/activity/125.svg" width="50" alt="">
                    </div>

                    <div class="d-flex flex-column ml-15">
                        <span class="font-30 text-secondary">
                            {{ $rows->pluck('webinar.id')->unique()->count() }}
                        </span>

                        <span class="font-16 text-gray font-weight-500">
                            {{ trans('update.webinars') }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Average Progress --}}
            <div class="col-6 col-md-3 mt-20">
                <div class="dashboard-stats rounded-sm panel-shadow p-15 d-flex align-items-center">
                    <div class="stat-icon stat-icon-pending-quizzes">
                        <img loading="lazy" src="/assets/default/img/activity/sales.svg" width="50" alt="">
                    </div>

                    <div class="d-flex flex-column ml-15">
                        <span class="font-30 text-secondary">
                            {{ round($rows->avg('course_progress'), 1) }}%
                        </span>

                        <span class="font-16 text-gray font-weight-500">
                            {{ trans('update.avg_progress') }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Total Passed Quizzes --}}
            <div class="col-6 col-md-3 mt-20">
                <div class="dashboard-stats rounded-sm panel-shadow p-15 d-flex align-items-center">
                    <div class="stat-icon stat-icon-pending-assignments">
                        <img loading="lazy" src="/assets/default/img/activity/88.svg" width="50" alt="">
                    </div>

                    <div class="d-flex flex-column ml-15">
                        <span class="font-30 text-secondary">
                            {{ $rows->sum('passed_quizzes') }}
                        </span>

                        <span class="font-16 text-gray font-weight-500">
                            {{ trans('update.passed_quizzes') }}
                        </span>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <section class="mt-30">
        <div class="row">

            {{-- Completed --}}
            <div class="col-12 col-md-4 mt-20">
                <div class="course-statistic-cards-shadow p-25 rounded-sm bg-white text-center">

                    <span class="font-40 font-weight-bold text-secondary">
                        {{ $rows->where('course_progress', 100)->count() }}
                    </span>

                    <div class="mt-10 text-gray font-16">
                         {{ trans('update.completed_courses') }}
                    </div>

                </div>
            </div>

            {{-- In Progress --}}
            <div class="col-12 col-md-4 mt-20">
                <div class="course-statistic-cards-shadow p-25 rounded-sm bg-white text-center">

                    <span class="font-40 font-weight-bold text-warning">
                        {{ $rows->filter(fn($r) => $r->course_progress > 0 && $r->course_progress < 100)->count() }}
                    </span>

                    <div class="mt-10 text-gray font-16">
                        {{ trans('webinars.in_progress') }}
                    </div>

                </div>
            </div>

            {{-- Not Started --}}
            <div class="col-12 col-md-4 mt-20">
                <div class="course-statistic-cards-shadow p-25 rounded-sm bg-white text-center">

                    <span class="font-40 font-weight-bold text-danger">
                        {{ $rows->where('course_progress', 0)->count() }}
                    </span>

                    <div class="mt-10 text-gray font-16">
                        {{ trans('update.not_started') }}
                    </div>

                </div>
            </div>

        </div>
    </section>

    <section class="mt-30">
        <div class="row">

            {{-- Course Progress Chart --}}
            <div class="col-12 col-md-6 mt-20">
                <div class="course-statistic-cards-shadow p-20 rounded-sm bg-white">

                    <h3 class="font-16 text-dark-blue font-weight-bold mb-20">
                        {{ trans('update.course_progress') }}
                    </h3>

                    <div class="d-flex justify-content-center">
                        <canvas id="courseProgressChart" width="250" height="250"></canvas>
                    </div>

                    <div class="d-flex align-items-center justify-content-center mt-20 flex-wrap">

                        <div class="statistics-pie-chart-legend mr-15">
                            <span class="legend-color bg-primary"></span>
                            <span class="font-14 text-gray"> {{ trans('update.completed') }}</span>
                        </div>

                        <div class="statistics-pie-chart-legend mr-15">
                            <span class="legend-color bg-warning"></span>
                            <span class="font-14 text-gray">{{ trans('update.in_progress') }}</span>
                        </div>

                        <div class="statistics-pie-chart-legend">
                            <span class="legend-color bg-danger"></span>
                            <span class="font-14 text-gray">{{ trans('update.not_started') }}</span>
                        </div>

                    </div>
                </div>
            </div>

            {{-- Assignments Chart --}}
            <div class="col-12 col-md-6 mt-20">
                <div class="course-statistic-cards-shadow p-20 rounded-sm bg-white">

                    <h3 class="font-16 text-dark-blue font-weight-bold mb-20">
                         {{ trans('update.assignments_status') }}
                    </h3>

                    <div class="d-flex justify-content-center">
                        <canvas id="assignmentsChart" width="250" height="250"></canvas>
                    </div>

                    <div class="d-flex align-items-center justify-content-center mt-20 flex-wrap">

                        <div class="statistics-pie-chart-legend mr-15">
                            <span class="legend-color bg-primary"></span>
                            <span class="font-14 text-gray"> {{ trans('update.pending') }}</span>
                        </div>

                        <div class="statistics-pie-chart-legend">
                            <span class="legend-color bg-danger"></span>
                            <span class="font-14 text-gray">{{ trans('update.unsent') }}</span>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </section>

    <div class="panel-section-card py-20 px-25 mt-20">
      {{-- Filter --}}
<form method="GET" action="" class="mb-20">
    <div class="row align-items-end">

        <div class="col-md-4">
            <label class="text-gray font-14">
                {{ trans('quiz.student_name') }}
            </label>

            <input
                type="text"
                name="student"
                value="{{ request('student') }}"
                class="form-control"
                placeholder="{{ trans('quiz.search_by_student_name') }}"
            >
        </div>

        <div class="col-md-2 mt-3 mt-md-0">
            <button type="submit" class="btn btn-primary btn-block">
                {{ trans('public.filter') }}
            </button>
        </div>

        @if(request()->has('student') && !empty(request('student')))
            <div class="col-md-2 mt-3 mt-md-0">
                <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-block">
                    {{ trans('public.reset') }}
                </a>
            </div>
        @endif

    </div>
</form>
        <div class="row">
            <div class="col-12">
                <div class="table-responsive">
                    <table class="table custom-table text-center">

                        <thead>
                            <tr>
                                <th class="text-left text-gray">{{ trans('quiz.student') }}</th>
                                <th class="text-center text-gray">{{ trans('update.webinar') }}</th>
                                <th class="text-center text-gray">{{ trans('update.progress') }}</th>
                                <th class="text-center text-gray">{{ trans('update.passed_quizzes') }}</th>
                                <th class="text-center text-gray">{{ trans('update.unsent_assignments') }}</th>
                                <th class="text-center text-gray">{{ trans('update.pending_assignments') }}</th>
                                <th class="text-center text-gray">{{ trans('panel.purchase_date') }}</th>
                            </tr>
                        </thead>

                        <tbody>

                         @foreach ($rows as $key => $row)
                          {{-- Main Row --}}
                          <tr>
                              {{-- Student --}}
                              <td class="text-left">
                                  <div class="user-inline-avatar d-flex align-items-center">

                                      <div class="avatar bg-gray200">
                                          <img loading="lazy" src="{{ $row->user->getAvatar() }}" class="img-cover" alt="">
                                      </div>

                                      <div class="ml-5">
                                          <span class="d-block text-dark-blue font-weight-500">
                                              {{ $row->user->full_name }}
                                          </span>

                                          <span class="mt-5 d-block font-12 text-gray">
                                              {{ $row->user->email }}
                                          </span>
                                      </div>

                                  </div>
                              </td>

                              {{-- Webinar --}}
                              <td class="align-middle text-dark-blue font-weight-500">

                                  <div>
                                      {{ $row->webinar->title }}
                                  </div>

                                  @if (!empty($row->bundle_title))
                                      <div class="font-12 text-gray mt-5">
                                          ({{ $row->bundle_title }})
                                      </div>
                                  @endif

                              </td>

                              {{-- Progress --}}
                              <td class="align-middle">
                                  <span class="text-dark-blue font-weight-500">
                                      {{ $row->course_progress ?? 0 }}%
                                  </span>
                              </td>

                              {{-- Passed Quizzes --}}
                              <td class="align-middle">
                                  <span class="text-dark-blue font-weight-500">
                                      {{ $row->passed_quizzes ?? 0 }}
                                  </span>
                              </td>

                              {{-- Unsent Assignments --}}
                              <td class="align-middle">
                                  <span class="text-dark-blue font-weight-500">
                                      {{ $row->unsent_assignments ?? 0 }}
                                  </span>
                              </td>

                              {{-- Pending Assignments --}}
                              <td class="align-middle">
                                  <span class="text-dark-blue font-weight-500">
                                      {{ $row->pending_assignments ?? 0 }}
                                  </span>
                              </td>

                              {{-- Purchase Date --}}
                              <td class="align-middle">
                                  <span class="text-dark-blue font-weight-500">
                                      {{ dateTimeFormat($row->created_at, 'j M Y | H:i') }}
                                  </span>
                              </td>

                              {{-- Details Button --}}
                              <td class="align-middle">

                                  <button
                                      class="btn btn-sm btn-outline-primary"
                                      type="button"
                                      data-toggle="collapse"
                                      data-target="#courseDetails{{ $key }}"
                                  >
                                            {{ trans('public.details') }}
                                  </button>

                              </td>

                          </tr>

                          {{-- Collapse Row --}}
                          <tr class="collapse bg-light" id="courseDetails{{ $key }}">
                              <td colspan="8">

                                  <div class="p-20">
                                      @php
                                          $groupedContents = collect($row->content_progress)->groupBy('type');
                                      @endphp

                                      <div class="row">
                                          @foreach ($groupedContents as $type => $items)
                                              <div class="col-md-6 mb-20">
                                                  <div class="border rounded bg-white h-100">
                                                      {{-- Card Header --}}
                                                      <div class="p-15 border-bottom">

                                                          <h5 class="font-weight-bold text-dark mb-0">
                                                                    {{ trans('update.' . strtolower($type) . 's') }}
                                                          </h5>

                                                      </div>

                                                      {{-- Card Body --}}
                                                      <div class="p-15">
                                                          @foreach ($items as $content)
                                                              <div class="d-flex justify-content-between align-items-center py-10 border-bottom">
                                                                  <div class="pr-15">
                                                                      <span class="font-14 text-dark">
                                                                          {{ $content['title'] }}
                                                                      </span>
                                                                  </div>

                                                                  <div>
                                                                      @if (!empty($content['empty']))
                                                                          <span class="badge badge-secondary">
                                                                            {{ trans('public.empty') }}
                                                                          </span>
                                                                      @elseif($content['completed'])
                                                                          <span class="badge badge-primary">
                                                                            ✓ {{ trans('quiz.passed') }}
                                                                          </span>
                                                                      @else

                                                                          <span class="badge badge-danger">
                                                                              ✗ {{ trans('update.not_passed') }}
                                                                          </span>
                                                                      @endif
                                                                  </div>
                                                              </div>
                                                          @endforeach
                                                      </div>
                                                  </div>
                                              </div>
                                          @endforeach
                                      </div>
                                  </div>
                              </td>
                          </tr>
                      @endforeach
                        </tbody>

                    </table>
                    @if($rows->hasPages())
                      <div class="d-flex justify-content-center mt-30">
                          {{ $rows->appends(request()->query())->links() }}
                      </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts_bottom')
    <script src="/assets/default/vendors/chartjs/chart.min.js"></script>

    <script>
        (function($) {
            "use strict";

            /*
            |--------------------------------------------------------------------------
            | Course Progress Chart
            |--------------------------------------------------------------------------
            */

            const completedCount =
                {{ $rows->where('course_progress', 100)->count() }};

            const inProgressCount =
                {{ $rows->filter(fn($r) => $r->course_progress > 0 && $r->course_progress < 100)->count() }};

            const notStartedCount =
                {{ $rows->where('course_progress', 0)->count() }};

            new Chart(document.getElementById('courseProgressChart'), {
                type: 'pie',

                data: {
                    labels: ['Completed', 'In Progress', 'Not Started'],

                    datasets: [{
                        data: [
                            completedCount,
                            inProgressCount,
                            notStartedCount
                        ],

                        backgroundColor: [
                            '#43d477',
                            '#ffc136',
                            '#f63c3c'
                        ],

                        borderWidth: 0
                    }]
                },

                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });


            /*
            |--------------------------------------------------------------------------
            | Assignments Chart
            |--------------------------------------------------------------------------
            */

            const pendingAssignments =
                {{ $rows->sum('pending_assignments') }};

            const unsentAssignments =
                {{ $rows->sum('unsent_assignments') }};

            new Chart(document.getElementById('assignmentsChart'), {
                type: 'pie',

                data: {
                    labels: ['Pending', 'Unsent'],

                    datasets: [{
                        data: [
                            pendingAssignments,
                            unsentAssignments
                        ],

                        backgroundColor: [
                            '#43d477',
                            '#f63c3c'
                        ],

                        borderWidth: 0
                    }]
                },

                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

        })(jQuery)
    </script>
@endpush
