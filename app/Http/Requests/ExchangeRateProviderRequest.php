<?php

namespace App\Http\Requests;

use App\Rules\PublicHttpUrl;
use Illuminate\Foundation\Http\FormRequest;

class ExchangeRateProviderRequest extends FormRequest
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
        $rules = [
            'driver' => [
                'required',
            ],
            'key' => [
                'required',
            ],
            'currencies' => [
                'nullable',
            ],
            'currencies.*' => [
                'nullable',
            ],
            'driver_config' => [
                'nullable',
            ],
            // Only the CurrencyConverter "DEDICATED" plan reads a custom URL from
            // driver_config; guard it against SSRF (private/reserved targets).
            'driver_config.url' => [
                'nullable',
                'string',
                'url',
                new PublicHttpUrl,
            ],
            'active' => [
                'nullable',
                'boolean',
            ],
        ];

        return $rules;
    }

    public function getExchangeRateProviderPayload()
    {
        return collect($this->validated())
            ->merge([
                'company_id' => $this->header('company'),
            ])
            ->toArray();
    }
}
