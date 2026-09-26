<?php

namespace App\Models\Localization;

use Illuminate\Database\Eloquent\Model;

/**
 * A terminology rule for AI translation.
 *
 *  preferred         use `translation` for `term`
 *  technical         same as preferred, for technical vocabulary
 *  context           use `translation` only in the given `context` (e.g. "checkout")
 *  do_not_translate  keep `term` as written
 *  brand             brand / product name: keep `term` as written
 *  forbidden         never use `translation` as the rendering of `term`
 */
class GlossaryTerm extends Model
{
    public const TYPE_PREFERRED = 'preferred';
    public const TYPE_TECHNICAL = 'technical';
    public const TYPE_CONTEXT = 'context';
    public const TYPE_DO_NOT_TRANSLATE = 'do_not_translate';
    public const TYPE_BRAND = 'brand';
    public const TYPE_FORBIDDEN = 'forbidden';

    public const TYPES = [
        self::TYPE_PREFERRED, self::TYPE_TECHNICAL, self::TYPE_CONTEXT,
        self::TYPE_DO_NOT_TRANSLATE, self::TYPE_BRAND, self::TYPE_FORBIDDEN,
    ];

    protected $table = 'translation_glossary';
    protected $guarded = ['id'];

    protected $casts = [
        'active' => 'boolean',
    ];

    /** Active terms for one target language: language-specific ones plus the shared ones. */
    public function scopeForLocale($query, string $locale)
    {
        return $query->where('active', true)->where(function ($q) use ($locale) {
            $q->whereNull('locale')->orWhere('locale', $locale);
        });
    }

    public function keepsOriginal(): bool
    {
        return in_array($this->type, [self::TYPE_DO_NOT_TRANSLATE, self::TYPE_BRAND])
            or ($this->type !== self::TYPE_FORBIDDEN and trim((string)$this->translation) === '');
    }
}
