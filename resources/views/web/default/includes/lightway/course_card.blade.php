{{--
    Course / bundle card for inner pages (lists, related courses, profile, search…).
    Params: $webinar (Webinar|Bundle), $variant ('grid'|'list'), $isFeature, $isRewardCourses
    Keeps the core card behaviour: status + custom badges, progress, calendar link for live classes,
    teacher/category links, rating, reward points and discounted price.
--}}
@php
    $bestTicket = $webinar->bestTicket();
    $hasDiscount = (!empty($webinar->price) and $webinar->price > 0 and $bestTicket < $webinar->price);

    $statusBadge = null;
    $statusTone = 'type';

    if ($hasDiscount) {
        $statusBadge = trans('public.offer', ['off' => $webinar->bestTicket(true)['percent']]);
        $statusTone = 'offer';
    } elseif (empty($isFeature) and !empty($webinar->feature)) {
        $statusBadge = trans('home.featured');
        $statusTone = 'featured';
    } elseif ($webinar->type == 'webinar') {
        if ($webinar->start_date > time()) {
            $statusBadge = trans('panel.not_conducted');
            $statusTone = 'live';
        } elseif ($webinar->isProgressing()) {
            $statusBadge = trans('webinars.in_progress');
            $statusTone = 'live';
        } else {
            $statusBadge = trans('public.finished');
            $statusTone = 'done';
        }
    } elseif (!empty($webinar->type)) {
        $statusBadge = trans('webinars.' . $webinar->type);
    }
@endphp

<article class="lw-card lw-course {{ ($variant ?? 'grid') == 'list' ? 'lw-course--list' : '' }}">
    <div class="lw-course__media">
        @include('web.default.includes.lightway.arch', ['src' => $webinar->getImage(), 'alt' => $webinar->title, 'class' => 'lw-arch--course'])

        <div class="lw-course__badges">
            @if(!empty($statusBadge))
                <span class="lw-badge lw-badge--{{ $statusTone }}">{{ $statusBadge }}</span>
            @endif

            @include('web.default.includes.product_custom_badge', ['itemTarget' => $webinar])
        </div>

        @if($webinar->checkShowProgress())
            <span class="lw-course__progress" role="progressbar" aria-valuenow="{{ $webinar->getProgress() }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ trans('home.lw_progress') }}">
                <span style="width: {{ $webinar->getProgress() }}%"></span>
            </span>
        @endif

        @if($webinar->type == 'webinar')
            <a href="{{ $webinar->addToCalendarLink() }}" target="_blank" rel="noopener" class="lw-icon-btn lw-course__calendar" aria-label="{{ trans('public.add_to_calendar') }}">
                <i data-feather="bell" width="16" height="16" aria-hidden="true"></i>
            </a>
        @endif
    </div>

    <div class="lw-course__body">
        @if(!empty($webinar->teacher))
            <a href="{{ $webinar->teacher->getProfileUrl() }}" target="_blank" class="lw-course__teacher lw-above-link">
                <span class="lw-avatar-dot" aria-hidden="true"><i data-feather="user" width="15" height="15"></i></span>
                <span>{{ $webinar->teacher->full_name }}</span>
            </a>
        @endif

        <h3 class="lw-card__title" dir="auto">
            <a href="{{ $webinar->getUrl() }}" class="lw-stretched-link">{{ clean($webinar->title, 'title') }}</a>
        </h3>

        @if(!empty($webinar->category))
            <span class="lw-card__meta">{{ trans('public.in') }} <a href="{{ $webinar->category->getUrl() }}" target="_blank" class="lw-card__cat lw-above-link">{{ $webinar->category->title }}</a></span>
        @endif

        @include('web.default.includes.lightway.stars', ['rate' => $webinar->getRate(), 'emptyText' => trans('home.lw_no_reviews')])

        <div class="lw-course__facts">
            <span>
                <i data-feather="clock" width="15" height="15" aria-hidden="true"></i>
                {{ convertMinutesToHourAndMinute($webinar->duration) }} {{ trans('home.hours') }}
            </span>
            <span>
                <i data-feather="calendar" width="15" height="15" aria-hidden="true"></i>
                {{ dateTimeFormat(!empty($webinar->start_date) ? $webinar->start_date : $webinar->created_at, 'j M Y') }}
            </span>
        </div>

        <div class="lw-price">
            @if(!empty($isRewardCourses) and !empty($webinar->points))
                <strong class="lw-price__real">{{ $webinar->points }} {{ trans('update.points') }}</strong>
            @elseif(!empty($webinar->price) and $webinar->price > 0)
                @if($hasDiscount)
                    <strong class="lw-price__real">{{ ($bestTicket > 0) ? handlePrice($bestTicket, true, true, false, null, true) : trans('public.free') }}</strong>
                    <del class="lw-price__old">{{ handlePrice($webinar->price, true, true, false, null, true) }}</del>
                @else
                    <strong class="lw-price__real">{{ handlePrice($webinar->price, true, true, false, null, true) }}</strong>
                @endif
            @else
                <strong class="lw-price__real">{{ trans('public.free') }}</strong>
            @endif
        </div>
    </div>
</article>
