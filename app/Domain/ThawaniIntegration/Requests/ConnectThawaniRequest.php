<?php

namespace App\Domain\ThawaniIntegration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConnectThawaniRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'thawani_store_id'     => ['required', 'string', 'max:100'],
            'auto_sync_products'   => ['sometimes', 'boolean'],
            'auto_sync_inventory'  => ['sometimes', 'boolean'],
            'auto_accept_orders'   => ['sometimes', 'boolean'],
            'operating_hours_json' => ['nullable', 'array'],
        ];
    }
}
