<?php

namespace App\Models\Localization;

use Illuminate\Database\Eloquent\Model;

class TranslationSetting extends Model
{
    protected $table = 'translation_settings';
    protected $guarded = ['id'];
    protected $hidden = ['value'];
}
