<?php

namespace Platform\Correspondence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Traits\Encryptable;
use Platform\ActivityLog\Traits\LogsActivity;
use Symfony\Component\Uid\UuidV7;

class CorrespondenceItem extends Model
{
    use SoftDeletes, LogsActivity, Encryptable;

    protected $table = 'correspondence_items';

    protected $encryptable = [
        'sender_name' => 'string',
        'sender_email' => 'string',
        'recipient_name' => 'string',
        'recipient_email' => 'string',
        'body_text' => 'string',
        'body_html' => 'string',
        'metadata' => 'json',
    ];

    protected $fillable = [
        'uuid',
        'team_id',
        'thread_id',
        'type',
        'status',
        'direction',
        'sender_name',
        'sender_email',
        'recipient_name',
        'recipient_email',
        'body_text',
        'body_html',
        'metadata',
        'provider',
        'provider_id',
        'correspondence_date',
        'is_read',
        'created_by_user_id',
    ];

    protected $casts = [
        'correspondence_date' => 'date',
        'is_read' => 'boolean',
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

        static::created(function (self $model) {
            $model->thread?->updateDenormalized();
        });

        static::deleted(function (self $model) {
            $model->thread?->updateDenormalized();
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

    public function thread(): BelongsTo
    {
        return $this->belongsTo(CorrespondenceThread::class, 'thread_id');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeEmails($query)
    {
        return $query->where('type', 'email');
    }

    public function scopeLetters($query)
    {
        return $query->where('type', 'letter');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
