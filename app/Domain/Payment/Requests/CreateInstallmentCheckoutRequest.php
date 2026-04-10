<?php

namespace App\Domain\Payment\Requests;

use App\Domain\Payment\Enums\InstallmentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInstallmentCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(InstallmentProvider::class)],
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'installment_count' => ['sometimes', 'nullable', 'integer', 'min:2', 'max:12'],
            'customer_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'customer_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'customer_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'order_reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'lang' => ['sometimes', 'string', Rule::in(['ar', 'en'])],
            'country_code' => ['sometimes', 'string', 'size:2'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'zip' => ['sometimes', 'nullable', 'string', 'max:20'],
            'tax_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'discount_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items' => ['sometimes', 'array'],
            'items.*.name' => ['required_with:items', 'string', 'max:200'],
            'items.*.product_id' => ['sometimes', 'nullable', 'string'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'items.*.tax_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items.*.image_url' => ['sometimes', 'nullable', 'url'],
        ];
    }
}
