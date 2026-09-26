<?php

namespace App\Models\Localization;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TranslationJob extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const SCOPE_MISSING = 'missing';
    public const SCOPE_ALL = 'all';
    public const SCOPE_OUTDATED = 'outdated';
    public const SCOPE_RETRANSLATE = 'retranslate';

    public const SCOPES = [self::SCOPE_MISSING, self::SCOPE_ALL, self::SCOPE_OUTDATED, self::SCOPE_RETRANSLATE];

    // economy: translate + validation; professional: + AI QA; premium: stronger model + AI QA + revision
    public const MODE_ECONOMY = 'economy';
    public const MODE_PROFESSIONAL = 'professional';
    public const MODE_PREMIUM = 'premium';

    public const MODES = [self::MODE_ECONOMY, self::MODE_PROFESSIONAL, self::MODE_PREMIUM];

    protected $table = 'translation_jobs';
    protected $guarded = ['id'];

    protected $casts = [
        'groups' => 'array',
        'publish_on_finish' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (self $job) {
            $job->uuid = $job->uuid ?: (string)Str::uuid();
        });
    }

    public function items()
    {
        return $this->hasMany(TranslationJobItem::class, 'translation_job_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function profile()
    {
        return $this->belongsTo(TranslationProfile::class, 'profile_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_PAUSED]);
    }

    public function usesAiQa(): bool
    {
        return in_array($this->quality_mode, [self::MODE_PROFESSIONAL, self::MODE_PREMIUM]);
    }

    public function progressPercent(): int
    {
        if ($this->total < 1) {
            return 0;
        }

        return (int)floor((($this->completed + $this->failed) / $this->total) * 100);
    }

    public function remaining(): int
    {
        return max(0, $this->total - $this->completed - $this->failed);
    }

    public function totalTokens(): int
    {
        return (int)$this->prompt_tokens + (int)$this->completion_tokens + (int)$this->qa_prompt_tokens + (int)$this->qa_completion_tokens;
    }

    /** Cost of the tokens the API reported, at the prices saved when the job started (null without prices). */
    public function actualCost(): ?float
    {
        if ($this->price_input === null or $this->price_output === null) {
            return null;
        }

        $qaIn = $this->qa_price_input ?? $this->price_input;
        $qaOut = $this->qa_price_output ?? $this->price_output;

        return round(
            $this->prompt_tokens / 1_000_000 * (float)$this->price_input
            + $this->completion_tokens / 1_000_000 * (float)$this->price_output
            + $this->qa_prompt_tokens / 1_000_000 * (float)$qaIn
            + $this->qa_completion_tokens / 1_000_000 * (float)$qaOut,
            4
        );
    }

    public function durationSeconds(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        return (int)$this->started_at->diffInSeconds($this->finished_at ?? now());
    }
}
