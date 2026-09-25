@extends(getTemplate().'.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/default/css/css-stars.css">
    <link rel="stylesheet" href="/assets/default/vendors/video/video-js.min.css">
@endpush

@section('content')
    @php
        $percent = $course->getProgress();
        $reviewersCount = $course->reviews->pluck('creator_id')->count();

        $courseStatus = null;
        if ($course->isWebinar()) {
            if ($course->start_date > time()) {
                $courseStatus = trans('panel.not_conducted');
            } elseif ($course->isProgressing()) {
                $courseStatus = trans('webinars.in_progress');
            } else {
                $courseStatus = trans('public.finished');
            }
        }

        $crumbs = [['title' => trans('home.lw_courses'), 'url' => '/classes']];
        if (!empty($course->category)) {
            $crumbs[] = ['title' => $course->category->title, 'url' => $course->category->getUrl()];
        }
        $crumbs[] = ['title' => $course->title];
    @endphp

    {{-- Header band --}}
    <section class="lw-banner lw-banner--item ms-lattice {{ $course->type }}">
        <div class="ms-container lw-banner__inner">
            <div class="lw-banner__text">
                <nav class="lw-breadcrumb" aria-label="{{ trans('home.lw_breadcrumb') }}">
                    <ol>
                        <li><a href="/">{{ trans('home.ms_home_link') }}</a></li>
                        @foreach($crumbs as $crumb)
                            <li>
                                @if(!$loop->last and !empty($crumb['url']))
                                    <a href="{{ $crumb['url'] }}">{{ $crumb['title'] }}</a>
                                @else
                                    <span @if($loop->last) aria-current="page" @endif>{{ $crumb['title'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>

                <span class="lw-badge lw-badge--sand">
                    {{ trans('webinars.' . $course->type) }}@if(!empty($courseStatus)) · {{ $courseStatus }}@endif
                </span>

                <h1 class="lw-banner__title lw-banner__title--item" dir="auto">{{ $course->title }}</h1>

                <div class="lw-item-meta">
                    @if(!empty($course->category))
                        <span>{{ trans('public.in') }} <a href="{{ $course->category->getUrl() }}" class="lw-item-meta__link">{{ $course->category->title }}</a></span>
                    @endif

                    <span class="lw-item-meta__rate">
                        @include('web.default.includes.lightway.stars', ['rate' => $course->getRate(), 'emptyText' => trans('home.lw_no_reviews')])
                        <span>({{ $reviewersCount }} {{ trans('public.ratings') }})</span>
                    </span>

                    <span>
                        <i data-feather="user" width="16" height="16" aria-hidden="true"></i>
                        {{ trans('public.created_by') }}
                        <a href="{{ $course->teacher->getProfileUrl() }}" target="_blank" class="lw-item-meta__link">{{ $course->teacher->full_name }}</a>
                    </span>

                    <span>
                        <i data-feather="users" width="16" height="16" aria-hidden="true"></i>
                        @if(!is_null($course->capacity))
                            {{ $course->getSalesCount() }}/{{ $course->capacity }} {{ trans('quiz.students') }}
                        @else
                            {{ $course->getSalesCount() }} {{ trans('quiz.students') }}
                        @endif
                    </span>
                </div>

                @if($hasBought or $percent)
                    <div class="lw-learning-progress">
                        <span class="lw-learning-progress__bar" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ trans('home.lw_progress') }}">
                            <span style="width: {{ $percent }}%"></span>
                        </span>
                        <span class="lw-learning-progress__text">
                            @if($hasBought and (!$course->isWebinar() or $course->isProgressing()))
                                {{ trans('public.course_learning_passed',['percent' => $percent]) }}
                            @elseif(!is_null($course->capacity))
                                {{ $course->getSalesCount() }}/{{ $course->capacity }} {{ trans('quiz.students') }}
                            @else
                                {{ trans('public.course_learning_passed',['percent' => $percent]) }}
                            @endif
                        </span>
                    </div>
                @endif
            </div>
        </div>

        <div class="ms-band lw-banner__band" aria-hidden="true"></div>
    </section>

    <section class="ms-container lw-page lw-course-page {{ $course->type }}">
        @if(!empty($activeSpecialOffer))
            <div class="lw-special-offer">
                @include('web.default.course.special_offer')
            </div>
        @endif

        <div class="lw-with-sidebar lw-with-sidebar--end">
            {{-- Main column --}}
            <div class="lw-stack">
                <div class="lw-media">
                    @include('web.default.includes.lightway.arch', ['src' => $course->getImage(), 'alt' => $course->title, 'class' => 'lw-arch--hero', 'lazy' => false])

                    @if($course->video_demo)
                        <button type="button" id="webinarDemoVideoBtn"
                                data-video-path="{{ $course->video_demo_source == 'upload' ?  url($course->video_demo) : $course->video_demo }}"
                                data-video-source="{{ $course->video_demo_source }}"
                                class="lw-media__play" aria-label="{{ trans('webinars.webinar_demo') }}">
                            <i data-feather="play" width="28" height="28" aria-hidden="true"></i>
                        </button>
                    @endif
                </div>

                @if(
                        !empty(getFeaturesSettings("frontend_coupons_display_type")) and
                        getFeaturesSettings("frontend_coupons_display_type") == "before_content" and
                        !empty($instructorDiscounts) and
                        count($instructorDiscounts)
                    )
                    @foreach($instructorDiscounts as $instructorDiscount)
                        @include('web.default.includes.discounts.instructor_discounts_card', ['discount' => $instructorDiscount, 'instructorDiscountClassName' => ""])
                    @endforeach
                @endif

                <div>
                    <ul class="nav lw-tabs" id="tabs-tab" role="tablist">
                        <li class="nav-item">
                            <a class="lw-tabs__link {{ (empty(request()->get('tab','')) or request()->get('tab','') == 'information') ? 'active' : '' }}" id="information-tab"
                               data-toggle="tab" href="#information" role="tab" aria-controls="information"
                               aria-selected="{{ (empty(request()->get('tab','')) or request()->get('tab','') == 'information') ? 'true' : 'false' }}">{{ trans('product.information') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="lw-tabs__link {{ (request()->get('tab','') == 'content') ? 'active' : '' }}" id="content-tab" data-toggle="tab"
                               href="#content" role="tab" aria-controls="content"
                               aria-selected="{{ (request()->get('tab','') == 'content') ? 'true' : 'false' }}">{{ trans('product.content') }} ({{ $webinarContentCount }})</a>
                        </li>
                        <li class="nav-item">
                            <a class="lw-tabs__link {{ (request()->get('tab','') == 'reviews') ? 'active' : '' }}" id="reviews-tab" data-toggle="tab"
                               href="#reviews" role="tab" aria-controls="reviews"
                               aria-selected="{{ (request()->get('tab','') == 'reviews') ? 'true' : 'false' }}">{{ trans('product.reviews') }} ({{ $course->reviews->count() > 0 ? $reviewersCount : 0 }})</a>
                        </li>
                    </ul>

                    <div class="tab-content lw-tab-content" id="nav-tabContent">
                        <div class="tab-pane fade {{ (empty(request()->get('tab','')) or request()->get('tab','') == 'information') ? 'show active' : '' }} " id="information" role="tabpanel"
                             aria-labelledby="information-tab">
                            @include(getTemplate().'.course.tabs.information')
                        </div>
                        <div class="tab-pane fade {{ (request()->get('tab','') == 'content') ? 'show active' : '' }}" id="content" role="tabpanel" aria-labelledby="content-tab">
                            @include(getTemplate().'.course.tabs.content')
                        </div>
                        <div class="tab-pane fade {{ (request()->get('tab','') == 'reviews') ? 'show active' : '' }}" id="reviews" role="tabpanel" aria-labelledby="reviews-tab">
                            @include(getTemplate().'.course.tabs.reviews')
                        </div>
                    </div>
                </div>

                @if(
                       !empty(getFeaturesSettings("frontend_coupons_display_type")) and
                       getFeaturesSettings("frontend_coupons_display_type") == "after_content" and
                       !empty($instructorDiscounts) and
                       count($instructorDiscounts)
                   )
                    @foreach($instructorDiscounts as $instructorDiscount)
                        @include('web.default.includes.discounts.instructor_discounts_card', ['discount' => $instructorDiscount, 'instructorDiscountClassName' => ""])
                    @endforeach
                @endif
            </div>

            {{-- Sidebar --}}
            <aside class="lw-stack lw-item-sidebar" aria-label="{{ trans('product.information') }}">
                <div class="lw-buy-card">
                    <form action="/cart/store" method="post">
                        {{ csrf_field() }}
                        <input type="hidden" name="item_id" value="{{ $course->id }}">
                        <input type="hidden" name="item_name" value="webinar_id">

                        @if(!empty($course->tickets))
                            @foreach($course->tickets as $ticket)
                                <label class="lw-ticket" for="courseOff{{ $ticket->id }}">
                                    <input @if(!$ticket->isValid()) disabled @endif type="radio"
                                           data-discount-price="{{ handleCoursePagePrice($ticket->getPriceWithDiscount($course->price, !empty($activeSpecialOffer) ? $activeSpecialOffer : null))['price'] }}"
                                           value="{{ ($ticket->isValid()) ? $ticket->id : '' }}"
                                           name="ticket_id"
                                           id="courseOff{{ $ticket->id }}">
                                    <span class="lw-ticket__text">
                                        <strong>{{ $ticket->title }} @if(!empty($ticket->discount)) ({{ $ticket->discount }}% {{ trans('public.off') }}) @endif</strong>
                                        <span>{{ $ticket->getSubTitle() }}</span>
                                    </span>
                                </label>
                            @endforeach
                        @endif

                        @if($course->price > 0)
                            <div id="priceBox" class="lw-buy-card__price {{ !empty($activeSpecialOffer) ? ' has-offer ' : '' }}">
                                <div>
                                    @php
                                        $realPrice = handleCoursePagePrice($course->price);
                                    @endphp
                                    <span id="realPrice" data-value="{{ $course->price }}"
                                          data-special-offer="{{ !empty($activeSpecialOffer) ? $activeSpecialOffer->percent : ''}}"
                                          class="d-block @if(!empty($activeSpecialOffer)) lw-buy-card__old @else lw-buy-card__amount @endif">
                                        {{ $realPrice['price'] }}
                                    </span>

                                    @if(!empty($realPrice['tax']) and empty($activeSpecialOffer))
                                        <span class="d-block lw-buy-card__tax">+ {{ $realPrice['tax'] }} {{ trans('cart.tax') }}</span>
                                    @endif
                                </div>

                                @if(!empty($activeSpecialOffer))
                                    <div>
                                        @php
                                            $priceWithDiscount = handleCoursePagePrice($course->getPrice());
                                        @endphp
                                        <span id="priceWithDiscount" class="d-block lw-buy-card__amount">
                                            {{ $priceWithDiscount['price'] }}
                                        </span>

                                        @if(!empty($priceWithDiscount['tax']))
                                            <span class="d-block lw-buy-card__tax">+ {{ $priceWithDiscount['tax'] }} {{ trans('cart.tax') }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="lw-buy-card__price">
                                <span class="lw-buy-card__amount">{{ trans('public.free') }}</span>
                            </div>
                        @endif

                        @php
                            $canSale = ($course->canSale() and !$hasBought);
                            $canPurchase = $canSale || $hasBought;
                            $authUserJoinedWaitlist = false;

                            if (!empty($authUser)) {
                                $authUserWaitlist = $course->waitlists()->where('user_id', $authUser->id)->first();
                                $authUserJoinedWaitlist = !empty($authUserWaitlist);
                            }
                        @endphp

                        <div class="lw-buy-card__actions">
                            @if(!$canSale and $course->canJoinToWaitlist())
                                <button type="button"
                                        data-slug="{{ $course->slug }}"
                                        class="lw-btn lw-btn--dark lw-btn--block {{ (!$authUserJoinedWaitlist) ? ((!empty($authUser)) ? 'js-join-waitlist-user' : 'js-join-waitlist-guest') : 'disabled' }}"
                                        {{ $authUserJoinedWaitlist ? 'disabled' : '' }}>
                                    @if($authUserJoinedWaitlist)
                                        {{ trans('update.already_joined') }}
                                    @else
                                        {{ trans('update.join_waitlist') }}
                                    @endif
                                </button>
                            @endif

                            @if($hasBought or !empty($course->getInstallmentOrder()))
                                <a href="{{ $course->getLearningPageUrl() }}" class="lw-btn lw-btn--cta lw-btn--block">
                                    {{ trans('update.go_to_learning_page') }}
                                </a>
                            @endif

                            {{-- Paid course --}}
                            @if(!empty($course->price) and $course->price > 0)
                                <button type="button"
                                        class="lw-btn lw-btn--block {{ $canPurchase ? 'lw-btn--dark js-course-add-to-cart-btn' : 'lw-btn--disabled disabled' }}"
                                        @if(!$canPurchase) aria-disabled="true" @endif>
                                    @if(!$canPurchase)
                                        <i data-feather="lock" width="16" height="16" aria-hidden="true"></i>
                                        @if($course->checkCapacityReached())
                                            {{ trans('update.capacity_reached') }}
                                        @else
                                            {{ trans('update.disabled_add_to_cart') }}
                                        @endif
                                    @else
                                        @if($hasBought)
                                            {{ trans('update.add_to_cart_for_someone_else') }}
                                        @else
                                            {{ trans('public.add_to_cart') }}
                                        @endif
                                    @endif
                                </button>

                                @if($canPurchase and !empty($course->points))
                                    <a href="{{ !(auth()->check()) ? '/login' : '#' }}"
                                       class="{{ (auth()->check()) ? 'js-buy-with-point' : '' }} lw-btn lw-btn--outline lw-btn--block {{ (!$canPurchase) ? 'disabled' : '' }}"
                                       rel="nofollow">
                                        {!! trans('update.buy_with_n_points',['points' => $course->points]) !!}
                                    </a>
                                @endif

                                @if($canPurchase and !empty(getFeaturesSettings('direct_classes_payment_button_status')))
                                    <button type="button" class="lw-btn lw-btn--cta lw-btn--block js-course-direct-payment">
                                        @if($hasBought)
                                            {{ trans('update.buy_now_for_someone_else') }}
                                        @else
                                            {{ trans('update.buy_now') }}
                                        @endif
                                    </button>
                                @endif

                                @if(!empty($installments) and count($installments) and getInstallmentsSettings('display_installment_button'))
                                    <a href="/course/{{ $course->slug }}/installments" class="lw-btn lw-btn--outline lw-btn--block">
                                        {{ trans('update.pay_with_installments') }}
                                    </a>
                                @endif
                            @else
                                <a href="{{ $canSale ? '/course/'. $course->slug .'/free' : '#' }}"
                                   class="lw-btn lw-btn--block {{ $canSale ? 'lw-btn--dark' : ('lw-btn--disabled disabled ' . $course->cantSaleStatus($hasBought)) }}"
                                   @if(!$canSale) aria-disabled="true" @endif>
                                    @if(!$canSale)
                                        <i data-feather="lock" width="16" height="16" aria-hidden="true"></i>
                                        @if($course->checkCapacityReached())
                                            {{ trans('update.capacity_reached') }}
                                        @else
                                            {{ trans('public.disabled') }}
                                        @endif
                                    @else
                                        {{ trans('public.enroll_on_webinar') }}
                                    @endif
                                </a>
                            @endif

                            @if($canSale and $course->canUseSubscribe())
                                <a href="/subscribes/apply/{{ $course->slug }}" class="lw-btn lw-btn--outline lw-btn--block btn-subscribe @if(!$canSale) disabled @endif">{{ trans('public.subscribe') }}</a>
                            @endif
                        </div>
                    </form>

                    @if(!empty(getOthersPersonalizationSettings('show_guarantee_text')) and getOthersPersonalizationSettings('show_guarantee_text'))
                        <p class="lw-buy-card__guarantee">
                            <i data-feather="check" width="16" height="16" aria-hidden="true"></i>
                            {{ trans('product.guarantee_text') }}
                        </p>
                    @endif

                    <div class="lw-buy-card__includes">
                        <strong>{{ trans('webinars.this_webinar_includes',['classes' => trans('webinars.'.$course->type)]) }}</strong>
                        <ul>
                            @if($course->isDownloadable())
                                <li><i data-feather="download-cloud" width="16" height="16" aria-hidden="true"></i>{{ trans('webinars.downloadable_content') }}</li>
                            @endif

                            @if($course->certificate or ($course->quizzes->where('certificate', 1)->count() > 0))
                                <li><i data-feather="award" width="16" height="16" aria-hidden="true"></i>{{ trans('webinars.official_certificate') }}</li>
                            @endif

                            @if($course->quizzes->where('status', \App\models\Quiz::ACTIVE)->count() > 0)
                                <li><i data-feather="file-text" width="16" height="16" aria-hidden="true"></i>{{ trans('webinars.online_quizzes_count',['quiz_count' => $course->quizzes->where('status', \App\models\Quiz::ACTIVE)->count()]) }}</li>
                            @endif

                            @if($course->support)
                                <li><i data-feather="message-circle" width="16" height="16" aria-hidden="true"></i>{{ trans('webinars.instructor_support') }}</li>
                            @endif
                        </ul>
                    </div>

                    <div class="lw-quick-actions favorites-share-box">
                        @if($course->isWebinar())
                            <a href="{{ $course->addToCalendarLink() }}" target="_blank" rel="noopener" class="lw-quick-actions__item">
                                <i data-feather="bell" width="18" height="18" aria-hidden="true"></i>
                                <span>{{ trans('public.reminder') }}</span>
                            </a>
                        @endif

                        <a href="/favorites/{{ $course->slug }}/toggle" id="favoriteToggle" class="lw-quick-actions__item">
                            <i data-feather="heart" class="{{ !empty($isFavorite) ? 'favorite-active' : '' }}" width="18" height="18" aria-hidden="true"></i>
                            <span>{{ trans('panel.favorite') }}</span>
                        </a>

                        <a href="#" class="js-share-course lw-quick-actions__item">
                            <i data-feather="share-2" width="18" height="18" aria-hidden="true"></i>
                            <span>{{ trans('public.share') }}</span>
                        </a>
                    </div>

                    <button type="button" id="webinarReportBtn" class="lw-link-btn">
                        <i data-feather="flag" width="14" height="14" aria-hidden="true"></i>
                        {{ trans('webinars.report_this_webinar') }}
                    </button>
                </div>

                {{-- Cashback Alert --}}
                @include('web.default.includes.cashback_alert',['itemPrice' => $course->price])

                {{-- Gift Card --}}
                @if($course->canSale() and !empty(getGiftsGeneralSettings('status')) and !empty(getGiftsGeneralSettings('allow_sending_gift_for_courses')))
                    <a href="/gift/course/{{ $course->slug }}" class="lw-panel lw-gift-card">
                        <span class="lw-avatar-dot"><i data-feather="gift" width="18" height="18" aria-hidden="true"></i></span>
                        <span>
                            <strong>{{ trans('update.gift_this_course') }}</strong>
                            <span>{{ trans('update.gift_this_course_hint') }}</span>
                        </span>
                    </a>
                @endif

                @if($course->teacher->offline)
                    <div class="lw-panel lw-offline-card">
                        <img loading="lazy" src="/assets/default/img/profile/time-icon.png" alt="" width="48" height="48">
                        <div>
                            <h3>{{ trans('public.instructor_is_not_available') }}</h3>
                            <p>{{ $course->teacher->offline_message }}</p>
                        </div>
                    </div>
                @endif

                @component('web.default.includes.lightway.panel', ['title' => trans('webinars.'.$course->type) .' '. trans('webinars.specifications')])
                    <dl class="lw-specs">
                        @if($course->isWebinar())
                            <div><dt>{{ trans('public.start_date') }}</dt><dd>{{ dateTimeFormat($course->start_date, 'j M Y | H:i') }}</dd></div>
                        @endif

                        <div>
                            <dt>{{ trans('public.capacity') }}</dt>
                            <dd>{{ !is_null($course->capacity) ? ($course->capacity . ' ' . trans('quiz.students')) : trans('update.unlimited') }}</dd>
                        </div>

                        <div><dt>{{ trans('public.duration') }}</dt><dd>{{ convertMinutesToHourAndMinute(!empty($course->duration) ? $course->duration : 0) }} {{ trans('home.hours') }}</dd></div>

                        <div><dt>{{ trans('quiz.students') }}</dt><dd>{{ $course->getSalesCount() }}</dd></div>

                        @if($course->isWebinar())
                            <div><dt>{{ trans('public.sessions') }}</dt><dd>{{ $course->sessions->count() }}</dd></div>
                        @endif

                        @if($course->isTextCourse())
                            <div><dt>{{ trans('webinars.text_lessons') }}</dt><dd>{{ $course->textLessons->count() }}</dd></div>
                        @endif

                        @if($course->isCourse() or $course->isTextCourse())
                            <div><dt>{{ trans('public.files') }}</dt><dd>{{ $course->files->count() }}</dd></div>
                            <div><dt>{{ trans('public.created_at') }}</dt><dd>{{ dateTimeFormat($course->created_at,'j M Y') }}</dd></div>
                        @endif

                        @if(!empty($course->access_days))
                            <div><dt>{{ trans('update.access_period') }}</dt><dd>{{ $course->access_days }} {{ trans('public.days') }}</dd></div>
                        @endif
                    </dl>
                @endcomponent

                {{-- organization --}}
                @if($course->creator_id != $course->teacher_id)
                    @include('web.default.course.sidebar_instructor_profile', ['courseTeacher' => $course->creator])
                @endif
                {{-- teacher --}}
                @include('web.default.course.sidebar_instructor_profile', ['courseTeacher' => $course->teacher])

                @if($course->webinarPartnerTeacher->count() > 0)
                    @foreach($course->webinarPartnerTeacher as $webinarPartnerTeacher)
                        @include('web.default.course.sidebar_instructor_profile', ['courseTeacher' => $webinarPartnerTeacher->teacher])
                    @endforeach
                @endif

                {{-- tags --}}
                @if($course->tags->count() > 0)
                    @component('web.default.includes.lightway.panel', ['title' => trans('public.tags')])
                        <div class="lw-tags">
                            @foreach($course->tags as $tag)
                                <a href="/tags/courses/{{ urlencode($tag->title) }}" class="lw-tag" dir="auto">{{ $tag->title }}</a>
                            @endforeach
                        </div>
                    @endcomponent
                @endif

                {{-- ads --}}
                @if(!empty($advertisingBannersSidebar) and count($advertisingBannersSidebar))
                    <div class="row">
                        @foreach($advertisingBannersSidebar as $sidebarBanner)
                            <div class="rounded-lg sidebar-ads mt-15 col-{{ $sidebarBanner->size }}">
                                <a href="{{ $sidebarBanner->link }}">
                                    <img loading="lazy" src="{{ $sidebarBanner->image }}" class="img-cover rounded-lg" alt="{{ $sidebarBanner->title }}">
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </aside>
        </div>

        {{-- Ads Banner --}}
        @if(!empty($advertisingBanners) and count($advertisingBanners))
            <div class="mt-30 mt-md-50">
                <div class="row">
                    @foreach($advertisingBanners as $banner)
                        <div class="col-{{ $banner->size }}">
                            <a href="{{ $banner->link }}">
                                <img loading="lazy" src="{{ $banner->image }}" class="img-cover rounded-sm" alt="{{ $banner->title }}">
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    <div id="webinarReportModal" class="d-none">
        <h3 class="section-title after-line font-20 text-dark-blue">{{ trans('product.report_the_course') }}</h3>

        <form action="/course/{{ $course->id }}/report" method="post" class="mt-25">

            <div class="form-group">
                <label class="text-dark-blue font-14" for="reason">{{ trans('product.reason') }}</label>
                <select id="reason" name="reason" class="form-control">
                    <option value="" selected disabled>{{ trans('product.select_reason') }}</option>

                    @foreach(getReportReasons() as $reason)
                        <option value="{{ $reason }}">{{ $reason }}</option>
                    @endforeach
                </select>
                <div class="invalid-feedback"></div>
            </div>

            <div class="form-group">
                <label class="text-dark-blue font-14" for="message_to_reviewer">{{ trans('public.message_to_reviewer') }}</label>
                <textarea name="message" id="message_to_reviewer" class="form-control" rows="10"></textarea>
                <div class="invalid-feedback"></div>
            </div>
            <p class="text-gray font-16">{{ trans('product.report_modal_hint') }}</p>

            <div class="mt-30 d-flex align-items-center justify-content-end">
                <button type="button" class="js-course-report-submit btn btn-sm btn-primary">{{ trans('panel.report') }}</button>
                <button type="button" class="btn btn-sm btn-danger ml-10 close-swl">{{ trans('public.close') }}</button>
            </div>
        </form>
    </div>

    @include('web.default.course.share_modal')
    @include('web.default.course.buy_with_point_modal')
@endsection

@push('scripts_bottom')
    <script src="/assets/default/js/parts/time-counter-down.min.js"></script>
    <script src="/assets/default/vendors/barrating/jquery.barrating.min.js"></script>
    <script src="/assets/default/vendors/video/video.min.js"></script>
    <script src="/assets/default/vendors/video/youtube.min.js"></script>
    <script src="/assets/default/vendors/video/vimeo.js"></script>

    <script>
        var webinarDemoLang = '{{ trans('webinars.webinar_demo') }}';
        var replyLang = '{{ trans('panel.reply') }}';
        var closeLang = '{{ trans('public.close') }}';
        var saveLang = '{{ trans('public.save') }}';
        var reportLang = '{{ trans('panel.report') }}';
        var reportSuccessLang = '{{ trans('panel.report_success') }}';
        var reportFailLang = '{{ trans('panel.report_fail') }}';
        var messageToReviewerLang = '{{ trans('public.message_to_reviewer') }}';
        var copyLang = '{{ trans('public.copy') }}';
        var copiedLang = '{{ trans('public.copied') }}';
        var learningToggleLangSuccess = '{{ trans('public.course_learning_change_status_success') }}';
        var learningToggleLangError = '{{ trans('public.course_learning_change_status_error') }}';
        var notLoginToastTitleLang = '{{ trans('public.not_login_toast_lang') }}';
        var notLoginToastMsgLang = '{{ trans('public.not_login_toast_msg_lang') }}';
        var notAccessToastTitleLang = '{{ trans('public.not_access_toast_lang') }}';
        var notAccessToastMsgLang = '{{ trans('public.not_access_toast_msg_lang') }}';
        var canNotTryAgainQuizToastTitleLang = '{{ trans('public.can_not_try_again_quiz_toast_lang') }}';
        var canNotTryAgainQuizToastMsgLang = '{{ trans('public.can_not_try_again_quiz_toast_msg_lang') }}';
        var canNotDownloadCertificateToastTitleLang = '{{ trans('public.can_not_download_certificate_toast_lang') }}';
        var canNotDownloadCertificateToastMsgLang = '{{ trans('public.can_not_download_certificate_toast_msg_lang') }}';
        var sessionFinishedToastTitleLang = '{{ trans('public.session_finished_toast_title_lang') }}';
        var sessionFinishedToastMsgLang = '{{ trans('public.session_finished_toast_msg_lang') }}';
        var sequenceContentErrorModalTitle = '{{ trans('update.sequence_content_error_modal_title') }}';
        var courseHasBoughtStatusToastTitleLang = '{{ trans('cart.fail_purchase') }}';
        var courseHasBoughtStatusToastMsgLang = '{{ trans('site.you_bought_webinar') }}';
        var courseNotCapacityStatusToastTitleLang = '{{ trans('public.request_failed') }}';
        var courseNotCapacityStatusToastMsgLang = '{{ trans('cart.course_not_capacity') }}';
        var courseHasStartedStatusToastTitleLang = '{{ trans('cart.fail_purchase') }}';
        var courseHasStartedStatusToastMsgLang = '{{ trans('update.class_has_started') }}';
        var joinCourseWaitlistLang = '{{ trans('update.join_course_waitlist') }}';
        var joinCourseWaitlistModalHintLang = "{{ trans('update.join_course_waitlist_modal_hint') }}";
        var joinLang = '{{ trans('footer.join') }}';
        var nameLang = '{{ trans('auth.name') }}';
        var emailLang = '{{ trans('auth.email') }}';
        var phoneLang = '{{ trans('public.phone') }}';
        var captchaLang = '{{ trans('site.captcha') }}';
    </script>

    <script src="/assets/default/js/parts/comment.min.js"></script>
    <script src="/assets/default/js/parts/video_player_helpers.min.js"></script>
    <script src="/assets/default/js/parts/webinar_show.min.js"></script>

    @if(!empty($course->creator) and !empty($course->creator->getLiveChatJsCode()) and !empty(getFeaturesSettings('show_live_chat_widget')))
        <script>
            (function () {
                "use strict"

                {!! $course->creator->getLiveChatJsCode() !!}
            })(jQuery)
        </script>
    @endif
@endpush
