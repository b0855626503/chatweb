const mix = require("laravel-mix");

if (mix == 'undefined') {
    const { mix } = require("laravel-mix");
}

require("laravel-mix-merge-manifest");

if (mix.inProduction()) {
    var publicPath = 'publishable/assets';
} else {
    var publicPath = "../../../public/assets/lineoa";
}

mix.setPublicPath(publicPath).mergeManifest();
mix.disableNotifications();

mix.inProduction()

mix.js(
    [
        __dirname + "/src/Resources/assets/js/app.js"
    ],
    "js/lineoa.js"
)
    .options({
        processCssUrls: false
    });

// mix.less(__dirname + "/src/Resources/assets/less/app.less", "css/btn.css");

if (!mix.inProduction()) {
    mix.sourceMaps();
}

if (mix.inProduction()) {
    mix.version();
}
