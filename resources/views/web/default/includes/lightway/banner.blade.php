{{--
    Inner-page banner: lattice background, breadcrumb, Aref Ruqaa title with a star,
    optional subtitle/count and optional search, ms-band strip + orange line at the bottom.

    Params:
      $title        string
      $subtitle     string|null
      $breadcrumbs  array of ['title' => .., 'url' => ..|null]  (home is prepended automatically)
      $search       array|null ['action' => '/classes', 'name' => 'search', 'placeholder' => .., 'keep' => ['type', ...]]
                    'keep' lists current query params to carry over as hidden inputs.
--}}
@php
    $crumbs = array_merge([['title' => trans('home.ms_home_link'), 'url' => '/']], $breadcrumbs ?? []);
    $searchName = $search['name'] ?? 'search';
@endphp

<section class="lw-banner ms-lattice">
    <div class="ms-container lw-banner__inner">
        <div class="lw-banner__text">
            <nav class="lw-breadcrumb" aria-label="{{ trans('home.lw_breadcrumb') }}">
                <ol>
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

            <h1 class="lw-banner__title">
                @include('web.default.includes.manuscript.star', ['size' => 34, 'dot' => '#FBF6EC', 'class' => 'lw-banner__star'])
                <span>{{ $title }}</span>
            </h1>

            @if(!empty($subtitle))
                <p class="lw-banner__subtitle">{{ $subtitle }}</p>
            @endif
        </div>

        @if(!empty($search))
            <form action="{{ $search['action'] }}" method="get" role="search" class="lw-search">
                @foreach(($search['keep'] ?? []) as $keep)
                    @foreach((array) request()->get($keep, []) as $keepValue)
                        @if(!is_array($keepValue) and $keepValue !== '')
                            <input type="hidden" name="{{ is_array(request()->get($keep)) ? $keep . '[]' : $keep }}" value="{{ $keepValue }}">
                        @endif
                    @endforeach
                @endforeach

                <label for="lwBannerSearch" class="sr-only">{{ $search['placeholder'] ?? trans('navbar.search_anything') }}</label>
                <i data-feather="search" width="20" height="20" class="lw-search__icon" aria-hidden="true"></i>
                <input id="lwBannerSearch" type="search" name="{{ $searchName }}" value="{{ request()->get($searchName) }}" placeholder="{{ $search['placeholder'] ?? trans('navbar.search_anything') }}">
                <button type="submit" class="lw-btn lw-btn--dark">{{ trans('home.find') }}</button>
            </form>
        @endif
    </div>

    <div class="ms-band lw-banner__band" aria-hidden="true"></div>
</section>
