@php
    $lzTextKeys = [
        'saved', 'save_failed', 'placeholder_force', 'ai_working', 'ai_failed', 'copied', 'loading', 'none',
        'test_ok', 'test_failed', 'models_found', 'strings', 'no_cost', 'used_in', 'placeholders',
        'other_languages', 'file', 'source_text',
    ];
    $lzText = [];
    foreach ($lzTextKeys as $lzKey) {
        $lzText[$lzKey] = trans('localization.' . $lzKey);
    }
    $lzText['confirm'] = trans('localization.are_you_sure');
    $lzText['reviewed'] = trans('localization.marked_reviewed');
    $lzText['not_used'] = trans('localization.not_found_in_code');
    $lzText['over_limit'] = trans('localization.err_over_limit', ['limit' => '__LIMIT__']);

    $lzClientConfig = array_merge(['base' => getAdminPanelUrl('/localization'), 'text' => $lzText], $lzConfig ?? []);
@endphp
<script>
    window.lzConfig = {!! json_encode($lzClientConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!};
</script>
<script src="/assets/admin/js/localization.js?v={{ @filemtime(public_path('assets/admin/js/localization.js')) }}"></script>
