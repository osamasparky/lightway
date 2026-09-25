{{--
    Instructor finder result card (horizontal). Also rendered by InstructorFinderController::handleLoadMoreHtml.
    Keeps: discount badge, occupations, about, rating, tutoring hours, badges, meeting price / free / not available.
--}}
@php
    $price = (!empty($instructor->meeting)) ? $instructor->meeting->amount : 0;
    $discount = (!empty($price) and !empty($instructor->meeting) and !empty($instructor->meeting->discount) and $instructor->meeting->discount > 0) ? $instructor->meeting->discount : 0;
    $hasMeetingTimes = (!empty($instructor->meeting) and !empty($instructor->meeting->meetingTimes) and count($instructor->meeting->meetingTimes));
    $canReserve = ($hasMeetingTimes and !$instructor->meeting->disabled);
@endphp

<article class="lw-card lw-finder-card">
    <div class="lw-finder-card__media">
        <span class="lw-arch lw-arch--avatar">
            <span class="lw-arch__clip">
                <img loading="lazy" src="{{ $instructor->getAvatar(190) }}" alt="{{ $instructor->full_name }}" class="lw-arch__img">
            </span>
        </span>

        @if($instructor->offline)
            <span class="lw-ring-avatar__state is-offline" title="{{ trans('public.unavailable') }}"><i data-feather="slash" width="14" height="14" aria-hidden="true"></i></span>
        @elseif($instructor->verified)
            <span class="lw-ring-avatar__state is-verified" title="{{ trans('public.verified') }}"><i data-feather="check" width="14" height="14" aria-hidden="true"></i></span>
        @endif
    </div>

    <div class="lw-finder-card__body">
        <div class="lw-finder-card__head">
            <h3 class="lw-finder-card__name" dir="auto">
                <a href="{{ $instructor->getProfileUrl() }}" class="lw-stretched-link">{{ $instructor->full_name }}</a>
            </h3>
            @if(!empty($discount))
                <span class="lw-badge lw-badge--featured">{{ trans('public.offer', ['off' => $discount]) }}</span>
            @endif
        </div>

        @if(!empty($instructor->bio))
            <span class="lw-finder-card__bio" dir="auto">{{ $instructor->bio }}</span>
        @endif

        @if(!empty($instructor->occupations) and count($instructor->occupations))
            <div class="lw-tags">
                @foreach($instructor->occupations as $occupation)
                    @if(!empty($occupation->category))
                        <span class="lw-tag">{{ $occupation->category->title }}</span>
                    @endif
                @endforeach
            </div>
        @endif

        @if(!empty($instructor->about))
            <p class="lw-finder-card__about" dir="auto">{{ truncate($instructor->about, 200) }}</p>
        @endif

        <div class="lw-finder-card__meta">
            @include('web.default.includes.lightway.stars', ['rate' => $instructor->rates(), 'emptyText' => trans('home.lw_no_reviews')])
            <span>
                <i data-feather="clock" width="15" height="15" aria-hidden="true"></i>
                {{ $instructor->getTotalHoursTutoring() }} {{ trans('update.hours_tutoring') }}
            </span>

            @php $finderBadges = $instructor->getBadges(); @endphp
            @if(!empty($finderBadges) and count($finderBadges))
                <span class="lw-finder-card__badges">
                    @foreach($finderBadges as $badge)
                        <img loading="lazy" src="{{ !empty($badge->badge_id) ? $badge->badge->image : $badge->image }}" width="22" height="22"
                             alt="{{ !empty($badge->badge_id) ? $badge->badge->title : $badge->title }}" title="{{ !empty($badge->badge_id) ? $badge->badge->title : $badge->title }}">
                    @endforeach
                </span>
            @endif
        </div>
    </div>

    <div class="lw-finder-card__side">
        <div class="lw-finder-card__price">
            @if($hasMeetingTimes)
                @if(!empty($price) and $price > 0)
                    <div class="lw-price lw-price--center">
                        <strong class="lw-price__real">{{ handlePrice(!empty($discount) ? ($price - ($price * $discount / 100)) : $price) }}</strong>
                        @if(!empty($discount))
                            <del class="lw-price__old">{{ handlePrice($price) }}</del>
                        @endif
                    </div>
                    <span class="lw-price__unit">/ {{ trans('update.hour') }}</span>
                @else
                    <strong class="lw-price__real">{{ trans('public.free') }}</strong>
                @endif
            @else
                <span class="lw-finder-card__na">{{ trans('update.not_available_for_meeting') }}</span>
            @endif
        </div>

        @if($canReserve)
            <a href="{{ $instructor->getProfileUrl() }}?tab=appointments" class="lw-btn lw-btn--dark lw-btn--block lw-above-link">{{ trans('public.reserve_a_meeting') }}</a>
        @endif
        <a href="{{ $instructor->getProfileUrl() }}" class="lw-btn lw-btn--outline lw-btn--block lw-above-link">{{ trans('public.profile') }}</a>
    </div>
</article>
