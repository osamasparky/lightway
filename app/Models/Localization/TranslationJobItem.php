<?php

namespace App\Models\Localization;

use Illuminate\Database\Eloquent\Model;

class TranslationJobItem extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    // How a done item was produced / stored
    public const OUTCOME_AI = 'ai';                 // AI translation saved
    public const OUTCOME_MEMORY = 'memory';         // reused an approved translation, no API call
    public const OUTCOME_SUGGESTION = 'suggestion'; // kept aside: the live text is human / approved
    public const OUTCOME_KEPT = 'kept';             // a person changed the string meanwhile

    public const MAX_ATTEMPTS = 3;

    protected $table = 'translation_job_items';
    protected $guarded = ['id'];

    protected $casts = [
        'qa_issues' => 'array',
    ];
}
