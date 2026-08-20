<?php
/**
 * Serve compiled theme assets from the CDN
 *
 * Rewrites enqueued stylesheet and script URLs that point at wp-content/themes
 * so they resolve to a release-scoped prefix on CloudFront instead of the pod
 * the request happened to land on.
 *
 * WHY
 * Compiled CSS/JS is baked into the container image, so every pod serves its
 * own copy from an identical path (/wp-content/themes/hale/dist/...). Laravel
 * Mix versions by query string, not filename, so the path is the same across
 * releases. During a rolling update a visitor can therefore take HTML from one
 * release and have the asset request land on a pod running another - a 200
 * with the wrong bytes. nginx then tells the browser to cache that for ten
 * years (`expires max`, opt/nginx/wordpress.conf), so the mismatch sticks
 * until the next release changes the hash.
 *
 * Publishing each release's assets under /assets/{release}/ and pointing the
 * HTML at that prefix makes every release's assets distinct and independently
 * retrievable, so HTML always resolves the CSS it was built against - whatever
 * pod answers, and however stale the HTML is.
 *
 * Assets are published by the "Publish theme assets to CDN" step in
 * .github/workflows/rw-build-image.yaml (hale-platform).
 *
 * SCOPE
 * Themes only. Plugin assets are not published to the CDN yet, so plugin URLs
 * are deliberately left alone - rewriting them would point at a prefix that
 * does not exist.
 *
 * CONFIGURATION
 * Both constants are set from the environment by opt/scripts/config.sh, the
 * same way S3_UPLOADS_BUCKET_URL and CLOUDFRONT_DISTRIBUTION_ID are:
 *
 *   APP_RELEASE    the commit SHA the image was built from (new - must match
 *                  the value the publish step used for the S3 prefix)
 *   ASSET_CDN_URL  optional override; defaults to S3_UPLOADS_BUCKET_URL, which
 *                  config.sh already sets to the CloudFront root
 *
 * If either is missing this file is inert and assets are served from the pod
 * exactly as before - which is what happens in local development.
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * CDN base and release, resolved once per request.
 *
 * Prefers the wp-config constants (how every other environment value reaches
 * PHP in this stack) and falls back to getenv() so the same code works under
 * WP-CLI and in local docker-compose.
 *
 * @return array{cdn: string, release: string, base: string}|null
 *         Null when the CDN is not configured.
 */
function hale_cdn_config(): ?array
{
    static $config = false;

    if (false !== $config) {
        return $config;
    }

    /*
     * ASSET_CDN_URL is an optional override. It normally falls back to
     * S3_UPLOADS_BUCKET_URL, which config.sh already defines and which is the
     * root of the same CloudFront distribution the assets are published to
     * (e.g. https://cdn.dev.websitebuilder.service.justice.gov.uk - no path
     * suffix). Reusing it means no extra secret, no extra ConfigMap entry,
     * and no way for the asset CDN to drift from the real distribution.
     *
     * Set ASSET_CDN_URL explicitly if uploads and theme assets ever need to
     * be served from different distributions.
     */
    $cdn = '';

    foreach (['ASSET_CDN_URL', 'S3_UPLOADS_BUCKET_URL'] as $source) {
        if (defined($source) && constant($source)) {
            $cdn = (string) constant($source);
            break;
        }

        $from_env = getenv($source);

        if ($from_env) {
            $cdn = (string) $from_env;
            break;
        }
    }

    $release = defined('APP_RELEASE') && APP_RELEASE
        ? (string) APP_RELEASE
        : (string) getenv('APP_RELEASE');

    $cdn     = rtrim($cdn, '/');
    $release = preg_replace('/[^A-Za-z0-9]+/', '-', $release);

    if ('' === $cdn || '' === $release) {
        $config = null;
        return $config;
    }

    $config = [
        'cdn'     => $cdn,
        'release' => $release,
        // Scheme-less so comparisons survive TLS terminating upstream: the
        // scheme WordPress believes it is on can differ from what the browser
        // saw. Trailing slash trimmed so the remainder always starts with "/".
        'base'    => rtrim(preg_replace('#^https?:#', '', content_url()), '/'),
    ];

    return $config;
}

/**
 * Rewrite a theme asset URL to its release-scoped CDN equivalent.
 *
 * Hooked to style_loader_src and script_loader_src, so it covers everything
 * enqueued through WordPress - all themes and their children - without each
 * one needing to know the CDN exists.
 *
 * @param  string $src
 * @return string The CDN URL, or $src unchanged if it should not be rewritten.
 */
function hale_cdn_rewrite_asset_url($src)
{
    if (! is_string($src) || '' === $src) {
        return $src;
    }

    $config = hale_cdn_config();

    if (null === $config) {
        return $src;
    }

    // Leave the admin alone. An editor unable to load its CSS is a worse
    // failure than a brief front-end mismatch, and admin requests are not
    // spread across pods in the same way. Includes the block editor.
    if (is_admin()) {
        return $src;
    }

    // Idempotent: these filters can run more than once for the same handle.
    if (0 === strpos($src, $config['cdn'])) {
        return $src;
    }

    $probe = preg_replace('#^https?:#', '', $src);

    if (0 !== strpos($probe, $config['base'])) {
        return $src;   // not a wp-content URL at all
    }

    // Path relative to wp-content, e.g.
    //   "/themes/hale/dist/css/style.min.css?id=8f7d3a2b"
    $relative = substr($probe, strlen($config['base']));

    // Themes only - see SCOPE above. Uploads are already on their own
    // CloudFront path via wp-s3-uploads and must not be touched.
    if (0 !== strpos($relative, '/themes/')) {
        return $src;
    }

    // Mirrors the S3 layout written by the publish step:
    //   s3://<bucket>/assets/<release>/themes/<theme>/dist/...
    return $config['cdn'] . '/assets/' . $config['release'] . $relative;
}

add_filter('style_loader_src', 'hale_cdn_rewrite_asset_url', 20);
add_filter('script_loader_src', 'hale_cdn_rewrite_asset_url', 20);

/**
 * Open the connection to the CDN early.
 *
 * The first asset from a new origin otherwise pays DNS + TCP + TLS before it
 * can even start downloading, which lands squarely on first paint because
 * stylesheets are render-blocking.
 *
 * @param  string[] $hints
 * @param  string   $relation
 * @return string[]
 */
function hale_cdn_resource_hints(array $hints, string $relation): array
{
    if ('preconnect' !== $relation || is_admin()) {
        return $hints;
    }

    $config = hale_cdn_config();

    if (null !== $config) {
        $hints[] = $config['cdn'];
    }

    return $hints;
}

add_filter('wp_resource_hints', 'hale_cdn_resource_hints', 10, 2);
