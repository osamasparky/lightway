{{-- Store sidebar: same field names as before (type[], options[], category_id, filter_option[]). Becomes a drawer on mobile. --}}
@component('web.default.includes.lightway.filters_sidebar', ['id' => 'lwStoreFilters', 'label' => trans('home.lw_filters')])
    @if(!empty($authUser) and ($authUser->isOrganization() or $authUser->isTeacher()))
        <a href="/panel/store/products/new" class="lw-btn lw-btn--cta lw-btn--block">
            <i data-feather="shopping-bag" width="18" height="18" aria-hidden="true"></i>
            <span>{{ trans('update.add_new_product') }}</span>
        </a>
    @endif

    @component('web.default.includes.lightway.panel', ['title' => trans('public.type')])
        @foreach(['virtual', 'physical'] as $typeOption)
            <label class="lw-check" for="filterTypes{{ $typeOption }}">
                <input type="checkbox" name="type[]" id="filterTypes{{ $typeOption }}" value="{{ $typeOption }}" @if(in_array($typeOption, (array) request()->get('type', []))) checked @endif>
                <span>{{ trans('update.product_type_' . $typeOption) }}</span>
            </label>
        @endforeach
    @endcomponent

    @component('web.default.includes.lightway.panel', ['title' => trans('update.options')])
        <label class="lw-check" for="filterOptionsOnlyAvailableProducts">
            <input type="checkbox" name="options[]" id="filterOptionsOnlyAvailableProducts" value="only_available" @if(in_array('only_available', (array) request()->get('options', []))) checked @endif>
            <span>{{ trans('update.only_available_products') }}</span>
        </label>

        <label class="lw-check" for="filterOptionsWithPoint">
            <input type="checkbox" name="options[]" id="filterOptionsWithPoint" value="with_point" @if(in_array('with_point', (array) request()->get('options', []))) checked @endif>
            <span>{{ trans('update.products_with_points') }}</span>
        </label>
    @endcomponent

    @if(!empty($productCategories))
        @if(!empty($selectedCategory))
            <input type="hidden" name="category_id" value="{{ $selectedCategory->id }}">
        @endif

        @component('web.default.includes.lightway.panel', ['title' => trans('categories.categories')])
            <nav aria-label="{{ trans('categories.categories') }}">
                @foreach($productCategories as $productCategory)
                    @if(!empty($productCategory->subCategories) and count($productCategory->subCategories))
                        <span class="lw-panel-group">{{ $productCategory->title }}</span>

                        @foreach($productCategory->subCategories as $subCategory)
                            @php $isSelected = (!empty($selectedCategory) and $selectedCategory->id == $subCategory->id); @endphp
                            <a href="{{ $subCategory->getUrl() }}" class="lw-panel-link lw-panel-link--sub {{ $isSelected ? 'is-active' : '' }}" @if($isSelected) aria-current="page" @endif dir="auto">{{ $subCategory->title }}</a>
                        @endforeach
                    @else
                        @php $isSelected = (!empty($selectedCategory) and $selectedCategory->id == $productCategory->id); @endphp
                        <a href="{{ $productCategory->getUrl() }}" class="lw-panel-link {{ $isSelected ? 'is-active' : '' }}" @if($isSelected) aria-current="page" @endif dir="auto">{{ $productCategory->title }}</a>
                    @endif
                @endforeach
            </nav>
        @endcomponent
    @endif

    @if(!empty($selectedCategory) and !empty($selectedCategory->filters) and count($selectedCategory->filters))
        @foreach($selectedCategory->filters as $filter)
            @component('web.default.includes.lightway.panel', ['title' => $filter->title])
                @foreach(($filter->options ?? []) as $option)
                    <label class="lw-check" for="filterLanguage{{ $option->id }}">
                        <input type="checkbox" name="filter_option[]" id="filterLanguage{{ $option->id }}" value="{{ $option->id }}" @if(in_array($option->id, (array) request()->get('filter_option', []))) checked @endif>
                        <span>{{ $option->title }}</span>
                    </label>
                @endforeach
            @endcomponent
        @endforeach
    @endif

    <button type="submit" class="lw-btn lw-btn--dark lw-btn--block">{{ trans('site.filter_items') }}</button>
@endcomponent
