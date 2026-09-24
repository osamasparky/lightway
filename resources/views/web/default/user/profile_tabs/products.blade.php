@if(!empty($user->products) and !$user->products->isEmpty())
    <div class="lw-grid">
        @foreach($user->products as $product)
            @include('web.default.includes.lightway.product_card', ['product' => $product])
        @endforeach
    </div>
@else
    <div class="lw-empty">
        @include(getTemplate() . '.includes.no-result',[
            'file_name' => 'webinar.png',
            'title' => trans('update.instructor_not_have_products'),
            'hint' => '',
        ])
    </div>
@endif
