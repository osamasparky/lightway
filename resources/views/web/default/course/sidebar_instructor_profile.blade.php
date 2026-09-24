@php
    $hasMeeting = !empty($courseTeacher->hasMeeting());
@endphp

<div class="lw-card lw-instructor lw-instructor--sidebar course-teacher-card">
    @if(!empty($webinarPartnerTeacher))
        <span class="lw-badge lw-badge--muted lw-instructor__flag">{{ trans('public.invited') }}</span>
    @endif

    <span class="lw-ring-avatar">
        <img loading="lazy" src="{{ $courseTeacher->getAvatar(100) }}" alt="{{ $courseTeacher->full_name }}">

        @if($courseTeacher->offline)
            <span class="lw-ring-avatar__state is-offline" title="{{ trans('public.unavailable') }}">
                <i data-feather="slash" width="14" height="14" aria-hidden="true"></i>
            </span>
        @elseif($courseTeacher->verified)
            <span class="lw-ring-avatar__state is-verified" title="{{ trans('public.verified') }}">
                <i data-feather="check" width="14" height="14" aria-hidden="true"></i>
            </span>
        @endif
    </span>

    <h3 class="lw-instructor__name" dir="auto">{{ $courseTeacher->full_name }}</h3>

    @if(!empty($courseTeacher->bio))
        <span class="lw-instructor__bio" dir="auto">{{ $courseTeacher->bio }}</span>
    @endif

    @include('web.default.includes.lightway.stars', ['rate' => $courseTeacher->rates()])

    @php $teacherBadges = $courseTeacher->getBadges(); @endphp
    @if(!empty($teacherBadges) and count($teacherBadges))
        <div class="lw-instructor__badges">
            @foreach($teacherBadges as $userBadge)
                <img loading="lazy" src="{{ !empty($userBadge->badge_id) ? $userBadge->badge->image : $userBadge->image }}" width="28" height="28"
                     alt="{{ !empty($userBadge->badge_id) ? $userBadge->badge->title : $userBadge->title }}"
                     data-toggle="tooltip" data-placement="bottom" data-html="true"
                     title="{!! (!empty($userBadge->badge_id) ? nl2br($userBadge->badge->description) : nl2br($userBadge->description)) !!}">
            @endforeach
        </div>
    @endif

    <div class="lw-instructor__actions">
        <a href="{{ $courseTeacher->getProfileUrl() }}" target="_blank" class="lw-btn lw-btn--outline lw-btn--block">{{ trans('public.profile') }}</a>

        @if($hasMeeting)
            <a href="{{ $courseTeacher->getProfileUrl() }}?tab=appointments" class="lw-btn lw-btn--cta lw-btn--block">{{ trans('public.book_a_meeting') }}</a>
        @endif
    </div>
</div>
