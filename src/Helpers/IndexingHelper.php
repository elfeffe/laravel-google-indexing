<?php

declare(strict_types=1);

namespace Elfeffe\LaravelGoogleIndexing\Helpers;

use Elfeffe\LaravelGoogleIndexing\Exceptions\GoogleQuotaExceededException;
use Elfeffe\LaravelGoogleIndexing\LaravelGoogleIndexing;
use Elfeffe\LaravelGoogleIndexing\Models\GoogleIndexingRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Helper class for direct interaction with Google Indexing API
 * Can be used independently without SlugRewrite or other packages
 */
class IndexingHelper
{
    /**
     * Google Indexing API daily quota limit
     */
    public const DAILY_QUOTA_LIMIT = 200;
    
    /**
     * @var LaravelGoogleIndexing
     */
    protected $indexingService;
    
    /**
     * Constructor
     */
    public function __construct(LaravelGoogleIndexing $indexingService)
    {
        $this->indexingService = $indexingService;
    }
    
    /**
     * Get an instance of the helper
     */
    public static function make(): self
    {
        return new self(new LaravelGoogleIndexing());
    }
    
    /**
     * Send a URL to be indexed by Google
     * 
     * @param string $url The URL to index
     * @param bool $checkExisting Whether to check if the URL is already indexed
     * @return array The result of the operation
     */
    public function indexUrl(string $url, bool $checkExisting = true): array
    {
        try {
            // Check for quota exceeded
            if ($this->isQuotaExceeded()) {
                return [
                    'success' => false,
                    'message' => 'Daily quota exceeded. Try again tomorrow.',
                    'quota_exceeded' => true
                ];
            }
            
            // Check if URL is already indexed
            if ($checkExisting) {
                try {
                    $metadata = $this->indexingService->status($url);
                    if (isset($metadata->latestUpdate) && $metadata->latestUpdate->url === $url) {
                        $this->recordUrlSuccessFromStatus($url, $metadata);
                        return [
                            'success' => true,
                            'message' => 'URL is already indexed',
                            'already_indexed' => true
                        ];
                    }
                } catch (GoogleQuotaExceededException $e) {
                    return [
                        'success' => false,
                        'message' => 'Quota exceeded during status check',
                        'quota_exceeded' => true,
                        'error' => $e->getMessage()
                    ];
                } catch (\Exception $e) {
                    // For other errors, log and continue with indexing attempt
                    Log::warning("Status check failed for URL: {$url}", ['error' => $e->getMessage()]);
                }
            }
            
            // Send the indexing request
            try {
                $this->indexingService->update($url);
                $this->recordUrlSuccess($url); // Record success only
                return [
                    'success' => true,
                    'message' => 'URL indexed successfully'
                ];
            } catch (GoogleQuotaExceededException $e) {
                return [
                    'success' => false,
                    'message' => 'Quota exceeded during indexing',
                    'quota_exceeded' => true,
                    'error' => $e->getMessage()
                ];
            } catch (\Exception $e) {
                // For other indexing errors
                return [
                    'success' => false,
                    'message' => 'Error indexing URL: ' . $e->getMessage(),
                    'error' => $e->getMessage()
                ];
            }
            
        } catch (\Exception $e) {
            // Catch any other unexpected exceptions during the whole process
            return [
                'success' => false,
                'message' => 'Unexpected error: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Index multiple URLs
     * 
     * @param array $urls Array of URLs to index
     * @param bool $checkExisting Whether to check if URLs are already indexed
     * @param int $delayMs Delay between requests in milliseconds
     * @return array Results of the operation
     */
    public function indexUrls(array $urls, bool $checkExisting = true, int $delayMs = 400): array
    {
        // Check for quota exceeded at the start
        if ($this->isQuotaExceeded()) {
            return [
                'success' => false,
                'message' => 'Daily quota exceeded. Try again tomorrow.',
                'quota_exceeded' => true,
                'processed' => 0,
                'remaining' => count($urls)
            ];
        }

        $remainingQuota = $this->getRemainingQuota();
        $processCount = min(count($urls), $remainingQuota);

        $results = [
            'success_count' => 0,
            'skipped_count' => 0,
            'failure_count' => 0,
            'quota_exceeded' => false,
            'processed' => 0,
            'details' => []
        ];

        for ($i = 0; $i < $processCount; $i++) {
            if ($results['quota_exceeded'] || $this->isQuotaExceeded()) {
                if (!$results['quota_exceeded']) {
                    $results['quota_exceeded'] = true;
                }
                break;
            }

            $url = $urls[$i];
            $result = $this->indexUrl($url, $checkExisting);
            $results['details'][$url] = $result;
            $results['processed']++;

            if ($result['success']) {
                $results['success_count']++;
                if (isset($result['already_indexed']) && $result['already_indexed']) {
                    $results['skipped_count']++;
                }
            } else {
                $results['failure_count']++;
                if (isset($result['quota_exceeded']) && $result['quota_exceeded']) {
                    $results['quota_exceeded'] = true;
                    // Break immediately, indexUrl already recorded it
                    break;
                }
            }

            // Add delay between requests if not the last one and no quota error
            if ($i < $processCount - 1 && !$results['quota_exceeded']) {
                usleep($delayMs * 1000);
            }
        }

        // Set final message and overall success status
        $results['message'] = $results['quota_exceeded']
            ? 'Quota exceeded. Some URLs may not have been processed.'
            : 'Processing complete.';
        $results['success'] = ($results['failure_count'] === 0 && !$results['quota_exceeded']);
        $results['remaining'] = count($urls) - $results['processed'];

        return $results;
    }
    
    /**
     * Send a model to be indexed by Google
     * 
     * @param Model $model The model to index
     * @param bool $checkExisting Whether to check if the URL is already indexed
     * @return array The result of the operation
     */
    public function indexModel(Model $model, bool $checkExisting = true): array
    {
        if (!method_exists($model, 'getGoogleIndexingUrl')) {
            return ['success' => false, 'message' => 'Model does not have the GoogleIndexable trait'];
        }
        
        try {
            $url = $model->getGoogleIndexingUrl();
            return $this->indexUrl($url, $checkExisting); // Delegate to indexUrl logic
        } catch (\Exception $e) {
            // Catch errors during getGoogleIndexingUrl or other setup
             return [
                'success' => false,
                'message' => 'Error preparing model for indexing: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if the daily quota has been exceeded
     */
    public function isQuotaExceeded(): bool
    {
        // Check the actual count based on successful sends
        return $this->getRemainingQuota() <= 0;
    }
    
    /**
     * Get the remaining quota for today
     */
    public function getRemainingQuota(): int
    {
        $usedToday = GoogleIndexingRecord::where('status', GoogleIndexingRecord::STATUS_SUCCESS)
            ->whereDate('sent_at', Carbon::today())
            ->count();
            
        return max(0, self::DAILY_QUOTA_LIMIT - $usedToday);
    }
    
    /**
     * Record successful indexing for a raw URL.
     */
    protected function recordUrlSuccess(string $url): void
    {
        GoogleIndexingRecord::updateOrCreate(
            ['url' => $url],
            [
                'status' => GoogleIndexingRecord::STATUS_SUCCESS,
                'sent_at' => now(),
                'error_message' => null, 
            ]
        );
    }

    /**
     * Record successful indexing for a raw URL discovered via status check.
     */
    protected function recordUrlSuccessFromStatus(string $url, \Google\Service\Indexing\UrlNotificationMetadata $metadata): void
    {
        GoogleIndexingRecord::updateOrCreate(
            ['url' => $url],
            [
                'status' => GoogleIndexingRecord::STATUS_SUCCESS,
                'sent_at' => now(), 
                'response_data' => json_decode(json_encode($metadata), true),
                'error_message' => null
            ]
        );
    }
} 
