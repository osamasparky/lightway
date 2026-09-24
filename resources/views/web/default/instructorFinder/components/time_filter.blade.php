@php
    $days = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    $requestDays = request()->get('day');
    if (!is_array($requestDays)) {
        $requestDays = [$requestDays];
    }
@endphp

@component('web.default.includes.lightway.panel', ['title' => trans('public.time')])
    <div class="lw-chips lw-chips--days">
        @foreach($days as $day)
            <label class="lw-chip" for="day_{{ $day }}">
                <input type="checkbox" name="day[]" value="{{ $day }}" id="day_{{ $day }}" {{ (in_array($day, $requestDays)) ? 'checked' : '' }}>
                <span>{{ trans('panel.' . $day) }}</span>
            </label>
        @endforeach
    </div>

    <span class="lw-range-label">{{ trans('update.time_range') }}</span>
    <div class="range wrunner-value-bottom lw-range" id="timeRangeInstructorPage" data-minLimit="0" data-maxLimit="23">
        <input type="hidden" name="min_time" value="{{ request()->get('min_time') ?? null }}">
        <input type="hidden" name="max_time" value="{{ request()->get('max_time') ?? null }}">
    </div>
@endcomponent
