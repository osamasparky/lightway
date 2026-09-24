@php
    // Split the admin-defined title after its first comma or full stop so the second
    // half can take the accent colour, as in the design. Otherwise it renders in one colour.
    $heroTitle = $heroSectionData['title'] ?? '';
    $heroTitleLead = $heroTitle;
    $heroTitleAccent = null;

    if (preg_match('/^(.+?[،,.؛])\s+(\S.*)$/u', $heroTitle, $titleParts)) {
        $heroTitleLead = $titleParts[1];
        $heroTitleAccent = $titleParts[2];
    }

    $statisticsSettings = getStatisticsSettings();
    $heroStats = [];

    if (!empty($statisticsSettings['enable_statistics'])) {
        if (!empty($statisticsSettings['display_default_statistics']) and !empty($homeDefaultStatistics)) {
            $heroStats = [
                ['count' => $homeDefaultStatistics['skillfulTeachersCount'], 'title' => trans('home.skillful_teachers'), 'desc' => trans('home.skillful_teachers_hint')],
                ['count' => $homeDefaultStatistics['studentsCount'], 'title' => trans('home.happy_students'), 'desc' => trans('home.happy_students_hint')],
                ['count' => $homeDefaultStatistics['liveClassCount'], 'title' => trans('home.live_classes'), 'desc' => trans('home.live_classes_hint')],
                ['count' => $homeDefaultStatistics['offlineCourseCount'], 'title' => trans('home.offline_courses'), 'desc' => trans('home.offline_courses_hint')],
            ];
        } elseif (!empty($homeCustomStatistics)) {
            foreach ($homeCustomStatistics as $homeCustomStatistic) {
                $heroStats[] = ['count' => $homeCustomStatistic->count, 'title' => $homeCustomStatistic->title, 'desc' => $homeCustomStatistic->description];
            }
        }
    }
@endphp

<section class="ms-hero ms-lattice">
    <div class="ms-band" aria-hidden="true"></div>

    <div class="ms-hero__body">
        @if(!empty($generalSettings['logo']))
            <img src="{{ $generalSettings['logo'] }}" alt="" aria-hidden="true" class="ms-hero__ornament ms-hero__ornament--start">
            <img src="{{ $generalSettings['logo'] }}" alt="" aria-hidden="true" class="ms-hero__ornament ms-hero__ornament--end">
        @endif

        <div class="ms-container ms-hero__content">
            <span class="ms-kicker">{{ trans('home.ms_hero_kicker') }}</span>

            @if(!empty($heroTitle))
                <h1 class="ms-hero__title">
                    {{ $heroTitleLead }}
                    @if(!empty($heroTitleAccent))
                        <span class="ms-hero__title-accent">{{ $heroTitleAccent }}</span>
                    @endif
                </h1>
            @endif

            @if(!empty($heroSectionData['description']))
                <p class="ms-hero__lead">{!! nl2br($heroSectionData['description']) !!}</p>
            @endif

            <form action="/search" method="get" role="search" class="ms-hero__search">
                <label for="msHeroSearch" class="sr-only">{{ trans('home.find') }}</label>
                <i data-feather="search" width="22" height="22" class="ms-hero__search-icon"></i>
                <input id="msHeroSearch" type="search" name="search" placeholder="{{ trans('home.slider_search_placeholder') }}">
                <button type="submit" class="ms-btn ms-btn--dark">{{ trans('home.find') }}</button>
            </form>

            @if(count($heroStats))
                <div class="ms-stats">
                    @foreach($heroStats as $stat)
                        <div class="ms-stat {{ $loop->odd ? 'ms-stat--blue' : 'ms-stat--orange' }}">
                            <span class="ms-stat__medal">
                                <svg width="96" height="96" viewBox="0 0 96 96" aria-hidden="true" focusable="false">
                                    <rect x="18" y="18" width="60" height="60" class="ms-stat__shape"></rect>
                                    <rect x="18" y="18" width="60" height="60" transform="rotate(45 48 48)" class="ms-stat__shape"></rect>
                                    <circle cx="48" cy="48" r="26" class="ms-stat__disc"></circle>
                                </svg>
                                <strong class="ms-stat__count">{{ $stat['count'] }}</strong>
                            </span>
                            <strong class="ms-stat__title">{{ $stat['title'] }}</strong>
                            @if(!empty($stat['desc']))
                                <span class="ms-stat__desc">{{ $stat['desc'] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="ms-band" aria-hidden="true"></div>
</section>
