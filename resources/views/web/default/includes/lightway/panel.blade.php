{{--
    Card block with a star title and a dashed divider (sidebar filter blocks, reviews, FAQ, etc.).
    Usage:
        @component('web.default.includes.lightway.panel', ['title' => trans('public.type')])
            ... content ...
        @endcomponent
    Params: $title, $tag (heading tag, default h3), $class
--}}
@php $tag = $tag ?? 'h3'; @endphp

<div class="lw-panel {{ $class ?? '' }}">
    @if(!empty($title))
        <{{ $tag }} class="lw-panel__title">
            @include('web.default.includes.manuscript.star', ['size' => 16, 'dot' => '#FFFDF8'])
            <span>{{ $title }}</span>
            @if(!empty($titleExtra))
                <span class="lw-panel__extra">{{ $titleExtra }}</span>
            @endif
        </{{ $tag }}>
    @endif

    {{ $slot }}
</div>
