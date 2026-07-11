<?php

use App\Providers\AiServiceProvider;
use App\Providers\AppConfigProvider;
use App\Providers\AppServiceProvider;
use App\Providers\DriverRegistryProvider;
use App\Providers\DropboxServiceProvider;
use App\Providers\PdfServiceProvider;
use App\Providers\RouteServiceProvider;
use App\Providers\ScrambleServiceProvider;
use App\Providers\ViewServiceProvider;
use App\Support\Hashids\HashidsServiceProvider;

return [
    HashidsServiceProvider::class,
    AppServiceProvider::class,
    RouteServiceProvider::class,
    DropboxServiceProvider::class,
    ViewServiceProvider::class,
    PdfServiceProvider::class,
    DriverRegistryProvider::class,
    AiServiceProvider::class,
    AppConfigProvider::class,
    ScrambleServiceProvider::class,
];
