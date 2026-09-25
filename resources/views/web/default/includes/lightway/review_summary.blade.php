{{--
    Review summary: big average inside an arched tile + per-criterion bars.
    Params:
      $rate    average (0–5)
      $count   number of reviews
      $rows    array of ['label' => .., 'value' => 0–5]   (optional)
--}}
@php
    $rate = (float)($rate ?? 0);
    $roundedRate = (int)round($rate);
@endphp

<div class="lw-review-summary">
    <div class="lw-review-summary__score">
        <strong class="lw-review-summary__value">{{ number_format($rate, 2) }}</strong>
        <span class="lw-stars lw-stars--lg" aria-label="{{ number_format($rate, 2) }} / 5">
            <span aria-hidden="true">{{ str_repeat('★', $roundedRate) }}<span class="lw-stars__empty">{{ str_repeat('★', 5 - $roundedRate) }}</span></span>
        </span>
        <span class="lw-review-summary__count">{{ trans('home.lw_reviews_count', ['count' => $count ?? 0]) }}</span>
    </div>

    @if(!empty($rows))
        <div class="lw-review-summary__rows">
            @foreach($rows as $row)
                @php $value = max(0, min(5, (float)$row['value'])); @endphp
                <div class="lw-review-summary__row">
                    <span class="lw-review-summary__label">{{ $row['label'] }}</span>
                    <span class="lw-review-summary__bar" role="img" aria-label="{{ $row['label'] }}: {{ $value }} / 5">
                        <span style="width: {{ $value * 20 }}%"></span>
                    </span>
                    <strong>{{ $value }}</strong>
                </div>
            @endforeach
        </div>
    @endif
</div>
