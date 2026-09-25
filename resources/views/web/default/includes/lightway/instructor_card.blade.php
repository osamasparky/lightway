{{--
    Instructor / organization card with an arched top. Params: $instructor
    Keeps: unavailable / discount label, offline & verified marks, bio, rating, badges,
    meeting price (with discount) and reserve / view-profile action.
--}}
@php
    $canReserve = (!empty($instructor->meeting) and !$instructor->meeting->disabled and !empty($instructor->meeting->meetingTimes) and $instructor->meeting->meeting_times_count > 0);
    $profileUrl = $instructor->getProfileUrl() . ($canReserve ? '?tab=appointments' : '');
@endphp

<article class="lw-card lw-instructor">
    @if(!empty($instructor->meeting) and $instructor->meeting->disabled)
        <span class="lw-badge lw-badge--muted lw-instructor__flag">{{ trans('public.unavailable') }}</span>
    @elseif(!empty($instructor->meeting) and !empty($instructor->meeting->discount))
        <span class="lw-badge lw-badge--featured lw-instructor__flag">{{ $instructor->meeting->discount }}% {{ trans('public.off') }}</span>
    @endif

    <span class="lw-ring-avatar">
        <img loading="lazy" src="{{ $instructor->getAvatar(190) }}" alt="{{ $instructor->full_name }}">

        @if($instructor->offline)
            <span class="lw-ring-avatar__state is-offline" title="{{ trans('public.unavailable') }}">
                <i data-feather="slash" width="14" height="14" aria-hidden="true"></i>
            </span>
        @elseif($instructor->verified)
            <span class="lw-ring-avatar__state is-verified" title="{{ trans('public.verified') }}">
                <i data-feather="check" width="14" height="14" aria-hidden="true"></i>
            </span>
        @endif
    </span>

    <h3 class="lw-instructor__name" dir="auto">
        <a href="{{ $profileUrl }}" class="lw-stretched-link">{{ $instructor->full_name }}</a>
    </h3>

    @if(!empty($instructor->bio))
        <span class="lw-instructor__bio" dir="auto">{{ $instructor->bio }}</span>
    @endif

    @include('web.default.includes.lightway.stars', ['rate' => $instructor->rates()])

    @php $badges = $instructor->getBadges(); @endphp
    @if(!empty($badges) and count($badges))
        <div class="lw-instructor__badges">
            @foreach($badges as $badge)
                <img loading="lazy" src="{{ !empty($badge->badge_id) ? $badge->badge->image : $badge->image }}" width="28" height="28"
                     alt="{{ !empty($badge->badge_id) ? $badge->badge->title : $badge->title }}"
                     title="{{ !empty($badge->badge_id) ? $badge->badge->title : $badge->title }}">
            @endforeach
        </div>
    @endif

    @if(!empty($instructor->meeting) and !$instructor->meeting->disabled and !empty($instructor->meeting->amount))
        <div class="lw-price lw-price--center">
            @if(!empty($instructor->meeting->discount))
                <strong class="lw-price__real">{{ handlePrice($instructor->meeting->amount - (($instructor->meeting->amount * $instructor->meeting->discount) / 100)) }}</strong>
                <del class="lw-price__old">{{ handlePrice($instructor->meeting->amount) }}</del>
            @else
                <strong class="lw-price__real">{{ handlePrice($instructor->meeting->amount) }}</strong>
            @endif
            <span class="lw-price__unit">/ {{ trans('home.lw_per_hour') }}</span>
        </div>
    @endif

    <a href="{{ $profileUrl }}" class="lw-btn {{ $canReserve ? 'lw-btn--dark' : 'lw-btn--outline' }} lw-btn--block lw-above-link">
        {{ $canReserve ? trans('public.reserve_a_meeting') : trans('public.view_profile') }}
    </a>
</article>
