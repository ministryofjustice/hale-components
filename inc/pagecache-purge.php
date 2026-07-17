<?php
/**
 * Page cache purge
 *
 * Clears the OpenResty/Redis full-page cache when a page or post
 * goes live - manual publish, scheduled publish (WP-Cron), or an
 * edit to already-published content. Page cache ONLY; this never
 * touches any object cache.
 */

if (! defined('ABSPATH')) {
    exit;
}

/*
 * Why transition_post_status (not save_post): save_post does NOT fire when
 * WP-Cron publishes a scheduled post. transition_post_status fires for manual
 * publish, scheduled publish, and edits of already-live content.
 */
add_action('transition_post_status', 'hc_pagecache_on_transition', 10, 3);
/**
 * @param string  $new_status
 * @param string  $old_status
 * @param WP_Post $post
 */
function hc_pagecache_on_transition($new_status, $old_status, $post): void
{
    if ('true' !== getenv('PAGECACHE_ENABLED')) {
        return;
    }
    // Only act when the result is a live, publicly viewable page/post.
    if ('publish' !== $new_status) {
        return;
    }
    if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
        return;
    }
    if (! is_post_type_viewable(get_post_type($post))) {
        return;
    }
    // URLs whose cached HTML is now stale.
    $paths   = ['/'];                                       // home lists/links new content
    $paths[] = hc_pagecache_path(get_permalink($post));
    // Hierarchical pages: ancestors show breadcrumbs / child listings.
    foreach (get_post_ancestors($post) as $ancestor_id) {
        $paths[] = hc_pagecache_path(get_permalink($ancestor_id));
    }
    hc_pagecache_purge_paths(array_values(array_unique(array_filter($paths))));
}
/**
 * Reduce a full permalink to the path used in the cache key (e.g. "/about/").
 */
function hc_pagecache_path($url): string
{
    $path = wp_parse_url((string) $url, PHP_URL_PATH);
    return $path ?: '/';
}
/**
 * DELETE the page-cache keys for the given paths on the current site, and
 * stamp a purge fence for each path so an in-flight request that started
 * rendering before this purge can't re-cache stale content afterward.
 *
 * RACE THIS CLOSES: a request can MISS and start rendering a path right
 * before an editor publishes. The Lua cache layer writes that render to
 * Redis asynchronously, *after* the response is already sent - which can
 * land after this DEL runs and silently undo the purge with stale HTML.
 * The fence key (pagecache:fence:{host}:{path}, stamped with Redis's own
 * clock) lets that deferred write check "was this path purged after I
 * started?" and skip its own write if so.
 *
 * Fail-soft: any Redis error is logged and swallowed so a cache problem can
 * never block an editor from publishing.
 *
 * @param string[] $paths
 */
function hc_pagecache_purge_paths(array $paths): void
{
    if ('true' !== getenv('PAGECACHE_ENABLED') || empty($paths)) {
        return;
    }
    $redis = hc_pagecache_redis_connect();
    if (null === $redis) {
        return;
    }
    try {
        $version    = (int) ($redis->get('pagecache:version') ?: 0);
        $hostname   = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));   // multisite: scope to this site
        $fenceTtl   = hc_pagecache_fence_ttl();
        $fenceValue = hc_pagecache_fence_value($redis);
        foreach ($paths as $path) {
            // Must match the Lua key schemes:
            //   content key -> pagecache:v{ver}:{host}:{uri}
            //   fence key   -> pagecache:fence:{host}:{uri}   (unversioned)
            $contentKey = "pagecache:v{$version}:{$hostname}:{$path}";
            $fenceKey   = "pagecache:fence:{$hostname}:{$path}";
            if (null !== $fenceValue) {
                $redis->set($fenceKey, $fenceValue, ['EX' => $fenceTtl]);
            } else {
                error_log('pagecache purge: Redis TIME unavailable, fence not set for ' . $path);
            }
            $redis->del($contentKey);
        }
        $redis->close();
    } catch (\Throwable $t) {
        try { $redis->close(); } catch (\Throwable $t2) {}
        error_log('pagecache purge failed: ' . $t->getMessage());
    }
}
/**
 * Connect to the page-cache Redis DB. Returns null (and logs) on any failure
 * so callers can fail-soft. Uses the same env vars as the Lua cache layer.
 */
function hc_pagecache_redis_connect(): ?\Redis
{
    if (! class_exists('Redis')) {
        error_log('pagecache purge: phpredis (Redis class) not available');
        return null;
    }
    try {
        $host = getenv('REDIS_HOST') ?: 'redis';
        $port = (int) (getenv('REDIS_PORT') ?: 6379);
        // ElastiCache in-transit encryption needs the tls:// scheme.
        // Local dev sets REDIS_SSL=false.
        if ('false' !== getenv('REDIS_SSL')) {
            $host = 'tls://' . $host;
        }
        $redis = new Redis();
        if (! $redis->connect($host, $port, 1.0)) {
            error_log('pagecache purge: Redis connect failed');
            return null;
        }
        if ($auth = getenv('REDIS_AUTH')) {
            $redis->auth($auth);
        }
        // Page cache lives in its own DB; the firewall is db0.
        $redis->select((int) (getenv('PAGECACHE_DB') ?: 1));
        return $redis;
    } catch (\Throwable $t) {
        error_log('pagecache purge: Redis connection failed: ' . $t->getMessage());
        return null;
    }
}
/**
 * Fence TTL must outlive the slowest realistic PHP render so a very slow
 * in-flight request can still be fenced out. 60s is comfortably above normal
 * render times; raise it if pages are known to render slower than that.
 */
function hc_pagecache_fence_ttl(): int
{
    return (int) (getenv('PAGECACHE_FENCE_TTL') ?: 60);
}
/**
 * Purge-fence timestamp from Redis's own clock, not the web server's
 * wall-clock - avoids clock drift between this PHP host and the OpenResty
 * pods. Integer microseconds: "sec.usec" concatenation would mis-order
 * within a second (usec isn't zero-padded). Must match the snapshot format
 * in opt/lua/pagecache/init.lua (fetch()) in the hale-platform repo.
 */
function hc_pagecache_fence_value(\Redis $redis): ?string
{
    $time = $redis->rawCommand('TIME');
    return (is_array($time) && isset($time[0]))
        ? (string) ((int) $time[0] * 1000000 + (int) ($time[1] ?? 0))
        : null;
}
/**
 * Purge the page cache for EVERY site on the network at once.
 *
 * Bumps pagecache:version - the version is part of every content key
 * (pagecache:v{ver}:{host}:{path}), so incrementing it instantly orphans
 * all existing entries network-wide. No fences needed: an in-flight render
 * writes to a key under the OLD version, which nothing reads any more.
 * Orphaned keys expire on their own TTL (PAGECACHE_TTL, default 300s),
 * so memory is reclaimed within minutes.
 *
 * @return int|\WP_Error The new cache version on success.
 */
function hc_pagecache_purge_all_sites(): int|\WP_Error
{
    if ('true' !== getenv('PAGECACHE_ENABLED')) {
        return new \WP_Error('hc_pagecache_disabled', __('The page cache is not enabled (PAGECACHE_ENABLED).', 'hale-components'));
    }
    $redis = hc_pagecache_redis_connect();
    if (null === $redis) {
        return new \WP_Error('hc_pagecache_redis', __('Could not connect to the page cache Redis database.', 'hale-components'));
    }
    try {
        $version = (int) $redis->incr('pagecache:version');
        $redis->close();
        return $version;
    } catch (\Throwable $t) {
        try { $redis->close(); } catch (\Throwable $t2) {}
        error_log('pagecache purge all: ' . $t->getMessage());
        return new \WP_Error('hc_pagecache_redis', __('Redis error while clearing the cache.', 'hale-components'));
    }
}
/**
 * Purge every cached page belonging to the CURRENT site only.
 *
 * Cache keys are scoped by host + path. This is a subdirectory multisite
 * (SUBDOMAIN_INSTALL false), so every site can share one hostname and a
 * site is identified by its path prefix. We SCAN for
 * pagecache:v{ver}:{host}:{site_path}* and DEL each match - but for the
 * main site (path "/") that pattern also matches every subsite, so keys
 * whose path belongs to a DESCENDANT site are skipped.
 *
 * Each deleted path also gets a purge fence stamped (same race-closing
 * mechanism as hc_pagecache_purge_paths) so an in-flight render can't
 * immediately re-cache stale content.
 *
 * @return int|\WP_Error Number of cache entries deleted on success.
 */
function hc_pagecache_purge_current_site(): int|\WP_Error
{
    if ('true' !== getenv('PAGECACHE_ENABLED')) {
        return new \WP_Error('hc_pagecache_disabled', __('The page cache is not enabled (PAGECACHE_ENABLED).', 'hale-components'));
    }
    $redis = hc_pagecache_redis_connect();
    if (null === $redis) {
        return new \WP_Error('hc_pagecache_redis', __('Could not connect to the page cache Redis database.', 'hale-components'));
    }
    try {
        $version   = (int) ($redis->get('pagecache:version') ?: 0);
        $hostname  = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
        $site_path = '/';
        $descendant_paths = [];
        if (is_multisite()) {
            $site      = get_site();
            $site_path = $site ? $site->path : '/';
            // Subdirectory multisite: any other site whose path sits under
            // this site's path would be caught by the SCAN pattern below.
            // Collect those paths so their keys can be skipped.
            foreach (get_sites(['number' => 0]) as $other) {
                if ((int) $other->blog_id === get_current_blog_id()) {
                    continue;
                }
                if ($other->path !== $site_path && str_starts_with($other->path, $site_path)) {
                    $descendant_paths[] = $other->path;
                }
            }
        }
        $prefix     = "pagecache:v{$version}:{$hostname}:";
        $fenceTtl   = hc_pagecache_fence_ttl();
        $fenceValue = hc_pagecache_fence_value($redis);
        $purged     = 0;
        $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        $iterator = null;
        do {
            $keys = $redis->scan($iterator, $prefix . $site_path . '*', 500);
            foreach ((array) $keys as $key) {
                $path = substr($key, strlen($prefix));
                foreach ($descendant_paths as $descendant) {
                    if (str_starts_with($path, $descendant)) {
                        continue 2;   // belongs to a subsite - leave it alone
                    }
                }
                if (null !== $fenceValue) {
                    $redis->set("pagecache:fence:{$hostname}:{$path}", $fenceValue, ['EX' => $fenceTtl]);
                }
                $redis->del($key);
                $purged++;
            }
        } while ($iterator > 0);
        $redis->close();
        return $purged;
    } catch (\Throwable $t) {
        try { $redis->close(); } catch (\Throwable $t2) {}
        error_log('pagecache purge site: ' . $t->getMessage());
        return new \WP_Error('hc_pagecache_redis', __('Redis error while clearing the cache.', 'hale-components'));
    }
}
