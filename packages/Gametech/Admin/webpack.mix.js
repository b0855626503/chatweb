const mix = require("laravel-mix");

if (mix === 'undefined') {
    const { mix } = require("laravel-mix");
}

require("laravel-mix-merge-manifest");

if (mix.inProduction()) {
    var publicPath = 'publishable/assets';
} else {
    var publicPath = '../../../public/assets/admin';
}

mix.setPublicPath(publicPath);
mix.disableNotifications();

mix.js([__dirname + '/src/Resources/assets/js/app.js'], 'js/app.js').extract(['vue'])
    .sass(__dirname + '/src/Resources/assets/sass/app.scss', 'css/web.css')
    .options({
        processCssUrls: false
    }).mergeManifest();


if (! mix.inProduction()) {
    mix.sourceMaps();
}

if (mix.inProduction()) {
    mix.version();
}
