@extends('web.default.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/default/vendors/daterangepicker/daterangepicker.min.css">
@endpush

@section('content')
    @include('web.default.includes.lightway.banner', [
        'title' => $form->title,
        'breadcrumbs' => [['title' => $form->title]],
    ])

    <div class="ms-container lw-page">
        <div class="lw-form-card lw-form lw-custom-form">
            @yield("formContent")
        </div>
    </div>
@endsection

@push('scripts_bottom')
    <script src="/assets/default/vendors/daterangepicker/daterangepicker.min.js"></script>
    <script src="/vendor/laravel-filemanager/js/stand-alone-button.js"></script>
    <script src="/assets/default/js/parts/forms.min.js"></script>
@endpush
