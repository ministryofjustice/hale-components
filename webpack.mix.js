const mix_ = require('laravel-mix');

mix_.setPublicPath('./dist')
  .sass('./assets/scss/login.scss', 'css/login.css')
  .sass('./assets/scss/hc-network-dashboard.scss', 'css/hc-network-dashboard.css')
  .options({
    processCssUrls: false
  });

if (mix_.inProduction()) {
  mix_.version();
} else {
  mix_.sourceMaps();
}
