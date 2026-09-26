<?php

namespace App\Models\Localization;

use Illuminate\Database\Eloquent\Model;

class TranslationJobItem extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const MAX_ATTEMPTS = 3;

    protected $table = 'translation_job_items';
    protected $guarded = ['id'];
}
