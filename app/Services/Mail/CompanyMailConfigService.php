<?php

namespace App\Services\Mail;

class CompanyMailConfigService
{
    public static function apply(int $companyId): void
    {
        app(MailConfigurationService::class)->applyCompanyConfig($companyId);
    }
}
