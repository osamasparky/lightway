{{-- Trending categories as star medals. Each medal takes its colour from the admin "Trending categories" colour. --}}
<section class="ms-section">
    <div class="ms-container">
        @include('web.default.includes.manuscript.section_head', ['title' => trans('home.trending_categories'), 'hint' => trans('home.trending_categories_hint')])

        <div class="ms-medals">
            @foreach($trendCategories as $trend)
                @continue(empty($trend->category))

                <a href="{{ $trend->category->getUrl() }}" class="ms-medal" style="--ms-medal-tone: {{ !empty($trend->color) ? $trend->color : '#138CCD' }}">
                    <span class="ms-medal__badge">
                        <svg viewBox="0 0 170 170" aria-hidden="true" focusable="false">
                            <rect x="30" y="30" width="110" height="110" class="ms-medal__shape"></rect>
                            <rect x="30" y="30" width="110" height="110" transform="rotate(45 85 85)" class="ms-medal__shape"></rect>
                            <circle cx="85" cy="85" r="52" class="ms-medal__disc"></circle>
                            <circle cx="85" cy="85" r="46" class="ms-medal__ring"></circle>
                        </svg>

                        <strong class="ms-medal__count">{{ $trend->category->webinars_count }}</strong>
                        <span class="ms-medal__unit">{{ trans('product.course') }}</span>
                    </span>

                    <strong class="ms-medal__title">{{ $trend->category->title }}</strong>
                </a>
            @endforeach
        </div>
    </div>
</section>
