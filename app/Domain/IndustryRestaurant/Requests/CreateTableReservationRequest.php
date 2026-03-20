<?php

namespace App\Domain\IndustryRestaurant\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTableReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_id'         => ['required', 'uuid'],
            'customer_name'    => ['required', 'string', 'max:255'],
            'customer_phone'   => ['required', 'string', 'max:20'],
            'party_size'       => ['required', 'integer', 'min:1', 'max:100'],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'reservation_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'notes'            => ['nullable', 'string', 'max:500'],
        ];
    }
}
