<?php

namespace Platform\Correspondence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Traits\Encryptable;
use Platform\ActivityLog\Traits\LogsActivity;
use Symfony\Component\Uid\UuidV7;

class CorrespondenceThread extends Model
{
    use SoftDeletes, LogsActivity, Encryptable;

    protected $table = 'correspondence_threads';

    protected $encryptable = [
        'subject' => 'string',
    ];

    protected $fillable = [
        'uuid',
        'team_id',
        'subject',
        'subject_normalized',
        'status',
        'ms365_conversation_id',
        'item_count',
        'latest_item_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'item_count' => 'integer',
        'latest_item_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CorrespondenceItem::class, 'thread_id');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeInbox($query)
    {
        return $query->where('status', 'inbox');
    }

    public function scopeAssigned($query)
    {
        return $query->where('status', 'assigned');
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    public function updateDenormalized(): void
    {
        $this->update([
            'item_count' => $this->items()->count(),
            'latest_item_at' => $this->items()->max('correspondence_date') ?? $this->items()->max('created_at'),
        ]);
    }
}
