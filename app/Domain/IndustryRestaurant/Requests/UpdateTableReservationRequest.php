<?php

namespace App\Domain\IndustryRestaurant\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTableReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_id' => 'nullable|string',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'party_size' => 'nullable|integer|min:1',
            'reservation_date' => 'nullable|date',
            'reservation_time' => 'nullable|string|max:10',
            'duration_minutes' => 'nullable|integer|min:15',
            'notes' => 'nullable|string',
        ];
    }
}
