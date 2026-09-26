<?php

namespace App\Models\Localization;

use App\User;
use Illuminate\Database\Eloquent\Model;

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
    public const SCOPE_RETRANSLATE = 'retranslate';

    protected $table = 'translation_jobs';
    protected $guarded = ['id'];

    protected $casts = [
        'groups' => 'array',
        'publish_on_finish' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(TranslationJobItem::class, 'translation_job_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_PAUSED]);
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
}
