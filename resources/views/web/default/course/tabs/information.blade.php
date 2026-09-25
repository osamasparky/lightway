@php
    $learningMaterialsExtraDescription = !empty($course->webinarExtraDescription) ? $course->webinarExtraDescription->where('type','learning_materials') : null;
    $companyLogosExtraDescription = !empty($course->webinarExtraDescription) ? $course->webinarExtraDescription->where('type','company_logos') : null;
    $requirementsExtraDescription = !empty($course->webinarExtraDescription) ? $course->webinarExtraDescription->where('type','requirements') : null;
@endphp

<div class="lw-stack">
    {{-- Installments --}}
    @if(!empty($installments) and count($installments) and getInstallmentsSettings('installment_plans_position') == 'top_of_page')
        @foreach($installments as $installmentRow)
            @include('web.default.installment.card',['installment' => $installmentRow, 'itemPrice' => $course->getPrice(), 'itemId' => $course->id, 'itemType' => 'course'])
        @endforeach
    @endif

    @if(!empty($learningMaterialsExtraDescription) and count($learningMaterialsExtraDescription))
        @component('web.default.includes.lightway.panel', ['title' => trans('update.what_you_will_learn'), 'class' => 'lw-panel--pale'])
            <ul class="lw-checklist">
                @foreach($learningMaterialsExtraDescription as $learningMaterial)
                    <li><i data-feather="check" width="18" height="18" aria-hidden="true"></i><span dir="auto">{{ $learningMaterial->value }}</span></li>
                @endforeach
            </ul>
        @endcomponent
    @endif

    {{-- course description --}}
    @if($course->description)
        @component('web.default.includes.lightway.panel', ['title' => trans('product.Webinar_description'), 'tag' => 'h2'])
            <div class="lw-prose course-description" dir="auto">
                {!! nl2br($course->description) !!}
            </div>
        @endcomponent
    @endif

    @if(!empty($companyLogosExtraDescription) and count($companyLogosExtraDescription))
        @component('web.default.includes.lightway.panel', ['title' => trans('update.suggested_by_top_companies')])
            <p class="lw-muted">{{ trans('update.suggested_by_top_companies_hint') }}</p>
            <div class="lw-logos">
                @foreach($companyLogosExtraDescription as $companyLogo)
                    <img loading="lazy" src="{{ $companyLogo->value }}" class="webinar-extra-description-company-logos" alt="{{ trans('update.company_logos') }}">
                @endforeach
            </div>
        @endcomponent
    @endif

    @if(!empty($requirementsExtraDescription) and count($requirementsExtraDescription))
        @component('web.default.includes.lightway.panel', ['title' => trans('update.requirements')])
            <ul class="lw-checklist">
                @foreach($requirementsExtraDescription as $requirementExtraDescription)
                    <li><i data-feather="check" width="18" height="18" aria-hidden="true"></i><span dir="auto">{{ $requirementExtraDescription->value }}</span></li>
                @endforeach
            </ul>
        @endcomponent
    @endif

    {{-- course prerequisites --}}
    @if(!empty($course->prerequisites) and $course->prerequisites->count() > 0)
        @component('web.default.includes.lightway.panel', ['title' => trans('public.prerequisites'), 'tag' => 'h2'])
            <div class="lw-list">
                @foreach($course->prerequisites as $prerequisite)
                    @if($prerequisite->prerequisiteWebinar)
                        @include('web.default.includes.lightway.course_card', ['webinar' => $prerequisite->prerequisiteWebinar, 'variant' => 'list'])
                    @endif
                @endforeach
            </div>
        @endcomponent
    @endif

    {{-- Related courses --}}
    @if(!empty($course->relatedCourses) and $course->relatedCourses->count() > 0)
        @component('web.default.includes.lightway.panel', ['title' => trans('update.related_courses'), 'tag' => 'h2'])
            <div class="lw-list">
                @foreach($course->relatedCourses as $relatedCourse)
                    @if($relatedCourse->course)
                        @include('web.default.includes.lightway.course_card', ['webinar' => $relatedCourse->course, 'variant' => 'list'])
                    @endif
                @endforeach
            </div>
        @endcomponent
    @endif

    {{-- course FAQ --}}
    @if(!empty($course->faqs) and $course->faqs->count() > 0)
        @component('web.default.includes.lightway.panel', ['title' => trans('public.faq'), 'tag' => 'h2'])
            <div class="lw-faq" id="accordion" role="tablist" aria-multiselectable="true">
                @foreach($course->faqs as $faq)
                    <div class="lw-faq__item">
                        <h3 class="lw-faq__q" role="tab" id="faq_{{ $faq->id }}">
                            <button type="button" data-target="#collapseFaq{{ $faq->id }}" aria-controls="collapseFaq{{ $faq->id }}" data-toggle="collapse" data-parent="#accordion" aria-expanded="false" class="collapsed">
                                <span dir="auto">{{ clean($faq->title,'title') }}</span>
                                <i class="collapse-chevron-icon" data-feather="chevron-down" width="20" height="20" aria-hidden="true"></i>
                            </button>
                        </h3>
                        <div id="collapseFaq{{ $faq->id }}" aria-labelledby="faq_{{ $faq->id }}" class="collapse" role="tabpanel">
                            <div class="lw-faq__a" dir="auto">
                                {{ clean($faq->answer,'answer') }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endcomponent
    @endif

    {{-- Installments --}}
    @if(!empty($installments) and count($installments) and getInstallmentsSettings('installment_plans_position') == 'bottom_of_page')
        @foreach($installments as $installmentRow)
            @include('web.default.installment.card',['installment' => $installmentRow, 'itemPrice' => $course->getPrice(), 'itemId' => $course->id, 'itemType' => 'course'])
        @endforeach
    @endif

    {{-- course Comments --}}
    <div class="lw-panel lw-comments">
        @include('web.default.includes.comments',[
                'comments' => $course->comments,
                'inputName' => 'webinar_id',
                'inputValue' => $course->id
            ])
    </div>
</div>
