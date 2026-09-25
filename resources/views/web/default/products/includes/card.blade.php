{{-- lightway --}}
{{-- Same card on the home page and in the store. --}}
@include('web.default.includes.lightway.product_card', ['product' => $product, 'isRewardProducts' => $isRewardProducts ?? null])
