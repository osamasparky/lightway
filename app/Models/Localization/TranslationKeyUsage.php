<?php

namespace App\Models\Localization;

use Illuminate\Database\Eloquent\Model;

class TranslationKeyUsage extends Model
{
    protected $table = 'translation_key_usages';
    protected $guarded = ['id'];
}
