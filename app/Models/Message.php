<?php

namespace App\Models;

use App\Events\MessageRemoveChatUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'content',
        'user_id',
        'created_at',
        'updated_at',
        'is_deleted',
        'is_edited',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_deleted' => 'boolean',
        'is_edited' => 'boolean',
    ];

    protected $with = [
        'user',
        'files',
    ];

    protected $dispatchesEvents = [
        'deleting' => MessageRemoveChatUploadedFiles::class
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ChatUploadedFile::class);
    }
}
