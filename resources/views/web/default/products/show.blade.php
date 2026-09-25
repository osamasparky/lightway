@extends('web.default.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/default/css/css-stars.css">
@endpush

@section('content')
    @php
        $productCrumbs = [['title' => trans('update.products'), 'url' => '/products']];
        if (!empty($product->category)) {
            $productCrumbs[] = ['title' => $product->category->title, 'url' => $product->category->getUrl()];
        }
        $productCrumbs[] = ['title' => clean($product->title, 't')];
        $productAvailability = $product->getAvailability();
    @endphp

    <section class="lw-banner lw-banner--slim ms-lattice">
        <div class="ms-container lw-banner__inner">
            <nav class="lw-breadcrumb" aria-label="{{ trans('home.lw_breadcrumb') }}">
                <ol>
                    <li><a href="/">{{ trans('home.ms_home_link') }}</a></li>
                    @foreach($productCrumbs as $crumb)
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
        </div>
        <div class="ms-band lw-banner__band" aria-hidden="true"></div>
    </section>

    <div class="ms-container lw-page lw-product-page">
        {{-- Cashback Alert --}}
        @if(!empty($cashbackRules) and count($cashbackRules))
            @include('web.default.includes.cashback_alert',['itemPrice' => $product->price])
        @endif

        @if(!empty($activeSpecialOffer))
            <div class="lw-special-offer product-show-special-offer">
                @include('web.default.course.special_offer')
            </div>
        @endif

        <div class="lw-product-top">
            {{-- Gallery: product_show.min.js swaps .main-s-image when a .thumbnail-card is clicked --}}
            <div class="lw-gallery">
                <div class="lazyImage product-show-image-card lw-gallery__main">
                    <span class="lw-arch lw-arch--gallery">
                        <span class="lw-arch__clip">
                            <img src="{{ $product->thumbnail }}" alt="{{ $product->title }}" class="main-s-image lw-arch__img">
                        </span>
                    </span>

                    @if(!empty($product->video_demo))
                        <button type="button" id="productDemoVideoBtn"
                                data-video-path="{{ url($product->video_demo) }}"
                                class="lw-media__play" aria-label="{{ trans('update.product_demo') }}">
                            <i data-feather="play" width="28" height="28" aria-hidden="true"></i>
                        </button>
                    @endif
                </div>

                <div class="product-show-thumbnail-card lw-gallery__thumbs">
                    <button type="button" class="thumbnail-card is-first-thumbnail-card lw-gallery__thumb" aria-label="{{ $product->title }}">
                        <img loading="lazy" src="{{ $product->thumbnail }}" alt="">

                        @if(!empty($product->video_demo))
                            <span class="lw-gallery__play" aria-hidden="true"><i data-feather="play" width="14" height="14"></i></span>
                        @endif
                    </button>

                    @if(!empty($product->images) and count($product->images))
                        @foreach($product->images as $image)
                            <button type="button" class="thumbnail-card lw-gallery__thumb" aria-label="{{ $product->title }} {{ $loop->iteration + 1 }}">
                                <img loading="lazy" src="{{ $image->path }}" alt="">
                            </button>
                        @endforeach
                    @endif
                </div>
            </div>

            {{-- Info --}}
            <div class="lw-product-info">
                <form action="/cart/store" method="post" id="productAddToCartForm" class="product-show-info-card">
                    {{ csrf_field() }}
                    <input type="hidden" name="item_id" value="{{ $product->id }}">
                    <input type="hidden" name="item_name" value="product_id">

                    <span class="lw-badge lw-badge--type">
                        <i data-feather="{{ $product->isPhysical() ? 'package' : 'file' }}" width="14" height="14" aria-hidden="true"></i>
                        {{ $product->isPhysical() ? trans('update.physical_product') : trans('update.virtual_product') }}
                    </span>

                    <h1 class="lw-product-info__title" dir="auto">{{ clean($product->title, 't') }}</h1>

                    <div class="lw-item-meta">
                        @if(!empty($product->category))
                            <span>{{ trans('public.in') }} <a href="{{ $product->category->getUrl() }}" class="lw-item-meta__link" dir="auto">{{ $product->category->title }}</a></span>
                        @endif
                        <span class="lw-item-meta__rate">
                            @include('web.default.includes.lightway.stars', ['rate' => $product->getRate(), 'emptyText' => trans('home.lw_no_reviews')])
                            <span>({{ $product->reviews->pluck('creator_id')->count() }} {{ trans('product.reviews') }})</span>
                        </span>
                    </div>

                    @if(!empty($selectableSpecifications) and count($selectableSpecifications))
                            @foreach($selectableSpecifications as $selectableSpecification)
                                <div class="product-show-selectable-specification mt-10">
                                    <span class="font-14 font-weight-bold text-dark">{{ $selectableSpecification->specification->title }}</span>

                                    <div class="d-flex align-items-center flex-wrap">
                                        @foreach($selectableSpecification->selectedMultiValues as $specificationValue)
                                            @if(!empty($specificationValue->multiValue))
                                                <div class="selectable-specification-item mr-5 mt-5">
                                                    <input type="radio" name="specifications[{{ $selectableSpecification->specification->createName() }}]" value="{{ $specificationValue->multiValue->createName() }}" id="{{ $specificationValue->multiValue->createName() }}" class="" {{ ($loop->iteration == 1) ? 'checked' : '' }}>
                                                    <label class="font-12 cursor-pointer px-10 py-5" for="{{ $specificationValue->multiValue->createName() }}">{{ $specificationValue->multiValue->title }}</label>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @endif

                    <div class="lw-price-box product-show-price-box">
                        <div>
                            <span class="lw-price-box__label">{{ trans('public.price') }}</span>
                            @if(!empty($product->price) and $product->price > 0)
                                @if($product->getPriceWithActiveDiscountPrice() < $product->price)
                                    <strong class="lw-buy-card__amount real">{{ ($product->getPriceWithActiveDiscountPrice() > 0) ? handlePrice($product->getPriceWithActiveDiscountPrice(), true, true, false, null, true, 'store') : trans('public.free') }}</strong>
                                    <del class="lw-price__old off">{{ handlePrice($product->price, true, true, false, null, true, 'store') }}</del>
                                @else
                                    <strong class="lw-buy-card__amount real">{{ handlePrice($product->price, true, true, false, null, true, 'store') }}</strong>
                                @endif
                            @else
                                <strong class="lw-buy-card__amount real">{{ trans('public.free') }}</strong>
                            @endif

                            @if($product->isPhysical())
                                @if(!empty($product->delivery_fee) and $product->delivery_fee > 0)
                                    <span class="lw-price-box__note">+ {{ handlePrice($product->delivery_fee) }} {{ trans('update.shipping') }}</span>
                                @else
                                    <span class="lw-price-box__note is-free">{{ trans('update.free_shipping') }}</span>
                                @endif
                            @endif
                        </div>

                        <div class="lw-price-box__stock">
                            <span class="lw-price-box__label">{{ trans('update.availability') }}</span>
                            @if(($productAvailability > 0))
                                @if(!empty($product->inventory) and !empty($product->inventory_warning) and $product->inventory_warning > $productAvailability)
                                    <strong class="is-low">{{ trans('update.only_n_left',['count' => $productAvailability]) }}</strong>
                                @else
                                    <strong class="is-ok"><i data-feather="check" width="14" height="14" aria-hidden="true"></i> {{ trans('update.in_stock') }}</strong>
                                @endif
                            @else
                                <strong class="is-out">{{ trans('update.out_of_stock') }}</strong>
                            @endif
                        </div>
                    </div>

                    @if($product->ordering)
                            <div class="product-show-cart-actions lw-product-actions">
                                <div class="cart-quantity lw-qty">
                                    <input type="hidden" id="productAvailabilityCount" value="{{ $product->getAvailability() }}">
                                    <button type="button" class="minus d-flex align-items-center justify-content-center" {{ ($product->getAvailability() < 1) ? 'disabled' : '' }}>
                                        <i data-feather="minus" class="" width="20" height="20"></i>
                                    </button>

                                    <input type="number" name="quantity" value="1" aria-label="{{ trans('update.quantity') }}" {{ ($product->getAvailability() < 1) ? 'disabled' : '' }}>

                                    <button type="button" class="plus d-flex align-items-center justify-content-center" {{ ($product->getAvailability() < 1) ? 'disabled' : '' }}>
                                        <i data-feather="plus" class="" width="20" height="20"></i>
                                    </button>
                                </div>

                                @php
                                    $productAvailability = $product->getAvailability();
                                @endphp

                                <div class="lw-product-actions__buttons">
                                    <button type="submit" class="lw-btn {{ ($productAvailability > 0) ? 'lw-btn--cta' : 'lw-btn--disabled' }}" {{ ($productAvailability < 1) ? 'disabled' : '' }}>
                                        <i data-feather="shopping-cart" width="18" height="18" aria-hidden="true"></i>
                                        {{ ($productAvailability > 0) ? trans('public.add_to_cart') : trans('update.out_of_stock') }}
                                    </button>

                                    @if($productAvailability > 0 and !empty($product->point) and $product->point > 0)
                                        <input type="hidden" class="js-product-points" value="{{ $product->point }}">

                                        <a href="{{ !(auth()->check()) ? '/login' : '#!' }}" class="{{ (auth()->check()) ? 'js-buy-with-point' : '' }} js-buy-with-point-show-btn lw-btn lw-btn--outline" rel="nofollow">
                                            {!! trans('update.buy_with_n_points',['points' => $product->point]) !!}
                                        </a>
                                    @endif

                                    @if($productAvailability > 0 and !empty(getFeaturesSettings('direct_products_payment_button_status')))
                                        <button type="button" class="lw-btn lw-btn--dark js-product-direct-payment">
                                            {{ trans('update.buy_now') }}
                                        </button>
                                    @endif

                                    @if($productAvailability > 0 and $hasInstallments)
                                        <a href="/products/{{ $product->slug }}/installments" class="js-installments-btn lw-btn lw-btn--outline">
                                            {{ trans('update.installments') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif

                    <div class="lw-product-notes">
                            @if($product->isPhysical() and !empty($product->delivery_estimated_time))
                                <div class="product-show-info-footer-items lw-product-note">
                                    <div class="icon-box">
                                        <i data-feather="package" class="" width="20" height="20"></i>
                                    </div>
                                    <div class="ml-5">
                                        <span class="d-block font-14 font-weight-bold text-dark">{{ trans('update.physical_product') }}</span>
                                        <span class="d-block font-12 text-gray">{{ trans('update.delivery_estimated_time_days_alert',['days' => $product->delivery_estimated_time]) }}</span>
                                    </div>
                                </div>
                            @elseif($product->isVirtual())
                                <div class="product-show-info-footer-items lw-product-note">
                                    <div class="icon-box">
                                        <i data-feather="package" class="" width="20" height="20"></i>
                                    </div>
                                    <div class="ml-5">
                                        <span class="d-block font-14 font-weight-bold text-dark">{{ trans('update.virtual_product') }}</span>
                                        <span class="d-block font-12 text-gray">{{ trans('update.download_all_files_after_payment') }}</span>
                                    </div>
                                </div>
                            @endif

                            <div class="js-share-product product-show-info-footer-items lw-product-note lw-product-note--action">
                                <div class="icon-box">
                                    <i data-feather="share-2" class="" width="20" height="20"></i>
                                </div>
                                <div class="ml-5">
                                    <span class="d-block font-14 font-weight-bold text-dark">{{ trans('public.share') }}</span>
                                    <span class="d-block font-12 text-gray">{{ trans('update.product_share_text') }}</span>
                                </div>
                            </div>
                        </div>

                    {{-- Gift Card --}}
                        @if($product->isVirtual() and $productAvailability > 0 and !empty(getGiftsGeneralSettings('status')) and !empty(getGiftsGeneralSettings('allow_sending_gift_for_products')))
                            <a href="/gift/product/{{ $product->slug }}" class="lw-panel lw-gift-card">
                                <div class="size-40 d-flex-center rounded-circle bg-gray200">
                                    <i data-feather="gift" class="text-gray" width="20" height="20"></i>
                                </div>
                                <div class="ml-5">
                                    <h4 class="font-14 font-weight-bold text-gray">{{ trans('update.gift_this_product') }}</h4>
                                    <p class="font-12 text-gray">{{ trans('update.gift_this_product_hint') }}</p>
                                </div>
                            </a>
                        @endif
                </form>
            </div>
        </div>

        @if(
               !empty(getFeaturesSettings("frontend_coupons_display_type")) and
               getFeaturesSettings("frontend_coupons_display_type") == "before_content" and
               !empty($instructorDiscounts) and
               count($instructorDiscounts)
           )
            @foreach($instructorDiscounts as $instructorDiscount)
                @include('web.default.includes.discounts.instructor_discounts_card', ['discount' => $instructorDiscount, 'instructorDiscountClassName' => "mt-30"])
            @endforeach
        @endif

        <div class="lw-product-tabs-wrap">
            <ul class="nav lw-tabs lw-tabs--wrap" id="tabs-tab" role="tablist">
                <li class="nav-item">
                    <a class="lw-tabs__link {{ (empty(request()->get('tab')) or request()->get('tab') == 'description') ? 'active' : '' }}" id="description-tab"
                       data-toggle="tab" href="#description" role="tab" aria-controls="description"
                       aria-selected="true">{{ trans('public.description') }}</a>
                </li>
                <li class="nav-item">
                    <a class="lw-tabs__link {{ (request()->get('tab') == 'seller') ? 'active' : '' }}" id="seller-tab" data-toggle="tab"
                       href="#seller" role="tab" aria-controls="seller"
                       aria-selected="false">{{ trans('update.seller') }}</a>
                </li>
                <li class="nav-item">
                    <a class="lw-tabs__link {{ (request()->get('tab') == 'specifications') ? 'active' : '' }}" id="specifications-tab" data-toggle="tab"
                       href="#specifications" role="tab" aria-controls="specifications"
                       aria-selected="false">{{ trans('update.specifications') }}</a>
                </li>

                @if(!empty($product->files) and count($product->files) and $product->checkUserHasBought())
                    <li class="nav-item">
                        <a class="lw-tabs__link {{ (request()->get('tab') == 'files') ? 'active' : '' }}" id="files-tab" data-toggle="tab"
                           href="#files" role="tab" aria-controls="files"
                           aria-selected="false">{{ trans('public.files') }}</a>
                    </li>
                @endif

                <li class="nav-item">
                    <a class="lw-tabs__link {{ (request()->get('tab') == 'reviews') ? 'active' : '' }}" id="reviews-tab" data-toggle="tab"
                       href="#reviews" role="tab" aria-controls="reviews"
                       aria-selected="false">{{ trans('product.reviews') }}</a>
                </li>
            </ul>

            <div class="tab-content lw-tab-content lw-product-tabs" id="nav-tabContent">
                <div class="tab-pane fade {{ (empty(request()->get('tab')) or request()->get('tab') == 'description') ? 'show active' : '' }} " id="description" role="tabpanel"
                     aria-labelledby="description-tab">
                    @include('web.default.products.includes.tabs.description')
                </div>

                <div class="tab-pane fade {{ (request()->get('tab') == 'seller') ? 'show active' : '' }} " id="seller" role="tabpanel"
                     aria-labelledby="seller-tab">
                    @include('web.default.products.includes.tabs.seller')
                </div>

                <div class="tab-pane fade {{ (request()->get('tab') == 'specifications') ? 'show active' : '' }} " id="specifications" role="tabpanel"
                     aria-labelledby="specifications-tab">
                    @include('web.default.products.includes.tabs.specifications')
                </div>

                <div class="tab-pane fade {{ (request()->get('tab') == 'files') ? 'show active' : '' }} " id="files" role="tabpanel"
                     aria-labelledby="files-tab">
                    @include('web.default.products.includes.tabs.files')
                </div>

                <div class="tab-pane fade {{ (request()->get('tab') == 'reviews') ? 'show active' : '' }} " id="reviews" role="tabpanel"
                     aria-labelledby="reviews-tab">
                    @include('web.default.products.includes.tabs.reviews')
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
                @include('web.default.includes.discounts.instructor_discounts_card', ['discount' => $instructorDiscount, 'instructorDiscountClassName' => "mt-30"])
            @endforeach
        @endif

        {{-- Ads Bannaer --}}
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
        {{-- ./ Ads Bannaer --}}

    </div>

    @include('web.default.products.includes.share_modal')
    @include('web.default.user.send_message_modal',['user' => $seller])
    @include('web.default.products.includes.buy_with_point_modal')
@endsection

@push('scripts_bottom')
    <script>
        var replyLang = '{{ trans('panel.reply') }}';
        var closeLang = '{{ trans('public.close') }}';
        var saveLang = '{{ trans('public.save') }}';
        var reportLang = '{{ trans('panel.report') }}';
        var reportSuccessLang = '{{ trans('panel.report_success') }}';
        var reportFailLang = '{{ trans('panel.report_fail') }}';
        var messageToReviewerLang = '{{ trans('public.message_to_reviewer') }}';
        var unFollowLang = '{{ trans('panel.unfollow') }}';
        var followLang = '{{ trans('panel.follow') }}';
        var messageSuccessSentLang = '{{ trans('site.message_success_sent') }}';
        var productDemoLang = '{{ trans('update.product_demo') }}';
        var onlineViewerModalTitleLang = '{{ trans('update.online_viewer') }}';
        var copyLang = '{{ trans('public.copy') }}';
        var copiedLang = '{{ trans('public.copied') }}';
    </script>

    <script src="/assets/default/js/parts/time-counter-down.min.js"></script>
    <script src="/assets/default/vendors/barrating/jquery.barrating.min.js"></script>
    <script src="/assets/default/js/parts/comment.min.js"></script>
    <script src="/assets/default/js/parts/profile.min.js"></script>
    <script src="/assets/default/js/parts/video_player_helpers.min.js"></script>
    <script src="/assets/default/js/parts/product_show.min.js"></script>
@endpush
