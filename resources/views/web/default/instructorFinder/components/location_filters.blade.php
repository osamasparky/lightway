{{-- Location selects (names used by get-regions.min.js). --}}
@component('web.default.includes.lightway.panel', ['title' => trans('update.location')])
    <div class="lw-select-stack">
        <label for="lwCountry" class="sr-only">{{ trans('update.country') }}</label>
        <select name="country_id" id="lwCountry" class="lw-select lw-select--block">
            <option value="">{{ trans('update.select_country') }}</option>
            @if(!empty($countries))
                @foreach($countries as $country)
                    <option value="{{ $country->id }}" {{ (request()->get('country_id') == $country->id) ? 'selected' : '' }}>{{ $country->title }}</option>
                @endforeach
            @endif
        </select>

        <label for="lwProvince" class="sr-only">{{ trans('update.province') }}</label>
        <select name="province_id" id="lwProvince" class="lw-select lw-select--block" {{ empty($provinces) ? 'disabled' : '' }}>
            <option value="">{{ trans('update.select_province') }}</option>
            @if(!empty($provinces))
                @foreach($provinces as $province)
                    <option value="{{ $province->id }}" {{ (request()->get('province_id') == $province->id) ? 'selected' : '' }}>{{ $province->title }}</option>
                @endforeach
            @endif
        </select>

        <label for="lwCity" class="sr-only">{{ trans('update.city') }}</label>
        <select name="city_id" id="lwCity" class="lw-select lw-select--block" {{ empty($cities) ? 'disabled' : '' }}>
            <option value="">{{ trans('update.select_city') }}</option>
            @if(!empty($cities))
                @foreach($cities as $city)
                    <option value="{{ $city->id }}" {{ (request()->get('city_id') == $city->id) ? 'selected' : '' }}>{{ $city->title }}</option>
                @endforeach
            @endif
        </select>

        <label for="lwDistrict" class="sr-only">{{ trans('update.district') }}</label>
        <select name="district_id" id="lwDistrict" class="lw-select lw-select--block" {{ empty($districts) ? 'disabled' : '' }}>
            <option value="">{{ trans('update.select_district') }}</option>
            @if(!empty($districts))
                @foreach($districts as $district)
                    <option value="{{ $district->id }}" {{ (request()->get('district_id') == $district->id) ? 'selected' : '' }}>{{ $district->title }}</option>
                @endforeach
            @endif
        </select>
    </div>
@endcomponent
