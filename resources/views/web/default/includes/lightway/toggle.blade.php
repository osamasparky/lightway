{{--
    Toggle switch built on a real checkbox (keeps form submission and query params).
    Params: $name, $label, $value (default 'on'), $checked (bool), $id (optional), $form (optional form id)
--}}
@php $toggleId = $id ?? ('lwToggle_' . preg_replace('/[^a-z0-9_]/i', '_', $name) . '_' . ($value ?? 'on')); @endphp

<label class="lw-toggle" for="{{ $toggleId }}">
    <input type="checkbox" role="switch" id="{{ $toggleId }}" name="{{ $name }}" value="{{ $value ?? 'on' }}" class="lw-toggle__input" @if(!empty($checked)) checked @endif @if(!empty($form)) form="{{ $form }}" @endif>
    <span class="lw-toggle__track" aria-hidden="true"><span class="lw-toggle__thumb"></span></span>
    <span class="lw-toggle__label">{{ $label }}</span>
</label>
