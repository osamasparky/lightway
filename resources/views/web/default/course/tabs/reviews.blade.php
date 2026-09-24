@php
    $courseReviewsCount = $course->reviews->pluck('creator_id')->count();
    $hasReviews = $course->reviews->count() > 0;
@endphp

<div class="lw-stack">
@component('web.default.includes.lightway.panel', ['title' => trans('product.reviews') . ' (' . $courseReviewsCount . ')', 'tag' => 'h2'])
    @include('web.default.includes.lightway.review_summary', [
        'rate' => $course->getRate(),
        'count' => $courseReviewsCount,
        'rows' => [
            ['label' => trans('product.content_quality'), 'value' => $hasReviews ? round($course->reviews->avg('content_quality'), 1) : 0],
            ['label' => trans('product.instructor_skills'), 'value' => $hasReviews ? round($course->reviews->avg('instructor_skills'), 1) : 0],
            ['label' => trans('product.purchase_worth'), 'value' => $hasReviews ? round($course->reviews->avg('purchase_worth'), 1) : 0],
            ['label' => trans('product.support_quality'), 'value' => $hasReviews ? round($course->reviews->avg('support_quality'), 1) : 0],
        ],
    ])
@endcomponent

<section class="lw-panel lw-reviews">
    <h2 class="lw-panel__title">
        @include('web.default.includes.manuscript.star', ['size' => 16, 'dot' => '#FFFDF8'])
        <span>{{ trans('product.post_review') }}</span>
    </h2>

    <form action="/reviews/store" class="lw-review-form" method="post">
        {{ csrf_field() }}
        <input type="hidden" name="webinar_id" value="{{ $course->id }}"/>

        <div class="form-group">
            <label for="lwReviewDescription" class="sr-only">{{ trans('product.post_review') }}</label>
            <textarea name="description" id="lwReviewDescription" class="form-control lw-textarea" rows="6"></textarea>
        </div>

        <div class="reviews-stars row align-items-center">

            <div class="col-6 col-md-3 d-flex flex-column align-items-center justify-content-center barrating-stars">
                <span class="font-14 text-gray">{{ trans('product.content_quality') }}</span>
                <select name="content_quality" data-rate="1">
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                </select>
            </div>

            <div class="col-6 col-md-3 d-flex flex-column align-items-center justify-content-center barrating-stars">
                <span class="font-14 text-gray">{{ trans('product.instructor_skills') }}</span>
                <select name="instructor_skills" data-rate="1">
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                </select>
            </div>

            <div class="col-6 col-md-3 d-flex flex-column align-items-center justify-content-center barrating-stars">
                <span class="font-14 text-gray">{{ trans('product.purchase_worth') }}</span>
                <select name="purchase_worth" data-rate="1">
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                </select>
            </div>

            <div class="col-6 col-md-3 d-flex flex-column align-items-center justify-content-center barrating-stars">
                <span class="font-14 text-gray">{{ trans('product.support_quality') }}</span>
                <select name="support_quality" data-rate="1">
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                </select>
            </div>
        </div>

        <button type="submit" class="lw-btn lw-btn--dark mt-20">{{ trans('product.post_review') }}</button>
    </form>

    <div class="lw-review-list">
        @if($course->reviews->count() > 0)
            @foreach($course->reviews as $review)

                <div class="comments-card shadow-lg rounded-sm border px-20 py-15 mt-30" data-address="/reviews/store-reply-comment" data-csrf="{{ csrf_token() }}" data-id="{{ $review->id }}">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="user-inline-avatar d-flex align-items-center mt-10">
                            <div class="avatar bg-gray200">
                                <img loading="lazy" src="{{ $review->creator->getAvatar() }}" class="img-cover" alt="">
                            </div>
                            <div class="d-flex flex-column ml-5">
                                <span class="font-weight-500 text-secondary">{{ $review->creator->full_name }}</span>

                                @include(getTemplate() . '.includes.webinar.rate',[
                                        'rate' => $review->rates,
                                        'dontShowRate' => true,
                                        'className' => 'justify-content-start mt-0'
                                    ])
                            </div>
                        </div>

                        <div class="d-flex align-items-center">
                            <span class="font-12 text-gray mr-10">{{ dateTimeFormat($review->created_at, 'j M Y | H:i') }}</span>

                            <div class="btn-group dropdown table-actions">
                                <button type="button" class="btn-transparent dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i data-feather="more-vertical" height="20"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a href="/reviews/store-reply-comment" class="webinar-actions d-block text-hover-primary reply-comment">{{ trans('panel.reply') }}</a>

                                    @if(!empty($user) and $user->id == $review->creator_id)
                                        <a href="/reviews/{{ $review->id }}/delete" class="webinar-actions d-block mt-10 text-hover-primary">{{ trans('public.delete') }}</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-20 text-gray font-14">
                        {!! clean($review->description,'description') !!}
                    </div>

                    @if($review->comments->count() > 0)
                        @foreach($review->comments as $comment)
                            <div class="shadow-lg rounded-sm border px-20 py-15 mt-30">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="user-inline-avatar d-flex align-items-center mt-10">
                                        <div class="avatar bg-gray200">
                                            <img loading="lazy" src="{{ $comment->user->getAvatar() }}" class="img-cover" alt="{{ $comment->user->full_name }}">
                                        </div>
                                        <div class="d-flex flex-column ml-5">
                                            <span class="font-weight-500 text-secondary">{{ $comment->user->full_name }}</span>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center">
                                        <span class="font-12 text-gray mr-10">{{ dateTimeFormat($comment->created_at, 'j M Y | H:i') }}</span>

                                        <div class="btn-group dropdown table-actions">
                                            <button type="button" class="btn-transparent dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i data-feather="more-vertical" height="20"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a href="" class="webinar-actions d-block text-hover-primary reply-comment">{{ trans('panel.reply') }}</a>
                                                <a href="/comments/{{ $comment->id }}/delete" class="webinar-actions d-block mt-10 text-hover-primary">{{ trans('public.delete') }}</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-20 text-gray">
                                    {!! clean($comment->comment,'comment') !!}
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</section>
</div>
