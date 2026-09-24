{{-- #topFilters is watched by instructor-finder.min.js (auto-submit). Same field names as before. --}}
<div id="topFilters" class="lw-toolbar">
    <div class="lw-toolbar__group">
        @include('web.default.includes.lightway.toggle', ['name' => 'available_for_meetings', 'id' => 'available_for_meetings', 'label' => trans('public.available_for_meetings'), 'checked' => request()->get('available_for_meetings') == 'on'])
        @include('web.default.includes.lightway.toggle', ['name' => 'free_meetings', 'id' => 'free_meetings', 'label' => trans('public.free_meetings'), 'checked' => request()->get('free_meetings') == 'on'])
        @include('web.default.includes.lightway.toggle', ['name' => 'discount', 'id' => 'discount', 'label' => trans('public.discount'), 'checked' => request()->get('discount') == 'on'])
    </div>

    <div class="lw-toolbar__group">
        <span class="lw-field">
            <label for="lwFinderSort">{{ trans('public.sort_by') }}</label>
            <select name="sort" id="lwFinderSort" class="lw-select">
                <option value="">{{ trans('public.all') }}</option>
                <option value="top_rate" @if(request()->get('sort') == 'top_rate') selected @endif>{{ trans('site.top_rate') }}</option>
                <option value="top_sale" @if(request()->get('sort') == 'top_sale') selected @endif>{{ trans('site.top_sellers') }}</option>
            </select>
        </span>

        <button type="button" class="lw-btn lw-btn--outline lw-drawer-opener js-lw-drawer-open" aria-controls="lwFinderFilters" aria-expanded="false">
            <i data-feather="sliders" width="18" height="18" aria-hidden="true"></i>
            {{ trans('home.lw_filters') }}
        </button>
    </div>
</div>
