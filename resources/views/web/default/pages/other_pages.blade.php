@extends(getTemplate().'.layouts.app')

@section('content')
    @include('web.default.includes.lightway.banner', [
        'title' => $page->title,
        'breadcrumbs' => [['title' => $page->title]],
    ])

    <div class="ms-container lw-page">
        <article class="lw-page-content">
            <div class="lw-prose lw-prose--page post-show" dir="auto">
                {!! nl2br($page->content) !!}
            </div>

            @if(!empty($generalSettings['logo']))
                <div class="lw-page-content__aside" aria-hidden="true">
                    <span class="lw-arch lw-arch--logo">
                        <span class="lw-arch__clip">
                            <img src="{{ $generalSettings['logo'] }}" alt="" class="lw-arch__img">
                        </span>
                    </span>
                </div>
            @endif
        </article>
    </div>
@endsection

@push('scripts_bottom')

@endpush
