@extends('web.default.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/default/vendors/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" href="/assets/vendors/leaflet/leaflet.css">
    <link rel="stylesheet" href="/assets/vendors/leaflet/leaflet.markercluster/markerCluster.css">
    <link rel="stylesheet" href="/assets/vendors/leaflet/leaflet.markercluster/markerCluster.Default.css">
    <link rel="stylesheet" href="/assets/vendors/wrunner-html-range-slider-with-2-handles/css/wrunner-default-theme.css">
@endpush

@section('content')
    @php
        $hasMap = (!empty($mapCenter) and is_array($mapCenter));
        $finderKeep = ['category_id', 'level_of_training', 'gender', 'role', 'meeting_type', 'population', 'available_for_meetings', 'free_meetings', 'discount', 'sort', 'day', 'country_id', 'province_id', 'city_id', 'district_id', 'min_price', 'max_price', 'min_age', 'max_age', 'min_time', 'max_time'];
    @endphp

    <div class="instructor-finder lw-finder">
        {{-- Hero: live map + search card --}}
        <section class="lw-finder-hero {{ $hasMap ? 'has-map' : 'ms-lattice' }}">
            @if($hasMap)
                <div id="instructorFinderMap"
                     class="instructor-finder-map lw-finder-hero__map"
                     data-latitude="{{ $mapCenter[0] }}"
                     data-longitude="{{ $mapCenter[1] }}"
                     data-zoom="{{ $mapZoom }}"
                     aria-label="{{ trans('update.location') }}"
                ></div>
            @endif

            <div class="ms-container lw-finder-hero__inner">
                <div class="lw-finder-search">
                    <nav class="lw-breadcrumb" aria-label="{{ trans('home.lw_breadcrumb') }}">
                        <ol>
                            <li><a href="/">{{ trans('home.ms_home_link') }}</a></li>
                            <li><span aria-current="page">{{ trans('home.instructors') }}</span></li>
                        </ol>
                    </nav>

                    <h1 class="lw-banner__title lw-finder-search__title">
                        @include('web.default.includes.manuscript.star', ['size' => 30, 'dot' => '#FFFDF8', 'class' => 'lw-banner__star'])
                        <span>{{ trans('home.lw_finder_title') }}</span>
                    </h1>

                    <p class="lw-finder-search__hint">{{ trans('home.lw_finder_hint', ['count' => $instructors->total()]) }}</p>

                    <form action="/instructor-finder" method="get" role="search" class="lw-search lw-search--block">
                        @foreach($finderKeep as $keep)
                            @foreach((array) request()->get($keep, []) as $keepValue)
                                @if(!is_array($keepValue) and $keepValue !== '' and $keepValue !== null)
                                    <input type="hidden" name="{{ is_array(request()->get($keep)) ? $keep . '[]' : $keep }}" value="{{ $keepValue }}">
                                @endif
                            @endforeach
                        @endforeach

                        <label for="lwFinderSearch" class="sr-only">{{ trans('home.lw_finder_search') }}</label>
                        <i data-feather="search" width="20" height="20" class="lw-search__icon" aria-hidden="true"></i>
                        <input id="lwFinderSearch" type="search" name="search" value="{{ request()->get('search') }}" placeholder="{{ trans('home.lw_finder_search') }}">
                        <button type="submit" class="lw-btn lw-btn--dark">{{ trans('home.find') }}</button>
                    </form>
                </div>
            </div>

            <div class="ms-band lw-banner__band" aria-hidden="true"></div>
        </section>

        <div class="ms-container lw-page">
            <form id="filtersForm" action="/instructor-finder?{{ http_build_query(request()->all()) }}" method="get" class="lw-with-sidebar">
                @if(request()->get('search'))
                    <input type="hidden" name="search" value="{{ request()->get('search') }}">
                @endif

                {{-- Sidebar filters (drawer on mobile) --}}
                @component('web.default.includes.lightway.filters_sidebar', ['id' => 'lwFinderFilters', 'label' => trans('update.filters')])
                    <h2 class="lw-sidebar__title">
                        @include('web.default.includes.manuscript.star', ['size' => 20, 'dot' => '#FBF6EC'])
                        {{ trans('update.filters') }}
                    </h2>

                    @include('web.default.instructorFinder.components.filters')
                    @include('web.default.instructorFinder.components.time_filter')
                    @include('web.default.instructorFinder.components.location_filters')

                    <button type="submit" class="lw-btn lw-btn--dark lw-btn--block">{{ trans('site.filter_items') }}</button>
                @endcomponent

                <div class="lw-stack">
                    @include('web.default.instructorFinder.components.top_filters')

                    <div class="lw-results-bar">
                        <span class="lw-results-bar__count">{{ trans('home.lw_finder_results', ['count' => $instructors->total()]) }}</span>

                        <div class="lw-view-switch" role="group" aria-label="{{ trans('home.lw_view') }}">
                            <button type="button" class="lw-view-switch__btn js-lw-layout is-active" data-target="#instructorsList" data-layout="list" aria-pressed="true" title="{{ trans('home.lw_list_view') }}">
                                <i data-feather="list" width="20" height="20" aria-hidden="true"></i>
                                <span class="sr-only">{{ trans('home.lw_list_view') }}</span>
                            </button>
                            <button type="button" class="lw-view-switch__btn js-lw-layout" data-target="#instructorsList" data-layout="grid" aria-pressed="false" title="{{ trans('home.lw_grid_view') }}">
                                <i data-feather="grid" width="20" height="20" aria-hidden="true"></i>
                                <span class="sr-only">{{ trans('home.lw_grid_view') }}</span>
                            </button>
                        </div>
                    </div>

                    <div id="instructorsList" class="lw-finder-list">
                        @if($instructors->isNotEmpty())
                            @foreach($instructors as $instructor)
                                @include('web.default.instructorFinder.components.instructor_card', ['instructor' => $instructor])
                            @endforeach
                        @else
                            <div class="lw-empty">
                                @include('web.default.includes.no-result',[
                                    'file_name' => 'support.png',
                                    'title' => trans('update.instructor_finder_no_result'),
                                    'hint' => nl2br(trans('update.instructor_finder_no_result_hint')),
                                ])
                            </div>
                        @endif
                    </div>

                    <div class="lw-center">
                        <button type="button" id="loadMoreInstructors" data-url="/instructor-finder" class="lw-btn lw-btn--outline {{ ($instructors->lastPage() <= $instructors->currentPage()) ? ' d-none' : '' }}">{{ trans('site.load_more_instructors') }}</button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Top rated --}}
        @if(!empty($bestRateInstructors) and count($bestRateInstructors))
            <section class="lw-band lw-band--blue">
                <div class="ms-container">
                    <div class="lw-section__head">
                        <h2 class="lw-section__title">
                            @include('web.default.includes.manuscript.star', ['size' => 24, 'dot' => '#EEF6FB'])
                            {{ trans('site.best_rated_instructors') }}
                        </h2>
                        <a href="/instructors?sort=top_rate" class="lw-section__link">{{ trans('home.view_all') }} <i data-feather="chevron-left" width="16" height="16" class="lw-flip-ltr" aria-hidden="true"></i></a>
                        <p class="lw-section__hint">{{ trans('site.best_rated_instructors_subtitle') }}</p>
                    </div>

                    <div class="lw-grid">
                        @foreach($bestRateInstructors->take(3) as $bestRateInstructor)
                            @include('web.default.includes.lightway.instructor_card', ['instructor' => $bestRateInstructor])
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- Top sellers --}}
        @if(!empty($bestSalesInstructors) and count($bestSalesInstructors))
            <section class="lw-band">
                <div class="ms-container">
                    <div class="lw-section__head">
                        <h2 class="lw-section__title">
                            @include('web.default.includes.manuscript.star', ['size' => 24, 'dot' => '#FBF6EC'])
                            {{ trans('site.top_sellers') }}
                        </h2>
                        <a href="/instructors?sort=top_sale" class="lw-section__link">{{ trans('home.view_all') }} <i data-feather="chevron-left" width="16" height="16" class="lw-flip-ltr" aria-hidden="true"></i></a>
                        <p class="lw-section__hint">{{ trans('site.top_sellers_subtitle') }}</p>
                    </div>

                    <div class="lw-grid lw-grid--6">
                        @foreach($bestSalesInstructors->take(6) as $bestSalesInstructor)
                            @include('web.default.includes.lightway.instructor_card', ['instructor' => $bestSalesInstructor])
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    </div>
@endsection

@push('scripts_bottom')
    <script src="/assets/vendors/wrunner-html-range-slider-with-2-handles/js/wrunner-jquery.js"></script>
    <script src="/assets/vendors/leaflet/leaflet.min.js"></script>
    <script src="/assets/vendors/leaflet/leaflet.markercluster/leaflet.markercluster-src.js"></script>
    <script src="/assets/default/vendors/swiper/swiper-bundle.min.js"></script>

    <script>
        var currency = '{{ $currency }}';
        var profileLang = '{{ trans('public.profile') }}';
        var hourLang = '{{ trans('update.hour') }}';
        var freeLang = '{{ trans('public.free') }}';
        var mapUsers = JSON.parse(@json($mapUsers->toJson()));
        var selectProvinceLang = '{{ trans('update.select_province') }}';
        var selectCityLang = '{{ trans('update.select_city') }}';
        var selectDistrictLang = '{{ trans('update.select_district') }}';
        var leafletApiPath = '{{ getLeafletApiPath() }}';
    </script>

    <script src="/assets/default/js/parts/get-regions.min.js"></script>
    <script src="/assets/default/js/parts/instructor-finder-wizard.min.js"></script>
    <script src="/assets/default/js/parts/instructors.min.js"></script>

    <script src="/assets/default/js/parts/instructor-finder.min.js"></script>
@endpush
