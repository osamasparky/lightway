{{-- Paginator view: $items->appends(request()->input())->links('web.default.includes.lightway.pagination') --}}
@if ($paginator->hasPages())
    <nav class="lw-pagination" aria-label="{{ trans('home.lw_pagination') }}">
        @if ($paginator->onFirstPage())
            <span class="lw-pagination__item lw-pagination__arrow is-disabled" aria-disabled="true" aria-label="{{ trans('home.lw_previous') }}">
                <i data-feather="chevron-right" width="18" height="18" class="lw-flip-ltr" aria-hidden="true"></i>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="lw-pagination__item lw-pagination__arrow" aria-label="{{ trans('home.lw_previous') }}">
                <i data-feather="chevron-right" width="18" height="18" class="lw-flip-ltr" aria-hidden="true"></i>
            </a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="lw-pagination__item is-gap" aria-hidden="true">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="lw-pagination__item is-active" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="lw-pagination__item">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="lw-pagination__item lw-pagination__arrow" aria-label="{{ trans('home.lw_next') }}">
                <i data-feather="chevron-left" width="18" height="18" class="lw-flip-ltr" aria-hidden="true"></i>
            </a>
        @else
            <span class="lw-pagination__item lw-pagination__arrow is-disabled" aria-disabled="true" aria-label="{{ trans('home.lw_next') }}">
                <i data-feather="chevron-left" width="18" height="18" class="lw-flip-ltr" aria-hidden="true"></i>
            </span>
        @endif
    </nav>
@endif
