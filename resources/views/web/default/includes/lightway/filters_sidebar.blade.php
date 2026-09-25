{{--
    Sidebar that turns into an off-canvas filter drawer below 992px.
    Usage:
        @component('web.default.includes.lightway.filters_sidebar', ['id' => 'lwCourseFilters', 'label' => trans('public.filter')])
            ... lightway.panel blocks + submit button ...
        @endcomponent
    Place the opener anywhere with:
        <button type="button" class="lw-btn lw-btn--outline js-lw-drawer-open" aria-controls="{id}" aria-expanded="false">…</button>
--}}
<aside class="lw-sidebar" id="{{ $id }}" aria-label="{{ $label ?? trans('home.lw_filters') }}">
    <div class="lw-sidebar__backdrop js-lw-drawer-close" aria-hidden="true"></div>

    <div class="lw-sidebar__panel">
        <div class="lw-sidebar__head">
            <strong>{{ $label ?? trans('home.lw_filters') }}</strong>
            <button type="button" class="lw-icon-btn js-lw-drawer-close" aria-label="{{ trans('home.lw_close_filters') }}">
                <i data-feather="x" width="20" height="20" aria-hidden="true"></i>
            </button>
        </div>

        {{ $slot }}
    </div>
</aside>
