{{--
    Section heading.
    Params: $title, $hint (optional), $url + $linkText (optional), $variant ('light' for dark backgrounds).
    Falls back to the core theme markup when the manuscript theme is off.
--}}
@if(!empty($manuscriptTheme))
    <div class="ms-section-head {{ !empty($variant) ? 'ms-section-head--' . $variant : '' }}">
        <div class="ms-cartouche">
            <svg class="ms-cartouche__shape" viewBox="0 0 600 82" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                <path d="M64 8 H536 Q556 8 566 22 L592 41 L566 60 Q556 74 536 74 H64 Q44 74 34 60 L8 41 L34 22 Q44 8 64 8Z" class="ms-cartouche__fill"></path>
                <path d="M70 16 H530 Q546 16 555 28 L574 41 L555 54 Q546 66 530 66 H70 Q54 66 45 54 L26 41 L45 28 Q54 16 70 16Z" class="ms-cartouche__dash"></path>
            </svg>
            @include('web.default.includes.manuscript.star', ['size' => 28, 'class' => 'ms-cartouche__star ms-cartouche__star--start', 'dot' => '#0A4F78'])
            <h2 class="ms-cartouche__title">{{ $title }}</h2>
            @include('web.default.includes.manuscript.star', ['size' => 28, 'class' => 'ms-cartouche__star ms-cartouche__star--end', 'dot' => '#0A4F78'])
        </div>

        @if(!empty($hint))
            <p class="ms-section-head__hint">{{ $hint }}</p>
        @endif

        @if(!empty($url))
            <a href="{{ $url }}" class="ms-section-head__link">
                {{ $linkText ?? trans('home.view_all') }}
                <i data-feather="arrow-left" width="16" height="16" class="ms-arrow"></i>
            </a>
        @endif
    </div>
@elseif(!empty($url))
    <div class="d-flex justify-content-between ">
        <div>
            <h2 class="section-title">{{ $title }}</h2>
            <p class="section-hint">{{ $hint }}</p>
        </div>

        <a href="{{ $url }}" class="btn btn-border-white">{{ $linkText ?? trans('home.view_all') }}</a>
    </div>
@else
    <h2 class="section-title">{{ $title }}</h2>
    <p class="section-hint">{{ $hint }}</p>
@endif
