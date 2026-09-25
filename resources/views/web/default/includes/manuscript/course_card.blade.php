{{--
    Manuscript course card (horizontal: arch image + details).
    Params: $webinar (Webinar/Bundle), $isFeature (optional, hides the "featured" badge).
    Keeps the core card behaviour: status badge, custom product badges, progress bar,
    calendar link for live classes, teacher/category links, rating, reward points and discounted price.
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

    $rate = $webinar->getRate();
    $roundedRate = (int)round($rate);
@endphp

<article class="ms-course">
    <div class="ms-course__media">
        <div class="ms-course__arch">
            <div class="ms-course__arch-clip">
                <img loading="lazy" src="{{ $webinar->getImage() }}" alt="{{ $webinar->title }}" class="ms-course__img">
            </div>

            @if($webinar->checkShowProgress())
                <span class="ms-course__progress" aria-hidden="true">
                    <span style="width: {{ $webinar->getProgress() }}%"></span>
                </span>
            @endif
        </div>

        @if($webinar->type == 'webinar')
            <a href="{{ $webinar->addToCalendarLink() }}" target="_blank" rel="noopener" class="ms-course__calendar" aria-label="{{ trans('public.add_to_calendar') }}">
                <i data-feather="bell" width="14" height="14"></i>
            </a>
        @endif
    </div>

    <div class="ms-course__body">
        <div class="ms-course__badges">
            @if(!empty($statusBadge))
                <span class="ms-badge ms-badge--{{ $statusTone }}">{{ $statusBadge }}</span>
            @endif

            @include('web.default.includes.product_custom_badge', ['itemTarget' => $webinar])

            @if(!empty($webinar->teacher))
                <a href="{{ $webinar->teacher->getProfileUrl() }}" target="_blank" class="ms-course__teacher">{{ $webinar->teacher->full_name }}</a>
            @endif
        </div>

        <h3 class="ms-course__title" dir="auto">
            <a href="{{ $webinar->getUrl() }}" class="ms-course__link">{{ clean($webinar->title, 'title') }}</a>
        </h3>

        <div class="ms-course__meta">
            @if(!empty($webinar->category))
                <span>{{ trans('public.in') }} <a href="{{ $webinar->category->getUrl() }}" target="_blank" class="ms-course__cat">{{ $webinar->category->title }}</a></span>
                <span aria-hidden="true">·</span>
            @endif

            <span>{{ convertMinutesToHourAndMinute($webinar->duration) }} {{ trans('home.hours') }}</span>
        </div>

        @if($rate > 0)
            <div class="ms-rate" aria-label="{{ $rate }} / 5">
                <span class="ms-rate__stars" aria-hidden="true">{{ str_repeat('★', $roundedRate) }}<span class="ms-rate__empty">{{ str_repeat('★', 5 - $roundedRate) }}</span></span>
                <strong>{{ $rate }}</strong>
            </div>
        @endif

        <div class="ms-course__foot">
            <span class="ms-course__date">{{ dateTimeFormat(!empty($webinar->start_date) ? $webinar->start_date : $webinar->created_at, 'j M Y') }}</span>

            <span class="ms-price">
                @if(!empty($isRewardCourses) and !empty($webinar->points))
                    <strong class="ms-price__real">{{ $webinar->points }} {{ trans('update.points') }}</strong>
                @elseif(!empty($webinar->price) and $webinar->price > 0)
                    @if($hasDiscount)
                        <del class="ms-price__old">{{ handlePrice($webinar->price, true, true, false, null, true) }}</del>
                        <strong class="ms-price__real">{{ ($bestTicket > 0) ? handlePrice($bestTicket, true, true, false, null, true) : trans('public.free') }}</strong>
                    @else
                        <strong class="ms-price__real">{{ handlePrice($webinar->price, true, true, false, null, true) }}</strong>
                    @endif
                @else
                    <strong class="ms-price__real">{{ trans('public.free') }}</strong>
                @endif
            </span>
        </div>
    </div>
</article>
