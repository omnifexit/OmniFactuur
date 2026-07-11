<?php

namespace App\Http\Requests;

use App\Services\Mail\MailConfigurationService;
use Illuminate\Foundation\Http\FormRequest;

class MailEnvironmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return app(MailConfigurationService::class)->validationRules(
            $this->string('mail_driver')->toString()
        );
    }
}
