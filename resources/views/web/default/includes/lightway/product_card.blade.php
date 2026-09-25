{{--
    Product card. Params: $product, $isRewardProducts
    Keeps: availability / discount / free-shipping badges, custom badges, creator link, category filter link,
    rating, reward points, discounted price and the add-to-cart button (.btn-add-product-to-cart, handled in main.js).
--}}
@php
    $hasDiscount = $product->getActiveDiscount();
    $isAvailable = ($product->getAvailability() > 0);
@endphp

<article class="lw-card lw-product">
    <div class="lw-product__media">
        @include('web.default.includes.lightway.arch', ['src' => $product->thumbnail, 'alt' => $product->title, 'class' => 'lw-arch--product'])

        <div class="lw-course__badges">
            @if(!$isAvailable)
                <span class="lw-badge lw-badge--muted">{{ trans('update.out_of_stock') }}</span>
            @elseif($hasDiscount)
                <span class="lw-badge lw-badge--offer">{{ trans('public.offer', ['off' => $hasDiscount->percent]) }}</span>
            @elseif($product->isPhysical() and empty($product->delivery_fee))
                <span class="lw-badge lw-badge--done">
                    <i data-feather="truck" width="14" height="14" aria-hidden="true"></i>
                    {{ trans('update.free_shipping') }}
                </span>
            @endif

            @include('web.default.includes.product_custom_badge', ['itemTarget' => $product])
        </div>
    </div>

    <div class="lw-product__body">
        <span class="lw-card__meta">
            @if(!empty($product->creator))
                <a href="{{ $product->creator->getProfileUrl() }}" target="_blank" class="lw-card__muted-link lw-above-link" dir="auto">{{ $product->creator->full_name }}</a>
            @endif
            @if(!empty($product->category))
                <span aria-hidden="true">·</span>
                {{ trans('public.in') }} <a href="/products?category_id={{ $product->category->id }}" class="lw-card__cat lw-above-link" dir="auto">{{ $product->category->title }}</a>
            @endif
        </span>

        <h3 class="lw-card__title" dir="auto">
            <a href="{{ $product->getUrl() }}" class="lw-stretched-link">{{ clean($product->title, 'title') }}</a>
        </h3>

        @include('web.default.includes.lightway.stars', ['rate' => $product->getRate(), 'emptyText' => trans('home.lw_no_reviews')])

        <div class="lw-product__foot">
            <div class="lw-price">
                @if(!empty($isRewardProducts) and !empty($product->point))
                    <strong class="lw-price__real">{{ $product->point }} {{ trans('update.points') }}</strong>
                @elseif($product->price > 0)
                    @if($product->getPriceWithActiveDiscountPrice() < $product->price)
                        <strong class="lw-price__real">{{ ($product->getPriceWithActiveDiscountPrice() > 0) ? handlePrice($product->getPriceWithActiveDiscountPrice(), true, true, false, null, true, 'store') : trans('public.free') }}</strong>
                        <del class="lw-price__old">{{ handlePrice($product->price, true, true, false, null, true, 'store') }}</del>
                    @else
                        <strong class="lw-price__real">{{ handlePrice($product->price, true, true, false, null, true, 'store') }}</strong>
                    @endif
                @else
                    <strong class="lw-price__real">{{ trans('public.free') }}</strong>
                @endif
            </div>

            @if($isAvailable)
                <button type="button" data-id="{{ $product->id }}" class="btn-add-product-to-cart lw-cart-btn lw-above-link" aria-label="{{ trans('public.add_to_cart') }}">
                    <i data-feather="shopping-cart" width="20" height="20" aria-hidden="true"></i>
                </button>
            @endif
        </div>
    </div>
</article>
