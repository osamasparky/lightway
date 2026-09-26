@php($percent = max(0, min(100, (int)$percent)))
<div class="lz-progress" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ trans('localization.completion') }}">
    <div class="lz-progress__track">
        <div class="lz-progress__bar {{ $percent >= 100 ? 'is-complete' : ($percent < 50 ? 'is-low' : '') }}" style="width: {{ $percent }}%"></div>
    </div>
    <span class="lz-progress__value">{{ $percent }}%</span>
</div>
