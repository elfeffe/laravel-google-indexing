<?php

declare(strict_types=1);

namespace Elfeffe\LaravelGoogleIndexing;

use Google_Client;
use Google_Service_Indexing;
use Google_Service_Indexing_UrlNotification;
use Illuminate\Support\Facades\Config;

class LaravelGoogleIndexing
{
    /** @var Google_Client */
    private Google_Client $googleClient;

    /** @var Google_Service_Indexing */
    private Google_Service_Indexing $indexingService;

    public function __construct()
    {
        $this->googleClient = new Google_Client();

        $authConfigPath = Config::get('laravel-google-indexing.google.auth_config');
        if (! $authConfigPath || ! file_exists($authConfigPath)) {
            throw new \InvalidArgumentException('Google Auth Config file path is not set or invalid.');
        }
        $this->googleClient->setAuthConfig($authConfigPath);

        foreach (Config::get('laravel-google-indexing.google.scopes', []) as $scope) {
            $this->googleClient->addScope($scope);
        }

        $this->indexingService = new Google_Service_Indexing($this->googleClient);
    }

    public static function create(): self
    {
        return new static();
    }

    public function status(string $url): \Google\Service\Indexing\UrlNotificationMetadata
    {
        return $this->indexingService
            ->urlNotifications
            ->getMetadata([
                'url' => urlencode($url),
            ]);
    }

    public function update(string $url): Google_Service_Indexing_UrlNotification
    {
        return $this->publish($url, 'URL_UPDATED');
    }

    public function delete(string $url): Google_Service_Indexing_UrlNotification
    {
        return $this->publish($url, 'URL_DELETED');
    }

    private function publish(string $url, string $action): Google_Service_Indexing_UrlNotification
    {
        $urlNotification = new Google_Service_Indexing_UrlNotification();

        $urlNotification->setUrl($url);
        $urlNotification->setType($action);

        return $this->indexingService
            ->urlNotifications
            ->publish($urlNotification);
    }

    /**
     * @param  array<string, string>  $urls Example: ['URL_UPDATED' => 'https://www.site.com', 'URL_DELETED' => 'https://www.site.com/deleted-url']
     * @return array<mixed>
     */
    public function multiplePublish(array $urls): array
    {
        $batch = $this->indexingService->createBatch();
        foreach ($urls as $action => $url) {
            $postBody = new Google_Service_Indexing_UrlNotification();
            $postBody->setUrl($url);
            $postBody->setType($action);
            $batch->add($this->indexingService->urlNotifications->publish($postBody));
        }

        return $batch->execute();
    }
}
