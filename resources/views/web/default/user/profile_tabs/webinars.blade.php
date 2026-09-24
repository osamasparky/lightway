@if(!empty($webinars) and !$webinars->isEmpty())
    <div class="lw-grid">
        @foreach($webinars as $webinar)
            @include('web.default.includes.lightway.course_card', ['webinar' => $webinar])
        @endforeach
    </div>
@else
    <div class="lw-empty">
        @include(getTemplate() . '.includes.no-result',[
            'file_name' => 'webinar.png',
            'title' => trans('site.instructor_not_have_webinar'),
            'hint' => '',
        ])
    </div>
@endif
