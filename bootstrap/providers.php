<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\JetstreamServiceProvider::class,
    // TelescopeServiceProvider is deliberately absent: laravel/telescope is a
    // require-dev package, so the class it extends does not exist in a
    // production install and listing it here made the container fatal on boot.
    // AppServiceProvider::register() registers it when it is actually present.
    App\Providers\TestingServiceProvider::class,
];
