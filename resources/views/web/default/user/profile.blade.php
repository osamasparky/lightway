@extends(getTemplate().'.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/default/vendors/persian-datepicker/persian-datepicker.min.css"/>
    <link rel="stylesheet" href="/assets/default/css/css-stars.css">
@endpush

@section('content')
    @php
        $activeTab = request()->get('tab') ?: 'about';
        $isFollower = (!empty($authUserIsFollower) and $authUserIsFollower);
        $listTitle = $user->isOrganization() ? trans('home.organizations') : trans('home.instructors');
        $listUrl = $user->isOrganization() ? '/organizations' : '/instructors';

        $profileTabs = [
            ['id' => 'about', 'label' => trans('site.about'), 'show' => true],
            ['id' => 'webinars', 'label' => trans('panel.classes'), 'show' => true],
            ['id' => 'instructors', 'label' => trans('home.instructors'), 'show' => $user->isOrganization()],
            ['id' => 'products', 'label' => trans('update.products'), 'show' => (!empty(getStoreSettings('status')) and getStoreSettings('status'))],
            ['id' => 'posts', 'label' => trans('update.articles'), 'show' => true],
            ['id' => 'forum', 'label' => trans('update.forum'), 'show' => (!empty(getFeaturesSettings('forums_status')) and getFeaturesSettings('forums_status'))],
            ['id' => 'badges', 'label' => trans('site.badges'), 'show' => true],
            ['id' => 'appointments', 'label' => trans('site.book_an_appointment'), 'show' => true],
        ];
    @endphp

    {{-- Navy profile header --}}
    <section class="lw-profile-hero">
        <div class="ms-container lw-profile-hero__inner">
            <div class="lw-profile-hero__main">
                <span class="lw-arch-avatar">
                    <img src="{{ $user->getAvatar(190) }}" alt="{{ $user['full_name'] }}">

                    @if($user->offline)
                        <span class="lw-ring-avatar__state is-offline" title="{{ trans('public.unavailable') }}">
                            <i data-feather="slash" width="14" height="14" aria-hidden="true"></i>
                        </span>
                    @elseif($user->verified)
                        <span class="lw-ring-avatar__state is-verified" title="{{ trans('public.verified') }}">
                            <i data-feather="check" width="14" height="14" aria-hidden="true"></i>
                        </span>
                    @endif
                </span>

                <div class="lw-profile-hero__text">
                    <nav class="lw-breadcrumb lw-breadcrumb--dark" aria-label="{{ trans('home.lw_breadcrumb') }}">
                        <ol>
                            <li><a href="/">{{ trans('home.ms_home_link') }}</a></li>
                            <li><a href="{{ $listUrl }}">{{ $listTitle }}</a></li>
                            <li><span aria-current="page">{{ $user['full_name'] }}</span></li>
                        </ol>
                    </nav>

                    <h1 class="lw-profile-hero__name" dir="auto">{{ $user['full_name'] }}</h1>

                    @if(!empty($user['headline']))
                        <p class="lw-profile-hero__headline" dir="auto">{{ $user['headline'] }}</p>
                    @endif

                    <div class="lw-profile-hero__meta">
                        @include('web.default.includes.lightway.stars', ['rate' => $userRates, 'emptyText' => trans('home.lw_no_reviews')])
                        <span>{{ $userFollowers->count() }} {{ trans('panel.followers') }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ $userFollowing->count() }} {{ trans('panel.following') }}</span>
                    </div>

                    @if(!empty($userBadges) and count($userBadges))
                        <div class="lw-profile-hero__badges">
                            @foreach($userBadges as $userBadge)
                                <img loading="lazy" src="{{ !empty($userBadge->badge_id) ? $userBadge->badge->image : $userBadge->image }}" width="30" height="30"
                                     alt="{{ !empty($userBadge->badge_id) ? $userBadge->badge->title : $userBadge->title }}"
                                     data-toggle="tooltip" data-placement="bottom" data-html="true"
                                     title="{!! (!empty($userBadge->badge_id) ? nl2br($userBadge->badge->description) : nl2br($userBadge->description)) !!}">
                            @endforeach
                        </div>
                    @endif

                    <div class="lw-profile-hero__actions">
                        <button type="button" id="followToggle" data-user-id="{{ $user['id'] }}" class="lw-btn lw-follow-btn btn-{{ $isFollower ? 'danger' : 'primary' }}">
                            {{ $isFollower ? trans('panel.unfollow') : trans('panel.follow') }}
                        </button>

                        @if($user->public_message)
                            <button type="button" class="js-send-message lw-btn lw-btn--ghost-light">
                                <i data-feather="mail" width="16" height="16" aria-hidden="true"></i>
                                {{ trans('site.send_message') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="lw-profile-stats">
                @include('web.default.includes.lightway.medallion', ['value' => $user->students_count, 'label' => trans('quiz.students'), 'tone' => 'orange'])
                @include('web.default.includes.lightway.medallion', ['value' => count($webinars), 'label' => trans('webinars.classes'), 'tone' => 'blue'])
                @include('web.default.includes.lightway.medallion', ['value' => $user->reviewsCount(), 'label' => trans('product.reviews'), 'tone' => 'orange'])
                @include('web.default.includes.lightway.medallion', ['value' => $appointments, 'label' => trans('site.appointments'), 'tone' => 'blue'])
            </div>
        </div>

        <div class="ms-band lw-banner__band" aria-hidden="true"></div>
    </section>

    <div class="ms-container lw-page">
        <ul class="nav lw-tabs lw-tabs--wrap" id="tabs-tab" role="tablist">
            @foreach($profileTabs as $profileTab)
                @if($profileTab['show'])
                    <li class="nav-item">
                        <a class="lw-tabs__link {{ $activeTab == $profileTab['id'] ? 'active' : '' }}" id="{{ $profileTab['id'] }}-tab" data-toggle="tab" href="#{{ $profileTab['id'] }}" role="tab" aria-controls="{{ $profileTab['id'] }}" aria-selected="{{ $activeTab == $profileTab['id'] ? 'true' : 'false' }}">{{ $profileTab['label'] }}</a>
                    </li>
                @endif
            @endforeach
        </ul>

        <div class="tab-content lw-tab-content lw-profile-tabs" id="nav-tabContent">
            <div class="tab-pane fade {{ $activeTab == 'about' ? 'show active' : '' }}" id="about" role="tabpanel" aria-labelledby="about-tab">
                @include('web.default.user.profile_tabs.about')
            </div>

            <div class="tab-pane fade {{ $activeTab == 'webinars' ? 'show active' : '' }}" id="webinars" role="tabpanel" aria-labelledby="webinars-tab">
                @include('web.default.user.profile_tabs.webinars')
            </div>

            @if($user->isOrganization())
                <div class="tab-pane fade {{ $activeTab == 'instructors' ? 'show active' : '' }}" id="instructors" role="tabpanel" aria-labelledby="instructors-tab">
                    @include('web.default.user.profile_tabs.instructors')
                </div>
            @endif

            <div class="tab-pane fade {{ $activeTab == 'posts' ? 'show active' : '' }}" id="posts" role="tabpanel" aria-labelledby="posts-tab">
                @include('web.default.user.profile_tabs.posts')
            </div>

            @if(!empty(getFeaturesSettings('forums_status')) and getFeaturesSettings('forums_status'))
                <div class="tab-pane fade {{ $activeTab == 'forum' ? 'show active' : '' }}" id="forum" role="tabpanel" aria-labelledby="forum-tab">
                    @include('web.default.user.profile_tabs.forum')
                </div>
            @endif

            @if(!empty(getStoreSettings('status')) and getStoreSettings('status'))
                <div class="tab-pane fade {{ $activeTab == 'products' ? 'show active' : '' }}" id="products" role="tabpanel" aria-labelledby="products-tab">
                    @include('web.default.user.profile_tabs.products')
                </div>
            @endif

            <div class="tab-pane fade {{ $activeTab == 'badges' ? 'show active' : '' }}" id="badges" role="tabpanel" aria-labelledby="badges-tab">
                @include('web.default.user.profile_tabs.badges')
            </div>

            <div class="tab-pane fade {{ $activeTab == 'appointments' ? 'show active' : '' }}" id="appointments" role="tabpanel" aria-labelledby="appointments-tab">
                <div class="lw-panel lw-booking">
                    @include('web.default.user.profile_tabs.appointments')
                </div>
            </div>
        </div>
    </div>

    @include('web.default.user.send_message_modal')
@endsection

@push('scripts_bottom')
    <script>
        var unFollowLang = '{{ trans('panel.unfollow') }}';
        var followLang = '{{ trans('panel.follow') }}';
        var reservedLang = '{{ trans('meeting.reserved') }}';
        var availableDays = {{ json_encode($times) }};
        var messageSuccessSentLang = '{{ trans('site.message_success_sent') }}';
    </script>

    <script src="/assets/default/vendors/persian-datepicker/persian-date.js"></script>
    <script src="/assets/default/vendors/persian-datepicker/persian-datepicker.js"></script>

    <script src="/assets/default/js/parts/profile.min.js"></script>

    @if(!empty($user->live_chat_js_code) and !empty(getFeaturesSettings('show_live_chat_widget')))
        <script>
            (function () {
                "use strict"

                {!! $user->live_chat_js_code !!}
            })(jQuery)
        </script>
    @endif
@endpush
