<?php

namespace App\Models\Localization;

use Illuminate\Database\Eloquent\Model;

/**
 * How one target language is translated: locale variant, tone, audience, rules,
 * cultural notes, quality mode and model overrides. Empty fields fall back to the
 * global Translation Settings (see TranslationProfiles::resolve()).
 */
class TranslationProfile extends Model
{
    protected $table = 'translation_profiles';
    protected $guarded = ['id'];

    protected $casts = [
        'active' => 'boolean',
    ];
}
