<?php

use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;

/**
 * Create (or reuse) a single-file invalidation by callerReference and wait until completion.
 * If an invalidation with the same callerReference & same path already exists,
 * CloudFront returns that existing invalidation, and we just poll it.
 */
function hale_components_invalidate_cloudfront_path(
    string $path,
    string $caller_reference,
    bool $blocking = false,
    array $client_options = [],
    int $timeout_seconds = 180
): array|false {

    // Ensure the AWS SDK can be loaded.
    if (! class_exists('\\Aws\\CloudFront\\CloudFrontClient')) {
        throw new Error('Cloudfront cache clearing requires the AWS SDK. Ensure Composer dependencies have been loaded.');
    }

    if (! defined('CLOUDFRONT_DISTRIBUTION_ID')) {
        throw new Error('CLOUDFRONT_DISTRIBUTION_ID constant is required. Please define it in your wp-config.php');
    }

    $distribution_id = CLOUDFRONT_DISTRIBUTION_ID;

    // ---- Client ----
    $client = new CloudFrontClient(array_replace([
        'region'  => S3_UPLOADS_REGION,
        'version' => 'latest',
    ], $client_options));

    try {
        // Idempotent: if same callerReference+paths was used before,
        // CloudFront returns the same invalidation instead of creating a new one.
        $create = $client->createInvalidation([
            'DistributionId' => $distribution_id,
            'InvalidationBatch' => [
                'CallerReference' => $caller_reference,
                'Paths' => [
                    'Quantity' => 1,
                    'Items' => [$path],
                ],
            ],
        ]);

        $invalidationId = $create['Invalidation']['Id'];

        if (!$blocking) {
            echo "Invalidation initialised for {$path}. (Id: {$invalidationId})\n";
            return [
                'Id'         => $invalidationId,
                'Status'     => $create['Invalidation']['Status'],
                'Path'       => $path,
                'CreateTime' => $create['Invalidation']['CreateTime'],
            ];
        }


        // ---- Poll until Completed or timeout ----
        return pollInvalidation($client, $distribution_id, $invalidationId, $timeout_seconds, $path);
    } catch (AwsException $e) {
        // If you reused the same callerReference with *different* paths,
        // CloudFront will throw an error; surface that clearly.
        $msg  = $e->getAwsErrorMessage() ?: $e->getMessage();
        $code = $e->getAwsErrorCode() ?: 'UnknownAwsErrorCode';
        throw new RuntimeException("CloudFront invalidation failed: {$msg} [{$code}]");
    }
}

/**
 * Poll helper.
 */
function pollInvalidation(
    CloudFrontClient $client,
    string $distribution_id,
    string $invalidationId,
    int $timeout_seconds,
    string $path
): array {
    $start = time();
    do {
        sleep(5);
        $desc = $client->getInvalidation([
            'DistributionId' => $distribution_id,
            'Id' => $invalidationId,
        ]);
        $status = $desc['Invalidation']['Status'];
        echo "Status: {$status}\n";

        if ((time() - $start) > $timeout_seconds) {
            throw new RuntimeException("Timed out waiting for CloudFront invalidation to complete.");
        }
    } while ($status !== 'Completed');

    echo "Invalidation completed for {$path}. (Id: {$invalidationId})\n";

    return [
        'Id'         => $invalidationId,
        'Status'     => $status,
        'Path'       => $path,
        'CreateTime' => $desc['Invalidation']['CreateTime'],
    ];
}



/**
 * Invalidate CloudFront cache, before an attachment is deleted from the WP attachment table.
 *
 * This function checks if an attachment url is on the cdn. 
 * If it is, then we invalidate that path on CloudFront.
 *
 * @see https://developer.wordpress.org/reference/hooks/pre_delete_attachment/
 */
function hale_components_invalidate_cloudfront_cache(WP_Post|false|null $delete, WP_Post $post)
{

    if ($delete === false) {
        // If $delete is already false, then do noting.
        return $delete;
    }

    $attachment_url = wp_get_attachment_url($post->ID);

    if (!$attachment_url) {
        // For whatever reason, we don't have an attachment URL, do noting.
        return $delete;
    }

    $cdn_hosts = [
        'cdn.websitebuilder.service.justice.gov.uk',
        'cdn.demo.websitebuilder.service.justice.gov.uk',
        'cdn.dev.websitebuilder.service.justice.gov.uk',
        'cdn.staging.websitebuilder.service.justice.gov.uk',
    ];

    $attachment_url_parts = parse_url($attachment_url);

    if (!in_array($attachment_url_parts['host'], $cdn_hosts, true)) {
        // The attachment URL is not on a CDN, so do nothing.
        return $delete;
    }

    // If we are here, then we should be invalidating the CloudFront cache.

    try {
        hale_components_invalidate_cloudfront_path(
            $attachment_url_parts['path'],
            'attachment-' . $post->ID   // ← your unique ID
            // Optionally pass SDK options, e.g. ['credentials' => [...]]
        );
    } catch (Throwable $t) {
        // Centralized error reporting/logging
        error_log($t->getMessage());
        // echo "Error: " . $t->getMessage() . PHP_EOL;

        // Cache clearing wasn't successful.
        // Don't delete the attachment (from WP database).
        // The user will see an alert with:
        // Error in deleting the attachment.
        return false;
    }

    // There were no errors - continue to delete the attachment from WP attachments table.
    return $delete;
};

add_filter('pre_delete_attachment', 'hale_components_invalidate_cloudfront_cache', 100, 2);
