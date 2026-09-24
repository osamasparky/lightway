{{--
    Courses toolbar: toggles + sort + grid/list switch.
    Same field names and #topFilters id as pages/includes/top_filters, so categories.min.js
    keeps auto-submitting #filtersForm on change and the query params stay identical.
--}}
@php
    $currentCard = request()->get('card', 'grid') ?: 'grid';
    $currentSort = request()->get('sort', null);
@endphp

<div id="topFilters" class="lw-toolbar">
    <div class="lw-toolbar__group">
        @include('web.default.includes.lightway.toggle', ['name' => 'upcoming', 'id' => 'upcoming', 'label' => trans('panel.upcoming'), 'checked' => request()->get('upcoming') == 'on'])
        @include('web.default.includes.lightway.toggle', ['name' => 'free', 'id' => 'free', 'label' => trans('public.free'), 'checked' => request()->get('free') == 'on'])
        @include('web.default.includes.lightway.toggle', ['name' => 'discount', 'id' => 'discount', 'label' => trans('public.discount'), 'checked' => request()->get('discount') == 'on'])
        @include('web.default.includes.lightway.toggle', ['name' => 'downloadable', 'id' => 'download', 'label' => trans('home.download'), 'checked' => request()->get('downloadable') == 'on'])
    </div>

    <div class="lw-toolbar__group">
        <span class="lw-field">
            <label for="lwSort">{{ trans('public.sort_by') }}</label>
            <select name="sort" id="lwSort" class="lw-select">
                <option value="">{{ trans('public.all') }}</option>
                @foreach(['newest', 'expensive', 'inexpensive', 'bestsellers', 'best_rates'] as $sortOption)
                    <option value="{{ $sortOption }}" @if($currentSort == $sortOption) selected @endif>{{ trans('public.' . $sortOption) }}</option>
                @endforeach
            </select>
        </span>

        <div class="lw-view-switch" role="radiogroup" aria-label="{{ trans('home.lw_view') }}">
            <input type="radio" name="card" id="gridView" value="grid" class="sr-only" @if($currentCard == 'grid') checked @endif>
            <label for="gridView" class="lw-view-switch__btn {{ $currentCard == 'grid' ? 'is-active' : '' }}" title="{{ trans('home.lw_grid_view') }}">
                <i data-feather="grid" width="20" height="20" aria-hidden="true"></i>
                <span class="sr-only">{{ trans('home.lw_grid_view') }}</span>
            </label>

            <input type="radio" name="card" id="listView" value="list" class="sr-only" @if($currentCard == 'list') checked @endif>
            <label for="listView" class="lw-view-switch__btn {{ $currentCard == 'list' ? 'is-active' : '' }}" title="{{ trans('home.lw_list_view') }}">
                <i data-feather="list" width="20" height="20" aria-hidden="true"></i>
                <span class="sr-only">{{ trans('home.lw_list_view') }}</span>
            </label>
        </div>

        <button type="button" class="lw-btn lw-btn--outline lw-drawer-opener js-lw-drawer-open" aria-controls="lwCourseFilters" aria-expanded="false">
            <i data-feather="sliders" width="18" height="18" aria-hidden="true"></i>
            {{ trans('home.lw_filters') }}
        </button>
    </div>
</div>
