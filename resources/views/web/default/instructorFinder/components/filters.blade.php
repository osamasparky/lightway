{{-- Finder filters (same names/values as before). Chip radios replace the selects/segmented radios. --}}
@php
    $chipGroups = [
        [
            'title' => trans('update.student_level'), 'name' => 'level_of_training',
            'options' => ['' => trans('update.not_preferenced'), 'beginner' => trans('update.beginner'), 'middle' => trans('update.middle'), 'expert' => trans('update.expert')],
        ],
        [
            'title' => trans('update.instructor_gender'), 'name' => 'gender',
            'options' => ['' => trans('update.not_preferenced'), 'man' => trans('update.man'), 'woman' => trans('update.woman')],
        ],
        [
            'title' => trans('update.instructor_type'), 'name' => 'role',
            'options' => ['' => trans('update.not_preferenced'), \App\Models\Role::$teacher => trans('public.instructor'), \App\Models\Role::$organization => trans('home.organization')],
        ],
        [
            'title' => trans('update.meeting_type'), 'name' => 'meeting_type',
            'options' => ['all' => trans('public.all'), 'in_person' => trans('update.in_person'), 'online' => trans('update.online')],
        ],
        [
            'title' => trans('update.population'), 'name' => 'population',
            'options' => ['all' => trans('public.all'), 'single' => trans('update.single'), 'group' => trans('update.group')],
        ],
    ];
@endphp

@component('web.default.includes.lightway.panel', ['title' => trans('public.category')])
    <label for="category_id" class="sr-only">{{ trans('public.category') }}</label>
    <select name="category_id" id="category_id" class="lw-select lw-select--block">
        <option value="">{{ trans('webinars.select_category') }}</option>

        @if(!empty($categories))
            @foreach($categories as $category)
                @if(!empty($category->subCategories) and count($category->subCategories))
                    <optgroup label="{{ $category->title }}">
                        @foreach($category->subCategories as $subCategory)
                            <option value="{{ $subCategory->id }}" @if(request()->get('category_id') == $subCategory->id) selected @endif>{{ $subCategory->title }}</option>
                        @endforeach
                    </optgroup>
                @else
                    <option value="{{ $category->id }}" @if(request()->get('category_id') == $category->id) selected @endif>{{ $category->title }}</option>
                @endif
            @endforeach
        @endif
    </select>
@endcomponent

@foreach($chipGroups as $group)
    @php
        $current = (string) request()->get($group['name'], '');
        $firstKey = array_key_first($group['options']);
    @endphp
    @component('web.default.includes.lightway.panel', ['title' => $group['title']])
        <div class="lw-chips" role="radiogroup" aria-label="{{ $group['title'] }}">
            @foreach($group['options'] as $value => $label)
                @php
                    $chipId = 'lwf_' . $group['name'] . '_' . ($value === '' ? 'none' : $value);
                    $isChecked = ($current === (string) $value) or ($current === '' and (string) $value === (string) $firstKey);
                @endphp
                <label class="lw-chip" for="{{ $chipId }}">
                    <input type="radio" name="{{ $group['name'] }}" value="{{ $value }}" id="{{ $chipId }}" @if($isChecked) checked @endif>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    @endcomponent
@endforeach

@component('web.default.includes.lightway.panel', ['title' => trans('update.price_range')])
    <div class="range wrunner-value-bottom lw-range" id="priceRange" data-minLimit="0" data-maxLimit="1000">
        <input type="hidden" name="min_price" value="{{ request()->get('min_price') ?? null }}">
        <input type="hidden" name="max_price" value="{{ request()->get('max_price') ?? null }}">
    </div>
@endcomponent

@component('web.default.includes.lightway.panel', ['title' => trans('update.instructor_age')])
    <div class="range wrunner-value-bottom lw-range" id="instructorAgeRange" data-minLimit="0" data-maxLimit="100">
        <input type="hidden" name="min_age" value="{{ request()->get('min_age') ?? null }}">
        <input type="hidden" name="max_age" value="{{ request()->get('max_age') ?? null }}">
    </div>
@endcomponent
