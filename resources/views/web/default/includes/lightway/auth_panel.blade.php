{{-- Navy arched panel beside the auth forms. Params: $welcome, $hint --}}
@php $authLogo = $generalSettings['logo'] ?? null; @endphp

<aside class="lw-auth-panel ms-lattice-dark" aria-label="{{ $generalSettings['site_name'] ?? '' }}">
    @if(!empty($authLogo))
        <span class="lw-auth-panel__logo">
            <img src="{{ $authLogo }}" alt="{{ $generalSettings['site_name'] ?? '' }}">
        </span>
    @endif

    <p class="lw-auth-panel__verse" lang="ar" dir="rtl">
        @include('web.default.includes.manuscript.star', ['size' => 14])
        <span>{{ trans('home.lw_auth_verse') }}</span>
        @include('web.default.includes.manuscript.star', ['size' => 14])
    </p>
    @if(app()->getLocale() != 'ar')
        <p class="lw-auth-panel__translation">{{ trans('home.lw_auth_verse_translation') }}</p>
    @endif
    <span class="lw-auth-panel__ref">{{ trans('home.lw_auth_verse_ref') }}</span>

    <h2 class="lw-auth-panel__welcome">{{ $welcome }}</h2>

    @if(!empty($hint))
        <p class="lw-auth-panel__hint">{{ $hint }}</p>
    @endif
</aside>
