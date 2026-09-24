{{--
    Arch image frame: orange outer border, cream gap, blue inner border with an arched top.
    Params: $src, $alt, $class (optional modifier, e.g. 'lw-arch--course'), $lazy (default true)
--}}
<span class="lw-arch {{ $class ?? '' }}">
    <span class="lw-arch__clip">
        <img src="{{ $src }}" alt="{{ $alt ?? '' }}" class="lw-arch__img" @if($lazy ?? true) loading="lazy" @endif>
    </span>
</span>
