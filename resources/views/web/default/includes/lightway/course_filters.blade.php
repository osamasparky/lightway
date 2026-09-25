{{--
    Courses sidebar: type[] / filter_option[] / moreOptions[] checkboxes (same names and values as the
    core views) plus category links (/classes has no category filter, so categories link to their pages).
    Params:
      $typeOptions      default ['bundle','webinar','course','text_lesson']
      $moreOptionsList  default ['subscribe','certificate_included','with_quiz','featured']
      $filterCategory   category whose ->filters are shown as filter_option[] blocks (categories page)
      $activeCategoryId highlights the current category link
--}}
@php
    $typeOptions = $typeOptions ?? ['bundle', 'webinar', 'course', 'text_lesson'];
    $moreOptionsList = $moreOptionsList ?? ['subscribe', 'certificate_included', 'with_quiz', 'featured'];
@endphp

@component('web.default.includes.lightway.filters_sidebar', ['id' => 'lwCourseFilters', 'label' => trans('home.lw_filters')])
    @component('web.default.includes.lightway.panel', ['title' => trans('public.type')])
        @foreach($typeOptions as $typeOption)
            <label class="lw-check" for="filterLanguage{{ $typeOption }}">
                <input type="checkbox" name="type[]" id="filterLanguage{{ $typeOption }}" value="{{ $typeOption }}" @if(in_array($typeOption, (array) request()->get('type', []))) checked @endif>
                <span>{{ $typeOption == 'bundle' ? trans('update.bundle') : trans('webinars.' . $typeOption) }}</span>
            </label>
        @endforeach
    @endcomponent

    @if(!empty($filterCategory) and !empty($filterCategory->filters))
        @foreach($filterCategory->filters as $filter)
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

    @if(!empty($categories) and count($categories))
        @component('web.default.includes.lightway.panel', ['title' => trans('categories.categories')])
            <nav aria-label="{{ trans('categories.categories') }}">
                @foreach($categories as $category)
                    <a href="{{ $category->getUrl() }}" class="lw-panel-link {{ (!empty($activeCategoryId) and $activeCategoryId == $category->id) ? 'is-active' : '' }}" @if(!empty($activeCategoryId) and $activeCategoryId == $category->id) aria-current="page" @endif>{{ $category->title }}</a>

                    @if(!empty($category->subCategories) and count($category->subCategories))
                        @foreach($category->subCategories as $subCategory)
                            <a href="{{ $subCategory->getUrl() }}" class="lw-panel-link lw-panel-link--sub {{ (!empty($activeCategoryId) and $activeCategoryId == $subCategory->id) ? 'is-active' : '' }}" @if(!empty($activeCategoryId) and $activeCategoryId == $subCategory->id) aria-current="page" @endif>{{ $subCategory->title }}</a>
                        @endforeach
                    @endif
                @endforeach
            </nav>
        @endcomponent
    @endif

    @component('web.default.includes.lightway.panel', ['title' => trans('site.more_options')])
        @foreach($moreOptionsList as $moreOption)
            <label class="lw-check" for="filterLanguage{{ $moreOption }}">
                <input type="checkbox" name="moreOptions[]" id="filterLanguage{{ $moreOption }}" value="{{ $moreOption }}" @if(in_array($moreOption, (array) request()->get('moreOptions', []))) checked @endif>
                <span>{{ trans('webinars.show_only_' . $moreOption) }}</span>
            </label>
        @endforeach
    @endcomponent

    <button type="submit" class="lw-btn lw-btn--dark lw-btn--block">{{ trans('site.filter_items') }}</button>
@endcomponent
