<?php

namespace App\Models\Localization;

use Illuminate\Database\Eloquent\Model;

class GlossaryTerm extends Model
{
    protected $table = 'translation_glossary';
    protected $guarded = ['id'];

    /** Terms for one target language: language-specific ones plus the shared ones. */
    public function scopeForLocale($query, string $locale)
    {
        return $query->where(function ($q) use ($locale) {
            $q->whereNull('locale')->orWhere('locale', $locale);
        });
    }

    public function keepsOriginal(): bool
    {
        return $this->translation === null or trim($this->translation) === '';
    }
}
