<?php

namespace App\Domain\Payment\Requests;

use App\Domain\Payment\Enums\InstallmentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertStoreInstallmentConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(InstallmentProvider::class)],
            'credentials' => ['required', 'array'],
            'credentials.*' => ['string'],
            'is_enabled' => ['sometimes', 'boolean'],
            'environment' => ['sometimes', 'string', Rule::in(['sandbox', 'production'])],
            'success_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'cancel_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'failure_url' => ['sometimes', 'nullable', 'url', 'max:500'],
        ];
    }
}
