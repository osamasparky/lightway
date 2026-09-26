<?php

namespace App\Models\Localization;

use Barryvdh\TranslationManager\Models\Translation;

/**
 * A row of ltm_translations (the translation-manager working copy) with the
 * localization review columns. Same table and status constants as the package model.
 */
class TranslationEntry extends Translation
{
    public const REVIEW_MISSING = 'missing';
    public const REVIEW_TRANSLATED = 'translated';
    public const REVIEW_AI = 'ai_translated';
    public const REVIEW_NEEDS_REVIEW = 'needs_review';
    public const REVIEW_REVIEWED = 'reviewed';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_AI = 'ai';

    protected $guarded = ['id', 'key_hash', 'created_at', 'updated_at'];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public static function hashFor(string $group, string $key): string
    {
        return sha1($group . '|' . $key);
    }

    /** Display state: empty value is missing whatever the stored review status says. */
    public function getReviewStateAttribute(): string
    {
        if ($this->value === null or trim($this->value) === '') {
            return self::REVIEW_MISSING;
        }

        return $this->review_status ?: self::REVIEW_TRANSLATED;
    }
}
