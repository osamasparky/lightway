{{--
    Lightway footer (inner pages).
    Content is 100% admin-driven: newsletter texts (lang), the four admin footer columns,
    footer logo, contact phone/email and social links from settings.
--}}
@php
    $socials = getSocials();
    if (!empty($socials) and count($socials)) {
        $socials = collect($socials)->sortBy('order')->toArray();
    }

    $footerColumns = getFooterColumns();
    $footerLogo = !empty($generalSettings['footer_logo']) ? $generalSettings['footer_logo'] : ($generalSettings['logo'] ?? null);
    $siteName = $generalSettings['site_name'] ?? '';

    $columnHasContent = function ($column) use ($footerColumns) {
        return !empty($footerColumns[$column]) and (!empty($footerColumns[$column]['title']) or trim(strip_tags($footerColumns[$column]['value'] ?? '')) !== '');
    };
@endphp

<footer class="lw-footer">
    <div class="ms-band" aria-hidden="true"></div>

    <div class="ms-container lw-footer__inner">
        {{-- Newsletter --}}
        <div class="lw-footer__subscribe">
            <div class="lw-footer__subscribe-text">
                <h2 class="lw-footer__title">{{ trans('footer.join_us_today') }}</h2>
                <p>{{ trans('footer.subscribe_content') }}</p>
            </div>

            <form action="/newsletters" method="post" class="lw-footer__form">
                {{ csrf_field() }}

                <label for="lwNewsletterEmail" class="sr-only">{{ trans('footer.enter_email_here') }}</label>
                <div class="lw-footer__field">
                    <input id="lwNewsletterEmail" type="email" name="newsletter_email" class="@error('newsletter_email') is-invalid @enderror" placeholder="{{ trans('footer.enter_email_here') }}" autocomplete="email"/>
                    @error('newsletter_email')
                        <span class="lw-footer__error" role="alert">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit" class="lw-btn lw-btn--cta">{{ trans('footer.join') }}</button>
            </form>
        </div>

        {{-- Columns: logo + admin columns + contact --}}
        <div class="lw-footer__grid">
            <div class="lw-footer__col lw-footer__col--about">
                @if(!empty($footerLogo))
                    <a href="/" class="lw-footer__logo" aria-label="{{ $siteName }}">
                        <img loading="lazy" src="{{ $footerLogo }}" alt="{{ $siteName }}">
                    </a>
                @endif

                @if($columnHasContent('first_column'))
                    @if(!empty($footerColumns['first_column']['title']))
                        <strong class="lw-footer__heading">{{ $footerColumns['first_column']['title'] }}</strong>
                    @endif
                    <div class="lw-footer__rich">{!! $footerColumns['first_column']['value'] ?? '' !!}</div>
                @endif
            </div>

            @foreach(['second_column', 'third_column'] as $column)
                @if($columnHasContent($column))
                    <nav class="lw-footer__col" @if(!empty($footerColumns[$column]['title'])) aria-label="{{ $footerColumns[$column]['title'] }}" @endif>
                        @if(!empty($footerColumns[$column]['title']))
                            <strong class="lw-footer__heading">{{ $footerColumns[$column]['title'] }}</strong>
                        @endif
                        <div class="lw-footer__rich lw-footer__links">{!! $footerColumns[$column]['value'] ?? '' !!}</div>
                    </nav>
                @endif
            @endforeach

            <div class="lw-footer__col">
                @if($columnHasContent('forth_column'))
                    @if(!empty($footerColumns['forth_column']['title']))
                        <strong class="lw-footer__heading">{{ $footerColumns['forth_column']['title'] }}</strong>
                    @endif
                    <div class="lw-footer__rich">{!! $footerColumns['forth_column']['value'] ?? '' !!}</div>
                @endif

                @if(!empty($generalSettings['site_phone']) or !empty($generalSettings['site_email']) or (!empty($socials) and count($socials)))
                    <strong class="lw-footer__heading">{{ trans('site.contact_us') }}</strong>

                    @if(!empty($generalSettings['site_phone']))
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $generalSettings['site_phone']) }}" class="lw-footer__contact" dir="ltr">{{ $generalSettings['site_phone'] }}</a>
                    @endif

                    @if(!empty($generalSettings['site_email']))
                        <a href="mailto:{{ $generalSettings['site_email'] }}" class="lw-footer__contact">{{ $generalSettings['site_email'] }}</a>
                    @endif

                    @if(!empty($socials) and count($socials))
                        <div class="lw-footer__socials">
                            @foreach($socials as $social)
                                <a href="{{ $social['link'] }}" target="_blank" rel="noopener" aria-label="{{ $social['title'] }}" title="{{ $social['title'] }}">
                                    <img loading="lazy" src="{{ $social['image'] }}" alt="" width="20" height="20">
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </div>

        <div class="lw-footer__copy">
            @include('web.default.includes.manuscript.star', ['size' => 14])
            <span>{{ trans('update.platform_copyright_hint') }}</span>
            @include('web.default.includes.manuscript.star', ['size' => 14])
        </div>
    </div>
</footer>
