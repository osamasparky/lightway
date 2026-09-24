@php
    $time = time();
    if (!empty($itemTarget) && $itemTarget->relationLoaded('productBadgeContent')) {
        $productBadges = $itemTarget->productBadgeContent->filter(function($content) use ($time) {
            $badge = $content->badge;
            if (!$badge || !$badge->enable) return false;
            if (!empty($badge->start_at) && $badge->start_at >= $time) return false;
            if (!empty($badge->end_at) && $badge->end_at <= $time) return false;
            return true;
        });
    } elseif (!empty($itemTarget)) {
        $productBadges = $itemTarget->productBadgeContent()
            ->whereHas('badge', function ($query) use ($time) {
                $query->where('enable', true)
                    ->where(function ($query) use ($time) {
                        $query->whereNull('start_at')->orWhere('start_at', '<', $time);
                    })
                    ->where(function ($query) use ($time) {
                        $query->whereNull('end_at')->orWhere('end_at', '>', $time);
                    });
            })
            ->with(['badge.translations'])
            ->get();
    } else {
        $productBadges = collect();
    }
@endphp


@if($productBadges->isNotEmpty())
    @foreach($productBadges as $productBadge)
        <div class="badge d-flex align-items-center" style="color: {{ $productBadge->badge->color }}; background-color: {{ $productBadge->badge->background }}">
            @if(!empty($productBadge->badge->icon))
                <div class="size-32 mr-5">
                    <img loading="lazy" src="{{ $productBadge->badge->icon }}" alt="{{ $productBadge->badge->title }}" class="img-cover">
                </div>
            @endif

            <span class="">{{ $productBadge->badge->title }}</span>
        </div>
    @endforeach
@endif
