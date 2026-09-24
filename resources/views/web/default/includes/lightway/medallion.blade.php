{{--
    8-point star medallion (two squares rotated 45°) with a number in the middle.
    Params: $value, $label (optional, under the medallion), $tone ('blue'|'orange'|css colour), $size ('sm'|'md'|'lg')
--}}
@php
    $toneClass = in_array($tone ?? 'blue', ['blue', 'orange']) ? 'lw-medal--' . ($tone ?? 'blue') : '';
    $toneStyle = (!empty($tone) and !in_array($tone, ['blue', 'orange'])) ? '--lw-medal-tone: ' . $tone : '';
@endphp

<span class="lw-medal {{ $toneClass }} lw-medal--{{ $size ?? 'md' }}" @if($toneStyle) style="{{ $toneStyle }}" @endif>
    <span class="lw-medal__badge">
        <svg viewBox="0 0 96 96" aria-hidden="true" focusable="false">
            <rect x="18" y="18" width="60" height="60" class="lw-medal__shape"></rect>
            <rect x="18" y="18" width="60" height="60" transform="rotate(45 48 48)" class="lw-medal__shape"></rect>
            <circle cx="48" cy="48" r="26" class="lw-medal__disc"></circle>
        </svg>
        <strong class="lw-medal__value">{{ $value }}</strong>
    </span>

    @if(!empty($label))
        <span class="lw-medal__label">{{ $label }}</span>
    @endif
</span>
