<?php

namespace App\Http\Controllers\Admin\Localization;

use App\Http\Controllers\Controller;
use App\Services\Localization\LanguageRegistry;

abstract class LocalizationController extends Controller
{
    protected function language(string $locale): array
    {
        $language = app(LanguageRegistry::class)->find($locale);
        abort_unless($language, 404);

        return $language;
    }

    /** Session toast for the admin layout (which prints it inside a JS string, so no quotes/newlines). */
    protected function toast(string $message, bool $success = true): array
    {
        return ['toast' => [
            'title' => $success ? trans('public.request_success') : trans('public.request_failed'),
            'msg' => str_replace(["'", '"', "\n", "\r", '\\'], ['’', '”', ' ', ' ', '/'], $message),
            'status' => $success ? 'success' : 'error',
        ]];
    }

    protected function url(string $path = ''): string
    {
        return getAdminPanelUrl('/localization' . $path);
    }
}
