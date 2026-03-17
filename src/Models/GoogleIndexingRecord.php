<?php

declare(strict_types=1);

namespace Elfeffe\LaravelGoogleIndexing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GoogleIndexingRecord extends Model
{
    /**
     * Indicates if all mass assignment is allowed.
     *
     * @var array
     */
    protected $guarded = [];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'sent_at' => 'datetime',
        'response_data' => 'array',
    ];
    
    /**
     * The possible statuses for an indexing record.
     */
    public const STATUS_SUCCESS = 'success';
    public const STATUS_QUOTA_EXCEEDED = 'quota_exceeded';
    
    /**
     * Get the parent indexable model (if applicable).
     */
    public function indexable(): MorphTo
    {
        return $this->morphTo();
    }
    
    /**
     * Scope a query to only include successful indexing records.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }
    
    /**
     * Scope a query to only include failed indexing records.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
    
    /**
     * Mark record as successful.
     */
    public function markAsSuccess(array $responseData = null): self
    {
        $this->status = self::STATUS_SUCCESS;
        $this->sent_at = now();
        if ($responseData) {
            $this->response_data = $responseData;
        }
        $this->save();
        
        return $this;
    }
    
    /**
     * Mark record as failed.
     */
    public function markAsFailed(string $errorMessage = null, array $responseData = null): self
    {
        $this->status = 'failed';
        $this->error_message = $errorMessage;
        if ($responseData) {
            $this->response_data = $responseData;
        }
        $this->save();
        
        return $this;
    }
} 
