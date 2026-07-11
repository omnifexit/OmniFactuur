<?php

namespace App\Providers;

use App\Support\Pdf\PdfService;
use Illuminate\Support\ServiceProvider;

class PdfServiceProvider extends ServiceProvider
{
    public $bindings = [
        'pdf.driver' => PdfService::class,
    ];
}
