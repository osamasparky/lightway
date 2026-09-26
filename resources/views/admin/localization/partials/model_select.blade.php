{{-- Model dropdown (models from the last connection test) with an "Other" free-text option. --}}
@php
    $current = old($name, $value);
    $isCustom = $current !== '' && $current !== null && !in_array($current, $models);
@endphp
<div class="form-group">
    <label for="lz_{{ $name }}">{{ $label }}</label>
    <select id="lz_{{ $name }}" name="{{ $name }}" class="form-control js-lz-model @error($name) is-invalid @enderror" data-custom="#lz_{{ $name }}_custom">
        <option value="">{{ $emptyLabel ?? (count($models) ? trans('localization.choose_model') : trans('localization.test_to_load_models')) }}</option>
        @foreach($models as $model)
            <option value="{{ $model }}" @selected($current === $model)>{{ $model }}{{ \App\Services\Localization\AI\OpenAITranslationProvider::isSlowModel($model) ? ' — ' . trans('localization.slow_model') : '' }}</option>
        @endforeach
        <option value="__custom" @selected($isCustom)>{{ trans('localization.other_model') }}</option>
    </select>
    <input type="text" id="lz_{{ $name }}_custom" name="{{ $name }}_custom" value="{{ $isCustom ? $current : '' }}" class="form-control mt-2 js-lz-model-custom {{ $isCustom ? '' : 'd-none' }}" spellcheck="false" placeholder="{{ trans('localization.model_placeholder') }}" aria-label="{{ trans('localization.other_model') }}">
    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if(!empty($hint))<small class="form-text text-muted">{{ $hint }}</small>@endif
</div>
