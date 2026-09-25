@extends(getTemplate().'.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/default/vendors/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" href="/assets/default/vendors/select2/select2.min.css">
@endpush

@section('content')
    @php
        $bannerCrumbs = [['title' => trans('home.lw_courses'), 'url' => '/classes']];
        if (!empty($category) and !empty($category->parent_id) and !empty($category->category)) {
            $bannerCrumbs[] = ['title' => $category->category->title, 'url' => $category->category->getUrl()];
        }
        $bannerCrumbs[] = ['title' => !empty($category) ? $category->title : $pageTitle];
    @endphp

    @include('web.default.includes.lightway.banner', [
        'title' => !empty($category) ? $category->title : $pageTitle,
        'subtitle' => $webinarsCount . ' ' . trans('product.courses'),
        'breadcrumbs' => $bannerCrumbs,
        'search' => ['action' => '/search', 'name' => 'search', 'placeholder' => trans('home.slider_search_placeholder')],
    ])

    <div class="ms-container lw-page">
        @if(!empty($featureWebinars) and !$featureWebinars->isEmpty())
            <section class="lw-section">
                <div class="lw-section__head">
                    <h2 class="lw-section__title">
                        @include('web.default.includes.manuscript.star', ['size' => 24, 'dot' => '#FBF6EC'])
                        {{ trans('home.featured_webinars') }}
                    </h2>
                    <p class="lw-section__hint">{{ trans('site.newest_courses_subtitle') }}</p>
                </div>

                <div class="position-relative">
                    <div class="swiper-container lw-swiper">
                        <div class="swiper-wrapper">
                            @foreach($featureWebinars as $featureWebinar)
                                <div class="swiper-slide">
                                    @include('web.default.includes.lightway.course_card', ['webinar' => $featureWebinar->webinar, 'isFeature' => true])
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex justify-content-center">
                        <div class="swiper-pagination"></div>
                    </div>
                </div>
            </section>
        @endif

        <form action="{{ $sortFormAction }}" method="get" id="filtersForm" class="lw-with-sidebar">

            @include('web.default.includes.lightway.course_filters', [
                'typeOptions' => ['webinar', 'course', 'text_lesson'],
                'moreOptionsList' => ['bundles', 'subscribe', 'certificate_included', 'with_quiz', 'featured'],
                'filterCategory' => $category ?? null,
                'activeCategoryId' => !empty($category) ? $category->id : null,
            ])

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
