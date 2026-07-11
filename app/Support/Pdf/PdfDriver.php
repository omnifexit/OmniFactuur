<?php

namespace App\Support\Pdf;

interface PdfDriver
{
    public function loadView(string $template): ResponseStream;
}
