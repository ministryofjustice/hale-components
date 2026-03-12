<?php

use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;


/**
 * Invalidate CloudFront cache before an attachment is deleted from the WP attachment table.
 *
 * This function checks if an attachment URL is on the CDN.
 * If it is, then we invalidate that path on CloudFront.
 *
 * LIMITATIONS:
 * - doesn't clear multiple image sizes, e.g. image-300x200.jpg
 * - currently, we don't set $blocking, so a user may see that the file is 
 *   still cached for a short while, after they have deleted it.
 *
 * @see https://developer.wordpress.org/reference/hooks/pre_delete_attachment/
 *
 * @param WP_Post|false|null $delete The current delete value passed by the filter.
 * @param WP_Post            $post   The attachment post object.
 *
 * @return WP_Post|false|null The filtered delete value, or false to prevent deletion.
 */
function hale_components_invalidate_cache_on_pre_delete_attachment(WP_Post|false|null $delete, WP_Post $post)
{

    if ($delete === false) {
        // If $delete is already false, then do nothing.
        return $delete;
    }

    // Get the props, either `false` or `['is_cdn' => boolean, 'path' => string ]`
    $attachment_props = hale_components_attachment_props_from_id($post->ID);

    if (!$attachment_props || !$attachment_props['is_cdn']) {
        // The URL wasn't parsed successfully, 
        // or the attachment URL is not on a CDN, so do nothing.
        return $delete;
    }

    // If we are here, then we should be invalidating the CloudFront cache.

    try {
        // Trigger the invalidation - don't block the delete request by waiting for success.
        hale_components_invalidate_cloudfront_path(
            $attachment_props['path'],
            'attachment-' . get_current_blog_id() . '-' . get_the_guid($post) . '-' . $post->ID
        );
        // If we are here, then the status is either InProgress or Completed.
    } catch (Throwable $t) {
        // If we are here, then something went wrong.
        // Log the error.
        error_log($t->getMessage());

        // Cache clearing wasn't successful.
        // Don't delete the attachment (from WP database).
        // The user will see an alert with:
        // Error in deleting the attachment.
        return false;
    }

    // There were no errors - continue to delete the attachment from WP attachments table.
    return $delete;
};

add_filter('pre_delete_attachment', 'hale_components_invalidate_cache_on_pre_delete_attachment', 100, 2);


/**
 * Fire off a second CloudFront invalidation request, 
 * to ensure that the invalidation happens when the source file is NOT on S3.
 *
 * This will run after WP S3 Uploads has deleted the file from S3.
 * This is because WP S3 Uploads uses this hook to delete the files, but with the default priority.
 *
 * @param int $post The attachment post ID.
 * @return void
 */
function hale_components_invalidate_cache_on_delete_attachment(int $post_id)
{

    $attachment_props = hale_components_attachment_props_from_id($post_id);

    if (!$attachment_props || !$attachment_props['is_cdn']) {
        return;
    }

    try {
        hale_components_invalidate_cloudfront_path(
            $attachment_props['path'],
            'delete_attachment-' . $post_id
        );
    } catch (Throwable $t) {
        error_log($t->getMessage());
    }
};

add_action('delete_attachment', 'hale_components_invalidate_cache_on_delete_attachment', 100, 1);

/**
 * Get attachment properties from an attachment ID.
 *
 * @param int $attachment_id
 * @return false|array Returned array is of the type ['is_cdn' => boolean, 'path' => string ]
 */
function hale_components_attachment_props_from_id($attachment_id): array|false
{

    if (!$attachment_id) {
        return false;
    }

    $attachment_url = wp_get_attachment_url($attachment_id);

    if (!$attachment_url) {
        // For whatever reason, we don't have an attachment URL, do nothing.
        return false;
    }

    $attachment_url_parts = parse_url($attachment_url);

    if (!$attachment_url_parts || !isset($attachment_url_parts['host'], $attachment_url_parts['path'])) {
        // The URL wasn't parsed successfully.
        return false;
    }

    return [
        'is_cdn' => hale_components_host_is_a_cdn($attachment_url_parts['host']),
        'path' => $attachment_url_parts['path'],
    ];
}

/**
 * Check if a host is CloudFront.
 *
 * This checks against AWS CF hosts. e.g. abc123xyz.cloudfront.net
 * And, alias hosts that are based on the WB domain, e.g.
 * - cdn.websitebuilder.service.justice.gov.uk
 * - cdn.dev.websitebuilder.service.justice.gov.uk
 *
 * @param string $host The host to be checked.
 * @return bool  Does the host match a CDN one?
 */
function hale_components_host_is_a_cdn(string $host): bool
{
    $cdn_aws_pattern = "^\w+\.cloudfront\.net$";
    $cdn_alias_pattern = "^cdn\.(\w+\.)?websitebuilder\.service\.justice\.gov\.uk$";
    $combined_pattern = "/($cdn_aws_pattern)|($cdn_alias_pattern)/";

    return preg_match($combined_pattern, $host);
}


/**
 * Create (or retry) a single-file CloudFront cache invalidation.
 *
 * Uses callerReference for idempotency: if an invalidation with the same
 * callerReference & path already exists, CloudFront returns that existing
 * invalidation rather than creating a new one.
 *
 * By default, returns immediately after initiating the invalidation. Set
 * $blocking to true to poll until the invalidation completes or times out.
 *
 * @param string $path             The path to invalidate (e.g., '/uploads/2026/02/image.jpg').
 * @param string $caller_reference A unique reference for idempotency (e.g., 'attachment-123').
 * @param bool   $blocking         Whether to wait for the invalidation to complete. Default false.
 * @param array  $client_options   Optional overrides for the CloudFrontClient configuration.
 * @param int    $timeout_seconds  Maximum seconds to wait when blocking. Default 180.
 *
 * @return array{Id: string, Status: string, Path: string, CreateTime: \DateTimeInterface} Invalidation details.
 *
 * @throws RuntimeException If AWS SDK is missing, required constants are undefined, or the API call fails.
 */
function hale_components_invalidate_cloudfront_path(
    string $path,
    string $caller_reference,
    bool $blocking = false,
    array $client_options = [],
    int $timeout_seconds = 180
): array {

    // Ensure the AWS SDK can be loaded.
    if (! class_exists('\\Aws\\CloudFront\\CloudFrontClient')) {
        throw new RuntimeException('Cloudfront cache clearing requires the AWS SDK. Ensure Composer dependencies have been loaded.');
    }

    if (! defined('CLOUDFRONT_DISTRIBUTION_ID')) {
        throw new RuntimeException('CLOUDFRONT_DISTRIBUTION_ID constant is required. Please define it in your wp-config.php');
    }

    if (! defined('S3_UPLOADS_REGION')) {
        throw new RuntimeException('S3_UPLOADS_REGION constant is required. Please define it in your wp-config.php');
    }

    $distribution_id = CLOUDFRONT_DISTRIBUTION_ID;

    // ---- Client ----
    $client = new CloudFrontClient(array_replace([
        'region'  => S3_UPLOADS_REGION,
        'version' => '2020-05-31',
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

        $invalidation_id = $create['Invalidation']['Id'];

        if (!$blocking) {
            error_log("Invalidation initialised for {$path}. (Id: {$invalidation_id})");
            return [
                'Id'         => $invalidation_id,
                'Status'     => $create['Invalidation']['Status'],
                'Path'       => $path,
                'CreateTime' => $create['Invalidation']['CreateTime'],
            ];
        }


        // ---- Poll until Completed or timeout ----
        return hale_components_poll_cloudfront_invalidation($client, $distribution_id, $invalidation_id, $timeout_seconds, $path);
    } catch (AwsException $e) {
        // If you reused the same callerReference with *different* paths,
        // CloudFront will throw an error; surface that clearly.
        $msg  = $e->getAwsErrorMessage() ?: $e->getMessage();
        $code = $e->getAwsErrorCode() ?: 'UnknownAwsErrorCode';
        throw new RuntimeException("CloudFront invalidation failed: {$msg} [{$code}]");
    }
}

/**
 * Poll CloudFront until an invalidation completes or times out.
 *
 * @param CloudFrontClient $client          The configured CloudFront client.
 * @param string           $distribution_id The CloudFront distribution ID.
 * @param string           $invalidation_id  The invalidation ID to poll.
 * @param int              $timeout_seconds Maximum seconds to wait before timing out.
 * @param string           $path            The path being invalidated (for logging).
 *
 * @return array{Id: string, Status: string, Path: string, CreateTime: \DateTimeInterface} Invalidation details.
 *
 * @throws RuntimeException If the invalidation does not complete within the timeout.
 */
function hale_components_poll_cloudfront_invalidation(
    CloudFrontClient $client,
    string $distribution_id,
    string $invalidation_id,
    int $timeout_seconds,
    string $path
): array {
    $start = time();
    do {
        sleep(5);
        $desc = $client->getInvalidation([
            'DistributionId' => $distribution_id,
            'Id' => $invalidation_id,
        ]);
        $status = $desc['Invalidation']['Status'];
        error_log("Status: {$status}");

        if ((time() - $start) > $timeout_seconds) {
            throw new RuntimeException("Timed out waiting for CloudFront invalidation to complete.");
        }
    } while ($status !== 'Completed');

    error_log("Invalidation completed for {$path}. (Id: {$invalidation_id})");

    return [
        'Id'         => $invalidation_id,
        'Status'     => $status,
        'Path'       => $path,
        'CreateTime' => $desc['Invalidation']['CreateTime'],
    ];
}
