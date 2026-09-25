{{-- Store toolbar. #topFilters is watched by products_lists.min.js, which submits #filtersForm on change. --}}
<div id="topFilters" class="lw-toolbar">
    <div class="lw-toolbar__group">
        @include('web.default.includes.lightway.toggle', ['name' => 'free', 'id' => 'free', 'label' => trans('public.free'), 'checked' => request()->get('free') == 'on'])
        @include('web.default.includes.lightway.toggle', ['name' => 'free_shipping', 'id' => 'free_shipping', 'label' => trans('update.free_shipping'), 'checked' => request()->get('free_shipping') == 'on'])
        @include('web.default.includes.lightway.toggle', ['name' => 'discount', 'id' => 'discount', 'label' => trans('public.discount'), 'checked' => request()->get('discount') == 'on'])
    </div>

    <div class="lw-toolbar__group">
        <span class="lw-field">
            <label for="lwStoreSort">{{ trans('public.sort_by') }}</label>
            <select name="sort" id="lwStoreSort" class="lw-select">
                <option value="">{{ trans('public.all') }}</option>
                @foreach(['newest', 'expensive', 'inexpensive', 'bestsellers', 'best_rates'] as $sortOption)
                    <option value="{{ $sortOption }}" @if(request()->get('sort') == $sortOption) selected @endif>{{ trans('public.' . $sortOption) }}</option>
                @endforeach
            </select>
        </span>

        <button type="button" class="lw-btn lw-btn--outline lw-drawer-opener js-lw-drawer-open" aria-controls="lwStoreFilters" aria-expanded="false">
            <i data-feather="sliders" width="18" height="18" aria-hidden="true"></i>
            {{ trans('home.lw_filters') }}
        </button>
    </div>
</div>
