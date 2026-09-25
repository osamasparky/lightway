@extends(getTemplate().'.layouts.app')

@section('content')
    @php
        $authUser = auth()->user();
        $isMultiCurrency = !empty(getFinancialCurrencySettings('multi_currency'));
        $userCurrency = currency();
        $userCharge = $userCharge ?? 0;
        $canPayWithCharge = (!empty($userCharge) and $total <= $userCharge);

        $validChannels = [];
        $invalidChannels = [];
        foreach (($paymentChannels ?? []) as $paymentChannel) {
            if (!$isMultiCurrency or (!empty($paymentChannel->currencies) and in_array($userCurrency, $paymentChannel->currencies))) {
                $validChannels[] = $paymentChannel;
            } else {
                $invalidChannels[] = $paymentChannel;
            }
        }

        // One click less: preselect the balance when it covers the order, otherwise the first gateway.
        $defaultGateway = $canPayWithCharge ? 'credit' : (count($validChannels) ? $validChannels[0]->id : null);

        $subTotalValue = $subTotal ?? $order->amount;
        $discountValue = $totalDiscount ?? $order->total_discount;
        $taxValue = $taxPrice ?? $order->tax;
    @endphp

    @include('web.default.includes.lightway.banner', [
        'title' => trans('home.lw_co_title'),
        'subtitle' => handlePrice($total) . ' ' . trans('cart.for_items', ['count' => $count]),
        'breadcrumbs' => array_values(array_filter([
            // Direct "Buy now" uses unsaved carts, so the cart crumb only shows for a real cart.
            (!empty($carts) and !empty($carts->first()) and $carts->first()->exists) ? ['title' => trans('cart.shopping_cart'), 'url' => '/cart'] : null,
            ['title' => trans('home.lw_co_title')],
        ])),
    ])

    <div class="ms-container lw-page">
        <form action="/payments/payment-request" method="post" id="paymentForm" class="lw-checkout" data-check-url="/payments/checkout-check" novalidate>
            {{ csrf_field() }}
            <input type="hidden" name="order_id" value="{{ $order->id }}">

            <div class="lw-checkout__main">
                {{-- Step 1: who is each item for --}}
                <section class="lw-panel lw-co-step" aria-labelledby="lwCoRecipientTitle">
                    <h2 class="lw-co-step__title" id="lwCoRecipientTitle">
                        <span class="lw-co-step__num" aria-hidden="true">1</span>
                        {{ trans('home.lw_co_step_recipient') }}
                    </h2>
                    <p class="lw-co-step__hint">{{ trans('home.lw_co_step_recipient_hint') }}</p>

                    <div class="lw-co-items">
                        @foreach($order->orderItems as $item)
                            @php
                                $giftable = (!empty($item->webinar_id) or !empty($item->bundle_id) or !empty($item->subscribe_id));
                                $owned = false;
                                $image = null;
                                $itemUrl = null;

                                if (!empty($item->webinar_id) and !empty($item->webinar)) {
                                    $owned = $item->webinar->checkUserHasBought($authUser);
                                    $image = $item->webinar->getImage();
                                    $itemUrl = $item->webinar->getUrl();
                                } elseif (!empty($item->bundle_id) and !empty($item->bundle)) {
                                    $owned = $item->bundle->checkUserHasBought($authUser);
                                    $image = $item->bundle->getImage();
                                    $itemUrl = $item->bundle->getUrl();
                                } elseif (!empty($item->subscribe_id) and !empty($item->subscribe)) {
                                    $image = $item->subscribe->icon;
                                } elseif (!empty($item->product_id) and !empty($item->product)) {
                                    $image = $item->product->thumbnail;
                                    $itemUrl = $item->product->getUrl();
                                } elseif (!empty($item->registration_package_id) and !empty($item->registrationPackage)) {
                                    $image = $item->registrationPackage->icon;
                                }

                                $isGift = ($giftable and $owned);
                            @endphp

                            <article class="lw-co-item {{ $isGift ? 'is-gift' : '' }}" data-item="{{ $item->id }}">
                                <div class="lw-co-item__head">
                                    <div class="lw-co-item__thumb">
                                        @if(!empty($image))
                                            <img src="{{ $image }}" alt="">
                                        @else
                                            <i data-feather="book-open" width="22" height="22" aria-hidden="true"></i>
                                        @endif
                                    </div>

                                    <div class="lw-co-item__info">
                                        <h3 class="lw-co-item__title">
                                            @if(!empty($itemUrl))
                                                <a href="{{ $itemUrl }}" target="_blank">{{ $item->title ?? trans('cart.item') }}</a>
                                            @else
                                                {{ $item->title ?? trans('cart.item') }}
                                            @endif
                                        </h3>
                                        <span class="lw-co-item__price">{{ handlePrice($item->amount) }}</span>
                                    </div>

                                    <span class="lw-co-item__badge" aria-hidden="true">
                                        <i data-feather="gift" width="14" height="14"></i> {{ trans('home.lw_co_gift_badge') }}
                                    </span>
                                </div>

                                @if($giftable)
                                    <fieldset class="lw-co-choice">
                                        <legend class="sr-only">{{ trans('home.lw_co_step_recipient') }} — {{ $item->title }}</legend>

                                        <label class="lw-co-choice__opt {{ $owned ? 'is-locked' : '' }}">
                                            <input type="radio" name="sale_type[{{ $item->id }}]" value="self" class="js-co-sale-type"
                                                   {{ !$isGift ? 'checked' : '' }} {{ $owned ? 'disabled' : '' }}>
                                            <span>
                                                <i data-feather="user" width="18" height="18" aria-hidden="true"></i>
                                                {{ trans('home.lw_co_for_me') }}
                                            </span>
                                        </label>

                                        <label class="lw-co-choice__opt">
                                            <input type="radio" name="sale_type[{{ $item->id }}]" value="other" class="js-co-sale-type"
                                                   {{ $isGift ? 'checked' : '' }}>
                                            <span>
                                                <i data-feather="gift" width="18" height="18" aria-hidden="true"></i>
                                                {{ trans('home.lw_co_as_gift') }}
                                            </span>
                                        </label>
                                    </fieldset>

                                    @if($owned)
                                        <p class="lw-co-item__note">
                                            <i data-feather="info" width="16" height="16" aria-hidden="true"></i>
                                            {{ trans('home.lw_co_owned') }}
                                        </p>
                                    @endif

                                    <div class="lw-co-gift" id="gift-form-{{ $item->id }}" @if(!$isGift) hidden @endif>
                                        <div class="lw-co-gift__head">
                                            <span class="lw-co-gift__icon" aria-hidden="true"><i data-feather="gift" width="20" height="20"></i></span>
                                            <div>
                                                <strong>{{ trans('home.lw_co_gift_title') }}</strong>
                                                <p>{{ trans('home.lw_co_gift_hint') }}</p>
                                            </div>
                                        </div>

                                        <div class="lw-co-gift__grid">
                                            <div class="lw-co-field">
                                                <label for="giftName{{ $item->id }}">{{ trans('home.lw_co_gift_name') }}</label>
                                                <input type="text" id="giftName{{ $item->id }}" name="gift_user[{{ $item->id }}][full_name]"
                                                       class="lw-input" maxlength="255" autocomplete="off" required {{ $isGift ? '' : 'disabled' }}>
                                                <span class="lw-co-field__error" data-error-for="gift_user.{{ $item->id }}.full_name"></span>
                                            </div>

                                            <div class="lw-co-field">
                                                <label for="giftEmail{{ $item->id }}">{{ trans('home.lw_co_gift_email') }}</label>
                                                <input type="email" id="giftEmail{{ $item->id }}" name="gift_user[{{ $item->id }}][email]"
                                                       class="lw-input" dir="ltr" autocomplete="off" required {{ $isGift ? '' : 'disabled' }}>
                                                <span class="lw-co-field__error" data-error-for="gift_user.{{ $item->id }}.email"></span>
                                            </div>

                                            <div class="lw-co-field lw-co-field--wide">
                                                <label for="giftPassword{{ $item->id }}">{{ trans('home.lw_co_gift_password') }}</label>
                                                <div class="lw-co-password">
                                                    <input type="password" id="giftPassword{{ $item->id }}" name="gift_user[{{ $item->id }}][password]"
                                                           class="lw-input" dir="ltr" minlength="6" autocomplete="new-password" required {{ $isGift ? '' : 'disabled' }}>
                                                    <button type="button" class="lw-co-password__btn js-co-show-password" aria-pressed="false"
                                                            aria-label="{{ trans('home.lw_co_show') }}" title="{{ trans('home.lw_co_show') }}">
                                                        <i data-feather="eye" width="18" height="18" aria-hidden="true"></i>
                                                    </button>
                                                    <button type="button" class="lw-btn lw-btn--outline lw-co-password__gen js-co-generate">
                                                        <i data-feather="refresh-cw" width="16" height="16" aria-hidden="true"></i>
                                                        {{ trans('home.lw_co_generate') }}
                                                    </button>
                                                </div>
                                                <span class="lw-co-field__hint">{{ trans('home.lw_co_gift_password_hint') }}</span>
                                                <span class="lw-co-field__error" data-error-for="gift_user.{{ $item->id }}.password"></span>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <input type="hidden" name="sale_type[{{ $item->id }}]" value="self">
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>

                {{-- Step 2: payment method --}}
                <section class="lw-panel lw-co-step" aria-labelledby="lwCoPaymentTitle">
                    <h2 class="lw-co-step__title" id="lwCoPaymentTitle">
                        <span class="lw-co-step__num" aria-hidden="true">2</span>
                        {{ trans('home.lw_co_step_payment') }}
                    </h2>

                    <div class="lw-co-gateways" role="radiogroup" aria-labelledby="lwCoPaymentTitle">
                        @foreach($validChannels as $paymentChannel)
                            <label class="lw-co-gateway">
                                <input type="radio" name="gateway" id="{{ $paymentChannel->title }}" data-class="{{ $paymentChannel->class_name }}"
                                       value="{{ $paymentChannel->id }}" {{ (string)$defaultGateway === (string)$paymentChannel->id ? 'checked' : '' }}>
                                <span class="lw-co-gateway__box">
                                    <img loading="lazy" src="{{ $paymentChannel->image }}" alt="" width="96" height="48">
                                    <span class="lw-co-gateway__name">{{ trans('financial.pay_via') }} <strong>{{ $paymentChannel->title }}</strong></span>
                                    <i data-feather="check-circle" width="20" height="20" class="lw-co-gateway__check" aria-hidden="true"></i>
                                </span>
                            </label>
                        @endforeach

                        <label class="lw-co-gateway {{ !$canPayWithCharge ? 'is-disabled' : '' }}">
                            <input type="radio" name="gateway" id="offline" value="credit"
                                   {{ !$canPayWithCharge ? 'disabled' : '' }} {{ $defaultGateway === 'credit' ? 'checked' : '' }}>
                            <span class="lw-co-gateway__box">
                                <span class="lw-co-gateway__wallet" aria-hidden="true"><i data-feather="credit-card" width="26" height="26"></i></span>
                                <span class="lw-co-gateway__name"><strong>{{ trans('home.lw_co_balance') }}</strong></span>
                                <span class="lw-co-gateway__balance">{{ handlePrice($userCharge) }}</span>
                                @if(!$canPayWithCharge)
                                    <span class="lw-co-gateway__low">{{ trans('home.lw_co_balance_low') }}</span>
                                @endif
                                <i data-feather="check-circle" width="20" height="20" class="lw-co-gateway__check" aria-hidden="true"></i>
                            </span>
                        </label>
                    </div>

                    @if(!$canPayWithCharge)
                        <a href="/panel/financial/account" class="lw-co-topup">
                            <i data-feather="plus-circle" width="16" height="16" aria-hidden="true"></i>
                            {{ trans('home.lw_co_top_up') }}
                        </a>
                    @endif

                    @if(!empty($invalidChannels) and empty(getFinancialSettings("hide_disabled_payment_gateways")))
                        <div class="lw-co-disabled">
                            <p><strong>{{ trans('update.disabled_payment_gateways') }}</strong> — {{ trans('update.disabled_payment_gateways_hint') }}</p>
                            <div class="lw-co-disabled__list">
                                @foreach($invalidChannels as $invalidChannel)
                                    <span class="lw-co-disabled__item">
                                        <img loading="lazy" src="{{ $invalidChannel->image }}" alt="" width="60" height="30">
                                        {{ $invalidChannel->title }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>
            </div>

            {{-- Order summary + the single pay button --}}
            <aside class="lw-checkout__aside">
                <div class="lw-panel lw-co-summary">
                    <h2 class="lw-co-summary__title">
                        @include('web.default.includes.manuscript.star', ['size' => 16, 'dot' => '#FFFDF8'])
                        {{ trans('home.lw_co_summary') }}
                    </h2>

                    <ul class="lw-co-summary__items">
                        @foreach($order->orderItems as $item)
                            <li data-summary-item="{{ $item->id }}">
                                <span class="lw-co-summary__name">
                                    {{ $item->title ?? trans('cart.item') }}
                                    <em class="lw-co-summary__gift"><i data-feather="gift" width="12" height="12" aria-hidden="true"></i> {{ trans('home.lw_co_gift_badge') }}</em>
                                </span>
                                <span>{{ handlePrice($item->amount) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <dl class="lw-co-summary__totals">
                        <div><dt>{{ trans('cart.sub_total') }}</dt><dd>{{ handlePrice($subTotalValue) }}</dd></div>
                        @if(!empty($discountValue) and $discountValue > 0)
                            <div class="is-discount"><dt>{{ trans('public.discount') }}</dt><dd>- {{ handlePrice($discountValue) }}</dd></div>
                        @endif
                        @if(!empty($taxValue) and $taxValue > 0)
                            <div><dt>{{ trans('cart.tax') }}</dt><dd>{{ handlePrice($taxValue) }}</dd></div>
                        @endif
                        <div class="is-total"><dt>{{ trans('cart.total') }}</dt><dd>{{ handlePrice($total) }}</dd></div>
                    </dl>

                    @if(!empty($totalCashbackAmount))
                        <p class="lw-co-summary__cashback">
                            <i data-feather="award" width="16" height="16" aria-hidden="true"></i>
                            {{ trans('update.by_purchasing_this_cart_you_will_get_amount_as_cashback',['amount' => handlePrice($totalCashbackAmount)]) }}
                        </p>
                    @endif

                    <div class="lw-co-alert" id="lwCoAlert" role="alert" hidden></div>

                    <button type="submit" id="paymentSubmit" class="lw-btn lw-btn--cta lw-btn--block lw-co-pay"
                            data-processing="{{ trans('home.lw_co_processing') }}" {{ empty($defaultGateway) ? 'disabled' : '' }}>
                        <i data-feather="lock" width="18" height="18" aria-hidden="true"></i>
                        <span>{{ trans('home.lw_co_pay', ['amount' => handlePrice($total)]) }}</span>
                    </button>

                    <p class="lw-co-summary__secure">
                        <i data-feather="shield" width="14" height="14" aria-hidden="true"></i>
                        {{ trans('home.lw_co_secure') }}
                    </p>
                </div>
            </aside>
        </form>

        @if(!empty($razorpay) and $razorpay)
            <form action="/payments/verify/Razorpay" method="get" class="lw-co-razorpay">
                <input type="hidden" name="order_id" value="{{ $order->id }}">

                <script src="https://checkout.razorpay.com/v1/checkout.js"
                        data-key="{{ getRazorpayApiKey()['api_key'] }}"
                        data-amount="{{ (int)($order->total_amount * 100) }}"
                        data-buttontext="product_price"
                        data-description="Rozerpay"
                        data-currency="{{ currency() }}"
                        data-image="{{ $generalSettings['logo'] }}"
                        data-prefill.name="{{ $order->user->full_name }}"
                        data-prefill.email="{{ $order->user->email }}"
                        data-theme.color="#43d477">
                </script>
            </form>
        @endif
    </div>
@endsection

@push('scripts_bottom')
    <script>
        var lwCheckoutLang = {
            required: @json(trans('home.lw_co_err_required')),
            email: @json(trans('home.lw_co_err_email')),
            password: @json(trans('home.lw_co_err_password')),
            sameEmail: @json(trans('home.lw_co_err_same_email')),
            generic: @json(trans('home.lw_co_err_generic')),
            show: @json(trans('home.lw_co_show')),
            hide: @json(trans('home.lw_co_hide'))
        };
    </script>
    <script src="/assets/lightway/checkout.js?v={{ @filemtime(public_path('assets/lightway/checkout.js')) }}"></script>
@endpush
