<?php

declare(strict_types=1);

namespace MOJComponents\CloudFront;

use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;
use DateTimeInterface;
use RuntimeException;
use Throwable;
use WP_Post;

class CloudFront
{
    public function __construct()
    {
        $this->actions();
    }

    private function actions(): void
    {
        add_filter('pre_delete_attachment', [$this, 'invalidateCacheOnPreDeleteAttachment'], 100, 2);
        add_action('delete_attachment', [$this, 'invalidateCacheOnDeleteAttachment'], 100, 1);
    }

    /**
     * Invalidate CloudFront cache before an attachment is deleted from the WP attachment table.
     *
     * LIMITATIONS:
     * - Does not clear multiple image sizes (e.g. image-300x200.jpg).
     * - Non-blocking by default; user may briefly see stale cached content after deletion.
     *
     * @see https://developer.wordpress.org/reference/hooks/pre_delete_attachment/
     *
     * @param WP_Post|false|null $delete Current delete value passed by the filter.
     * @param WP_Post            $post   The attachment post object.
     * @return WP_Post|false|null
     */
    public function invalidateCacheOnPreDeleteAttachment(
        WP_Post|false|null $delete,
        WP_Post $post
    ): WP_Post|false|null {
        if ($delete === false) {
            return $delete;
        }

        $attachmentProps = $this->getAttachmentProps($post->ID);

        if (!$attachmentProps || !$attachmentProps['is_cdn']) {
            return $delete;
        }

        try {
            $this->invalidateCloudFrontPath(
                $attachmentProps['path'],
                'attachment-' . get_current_blog_id() . '-' . $post->ID . '-' . time()
            );
        } catch (Throwable $t) {
            error_log($t->getMessage());
            return false;
        }

        return $delete;
    }

    /**
     * Fire a second invalidation after WP S3 Uploads has deleted the file from S3.
     *
     * @param int $postId The attachment post ID.
     */
    public function invalidateCacheOnDeleteAttachment(int $postId): void
    {
        $attachmentProps = $this->getAttachmentProps($postId);

        if (!$attachmentProps || !$attachmentProps['is_cdn']) {
            return;
        }

        try {
            $this->invalidateCloudFrontPath(
                $attachmentProps['path'],
                'delete_attachment-' . get_current_blog_id() . '-' . $postId . '-' . time()
            );
        } catch (Throwable $t) {
            error_log($t->getMessage());
        }
    }

    /**
     * Get attachment URL properties from an attachment ID.
     *
     * @param int $attachmentId
     * @return array{is_cdn: bool, path: string}|false
     */
    public function getAttachmentProps(int $attachmentId): array|false
    {
        if (!$attachmentId) {
            return false;
        }

        $attachmentUrl = wp_get_attachment_url($attachmentId);

        if (!$attachmentUrl) {
            return false;
        }

        $urlParts = parse_url($attachmentUrl);

        if (!$urlParts || !isset($urlParts['host'], $urlParts['path'])) {
            return false;
        }

        return [
            'is_cdn' => $this->hostIsACdn($urlParts['host']),
            'path'   => $urlParts['path'],
        ];
    }

    /**
     * Check if a host is a CloudFront or aliased CDN host.
     *
     * Matches AWS CF hosts (e.g. abc123.cloudfront.net) and alias hosts
     * (e.g. cdn.websitebuilder.service.justice.gov.uk).
     *
     * @param string $host
     * @return bool
     */
    public function hostIsACdn(string $host): bool
    {
        $awsPattern    = '^\w+\.cloudfront\.net$';
        $aliasPattern  = '^cdn\.(\w+\.)?websitebuilder\.service\.justice\.gov\.uk$';
        $combinedPattern = "/($awsPattern)|($aliasPattern)/";

        return (bool) preg_match($combinedPattern, $host);
    }

    /**
     * Create (or retry) a single-file CloudFront cache invalidation.
     *
     * Uses callerReference for idempotency: if an invalidation with the same
     * callerReference and path already exists, CloudFront returns that existing
     * invalidation rather than creating a new one.
     *
     * @param string $path            The path to invalidate (e.g. '/uploads/2026/02/image.jpg').
     * @param string $callerReference A unique reference for idempotency (e.g. 'attachment-123').
     * @param bool   $blocking        Whether to wait for the invalidation to complete. Default false.
     * @param array  $clientOptions   Optional overrides for the CloudFrontClient configuration.
     * @param int    $timeoutSeconds  Maximum seconds to wait when blocking. Default 180.
     *
     * @return array{Id: string, Status: string, Path: string, CreateTime: DateTimeInterface}
     *
     * @throws RuntimeException If AWS SDK is missing, required constants are undefined, or the API call fails.
     */
    public function invalidateCloudFrontPath(
        string $path,
        string $callerReference,
        bool $blocking = false,
        array $clientOptions = [],
        int $timeoutSeconds = 180
    ): array {
        if (!class_exists('\\Aws\\CloudFront\\CloudFrontClient')) {
            throw new RuntimeException(
                'Cloudfront cache clearing requires the AWS SDK. Ensure Composer dependencies have been loaded.'
            );
        }

        if (!defined('CLOUDFRONT_DISTRIBUTION_ID')) {
            throw new RuntimeException(
                'CLOUDFRONT_DISTRIBUTION_ID constant is required. Please define it in your wp-config.php'
            );
        }

        if (!defined('S3_UPLOADS_REGION')) {
            throw new RuntimeException(
                'S3_UPLOADS_REGION constant is required. Please define it in your wp-config.php'
            );
        }

        $distributionId = CLOUDFRONT_DISTRIBUTION_ID;

        $client = new CloudFrontClient(array_replace([
            'region'  => S3_UPLOADS_REGION,
            'version' => '2020-05-31',
        ], $clientOptions));

        try {
            $create = $client->createInvalidation([
                'DistributionId'    => $distributionId,
                'InvalidationBatch' => [
                    'CallerReference' => $callerReference,
                    'Paths' => [
                        'Quantity' => 1,
                        'Items'    => [$path],
                    ],
                ],
            ]);

            $invalidationId = $create['Invalidation']['Id'];

            if (!$blocking) {
                error_log("Invalidation initialised for {$path}. (Id: {$invalidationId})");

                return [
                    'Id'         => $invalidationId,
                    'Status'     => $create['Invalidation']['Status'],
                    'Path'       => $path,
                    'CreateTime' => $create['Invalidation']['CreateTime'],
                ];
            }

            return $this->pollCloudFrontInvalidation(
                $client,
                $distributionId,
                $invalidationId,
                $timeoutSeconds,
                $path
            );
        } catch (AwsException $e) {
            $msg  = $e->getAwsErrorMessage() ?: $e->getMessage();
            $code = $e->getAwsErrorCode() ?: 'UnknownAwsErrorCode';
            throw new RuntimeException("CloudFront invalidation failed: {$msg} [{$code}]");
        }
    }

    /**
     * Poll CloudFront until an invalidation completes or times out.
     *
     * @param CloudFrontClient $client          The configured CloudFront client.
     * @param string           $distributionId  The CloudFront distribution ID.
     * @param string           $invalidationId  The invalidation ID to poll.
     * @param int              $timeoutSeconds  Maximum seconds to wait before timing out.
     * @param string           $path            The path being invalidated (for logging).
     *
     * @return array{Id: string, Status: string, Path: string, CreateTime: DateTimeInterface}
     *
     * @throws RuntimeException If the invalidation does not complete within the timeout.
     */
    public function pollCloudFrontInvalidation(
        CloudFrontClient $client,
        string $distributionId,
        string $invalidationId,
        int $timeoutSeconds,
        string $path
    ): array {
        $start = time();

        do {
            sleep(5);
            $desc   = $client->getInvalidation([
                'DistributionId' => $distributionId,
                'Id'             => $invalidationId,
            ]);
            $status = $desc['Invalidation']['Status'];
            error_log("Status: {$status}");

            if ((time() - $start) > $timeoutSeconds) {
                throw new RuntimeException('Timed out waiting for CloudFront invalidation to complete.');
            }
        } while ($status !== 'Completed');

        error_log("Invalidation completed for {$path}. (Id: {$invalidationId})");

        return [
            'Id'         => $invalidationId,
            'Status'     => $status,
            'Path'       => $path,
            'CreateTime' => $desc['Invalidation']['CreateTime'],
        ];
    }
}
