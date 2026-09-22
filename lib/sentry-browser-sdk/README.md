# Vendored Sentry Browser SDK

Official CDN builds from `https://browser.sentry-cdn.com/<version>/<file>`, used by `inc/sentry-browser-sdk.php`
to replace the Browser SDK that wp-sentry-integration bundles (8.55.0 as of plugin 8.12.0) until the plugin ships a
fixed one. Why: sentry-javascript < 10.51.0 has a Session Replay bug (getsentry/sentry-javascript#20547) that
freezes the block editor on long pages on slower machines.

Files per version: `bundle.min.js`, `bundle.tracing.min.js`, `bundle.replay.min.js`,
`bundle.tracing.replay.min.js`, matching the four variants the plugin can choose, plus `SHA256SUMS`.

To update to a newer version:

```sh
V=10.51.0   # new version
mkdir -p lib/sentry-browser-sdk/$V && cd lib/sentry-browser-sdk/$V
for f in bundle.min.js bundle.tracing.min.js bundle.replay.min.js bundle.tracing.replay.min.js; do
  curl -sSL -o $f https://browser.sentry-cdn.com/$V/$f
done
shasum -a 256 bundle*.js > SHA256SUMS
```

Then set `HALE_SENTRY_BROWSER_SDK_VERSION` in `inc/sentry-browser-sdk.php`, remove the old directory, and check
that `public/wp-sentry-init.js` in the plugin still only uses `Sentry.init`, `Sentry.replayIntegration`,
`Sentry.browserTracingIntegration` and `Sentry.feedbackIntegration`.

Retire the whole thing once wp-sentry-integration bundles a Browser SDK at or above the fixed version; the override
already stands down by itself when it detects that.
