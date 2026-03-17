<?php

declare(strict_types=1);

namespace Elfeffe\LaravelGoogleIndexing;

use Google_Client;
use Google_Service_Exception as GoogleServiceException;
use Google_Service_Indexing;
use Illuminate\Support\Facades\Config;
use Google\Service\Indexing\PublishUrlNotificationResponse;
use Google\Service\Indexing\UrlNotificationMetadata;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException as HttpRequestException;
use Elfeffe\LaravelGoogleIndexing\Exceptions\GoogleQuotaExceededException;
use Exception;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class LaravelGoogleIndexing
{
    private const ENDPOINT = 'https://indexing.googleapis.com/v3/urlNotifications:publish';

    /** @var Google_Client */
    private Google_Client $googleClient;

    /** @var Google_Service_Indexing */
    private Google_Service_Indexing $indexingService;

    /**
     * @param  array<string, mixed>|string|null  $authConfig
     * @param  array<string>|null  $scopes
     */
    public function __construct(array|string|null $authConfig = null, ?array $scopes = null)
    {
        $this->googleClient = new Google_Client();

        $authConfig ??= Config::get('laravel-google-indexing.google.auth_config');
        $this->setAuthConfig($authConfig);

        // Ensure the indexing scope is added for the googleClient to authorize correctly
        $scopes ??= Config::get('laravel-google-indexing.google.scopes', [Google_Service_Indexing::INDEXING]);
        if (!in_array(Google_Service_Indexing::INDEXING, $scopes)) {
            $scopes[] = Google_Service_Indexing::INDEXING;
        }
        foreach ($scopes as $scope) {
            $this->googleClient->addScope($scope);
        }

        // Still create indexingService for other methods like status() / multiplePublish()
        $this->indexingService = new Google_Service_Indexing($this->googleClient);
    }

    public static function create(): self
    {
        return new static();
    }

    /**
     * @param  array<string, mixed>|string  $authConfig
     * @param  array<string>|null  $scopes
     */
    public static function forAuthConfig(array|string $authConfig, ?array $scopes = null): self
    {
        return new static($authConfig, $scopes);
    }

    /**
     * @param  array<string, mixed>|string|null  $authConfig
     */
    protected function setAuthConfig(array|string|null $authConfig): void
    {
        if (is_array($authConfig)) {
            $this->googleClient->setAuthConfig($authConfig);
            return;
        }

        if (! is_string($authConfig) || $authConfig === '') {
            throw new InvalidArgumentException('Google Auth Config must be a file path, a JSON string, or an array.');
        }

        if (is_file($authConfig)) {
            $this->googleClient->setAuthConfig($authConfig);
            return;
        }

        $decoded = json_decode($authConfig, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Google Auth Config must be a file path, a JSON string, or an array.');
        }

        $this->googleClient->setAuthConfig($decoded);
    }

    /**
     * Get metadata for a URL.
     * @throws GoogleQuotaExceededException if quota is exceeded.
     * @throws Exception for other errors.
     */
    public function status(string $url): UrlNotificationMetadata
    {
        try {
            // Uses the original Google Service object
            return $this->indexingService
                ->urlNotifications
                ->getMetadata([
                    'url' => urlencode($url),
                ]);
        } catch (GoogleServiceException $e) {
            // Check if it's a quota error (429)
            if ($e->getCode() == 429) {
                throw new GoogleQuotaExceededException(
                    'Quota exceeded during status check: ' . $e->getMessage(),
                    $e->getCode(),
                    $e
                );
            }
            // Re-throw other Google service errors
            throw new Exception('Google API error during status check: ' . $e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            // Catch any other exceptions
            throw new Exception('An error occurred during the Google API status check: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws GoogleQuotaExceededException if quota is exceeded.
     * @throws Exception for other errors.
     */
    public function update(string $url): bool
    {
        return $this->publish($url, 'URL_UPDATED');
    }

    /**
     * @throws GoogleQuotaExceededException if quota is exceeded.
     * @throws Exception for other errors.
     */
    public function delete(string $url): bool
    {
        return $this->publish($url, 'URL_DELETED');
    }
    
    /**
     * Update indexing for a model that uses the GoogleIndexable trait.
     * 
     * @param Model $model The model to index
     * @throws GoogleQuotaExceededException if quota is exceeded.
     * @throws Exception for other errors.
     */
    public function updateModel(Model $model): bool
    {
        if (!method_exists($model, 'getGoogleIndexingUrl')) {
            throw new Exception('Model must use the GoogleIndexable trait');
        }
        
        $url = $model->getGoogleIndexingUrl();
        
        // publish() will throw GoogleQuotaExceededException on 429
        $result = $this->update($url);
            
        // Record the successful indexing ONLY if no exception was thrown
        if ($result && method_exists($model, 'markAsGoogleIndexed')) {
            $model->markAsGoogleIndexed($url); // Only record success
        }
            
        return $result;
    }
    
    /**
     * Delete indexing for a model that uses the GoogleIndexable trait.
     * 
     * @param Model $model The model to remove from index
     * @throws GoogleQuotaExceededException if quota is exceeded.
     * @throws Exception for other errors.
     */
    public function deleteModel(Model $model): bool
    {   
        if (!method_exists($model, 'getGoogleIndexingUrl')) {
            throw new Exception('Model must use the GoogleIndexable trait');
        }
        
        $url = $model->getGoogleIndexingUrl();

        // publish() will throw GoogleQuotaExceededException on 429
        $result = $this->delete($url);
        
        // Optional: Maybe remove indexing records on successful delete?
        // if ($result && method_exists($model, 'googleIndexingRecords')) {
        //     $model->googleIndexingRecords()->delete();
        // }
        
        return $result;
    }
    
    /**
     * Check if a model needs to be indexed based on its records.
     * 
     * @param Model $model The model to check
     * @param int $daysThreshold Number of days after which a re-index is needed
     * @return bool True if the model needs indexing, false otherwise
     */
    public function modelNeedsIndexing(Model $model, int $daysThreshold = 30): bool
    {
        if (!method_exists($model, 'hasBeenGoogleIndexedWithinDays')) {
            return true; // Assume needs indexing if trait method missing
        }
        
        return !$model->hasBeenGoogleIndexedWithinDays($daysThreshold);
    }

    /**
     * Executes the publish request using Laravel HTTP facade.
     *
     * @throws GoogleQuotaExceededException if quota is exceeded (429).
     * @throws Exception|HttpRequestException for other API or HTTP call failures.
     */
    private function publish(string $url, string $action): bool
    {
        try {
            // Fetch the access token using the Google Client's auth flow
            $tokenData = $this->googleClient->fetchAccessTokenWithAssertion();
            if (!isset($tokenData['access_token'])) {
                throw new Exception('Failed to fetch access token from Google Client.');
            }
            $accessToken = $tokenData['access_token'];

            $payload = [
                'url' => $url,
                'type' => $action,
            ];

            // Use Laravel HTTP facade
            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(self::ENDPOINT, $payload);

            // Check specifically for Quota Exceeded error (429)
            if ($response->status() == 429) {
                throw new GoogleQuotaExceededException(
                    'Google Indexing API quota exceeded. Status: ' . $response->status() . 
                    ' Body: ' . $response->body(),
                    429
                );
            }
            
            // Check if the request was generally successful (status code 2xx)
            if ($response->successful()) {
                return true;
            }

            // Throw generic exception for other non-successful responses
            throw new Exception(
                'Google Indexing API request failed with status code: ' . $response->status() .
                ' Body: ' . $response->body(),
                 $response->status()
            );

        } catch (HttpRequestException $e) {
            throw $e;
        } catch (GoogleQuotaExceededException $e) {
             // Re-throw the specific quota exception
            throw $e;
        } catch (Exception $e) {
            // Catch any other exceptions (e.g., from fetchAccessTokenWithAssertion or json_encode)
            throw new Exception('An error occurred during the Google Indexing API publish process: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
