@php
    if (empty($authUser) and auth()->check()) {
        $authUser = auth()->user();
    }

    $navBtnUrl = null;
    $navBtnText = null;

    $navbarButton = getNavbarButton(!empty($authUser) ? $authUser->role_id : null, empty($authUser));

    if (!empty($navbarButton)) {
        $navBtnUrl = $navbarButton->url;
        $navBtnText = $navbarButton->title;
    }

    $userLanguages = !empty($generalSettings['site_language']) ? [$generalSettings['site_language'] => getLanguages($generalSettings['site_language'])] : [];

    if (!empty($generalSettings['user_languages']) and is_array($generalSettings['user_languages'])) {
        $userLanguages = getLanguages($generalSettings['user_languages']);
    }

    $currentLocale = mb_strtoupper(app()->getLocale());
    $otherLanguages = collect($userLanguages)->except($currentLocale);

    $showContactInHeader = (getOthersPersonalizationSettings('platform_phone_and_email_position') == 'header');

    $currentPath = '/' . trim(request()->path(), '/');
    $isActiveLink = function ($link) use ($currentPath) {
        $linkPath = '/' . trim(parse_url($link, PHP_URL_PATH) ?? '', '/');

        return ($linkPath == '/') ? ($currentPath == '/') : \Illuminate\Support\Str::startsWith($currentPath, $linkPath);
    };

    $siteName = $generalSettings['site_name'] ?? '';
@endphp

{{-- Top bar --}}
<div class="ms-topbar">
    <div class="ms-container ms-topbar__inner">
        <div class="ms-topbar__group">
            @if($otherLanguages->count())
                @php
                    $currentLangFlag = \App\Services\Localization\LanguageRegistry::flagUrl($currentLocale);
                @endphp
                <div class="ms-lang" data-ms-lang>
                    <button type="button" class="ms-lang__toggle" id="msLangToggle" aria-haspopup="true" aria-expanded="false" aria-controls="msLangMenu"
                            aria-label="{{ trans('home.lw_language') }}: {{ \App\Services\Localization\LanguageRegistry::nativeName($currentLocale) }}">
                        @if($currentLangFlag)
                            <img src="{{ $currentLangFlag }}" alt="" class="ms-lang__flag" width="22" height="16">
                        @else
                            <i data-feather="globe" width="15" height="15" aria-hidden="true"></i>
                        @endif
                        <span class="ms-lang__label">{{ \App\Services\Localization\LanguageRegistry::nativeName($currentLocale) }}</span>
                        <i data-feather="chevron-down" width="14" height="14" class="ms-lang__chevron" aria-hidden="true"></i>
                    </button>

                    <form action="/locale" method="post" class="ms-lang__menu" id="msLangMenu" aria-labelledby="msLangToggle" hidden>
                        {{ csrf_field() }}
                        @if(!empty($previousUrl))
                            <input type="hidden" name="previous_url" value="{{ $previousUrl }}">
                        @endif

                        @foreach($userLanguages as $langCode => $langTitle)
                            @php
                                $isCurrentLang = mb_strtoupper($langCode) === $currentLocale;
                                $langFlag = \App\Services\Localization\LanguageRegistry::flagUrl($langCode);
                            @endphp
                            <button type="submit" name="locale" value="{{ localeToCountryCode($langCode) }}"
                                    class="ms-lang__item {{ $isCurrentLang ? 'is-current' : '' }}" lang="{{ mb_strtolower($langCode) }}"
                                    @if($isCurrentLang) aria-current="true" @endif>
                                @if($langFlag)
                                    <img src="{{ $langFlag }}" alt="" class="ms-lang__flag" width="24" height="18" loading="lazy">
                                @endif
                                <span class="ms-lang__names">
                                    <span class="ms-lang__native">{{ \App\Services\Localization\LanguageRegistry::nativeName($langCode) }}</span>
                                    @if(\App\Services\Localization\LanguageRegistry::nativeName($langCode) !== $langTitle)
                                        <span class="ms-lang__english">{{ $langTitle }}</span>
                                    @endif
                                </span>
                                @if($isCurrentLang)
                                    <i data-feather="check" width="16" height="16" class="ms-lang__check" aria-hidden="true"></i>
                                @endif
                            </button>
                        @endforeach
                    </form>
                </div>

                <script>
                    (function () {
                        var root = document.querySelector('[data-ms-lang]');
                        if (!root) return;
                        var toggle = root.querySelector('.ms-lang__toggle');
                        var menu = root.querySelector('.ms-lang__menu');
                        var items = function () { return Array.prototype.slice.call(menu.querySelectorAll('.ms-lang__item')); };

                        function open(focusFirst) {
                            menu.hidden = false;
                            toggle.setAttribute('aria-expanded', 'true');
                            root.classList.add('is-open');
                            if (focusFirst) { (menu.querySelector('.is-current') || items()[0]).focus(); }
                        }
                        function close(returnFocus) {
                            menu.hidden = true;
                            toggle.setAttribute('aria-expanded', 'false');
                            root.classList.remove('is-open');
                            if (returnFocus) { toggle.focus(); }
                        }

                        toggle.addEventListener('click', function () { menu.hidden ? open(false) : close(false); });
                        toggle.addEventListener('keydown', function (e) {
                            if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(true); }
                        });
                        menu.addEventListener('keydown', function (e) {
                            var list = items(), i = list.indexOf(document.activeElement);
                            if (e.key === 'Escape') { close(true); }
                            else if (e.key === 'ArrowDown') { e.preventDefault(); list[(i + 1) % list.length].focus(); }
                            else if (e.key === 'ArrowUp') { e.preventDefault(); list[(i - 1 + list.length) % list.length].focus(); }
                        });
                        document.addEventListener('click', function (e) { if (!root.contains(e.target)) { close(false); } });
                        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) { close(true); } });
                        root.addEventListener('focusout', function (e) { if (!root.contains(e.relatedTarget)) { close(false); } });
                    })();
                </script>
            @endif

            @if($showContactInHeader)
                @if(!empty($generalSettings['site_phone']))
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $generalSettings['site_phone']) }}" class="ms-topbar__link d-none d-md-inline" dir="ltr">{{ $generalSettings['site_phone'] }}</a>
                @endif

                @if(!empty($generalSettings['site_email']))
                    <a href="mailto:{{ $generalSettings['site_email'] }}" class="ms-topbar__link d-none d-lg-inline">{{ $generalSettings['site_email'] }}</a>
                @endif
            @endif

            <div class="ms-topbar__currency d-none d-md-block">
                @include('web.default.includes.top_nav.currency')
            </div>
        </div>

        <div class="ms-topbar__group">
            <div class="ms-topbar__icons d-flex align-items-center">
                @include(getTemplate().'.includes.shopping-cart-dropdwon')
                @include(getTemplate().'.includes.notification-dropdown')
            </div>

            <span class="ms-topbar__divider" aria-hidden="true"></span>

            @if(!empty($authUser))
                <div class="ms-topbar__user">
                    @include('web.default.includes.top_nav.user_menu')
                </div>
            @else
                <a href="/login" class="ms-topbar__link">{{ trans('auth.login') }}</a>
                <a href="/register" class="ms-topbar__accent">{{ trans('auth.register') }}</a>
            @endif
        </div>
    </div>
</div>

{{-- Main header --}}
<header class="ms-header" id="msHeader">
    <div class="ms-container ms-header__inner">
        <div class="ms-header__start">
            <a href="/" class="ms-brand" aria-label="{{ $siteName }} — {{ trans('home.ms_home_link') }}">
                @if(!empty($generalSettings['logo']))
                    <img src="{{ $generalSettings['logo'] }}" alt="{{ $siteName }}" class="ms-brand__logo">
                @else
                    <span class="ms-brand__name">{{ $siteName }}</span>
                @endif
            </a>

            @if(!empty($categories) and count($categories))
                <div class="ms-cats d-none d-lg-block">
                    <button type="button" class="ms-cats__toggle js-ms-dropdown-toggle" aria-expanded="false" aria-controls="msCategoriesMenu">
                        <i data-feather="grid" width="18" height="18"></i>
                        <span>{{ trans('categories.categories') }}</span>
                        <i data-feather="chevron-down" width="16" height="16" class="ms-cats__chevron"></i>
                    </button>

                    <ul class="ms-dropdown" id="msCategoriesMenu">
                        @foreach($categories as $category)
                            @php $hasSub = (!empty($category->subCategories) and count($category->subCategories)); @endphp
                            <li class="{{ $hasSub ? 'has-sub' : '' }}">
                                <a href="{{ $category->getUrl() }}" class="ms-dropdown__link">
                                    @if(!empty($category->icon))
                                        <img loading="lazy" src="{{ $category->icon }}" class="ms-dropdown__icon" alt="">
                                    @endif
                                    <span>{{ $category->title }}</span>
                                    @if($hasSub)
                                        <i data-feather="chevron-left" width="16" height="16" class="ms-dropdown__chevron"></i>
                                    @endif
                                </a>

                                @if($hasSub)
                                    <ul class="ms-dropdown ms-dropdown--sub">
                                        @foreach($category->subCategories as $subCategory)
                                            <li>
                                                <a href="{{ $subCategory->getUrl() }}" class="ms-dropdown__link">
                                                    @if(!empty($subCategory->icon))
                                                        <img loading="lazy" src="{{ $subCategory->icon }}" class="ms-dropdown__icon" alt="">
                                                    @endif
                                                    <span>{{ $subCategory->title }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @if(!empty($navbarPages) and count($navbarPages))
            <nav class="ms-nav d-none d-lg-block" aria-label="{{ trans('home.ms_main_menu') }}">
                <ul class="ms-nav__list">
                    @foreach($navbarPages as $navbarPage)
                        @php $active = $isActiveLink($navbarPage['link']); @endphp
                        <li>
                            <a href="{{ $navbarPage['link'] }}" class="ms-nav__link {{ $active ? 'is-active' : '' }}" @if($active) aria-current="page" @endif>{{ $navbarPage['title'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <div class="ms-header__actions">
            @if(!empty($navBtnUrl))
                <a href="{{ $navBtnUrl }}" class="ms-btn ms-btn--cta d-none d-sm-inline-flex">
                    {{ $navBtnText }}
                    <i data-feather="arrow-left" width="18" height="18" class="ms-arrow"></i>
                </a>
            @endif

            <button type="button" class="ms-icon-btn d-lg-none js-ms-drawer-open" aria-expanded="false" aria-controls="msDrawer" aria-label="{{ trans('home.ms_open_menu') }}">
                <i data-feather="menu" width="22" height="22"></i>
            </button>
        </div>
    </div>
</header>

{{-- Mobile drawer --}}
<div class="ms-drawer" id="msDrawer" aria-hidden="true">
    <div class="ms-drawer__backdrop js-ms-drawer-close"></div>

    <div class="ms-drawer__panel" role="dialog" aria-modal="true" aria-label="{{ trans('home.ms_main_menu') }}">
        <div class="ms-drawer__head">
            <a href="/" class="ms-brand">
                @if(!empty($generalSettings['logo']))
                    <img src="{{ $generalSettings['logo'] }}" alt="{{ $siteName }}" class="ms-brand__logo">
                @else
                    <span class="ms-brand__name">{{ $siteName }}</span>
                @endif
            </a>

            <button type="button" class="ms-icon-btn js-ms-drawer-close" aria-label="{{ trans('home.ms_close_menu') }}">
                <i data-feather="x" width="22" height="22"></i>
            </button>
        </div>

        <form action="/search" method="get" role="search" class="ms-drawer__search">
            <input type="search" name="search" placeholder="{{ trans('navbar.search_anything') }}" aria-label="{{ trans('navbar.search_anything') }}">
            <button type="submit" class="ms-btn ms-btn--dark">{{ trans('home.find') }}</button>
        </form>

        <ul class="ms-drawer__nav">
            @if(!empty($navbarPages) and count($navbarPages))
                @foreach($navbarPages as $navbarPage)
                    @php $active = $isActiveLink($navbarPage['link']); @endphp
                    <li>
                        <a href="{{ $navbarPage['link'] }}" class="{{ $active ? 'is-active' : '' }}" @if($active) aria-current="page" @endif>{{ $navbarPage['title'] }}</a>
                    </li>
                @endforeach
            @endif

            @if(!empty($categories) and count($categories))
                <li>
                    <details class="ms-drawer__cats">
                        <summary>{{ trans('categories.categories') }}</summary>
                        <ul>
                            @foreach($categories as $category)
                                <li><a href="{{ $category->getUrl() }}">{{ $category->title }}</a></li>
                                @if(!empty($category->subCategories) and count($category->subCategories))
                                    @foreach($category->subCategories as $subCategory)
                                        <li class="is-sub"><a href="{{ $subCategory->getUrl() }}">{{ $subCategory->title }}</a></li>
                                    @endforeach
                                @endif
                            @endforeach
                        </ul>
                    </details>
                </li>
            @endif
        </ul>

        @if(!empty($navBtnUrl))
            <a href="{{ $navBtnUrl }}" class="ms-btn ms-btn--cta ms-btn--block">{{ $navBtnText }}</a>
        @endif

        <div class="ms-drawer__currency">
            @include('web.default.includes.top_nav.currency')
        </div>
    </div>
</div>

@push('scripts_bottom')
    <script src="/assets/design/manuscript.js?v={{ filemtime(public_path('assets/design/manuscript.js')) }}"></script>
@endpush
