@php
    $currentLocale = mb_strtolower(request()->get('locale', app()->getLocale()));
    $templateBody = (!empty($template) and !empty($template->translate($currentLocale)) and !empty($template->translate($currentLocale)->body)) ? $template->translate($currentLocale)->body : (!empty($template->body) ? $template->body : '');
@endphp

<div class="card">
    <div class="card-body">
        <p class="text-muted mb-1 px-2">{{ trans('update.certificate_template_box_hint') }}</p>

        <div id="certificateTemplateContainer" class="d-flex justify-content-center">
            @if(!empty($templateBody))
                {!! $templateBody !!}
            @else
                <div class="certificate-template-container"></div>
            @endif
        </div>
    </div>
</div>
