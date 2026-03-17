<?php

declare(strict_types=1);

namespace Elfeffe\LaravelGoogleIndexing\Traits;

use Elfeffe\LaravelGoogleIndexing\Models\GoogleIndexingRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

trait GoogleIndexable
{
    /**
     * Boot the trait.
     */
    public static function bootGoogleIndexable(): void
    {
        static::deleting(function ($model) {
            // Delete associated indexing records when the model is deleted
            // This includes success and quota_marker records
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }
            
            $model->googleIndexingRecords()->delete();
        });
    }
    
    /**
     * Get the Google indexing records for this model.
     */
    public function googleIndexingRecords(): MorphMany
    {
        return $this->morphMany(GoogleIndexingRecord::class, 'indexable');
    }
    
    /**
     * Get the latest successful Google indexing record for this model.
     */
    public function getLatestSuccessfulGoogleIndexingRecord()
    {
        return $this->googleIndexingRecords()
            ->where('status', 'success')
            ->latest('sent_at')
            ->first();
    }
    
    /**
     * Check if the model has been successfully indexed by Google.
     */
    public function hasBeenGoogleIndexed(): bool
    {
        return $this->googleIndexingRecords()
            ->where('status', 'success')
            ->exists();
    }
    
    /**
     * Check if the model has been indexed by Google within the given timeframe.
     */
    public function hasBeenGoogleIndexedWithinDays(int $days = 30): bool
    {
        return $this->googleIndexingRecords()
            ->where('status', 'success')
            ->where('sent_at', '>=', now()->subDays($days))
            ->exists();
    }
    
    /**
     * Get the count of successful Google indexing requests sent today.
     */
    public static function getTodayIndexingCount(): int
    {
        return GoogleIndexingRecord::where('status', 'success')
            ->whereDate('sent_at', Carbon::today())
            ->count();
    }
    
    /**
     * Get the count of successful Google indexing requests sent in the last 24 hours.
     */
    public static function getLast24HoursIndexingCount(): int
    {
        return GoogleIndexingRecord::where('status', 'success')
            ->where('sent_at', '>=', Carbon::now()->subHours(24))
            ->count();
    }
    
    /**
     * Get the remaining Google indexing API quota for today.
     * 
     * @param int $dailyLimit The daily limit for the Google Indexing API (default: 200)
     * @return int The number of remaining requests allowed today
     */
    public static function getRemainingDailyQuota(int $dailyLimit = 200): int
    {
        // Check only the count of successful sends today
        $sentToday = self::getTodayIndexingCount();
        return max(0, $dailyLimit - $sentToday);
    }
    
    /**
     * Scope a query to only include models that need Google indexing.
     * This excludes models that have been successfully indexed within the specified days.
     */
    public function scopeNeedsGoogleIndexing(Builder $query, int $days = 30): Builder
    {
        // Get table name from the model using the trait
        $table = $this->getTable();
        
        // We need to use LEFT JOIN and WHERE IS NULL to find records that don't have
        // a successful indexing record within the timeframe
        return $query->leftJoin('google_indexing_records', function ($join) use ($table, $days) {
            $join->on('google_indexing_records.indexable_id', '=', "$table.id")
                ->where('google_indexing_records.indexable_type', '=', $this->getMorphClass())
                ->where('google_indexing_records.status', '=', 'success')
                ->where('google_indexing_records.sent_at', '>=', now()->subDays($days));
        })
        ->whereNull('google_indexing_records.id')
        // Make sure we only get the model fields, not the joined table fields
        ->select("$table.*");
    }
    
    /**
     * Mark the model as successfully indexed by Google.
     * Should only be called after a successful API call.
     */
    public function markAsGoogleIndexed(string $url, array $responseData = null): GoogleIndexingRecord
    {
        // Use updateOrCreate to avoid duplicate success records for the same model & url
        // Note: This assumes a model primarily uses one canonical URL for indexing.
        // If a model can have multiple indexed URLs, this logic might need adjustment.
        return $this->googleIndexingRecords()->updateOrCreate(
            ['url' => $url], // Find existing record by URL for this model
            [
                'status' => 'success',
                'sent_at' => now(),
                'response_data' => $responseData,
                'error_message' => null // Clear any previous error
            ]
        );
    }
    
    /**
     * Get the URL to be used for Google indexing.
     * This method should be overridden by the implementing model.
     */
    public function getGoogleIndexingUrl(): string
    {
        // Default implementation attempts to use the model's url attribute or method
        if (method_exists($this, 'getUrl')) {
            return $this->getUrl();
        }
        
        if (method_exists($this, 'url')) {
            return $this->url();
        }
        
        if (isset($this->url)) {
            return $this->url;
        }
        
        if (isset($this->full_slug)) {
            return url($this->full_slug);
        }
        
        throw new \LogicException(
            'GoogleIndexable trait requires a getGoogleIndexingUrl method to be implemented, or a url attribute/method.'
        );
    }
} 
