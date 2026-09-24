@php
    $productBadges = !empty($productBadges) ? $productBadges : \App\Models\ProductBadge::query()->with('contents')->get();
    $targetId = !empty($itemTarget) ? $itemTarget->id : null;
    $targetType = !empty($itemTarget) ? $itemTarget->getMorphClass() : null;
@endphp

@if($productBadges->isNotEmpty())
    <div class="form-group mt-15">
        <label class="input-label d-block">{{ trans('update.select_badges') }}</label>

        <select name="product_badges[]" multiple class="form-control select2"
                data-placeholder="{{ trans('update.select_badges') }}"
        >
            @foreach($productBadges as $productBadge)
                @php
                    $selected = $productBadge->contents->first(function($c) use ($targetId, $targetType) {
                        return $c->targetable_id == $targetId && $c->targetable_type == $targetType;
                    });
                @endphp

                <option value="{{ $productBadge->id }}" {{ !empty($selected) ? 'selected' : '' }}>{{ $productBadge->title }}</option>
            @endforeach
        </select>

        <div class="text-muted text-small mt-1">{{ trans('update.select_badges_hint') }}</div>
    </div>
@endif
