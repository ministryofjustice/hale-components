const mix_ = require('laravel-mix');

mix_.setPublicPath('./dist')
  .sass('./assets/scss/login.scss', 'css/login.css')
  .options({
    processCssUrls: false
  });

if (mix_.inProduction()) {
  mix_.version();
} else {
  mix_.sourceMaps();
}
