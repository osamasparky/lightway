{{-- Star rating line. Params: $rate (0–5), $showValue (default true), $emptyText (optional text when rate is 0) --}}
@php
    $rate = (float)($rate ?? 0);
    $roundedRate = (int)round($rate);
@endphp

@if($rate > 0)
    <span class="lw-stars" aria-label="{{ $rate }} / 5">
        <span aria-hidden="true">{{ str_repeat('★', $roundedRate) }}<span class="lw-stars__empty">{{ str_repeat('★', 5 - $roundedRate) }}</span></span>
        @if($showValue ?? true)
            <strong>{{ $rate }}</strong>
        @endif
    </span>
@elseif(!empty($emptyText))
    <span class="lw-stars lw-stars--none">{{ $emptyText }}</span>
@endif
