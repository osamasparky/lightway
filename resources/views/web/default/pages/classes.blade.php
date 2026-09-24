@extends(getTemplate().'.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/default/vendors/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" href="/assets/default/vendors/select2/select2.min.css">
@endpush

@section('content')
    @include('web.default.includes.lightway.banner', [
        'title' => trans('home.lw_courses'),
        'subtitle' => $coursesCount . ' ' . trans('product.courses'),
        'breadcrumbs' => [['title' => trans('home.lw_courses')]],
        'search' => ['action' => '/search', 'name' => 'search', 'placeholder' => trans('home.slider_search_placeholder')],
    ])

    <div class="ms-container lw-page">
        <form action="/classes" method="get" id="filtersForm" class="lw-with-sidebar">

            @include('web.default.includes.lightway.course_filters')

            <div class="lw-stack">
                @include('web.default.includes.lightway.course_toolbar')

                @if($webinars->count())
                    @if(request()->get('card') == 'list')
                        <div class="lw-list">
                            @foreach($webinars as $webinar)
                                @include('web.default.includes.lightway.course_card', ['webinar' => $webinar, 'variant' => 'list'])
                            @endforeach
                        </div>
                    @else
                        <div class="lw-grid">
                            @foreach($webinars as $webinar)
                                @include('web.default.includes.lightway.course_card', ['webinar' => $webinar])
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="lw-empty">
                        @include(getTemplate() . '.includes.no-result', [
                            'file_name' => 'webinar.png',
                            'title' => trans('site.no_result_search'),
                            'hint' => trans('home.lw_try_other_filters'),
                        ])
                    </div>
                @endif

                {{ $webinars->appends(request()->input())->links('web.default.includes.lightway.pagination') }}
            </div>
        </form>
    </div>
@endsection

@push('scripts_bottom')
    <script src="/assets/default/vendors/select2/select2.min.js"></script>
    <script src="/assets/default/vendors/swiper/swiper-bundle.min.js"></script>

    <script src="/assets/default/js/parts/categories.min.js"></script>
@endpush
