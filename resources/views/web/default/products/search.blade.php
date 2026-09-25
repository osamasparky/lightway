@extends('web.default.layouts.app')

@section('content')
    @php
        $storeAction = (!empty($isRewardProducts) and $isRewardProducts) ? '/reward-products' : '/products';
        $storeCrumbs = [['title' => trans('update.products'), 'url' => $storeAction]];
        if (!empty($selectedCategory)) {
            $storeCrumbs[] = ['title' => $selectedCategory->title];
        }
    @endphp

    @include('web.default.includes.lightway.banner', [
        'title' => !empty($selectedCategory) ? $selectedCategory->title : trans('update.products'),
        'subtitle' => $productsCount . ' ' . trans('update.products'),
        'breadcrumbs' => !empty($selectedCategory) ? $storeCrumbs : [['title' => trans('update.products')]],
        'search' => ['action' => $storeAction, 'name' => 'search', 'placeholder' => trans('update.products_search_placeholder'), 'keep' => ['category_id', 'type', 'options', 'sort']],
    ])

    <div class="ms-container lw-page">
        <form action="{{ $storeAction }}" method="get" id="filtersForm" class="lw-with-sidebar">
            @if(request()->get('search'))
                <input type="hidden" name="search" value="{{ request()->get('search') }}">
            @endif

            @include('web.default.products.includes.right_filters')

            <div class="lw-stack">
                @include('web.default.products.includes.top_filters')

                @if($products->count())
                    <div class="lw-grid">
                        @foreach($products as $product)
                            @include('web.default.includes.lightway.product_card', ['product' => $product, 'isRewardProducts' => $isRewardProducts ?? false])
                        @endforeach
                    </div>
                @else
                    <div class="lw-empty">
                        @include(getTemplate() . '.includes.no-result', [
                            'file_name' => 'webinar.png',
                            'title' => trans('site.no_result_search'),
                            'hint' => trans('home.lw_try_other_filters'),
                        ])
                    </div>
                @endif

                {{ $products->appends(request()->input())->links('web.default.includes.lightway.pagination') }}
            </div>
        </form>
    </div>
@endsection

@push('scripts_bottom')
    <script src="/assets/default/js/parts/products_lists.min.js"></script>
@endpush
