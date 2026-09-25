@extends(getTemplate().'.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/default/vendors/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" href="/assets/default/vendors/select2/select2.min.css">
@endpush

@section('content')
    @include('web.default.includes.lightway.banner', [
        'title' => $title,
        'subtitle' => $instructorsCount . ' ' . $title,
        'breadcrumbs' => [['title' => $title]],
        'search' => ['action' => '/' . $page, 'name' => 'search', 'placeholder' => trans('public.search') . ' ' . $title, 'keep' => ['available_for_meetings', 'free_meetings', 'discount', 'sort', 'categories']],
    ])

    <div class="ms-container lw-page">
        <form id="filtersForm" action="/{{ $page }}" method="get" class="lw-stack">
            @if(request()->get('search'))
                <input type="hidden" name="search" value="{{ request()->get('search') }}">
            @endif

            <div id="topFilters" class="lw-toolbar">
                <div class="lw-toolbar__group">
                    @include('web.default.includes.lightway.toggle', ['name' => 'available_for_meetings', 'id' => 'available_for_meetings', 'label' => trans('public.available_for_meetings'), 'checked' => request()->get('available_for_meetings') == 'on'])
                    @include('web.default.includes.lightway.toggle', ['name' => 'free_meetings', 'id' => 'free_meetings', 'label' => trans('public.free_meetings'), 'checked' => request()->get('free_meetings') == 'on'])
                    @include('web.default.includes.lightway.toggle', ['name' => 'discount', 'id' => 'discount', 'label' => trans('public.discount'), 'checked' => request()->get('discount') == 'on'])
                </div>

                <div class="lw-toolbar__group">
                    <span class="lw-field">
                        <label for="lwInstructorSort">{{ trans('public.sort_by') }}</label>
                        <select name="sort" id="lwInstructorSort" class="lw-select">
                            <option value="">{{ trans('public.all') }}</option>
                            <option value="top_rate" @if(request()->get('sort') == 'top_rate') selected @endif>{{ trans('site.top_rate') }}</option>
                            <option value="top_sale" @if(request()->get('sort') == 'top_sale') selected @endif>{{ trans('site.top_sellers') }}</option>
                        </select>
                    </span>
                </div>
            </div>

            @if(!empty($categories) and count($categories))
                <details class="lw-panel lw-chips-panel" @if(!empty(request()->get('categories'))) open @endif>
                    <summary class="lw-panel__title">
                        @include('web.default.includes.manuscript.star', ['size' => 16, 'dot' => '#FFFDF8'])
                        <span>{{ trans('categories.categories') }}</span>
                        @if(!empty(request()->get('categories')))
                            <span class="lw-panel__extra">{{ count((array) request()->get('categories')) }}</span>
                        @endif
                    </summary>

                    <div class="lw-chips">
                        @foreach($categories as $category)
                            @if(!empty($category->subCategories) and count($category->subCategories))
                                @foreach($category->subCategories as $subCategory)
                                    <label class="lw-chip" for="checkbox{{ $subCategory->id }}">
                                        <input type="checkbox" name="categories[]" id="checkbox{{ $subCategory->id }}" value="{{ $subCategory->id }}" @if(in_array($subCategory->id, (array) request()->get('categories', []))) checked @endif>
                                        <span>{{ $subCategory->title }}</span>
                                    </label>
                                @endforeach
                            @else
                                <label class="lw-chip" for="checkbox{{ $category->id }}">
                                    <input type="checkbox" name="categories[]" id="checkbox{{ $category->id }}" value="{{ $category->id }}" @if(in_array($category->id, (array) request()->get('categories', []))) checked @endif>
                                    <span>{{ $category->title }}</span>
                                </label>
                            @endif
                        @endforeach
                    </div>
                </details>
            @endif
        </form>

        <section class="lw-section lw-section--list">
            @if($instructors->count())
                <div id="instructorsList" class="lw-grid">
                    @foreach($instructors as $instructor)
                        @include('web.default.pages.instructor_card', ['instructor' => $instructor])
                    @endforeach
                </div>
            @else
                <div id="instructorsList"></div>
                <div class="lw-empty">
                    @include(getTemplate() . '.includes.no-result', [
                        'file_name' => 'bio.png',
                        'title' => trans('site.no_result_search'),
                        'hint' => trans('home.lw_try_other_filters'),
                    ])
                </div>
            @endif

            <div class="lw-center">
                <button type="button" id="loadMoreInstructors" data-page="{{ ($page == 'instructors') ? \App\Models\Role::$teacher : \App\Models\Role::$organization }}" class="lw-btn lw-btn--outline {{ ($instructors->lastPage() <= $instructors->currentPage()) ? ' d-none' : '' }}">{{ trans('site.load_more_instructors') }}</button>
            </div>
        </section>
    </div>

    @if(!empty($bestRateInstructors) and !$bestRateInstructors->isEmpty() and (empty(request()->get('sort')) or !in_array(request()->get('sort'),['top_rate','top_sale'])))
        <section class="lw-band lw-band--blue">
            <div class="ms-container">
                <div class="lw-section__head">
                    <h2 class="lw-section__title">
                        @include('web.default.includes.manuscript.star', ['size' => 24, 'dot' => '#EEF6FB'])
                        {{ trans('site.best_rated_instructors') }}
                    </h2>
                    <a href="/{{ $page }}?sort=top_rate" class="lw-section__link">{{ trans('home.view_all') }} <i data-feather="chevron-left" width="16" height="16" class="lw-flip-ltr" aria-hidden="true"></i></a>
                    <p class="lw-section__hint">{{ trans('site.best_rated_instructors_subtitle') }}</p>
                </div>

                <div class="position-relative">
                    <div id="bestRateInstructorsSwiper" class="swiper-container lw-swiper">
                        <div class="swiper-wrapper">
                            @foreach($bestRateInstructors as $bestRateInstructor)
                                <div class="swiper-slide">
                                    @include('web.default.pages.instructor_card', ['instructor' => $bestRateInstructor])
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex justify-content-center">
                        <div class="swiper-pagination best-rate-swiper-pagination"></div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if(!empty($bestSalesInstructors) and !$bestSalesInstructors->isEmpty() and (empty(request()->get('sort')) or !in_array(request()->get('sort'),['top_rate','top_sale'])))
        <section class="lw-band">
            <div class="ms-container">
                <div class="lw-section__head">
                    <h2 class="lw-section__title">
                        @include('web.default.includes.manuscript.star', ['size' => 24, 'dot' => '#FBF6EC'])
                        {{ trans('site.top_sellers') }}
                    </h2>
                    <a href="/{{ $page }}?sort=top_sale" class="lw-section__link">{{ trans('home.view_all') }} <i data-feather="chevron-left" width="16" height="16" class="lw-flip-ltr" aria-hidden="true"></i></a>
                    <p class="lw-section__hint">{{ trans('site.top_sellers_subtitle') }}</p>
                </div>

                <div class="position-relative">
                    <div id="topSaleInstructorsSwiper" class="swiper-container lw-swiper">
                        <div class="swiper-wrapper">
                            @foreach($bestSalesInstructors as $bestSalesInstructor)
                                <div class="swiper-slide">
                                    @include('web.default.pages.instructor_card', ['instructor' => $bestSalesInstructor])
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex justify-content-center">
                        <div class="swiper-pagination best-sale-swiper-pagination"></div>
                    </div>
                </div>
            </div>
        </section>
    @endif
@endsection

@push('scripts_bottom')
    <script src="/assets/default/vendors/select2/select2.min.js"></script>
    <script src="/assets/default/vendors/swiper/swiper-bundle.min.js"></script>

    <script src="/assets/default/js/parts/instructors.min.js"></script>
@endpush
