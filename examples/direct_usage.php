<?php

/**
 * Example of direct usage of the GoogleIndexing facade (without SlugRewrite)
 */

use Elfeffe\LaravelGoogleIndexing\Facades\GoogleIndexing;

// Example 1: Check remaining quota
$remainingQuota = GoogleIndexing::getRemainingQuota();
echo "Remaining quota: {$remainingQuota}\n";

// Example 2: Index a single URL
$result = GoogleIndexing::indexUrl('https://example.com/page1');
if ($result['success']) {
    echo "Successfully indexed URL: {$result['message']}\n";
} else {
    echo "Failed to index URL: {$result['message']}\n";
}

// Example 3: Index multiple URLs at once
$urls = [
    'https://example.com/page2',
    'https://example.com/page3',
    'https://example.com/page4',
];

$batchResult = GoogleIndexing::indexUrls($urls, true, 500); // true = check existing, 500ms delay
echo "Batch processing results:\n";
echo "- Processed: {$batchResult['processed']}\n";
echo "- Success: {$batchResult['success_count']}\n";
echo "- Skipped: {$batchResult['skipped_count']}\n";
echo "- Failures: {$batchResult['failure_count']}\n";

// Example 4: Index a model with the GoogleIndexable trait
$post = App\Models\Post::find(1);
$modelResult = GoogleIndexing::indexModel($post);
if ($modelResult['success']) {
    echo "Successfully indexed model: {$modelResult['message']}\n";
} else {
    echo "Failed to index model: {$modelResult['message']}\n";
}

/**
 * Alternative: Using the Helper directly
 */
$helper = app(\Elfeffe\LaravelGoogleIndexing\Helpers\IndexingHelper::class);
$isQuotaExceeded = $helper->isQuotaExceeded();
if ($isQuotaExceeded) {
    echo "Quota is exceeded, try again tomorrow\n";
}

/**
 * Alternative: Static creation
 */
$helper = \Elfeffe\LaravelGoogleIndexing\Helpers\IndexingHelper::make();
$result = $helper->indexUrl('https://example.com/page5'); 
