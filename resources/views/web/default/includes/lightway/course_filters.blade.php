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
        @php
            // Collapsible like the instructors filter: closed until opened, or open on a category page.
            $activeCategoryTitle = null;
            $singleCategories = [];
            foreach ($categories as $category) {
                if (!empty($activeCategoryId) and $activeCategoryId == $category->id) {
                    $activeCategoryTitle = $category->title;
                }
                foreach (($category->subCategories ?? []) as $subCategory) {
                    if (!empty($activeCategoryId) and $activeCategoryId == $subCategory->id) {
                        $activeCategoryTitle = $subCategory->title;
                    }
                }
                if (empty($category->subCategories) or !count($category->subCategories)) {
                    $singleCategories[] = $category;
                }
            }
        @endphp
        <details class="lw-panel lw-chips-panel lw-cat-panel" @if($activeCategoryTitle) open @endif>
            <summary class="lw-panel__title">
                @include('web.default.includes.manuscript.star', ['size' => 16, 'dot' => '#FFFDF8'])
                <span>{{ trans('categories.categories') }}</span>
                @if($activeCategoryTitle)
                    <span class="lw-panel__extra">{{ $activeCategoryTitle }}</span>
                @endif
            </summary>

            <nav class="lw-cat-groups" aria-label="{{ trans('categories.categories') }}">
                @if(count($singleCategories))
                    <div class="lw-chips">
                        @foreach($singleCategories as $category)
                            <a href="{{ $category->getUrl() }}" class="lw-chip {{ (!empty($activeCategoryId) and $activeCategoryId == $category->id) ? 'is-active' : '' }}" @if(!empty($activeCategoryId) and $activeCategoryId == $category->id) aria-current="page" @endif><span>{{ $category->title }}</span></a>
                        @endforeach
                    </div>
                @endif

                @foreach($categories as $category)
                    @if(!empty($category->subCategories) and count($category->subCategories))
                        <div class="lw-cat-group">
                            <a href="{{ $category->getUrl() }}" class="lw-cat-group__title {{ (!empty($activeCategoryId) and $activeCategoryId == $category->id) ? 'is-active' : '' }}" @if(!empty($activeCategoryId) and $activeCategoryId == $category->id) aria-current="page" @endif>{{ $category->title }}</a>
                            <div class="lw-chips">
                                @foreach($category->subCategories as $subCategory)
                                    <a href="{{ $subCategory->getUrl() }}" class="lw-chip {{ (!empty($activeCategoryId) and $activeCategoryId == $subCategory->id) ? 'is-active' : '' }}" @if(!empty($activeCategoryId) and $activeCategoryId == $subCategory->id) aria-current="page" @endif><span>{{ $subCategory->title }}</span></a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </nav>
        </details>
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
